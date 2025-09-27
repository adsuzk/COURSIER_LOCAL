// CoursierAppV7/app/src/main/java/com/suzosky/coursier/telemetry/TelemetrySDK.kt
package com.suzosky.coursier.telemetry

import android.content.Context
import android.content.SharedPreferences
import android.os.Build
import android.provider.Settings
import androidx.lifecycle.DefaultLifecycleObserver
import androidx.lifecycle.LifecycleOwner
import androidx.lifecycle.ProcessLifecycleOwner
import androidx.core.content.edit
import com.suzosky.coursier.BuildConfig
import kotlinx.coroutines.*
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException
import java.util.*
import java.util.concurrent.TimeUnit
import kotlin.coroutines.resume
import kotlin.coroutines.suspendCoroutine

/**
 * SDK de télémétrie pour remonter les données vers le serveur Suzosky
 * - Enregistrement des appareils
 * - Tracking des sessions
 * - Remontée des crashes
 * - Vérification des mises à jour
 */
class TelemetrySDK private constructor(
    private val context: Context,
    private val baseUrl: String,
    private val apiKey: String
) : DefaultLifecycleObserver {
    
    companion object {
        @Volatile
        private var INSTANCE: TelemetrySDK? = null
        
        fun initialize(context: Context, baseUrl: String, apiKey: String): TelemetrySDK {
            return INSTANCE ?: synchronized(this) {
                INSTANCE ?: TelemetrySDK(
                    context.applicationContext,
                    baseUrl,
                    apiKey
                ).also { 
                    INSTANCE = it
                    it.setup()
                }
            }
        }
        
        fun getInstance(): TelemetrySDK? = INSTANCE
    }
    
    private val prefs: SharedPreferences = context.getSharedPreferences("telemetry_sdk", Context.MODE_PRIVATE)
    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())
    private val httpClient = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .build()
    
    private var currentSessionId: String? = null
    private var sessionStartTime: Long = 0
    private var screenCount: Int = 0
    private var actionCount: Int = 0
    
    private val deviceId: String by lazy {
        prefs.getString("device_id", null) ?: generateDeviceId().also {
            prefs.edit { putString("device_id", it) }
        }
    }
    
    private fun setup() {
        // Enregistrer le lifecycle observer
        ProcessLifecycleOwner.get().lifecycle.addObserver(this)
        
        // Enregistrer l'appareil au démarrage
        scope.launch {
            registerDevice()
            checkForUpdates()
        }
        
        // Configurer la détection de crash
        setupCrashReporting()
    }
    
    private fun generateDeviceId(): String {
        return try {
            // Utiliser Android ID si disponible
            Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID)
                ?: UUID.randomUUID().toString()
        } catch (e: Exception) {
            UUID.randomUUID().toString()
        }
    }
    
    private suspend fun registerDevice() {
        try {
            val deviceInfo = JSONObject().apply {
                put("device_id", deviceId)
                put("device_model", Build.MODEL)
                put("device_brand", Build.BRAND)
                put("android_version", Build.VERSION.RELEASE)
                put("app_version_code", BuildConfig.VERSION_CODE)
                put("app_version_name", BuildConfig.VERSION_NAME)
            }
            
            makeApiCall("register_device", deviceInfo)
            
        } catch (e: Exception) {
            logError("Failed to register device", e)
        }
    }
    
    suspend fun checkForUpdates(): UpdateInfo? {
        return try {
            val response = makeApiCall("heartbeat", JSONObject().apply {
                put("device_id", deviceId)
            })
            
            if (response.optBoolean("update_available", false)) {
                val updateInfo = response.optJSONObject("update_info")
                if (updateInfo != null) {
                    UpdateInfo(
                        versionName = updateInfo.optString("version_name"),
                        versionCode = updateInfo.optInt("version_code"),
                        downloadUrl = updateInfo.optString("download_url"),
                        isMandatory = updateInfo.optBoolean("is_mandatory"),
                        releaseNotes = updateInfo.optString("release_notes")
                    )
                } else null
            } else null
            
        } catch (e: Exception) {
            logError("Failed to check updates", e)
            null
        }
    }
    
    fun reportCrash(
        throwable: Throwable,
        screenName: String? = null,
        userAction: String? = null,
        additionalData: Map<String, Any>? = null
    ) {
        scope.launch {
            try {
                val crashInfo = JSONObject().apply {
                    put("device_id", deviceId)
                    put("app_version_code", BuildConfig.VERSION_CODE)
                    put("android_version", Build.VERSION.RELEASE)
                    put("device_model", Build.MODEL)
                    put("crash_type", "EXCEPTION")
                    put("exception_class", throwable.javaClass.simpleName)
                    put("exception_message", throwable.message ?: "No message")
                    put("stack_trace", throwable.stackTraceToString())
                    screenName?.let { put("screen_name", it) }
                    userAction?.let { put("user_action", it) }
                    
                    // Infos système
                    put("memory_usage", getMemoryUsage())
                    put("battery_level", getBatteryLevel())
                    put("network_type", getNetworkType())
                    
                    // Données additionnelles
                    additionalData?.let { data ->
                        val extraData = JSONObject()
                        data.forEach { (key, value) ->
                            extraData.put(key, value)
                        }
                        put("additional_data", extraData)
                    }
                }
                
                makeApiCall("report_crash", crashInfo)
                
            } catch (e: Exception) {
                logError("Failed to report crash", e)
            }
        }
    }
    
    fun trackEvent(
        eventType: String,
        eventName: String,
        screenName: String? = null,
        eventData: Map<String, Any>? = null
    ) {
        scope.launch {
            try {
                val event = JSONObject().apply {
                    put("device_id", deviceId)
                    put("event_type", eventType)
                    put("event_name", eventName)
                    screenName?.let { put("screen_name", it) }
                    currentSessionId?.let { put("session_id", it) }
                    
                    eventData?.let { data ->
                        val dataJson = JSONObject()
                        data.forEach { (key, value) ->
                            dataJson.put(key, value)
                        }
                        put("event_data", dataJson)
                    }
                }
                
                makeApiCall("track_event", event)
                actionCount++
                
            } catch (e: Exception) {
                logError("Failed to track event", e)
            }
        }
    }
    
    fun trackScreenView(screenName: String) {
        screenCount++
        trackEvent("SCREEN_VIEW", screenName, screenName)
    }
    
    // Lifecycle callbacks
    override fun onStart(owner: LifecycleOwner) {
        startSession()
    }
    
    override fun onStop(owner: LifecycleOwner) {
        endSession(crashed = false)
    }
    
    private fun startSession() {
        currentSessionId = "sess_${UUID.randomUUID()}"
        sessionStartTime = System.currentTimeMillis()
        screenCount = 0
        actionCount = 0
        
        scope.launch {
            try {
                val sessionData = JSONObject().apply {
                    put("device_id", deviceId)
                    put("session_id", currentSessionId)
                }
                
                makeApiCall("start_session", sessionData)
                
            } catch (e: Exception) {
                logError("Failed to start session", e)
            }
        }
    }
    
    private fun endSession(crashed: Boolean) {
        val sessionId = currentSessionId ?: return
        
        scope.launch {
            try {
                val sessionData = JSONObject().apply {
                    put("device_id", deviceId)
                    put("session_id", sessionId)
                    put("screens_visited", screenCount)
                    put("actions_performed", actionCount)
                    put("crashed", if (crashed) 1 else 0)
                }
                
                makeApiCall("end_session", sessionData)
                
            } catch (e: Exception) {
                logError("Failed to end session", e)
            }
        }
        
        currentSessionId = null
    }
    
    private fun setupCrashReporting() {
        val defaultHandler = Thread.getDefaultUncaughtExceptionHandler()
        
        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            // Reporter le crash
            reportCrash(
                throwable = throwable,
                screenName = "UnknownScreen",
                userAction = "App crashed unexpectedly"
            )
            
            // Marquer la session comme crashée
            endSession(crashed = true)
            
            // Délai pour permettre l'envoi
            Thread.sleep(2000)
            
            // Appeler le handler par défaut
            defaultHandler?.uncaughtException(thread, throwable)
        }
    }
    
    private suspend fun makeApiCall(endpoint: String, data: JSONObject): JSONObject {
        return suspendCoroutine { continuation ->
            val requestBody = data.toString().toRequestBody("application/json; charset=utf-8".toMediaType())
            
            val request = Request.Builder()
                .url("$baseUrl/api/telemetry.php?endpoint=$endpoint")
                .post(requestBody)
                .addHeader("X-API-Key", apiKey)
                .addHeader("X-Device-ID", deviceId)
                .addHeader("X-App-Version", BuildConfig.VERSION_NAME)
                .addHeader("User-Agent", "SuzoskyCourierApp/${BuildConfig.VERSION_NAME}")
                .build()
            
            httpClient.newCall(request).enqueue(object : Callback {
                override fun onFailure(call: Call, e: IOException) {
                    continuation.resume(JSONObject().apply {
                        put("error", e.message ?: "Network error")
                    })
                }
                
                override fun onResponse(call: Call, response: Response) {
                    try {
                        val body = response.body?.string() ?: "{}"
                        continuation.resume(JSONObject(body))
                    } catch (e: Exception) {
                        continuation.resume(JSONObject().apply {
                            put("error", e.message ?: "Parse error")
                        })
                    }
                }
            })
        }
    }
    
    private fun getMemoryUsage(): Int {
        return try {
            val runtime = Runtime.getRuntime()
            val usedMemory = runtime.totalMemory() - runtime.freeMemory()
            (usedMemory / (1024 * 1024)).toInt() // MB
        } catch (e: Exception) {
            -1
        }
    }
    
    private fun getBatteryLevel(): Int {
        return try {
            // Implémentation basique - à améliorer avec BatteryManager
            -1
        } catch (e: Exception) {
            -1
        }
    }
    
    private fun getNetworkType(): String {
        return try {
            // Implémentation basique - à améliorer avec ConnectivityManager
            "Unknown"
        } catch (e: Exception) {
            "Error"
        }
    }
    
    private fun logError(message: String, throwable: Throwable? = null) {
        println("TelemetrySDK: $message")
        throwable?.printStackTrace()
    }
    
    fun cleanup() {
        scope.cancel()
        httpClient.dispatcher.executorService.shutdown()
        INSTANCE = null
    }
}

/**
 * Data class pour les informations de mise à jour
 */
data class UpdateInfo(
    val versionName: String,
    val versionCode: Int,
    val downloadUrl: String,
    val isMandatory: Boolean,
    val releaseNotes: String?
)

/**
 * Extensions pour faciliter l'usage
 */
fun TelemetrySDK.trackButtonClick(buttonName: String, screenName: String) {
    trackEvent("USER_ACTION", "button_click", screenName, mapOf("button" to buttonName))
}

fun TelemetrySDK.trackFeatureUsed(featureName: String, screenName: String, params: Map<String, Any> = emptyMap()) {
    trackEvent("FEATURE_USE", featureName, screenName, params)
}

fun TelemetrySDK.trackError(errorType: String, errorMessage: String, screenName: String) {
    trackEvent("ERROR", errorType, screenName, mapOf("message" to errorMessage))
}