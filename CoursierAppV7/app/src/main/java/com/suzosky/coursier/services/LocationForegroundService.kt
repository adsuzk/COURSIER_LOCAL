package com.suzosky.coursier.services

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.location.Location
import android.os.BatteryManager
import android.os.Build
import android.os.IBinder
import android.os.Looper
import android.util.Log
import androidx.core.app.NotificationCompat
import com.google.android.gms.location.*
import com.suzosky.coursier.MainActivity
import com.suzosky.coursier.R
import com.suzosky.coursier.network.ApiService
import kotlinx.coroutines.*
import org.json.JSONObject
import java.util.concurrent.ConcurrentLinkedQueue
import kotlin.math.max

/**
 * Robust foreground service for continuous location tracking.
 * Features:
 *  - Foreground with persistent notification
 *  - Runtime permission check expectation (caller must request permissions)
 *  - Queue + retry with exponential backoff for failed network posts
 *  - Simple batching of positions when offline or under poor network
 *  - Battery-aware frequency reduction
 */
class LocationForegroundService : Service() {
    private val TAG = "LocationFGService"

    private lateinit var fusedClient: FusedLocationProviderClient
    private lateinit var locationRequest: LocationRequest
    private var locationCallback: LocationCallback? = null
    private var coursierId: Int = -1

    // Local in-memory queue for positions; persisted queue is left as future improvement
    private val sendQueue = ConcurrentLinkedQueue<JSONObject>()
    private var isSending = false

    // Coroutine scope for async sends
    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    // Backoff state
    @Volatile
    private var backoffMillis = 0L

    override fun onCreate() {
        super.onCreate()
        fusedClient = LocationServices.getFusedLocationProviderClient(this)
        locationRequest = LocationRequest.create().apply {
            interval = 10_000L
            fastestInterval = 5_000L
            priority = Priority.PRIORITY_HIGH_ACCURACY
            maxWaitTime = 15_000L
        }
    }

    private fun buildNotification(): Notification {
        val channelId = "suzosky_tracking"
        val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val ch = NotificationChannel(channelId, "Tracking", NotificationManager.IMPORTANCE_LOW)
            nm.createNotificationChannel(ch)
        }
        val pending = PendingIntent.getActivity(this, 0, Intent(this, MainActivity::class.java), PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        return NotificationCompat.Builder(this, channelId)
            .setContentTitle("Suzosky — Tracking actif")
            .setContentText("Envoi de la position au serveur")
            .setSmallIcon(R.mipmap.ic_launcher)
            .setContentIntent(pending)
            .setOngoing(true)
            .build()
    }

    private fun currentBatteryPercent(): Int {
        return try {
            val ifilter = IntentFilter(Intent.ACTION_BATTERY_CHANGED)
            val batteryStatus = registerReceiver(null, ifilter)
            val level = batteryStatus?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
            val scale = batteryStatus?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
            if (level >= 0 && scale > 0) (level * 100) / scale else 100
        } catch (e: Exception) { 100 }
    }

    private fun effectiveIntervalMs(): Long {
        val battery = currentBatteryPercent()
        // Reduce frequency if battery low
        return if (battery < 20) 60_000L else 10_000L
    }

    private fun startLocationUpdates() {
        if (locationCallback != null) return
        locationCallback = object : LocationCallback() {
            override fun onLocationResult(result: LocationResult) {
                val loc = result.lastLocation ?: return
                Log.d(TAG, "Location update: ${'$'}{loc.latitude}, ${'$'}{loc.longitude} acc=${'$'}{loc.accuracy}")
                if (coursierId > 0) {
                    enqueuePosition(coursierId, loc)
                } else {
                    Log.w(TAG, "coursierId not set; skipping enqueue")
                }
            }
        }

        // Adjust request interval according to battery status
        val interval = effectiveIntervalMs()
        locationRequest.interval = interval
        locationRequest.fastestInterval = max(5000L, interval / 2)

        try {
            fusedClient.requestLocationUpdates(locationRequest, locationCallback as LocationCallback, Looper.getMainLooper())
        } catch (e: SecurityException) {
            Log.e(TAG, "Missing location permission: ${e.message}")
        }
    }

    private fun stopLocationUpdates() {
        locationCallback?.let { fusedClient.removeLocationUpdates(it) }
        locationCallback = null
    }

