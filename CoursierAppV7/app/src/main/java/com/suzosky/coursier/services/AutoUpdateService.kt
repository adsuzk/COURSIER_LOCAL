package com.suzosky.coursier.services

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Environment
import android.os.IBinder
import android.provider.Settings
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.content.FileProvider
import androidx.core.content.ContextCompat
import com.suzosky.coursier.MainActivity
import com.suzosky.coursier.network.ApiResult
import com.suzosky.coursier.network.UpdateApiService
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.io.File
import java.io.FileOutputStream
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Service de mise à jour automatique en arrière-plan
 */
class AutoUpdateService : Service() {

    companion object {
        private const val TAG = "AutoUpdateService"
        private const val NOTIFICATION_ID = 1001
        private const val CHANNEL_ID = "auto_update_channel"
        private const val CHECK_INTERVAL_DEFAULT = 3600000L // 1 heure par défaut
        
        const val ACTION_CHECK_UPDATES = "com.suzosky.coursier.CHECK_UPDATES"
        const val ACTION_FORCE_UPDATE = "com.suzosky.coursier.FORCE_UPDATE"
        const val ACTION_REGISTER_DEVICE = "com.suzosky.coursier.REGISTER_DEVICE"
        
        fun startService(context: Context) {
            val intent = Intent(context, AutoUpdateService::class.java)
            // On Android 13+, don't even start the service if notification permission is missing
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                val hasNotif = ContextCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
                if (!hasNotif) {
                    Log.w(TAG, "POST_NOTIFICATIONS not granted; skipping service start")
                    return
                }
            }
            try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    context.startForegroundService(intent)
                } else {
                    context.startService(intent)
                }
            } catch (se: SecurityException) {
                Log.e(TAG, "SecurityException starting service: ${se.message}")
            } catch (e: Exception) {
                Log.e(TAG, "Error starting service", e)
            }
        }
    }

    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val updateApi = UpdateApiService.create()
    private var checkInterval = CHECK_INTERVAL_DEFAULT
    
    private val updateReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            when (intent?.action) {
                ACTION_CHECK_UPDATES -> {
                    serviceScope.launch { checkForUpdates() }
                }
                ACTION_FORCE_UPDATE -> {
                    serviceScope.launch { checkForUpdates(forceCheck = true) }
                }
                ACTION_REGISTER_DEVICE -> {
                    serviceScope.launch { registerDevice() }
                }
            }
        }
    }

    override fun onCreate() {
        super.onCreate()
        Log.d(TAG, "AutoUpdateService créé")
        // Extra guard: if notifications not allowed on Android 13+, stop immediately to avoid crashes on some OEMs
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            val hasNotifPermission = ContextCompat.checkSelfPermission(this, android.Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
            if (!hasNotifPermission) {
                Log.w(TAG, "POST_NOTIFICATIONS missing in onCreate; stopping service early")
                stopSelf()
                return
            }
        }
        
        createNotificationChannel()
        registerReceiver()
        
        // Enregistrer le périphérique et commencer les vérifications
        serviceScope.launch {
            registerDevice()
            startPeriodicUpdateCheck()
        }
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        Log.d(TAG, "Service démarré avec action: ${intent?.action}")

        // Android 13+ requires POST_NOTIFICATIONS to post foreground notification
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            val hasNotifPermission = ContextCompat.checkSelfPermission(this, android.Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
            if (!hasNotifPermission) {
                Log.w(TAG, "Permission POST_NOTIFICATIONS manquante, arrêt du service pour éviter SecurityException")
                stopSelf()
                return START_NOT_STICKY
            }
        }

        // Toujours démarrer en foreground immédiatement
        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Service de mise à jour")
            .setContentText("Surveillance des mises à jour en cours...")
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .build()
        startForeground(NOTIFICATION_ID, notification)

        when (intent?.action) {
            ACTION_CHECK_UPDATES -> {
                serviceScope.launch { checkForUpdates() }
            }
            ACTION_FORCE_UPDATE -> {
                serviceScope.launch { checkForUpdates(forceCheck = true) }
            }
            ACTION_REGISTER_DEVICE -> {
                serviceScope.launch { registerDevice() }
            }
            // else: rien de spécial, juste foreground
        }

        return START_STICKY
    }

    override fun onDestroy() {
        Log.d(TAG, "AutoUpdateService détruit")
        try {
            unregisterReceiver(updateReceiver)
        } catch (e: IllegalArgumentException) {
            Log.w(TAG, "Receiver déjà non enregistré", e)
        }
        serviceScope.cancel()
        super.onDestroy()
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Mises à jour automatiques",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Notifications des mises à jour de l'application"
            }
            
            val notificationManager = ContextCompat.getSystemService(this, NotificationManager::class.java)
            notificationManager?.createNotificationChannel(channel)
        }
    }

    private fun registerReceiver() {
        val filter = IntentFilter().apply {
            addAction(ACTION_CHECK_UPDATES)
            addAction(ACTION_FORCE_UPDATE)
            addAction(ACTION_REGISTER_DEVICE)
        }

        // Android 14 (API 34) exige d'indiquer explicitement si le receiver est exporté ou non
        // lorsqu'on enregistre dynamiquement un BroadcastReceiver qui n'est pas exclusivement pour des broadcasts système.
        // Ici, nos actions sont internes à l'app, donc on choisit NOT_EXPORTED.
        try {
            ContextCompat.registerReceiver(this, updateReceiver, filter, ContextCompat.RECEIVER_NOT_EXPORTED)
        } catch (se: SecurityException) {
            Log.e(TAG, "SecurityException lors de l'enregistrement du receiver: ${se.message}")
            // En cas d'échec, on continue sans receiver (fonctionnalité dégradée mais pas de crash)
        } catch (e: Exception) {
            Log.e(TAG, "Erreur lors de l'enregistrement du receiver", e)
        }
    }

    private suspend fun registerDevice() {
        Log.d(TAG, "Enregistrement du périphérique...")
        
        try {
            val packageInfo = packageManager.getPackageInfo(packageName, 0)
            val versionCode = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                packageInfo.longVersionCode.toInt()
            } else {
                @Suppress("DEPRECATION")
                packageInfo.versionCode
            }

            val deviceInfo = mapOf(
                "action" to "register_device",
                "device_id" to getDeviceIdUnique(),
                "app_version" to versionCode,
                "android_version" to Build.VERSION.RELEASE,
                "device_model" to "${Build.MANUFACTURER} ${Build.MODEL}",
                "last_active" to SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())
            )

            val result = updateApi.registerDevice(deviceInfo)
            if (result.isSuccessful()) {
                Log.d(TAG, "Périphérique enregistré avec succès")
            } else {
                Log.e(TAG, "Échec de l'enregistrement du périphérique")
            }
        } catch (e: Exception) {
            Log.e(TAG, "Erreur lors de l'enregistrement", e)
        }
    }

    private suspend fun checkForUpdates(forceCheck: Boolean = false) {
        Log.d(TAG, "Vérification des mises à jour... (forcé: $forceCheck)")
        
        try {
            val packageInfo = packageManager.getPackageInfo(packageName, 0)
            val currentVersion = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
                packageInfo.longVersionCode.toInt()
            } else {
                @Suppress("DEPRECATION")
                packageInfo.versionCode
            }

            val updateResponse = updateApi.checkForUpdates(getDeviceIdUnique(), currentVersion)
            
            // Mettre à jour l'intervalle de vérification
            checkInterval = (updateResponse.checkInterval * 1000).toLong()
            
            if (updateResponse.updateAvailable || forceCheck) {
                Log.i(TAG, "Mise à jour disponible: ${updateResponse.latestVersion.versionName}")
                
                showUpdateNotification(updateResponse.latestVersion.versionName)
                
                if (updateResponse.autoInstall || forceCheck) {
                    downloadAndInstallUpdate(updateResponse.latestVersion.apkUrl)
                }
            } else {
                Log.d(TAG, "Aucune mise à jour disponible")
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Erreur lors de la vérification des mises à jour", e)
        }
    }

    private suspend fun downloadAndInstallUpdate(downloadUrl: String) {
        Log.d(TAG, "Téléchargement de la mise à jour depuis: $downloadUrl")
        
        try {
            reportStatus("downloading")
            
            val result = updateApi.downloadApk(downloadUrl)
            
            when (result) {
                is ApiResult.Success -> {
                    val responseBody = result.data
                    val apkFile = File(getExternalFilesDir(Environment.DIRECTORY_DOWNLOADS), "update.apk")
                    
                    FileOutputStream(apkFile).use { output ->
                        responseBody.byteStream().use { input ->
                            input.copyTo(output)
                        }
                    }
                    
                    Log.d(TAG, "Téléchargement terminé: ${apkFile.absolutePath}")
                    
                    reportStatus("downloaded")
                    installApkSilently(apkFile)
                }
                is ApiResult.Error -> {
                    Log.e(TAG, "Échec du téléchargement: ${result.message}")
                    reportStatus("download_failed")
                }
            }
            
        } catch (e: Exception) {
            Log.e(TAG, "Erreur lors du téléchargement", e)
            reportStatus("download_error")
        }
    }

    private suspend fun installApkSilently(apkFile: File) {
        Log.d(TAG, "Installation silencieuse de l'APK: ${apkFile.name}")
        
        try {
            if (!apkFile.exists()) {
                Log.e(TAG, "Fichier APK introuvable")
                reportStatus("install_failed")
                return
            }

            reportStatus("installing")
            
            // Vérifier les permissions d'installation
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                if (!packageManager.canRequestPackageInstalls()) {
                    Log.w(TAG, "Permission d'installation requise")
                    requestInstallPermission()
                    return
                }
            }
            
            // Créer l'intent d'installation
            val intent = Intent(Intent.ACTION_VIEW).apply {
                val apkUri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                    // Authority doit correspondre à celle déclarée dans le manifest ("${applicationId}.provider")
                    FileProvider.getUriForFile(this@AutoUpdateService, "$packageName.provider", apkFile)
                } else {
                    Uri.fromFile(apkFile)
                }
                
                setDataAndType(apkUri, "application/vnd.android.package-archive")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_GRANT_READ_URI_PERMISSION
            }
            
            startActivity(intent)
            
            Log.d(TAG, "Intent d'installation lancé")
            reportStatus("install_initiated")
            
        } catch (e: Exception) {
            Log.e(TAG, "Erreur lors de l'installation", e)
            reportStatus("install_error")
        }
    }

    private suspend fun reportStatus(status: String) {
        try {
            val statusData = mapOf(
                "action" to "report_status",
                "device_id" to getDeviceIdUnique(),
                "status" to status,
                "timestamp" to SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault()).format(Date())
            )
            
            updateApi.reportStatus(statusData)
            Log.d(TAG, "Statut rapporté: $status")
            
        } catch (e: Exception) {
            Log.e(TAG, "Erreur lors du rapport de statut", e)
        }
    }

    private suspend fun startPeriodicUpdateCheck() {
        while (true) {
            try {
                checkForUpdates()
                delay(checkInterval)
            } catch (e: Exception) {
                Log.e(TAG, "Erreur dans la vérification périodique", e)
                delay(60000) // Attendre 1 minute en cas d'erreur
            }
        }
    }

    private fun showUpdateNotification(versionName: String) {
        val intent = Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val notification = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Mise à jour disponible")
            .setContentText("Version $versionName prête à être installée")
            .setSmallIcon(android.R.drawable.ic_dialog_info)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .build()

    val notificationManager = ContextCompat.getSystemService(this, NotificationManager::class.java)
    notificationManager?.notify(NOTIFICATION_ID + 1, notification)
    }

    private fun requestInstallPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val intent = Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES).apply {
                data = Uri.parse("package:$packageName")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK
            }
            startActivity(intent)
        }
    }

    private fun getDeviceIdUnique(): String {
        return try {
            Settings.Secure.getString(contentResolver, Settings.Secure.ANDROID_ID) ?: "unknown_device"
        } catch (e: Exception) {
            Log.e(TAG, "Erreur lors de la récupération de l'ID du périphérique", e)
            "unknown_device"
        }
    }
}