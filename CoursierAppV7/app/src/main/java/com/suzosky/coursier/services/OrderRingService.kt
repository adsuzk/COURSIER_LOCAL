package com.suzosky.coursier.services

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat
import com.suzosky.coursier.MainActivity
import com.suzosky.coursier.R
import android.media.MediaPlayer
import android.os.Handler
import android.os.Looper

/**
 * Service de sonnerie: joue en boucle les 2 premières secondes du son jusqu'à arrêt explicite.
 */
class OrderRingService : Service() {

    companion object {
        private const val CHANNEL_ID = "orders_ringer_channel"
        private const val NOTIF_ID = 2002
        private const val ACTION_STOP = "com.suzosky.coursier.action.STOP_RING"
        private const val EXTRA_ORDER_ID = "order_id"

        fun start(context: Context, orderId: String? = null) {
            val intent = Intent(context, OrderRingService::class.java)
            intent.putExtra(EXTRA_ORDER_ID, orderId)
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) context.startForegroundService(intent) else context.startService(intent)
            } catch (_: Exception) {}
        }
        fun stop(context: Context) {
            try {
                // Ne PAS démarrer un nouveau foreground service ici (sinon Android exige startForeground sous 5s)
                // On demande simplement l'arrêt du service s'il tourne; si non, c'est un no-op.
                context.stopService(Intent(context, OrderRingService::class.java))
            } catch (_: Exception) {}
        }
    }

    private var mediaPlayer: MediaPlayer? = null
    private val handler = Handler(Looper.getMainLooper())
    private val loopRunnable = object : Runnable {
        override fun run() {
            mediaPlayer?.let { mp ->
                if (mp.isPlaying) {
                    try { mp.seekTo(0) } catch (_: Exception) {}
                }
                handler.postDelayed(this, 2000L)
            }
        }
    }

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onCreate() {
        super.onCreate()
        createChannel()
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (intent?.action == ACTION_STOP) {
            // Si le service a été démarré via startForegroundService() pour traiter STOP,
            // nous devons appeler startForeground rapidement avant d'arrêter, sinon Android lève une exception.
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val notif: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
                    .setContentTitle(getString(R.string.app_name))
                    .setContentText("Arrêt de la sonnerie…")
                    .setSmallIcon(R.drawable.ic_notification)
                    .setOngoing(false)
                    .setPriority(NotificationCompat.PRIORITY_LOW)
                    .build()
                startForeground(NOTIF_ID, notif)
            }
            stopRinging()
            try { stopForeground(STOP_FOREGROUND_REMOVE) } catch (_: Exception) {}
            stopSelf()
            return START_NOT_STICKY
        }
        startInForeground(intent?.getStringExtra(EXTRA_ORDER_ID))
        startRinging()
        return START_STICKY
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val m = getSystemService(NotificationManager::class.java)
            val channel = NotificationChannel(CHANNEL_ID, "Sonnerie nouvelle commande", NotificationManager.IMPORTANCE_HIGH)
            val attrs = AudioAttributes.Builder()
                .setUsage(AudioAttributes.USAGE_NOTIFICATION_EVENT)
                .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                .build()
            // Le son est géré par MediaPlayer; on met pas de son sur le canal pour éviter double son
            channel.setSound(null, attrs)
            m.createNotificationChannel(channel)
        }
    }

    private fun startInForeground(orderId: String?) {
        val openIntent = Intent(this, MainActivity::class.java)
        openIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP)
        orderId?.let { openIntent.putExtra("open_order_id", it) }
        val openPending = PendingIntent.getActivity(
            this, 0, openIntent,
            (PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= 23) PendingIntent.FLAG_IMMUTABLE else 0))
        )
        val stopIntent = Intent(this, OrderRingService::class.java).apply { action = ACTION_STOP }
        val stopPending = PendingIntent.getService(
            this, 1, stopIntent,
            (PendingIntent.FLAG_UPDATE_CURRENT or (if (Build.VERSION.SDK_INT >= 23) PendingIntent.FLAG_IMMUTABLE else 0))
        )
        val notif: Notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(getString(R.string.app_name))
            .setContentText("Nouvelle commande - sonnerie en cours")
            .setSmallIcon(R.drawable.ic_notification)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(openPending)
            .addAction(0, "Arrêter", stopPending)
            .build()
        startForeground(NOTIF_ID, notif)
    }

    private fun startRinging() {
        if (mediaPlayer != null) return
        mediaPlayer = MediaPlayer.create(this, R.raw.new_order_sound)
        mediaPlayer?.let { mp ->
            try {
                mp.isLooping = false // On gère la boucle manuellement pour limiter à 2s
                mp.setOnCompletionListener { /* ignore */ }
                mp.start()
                handler.postDelayed(loopRunnable, 2000L)
            } catch (_: Exception) {}
        }
    }

    private fun stopRinging() {
        handler.removeCallbacks(loopRunnable)
        try {
            mediaPlayer?.stop()
        } catch (_: Exception) {}
        try {
            mediaPlayer?.release()
        } catch (_: Exception) {}
        mediaPlayer = null
    }

    override fun onDestroy() {
        stopRinging()
            try { stopForeground(STOP_FOREGROUND_REMOVE) } catch (_: Exception) {}
        super.onDestroy()
    }
}
