package com.suzosky.coursier.receivers

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.util.Log
import androidx.core.content.ContextCompat
import com.suzosky.coursier.services.AutoUpdateService

/**
 * Receiver qui démarre le service de mise à jour automatique au démarrage du système
 */
class BootReceiver : BroadcastReceiver() {
    
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED || 
            intent.action == Intent.ACTION_MY_PACKAGE_REPLACED ||
            intent.action == Intent.ACTION_PACKAGE_REPLACED) {
            
            val canStart = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                ContextCompat.checkSelfPermission(context, android.Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED
            } else true

            if (canStart) {
                // Protection Android 14 : délai avant démarrage
                if (android.os.Build.VERSION.SDK_INT >= 34) {
                    android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                        Log.d("BootReceiver", "Démarrage du service de mise à jour après boot/remplacement (Android 14 avec délai)")
                        try {
                            AutoUpdateService.startService(context)
                        } catch (e: SecurityException) {
                            Log.e("BootReceiver", "Service bloqué au boot: ${e.message}")
                        }
                    }, 5000) // Délai de 5 secondes au boot
                } else {
                    Log.d("BootReceiver", "Démarrage du service de mise à jour après boot/remplacement")
                    try {
                        AutoUpdateService.startService(context)
                    } catch (e: SecurityException) {
                        Log.e("BootReceiver", "Service bloqué au boot: ${e.message}")
                    }
                }
            } else {
                Log.w("BootReceiver", "POST_NOTIFICATIONS non accordée, service non démarré")
            }
        }
    }
}