    private fun enqueuePosition(coursierId: Int, loc: Location) {
        val json = JSONObject().apply {
            put("coursier_id", coursierId)
            put("lat", loc.latitude)
            put("lng", loc.longitude)
            put("accuracy", loc.accuracy)
            put("timestamp", System.currentTimeMillis())
        }
        sendQueue.add(json)
        triggerSendLoop()
    }

    private fun triggerSendLoop() {
        if (isSending) return
        isSending = true
        scope.launch {
            while (!sendQueue.isEmpty()) {
                // Apply backoff if set
                if (backoffMillis > 0) {
                    Log.d(TAG, "Backing off for ${'$'}backoffMillis ms")
                    delay(backoffMillis)
                }

                // Batch: take up to 5 items
                val batch = ArrayList<JSONObject>()
                while (batch.size < 5 && !sendQueue.isEmpty()) {
                    sendQueue.poll()?.let { batch.add(it) }
                }

                if (batch.isEmpty()) break

                val success = sendBatch(batch)
                if (success) {
                    backoffMillis = 0L
                } else {
                    // Exponential backoff capped at 5 minutes
                    backoffMillis = max(1000L, if (backoffMillis == 0L) 2000L else backoffMillis * 2)
                    if (backoffMillis > 5 * 60_000L) backoffMillis = 5 * 60_000L
                    // Re-enqueue failed batch at the head
                    batch.reversed().forEach { sendQueue.add(it) }
                    // Wait a bit before retrying
                    delay(backoffMillis)
                }
            }
            isSending = false
        }
    }

    private suspend fun sendBatch(batch: List<JSONObject>): Boolean = withContext(Dispatchers.IO) {
        try {
            // If single element, call normal update; if multiple, send as an array to a batch endpoint if available
            if (batch.size == 1) {
                val o = batch[0]
                val courId = o.optInt("coursier_id", -1)
                val lat = o.optDouble("lat", Double.NaN)
                val lng = o.optDouble("lng", Double.NaN)
                val deferred = CompletableDeferred<Boolean>()
                ApiService.updateCoursierPosition(courId, lat, lng) { ok, err ->
                    if (!ok) Log.w(TAG, "Single post failed: $err")
                    deferred.complete(ok)
                }
                return@withContext deferred.await()
            } else {
                // If server supports batching, post to /api/update_coursier_positions_batch.php
                val payload = JSONObject().apply { put("positions", batch) }
                val deferred = CompletableDeferred<Boolean>()
                ApiService.executeRawJson(buildApiPath("update_coursier_positions_batch.php"), payload) { ok, err ->
                    if (!ok) Log.w(TAG, "Batch post failed: $err")
                    deferred.complete(ok)
                }
                return@withContext deferred.await()
            }
        } catch (e: Exception) {
            Log.e(TAG, "sendBatch exception: ${e.message}")
            return@withContext false
        }
    }

    private fun buildApiPath(file: String): String {
        // Reuse ApiService's builder helpers via reflection-like static method if exists
        return try {
            // fallback to debugging local base + api file
            val base = try { com.suzosky.coursier.BuildConfig.DEBUG_LOCAL_HOST } catch (_: Throwable) { "http://localhost/COURSIER_LOCAL" }
            "$base/api/$file"
        } catch (e: Exception) { "" }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        intent?.let {
            val action = it.action
            when (action) {
                ACTION_START -> {
                    coursierId = it.getIntExtra(EXTRA_COURSIER_ID, -1)
                    startForeground(NOTIF_ID, buildNotification())
                    startLocationUpdates()
                    Log.d(TAG, "Started foreground tracking (coursier=$coursierId)")
                }
                ACTION_STOP -> {
                    stopLocationUpdates()
                    stopForeground(true)
                    stopSelf()
                    Log.d(TAG, "Stopped foreground tracking")
                }
            }
        }
        return START_STICKY
    }

    override fun onDestroy() {
        stopLocationUpdates()
        scope.cancel()
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    companion object {
        const val ACTION_START = "com.suzosky.coursier.action.START_TRACKING"
        const val ACTION_STOP = "com.suzosky.coursier.action.STOP_TRACKING"
        const val EXTRA_COURSIER_ID = "extra_coursier_id"
        const val NOTIF_ID = 2244
    }
}
