package com.suzosky.coursier.messaging

import android.app.NotificationChannel
import android.app.NotificationManager
import android.content.Intent
import android.media.RingtoneManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import com.suzosky.coursier.R
import com.suzosky.coursier.network.ApiService
import android.media.AudioAttributes
import android.net.Uri
import android.util.Log
import com.suzosky.coursier.services.OrderRingService
import androidx.core.content.ContextCompat
import androidx.core.content.edit
import android.content.pm.PackageManager

class FCMService : FirebaseMessagingService() {
    companion object {
        private const val TAG = "FCMService"
        const val ACTION_REFRESH_DATA = "com.suzosky.coursier.ACTION_REFRESH_COURSIER_DATA"
        const val EXTRA_ORDER_ID = "order_id"
    }
     
     override fun onNewToken(token: String) {
         super.onNewToken(token)
         Log.d(TAG, "🔥 FCMService: onNewToken appelé!")
         Log.d(TAG, "🔥 FCMService: Token reçu: ${token.substring(0, 20)}...")
         println("🔥 FCMService: onNewToken appelé! Token: ${token.substring(0, 20)}...")
         
         // Sauvegarder le token localement
         val prefs = getSharedPreferences("suzosky_prefs", MODE_PRIVATE)
         prefs.edit().putString("fcm_token", token).apply()
         Log.d(TAG, "📱 FCMService: Token sauvé localement")
         
         // Enregistrer le token côté serveur pour le coursier connecté
         try {
             val storedId = prefs.getInt("coursier_id", -1)
             if (storedId > 0) {
                 Log.d(TAG, "🔥 FCMService: Enregistrement token pour coursier $storedId")
                 println("🔥 FCMService: Enregistrement token pour coursier $storedId")
                 ApiService.registerDeviceToken(this, storedId, token)
                 
                 // ⚡ PING IMMÉDIAT pour ouvrir le formulaire à la seconde
                 Log.d(TAG, "⚡ FCMService: Ping immédiat pour ouverture formulaire")
                 println("⚡ FCMService: Ping immédiat pour ouverture formulaire")
                 ApiService.pingDeviceToken(this, token)
                 
                 Log.d(TAG, "✅ FCMService: Appel API lancé")
                 println("✅ FCMService: Appel API lancé")
             } else {
                 Log.w(TAG, "⚠️ FCMService: Aucun coursier_id stocké, tentative de récupération de session")
                 println("⚠️ FCMService: Aucun coursier_id stocké, tentative de récupération de session")
                 ApiService.checkCoursierSession { id, err ->
                     val resolvedId = id ?: -1
                     if (resolvedId > 0) {
                         try { prefs.edit { putInt("coursier_id", resolvedId) } } catch (_: Exception) {}
                         Log.d(TAG, "✅ FCMService: Session coursier validée (id=$resolvedId), enregistrement token")
                         println("✅ FCMService: Session coursier validée (id=$resolvedId), enregistrement token")
                         ApiService.registerDeviceToken(this@FCMService, resolvedId, token)
                         
                         // ⚡ PING IMMÉDIAT pour ouvrir le formulaire à la seconde
                         Log.d(TAG, "⚡ FCMService: Ping immédiat pour ouverture formulaire (session récupérée)")
                         println("⚡ FCMService: Ping immédiat pour ouverture formulaire (session récupérée)")
                         ApiService.pingDeviceToken(this@FCMService, token)
                     } else {
                         Log.e(TAG, "❌ FCMService: Impossible d'enregistrer le token, session invalide (${err ?: "inconnue"})")
                         println("❌ FCMService: Impossible d'enregistrer le token, session invalide (${err ?: "inconnue"})")
                     }
                 }
             }
        } catch (e: Exception) {
            Log.e(TAG, "❌ FCMService: Erreur enregistrement token", e)
            println("❌ FCMService: Erreur enregistrement token: ${e.message}")
            e.printStackTrace()
        }
     }

     override fun onMessageReceived(message: RemoteMessage) {
         super.onMessageReceived(message)
         try {
            Log.d(TAG, "Received message data: ${message.data}")
         } catch (e: Exception) {
            Log.e(TAG, "Error logging message data", e)
         }
         val title = message.notification?.title ?: message.data["title"] ?: "Nouvelle commande"
         val body = message.notification?.body ?: message.data["body"] ?: "Une nouvelle commande est disponible"
         val type = message.data["type"]
         val orderId = message.data["order_id"]
         val ttsFlag = message.data["tts"] == "1"
         if (type == "new_order") {
             // Démarrer la sonnerie en boucle 2s
            try {
                Log.d(TAG, "Starting ring service for orderId=$orderId")
                OrderRingService.start(this, orderId)
            } catch (e: Exception) {
                Log.e(TAG, "Error starting OrderRingService", e)
            }
            // Annonce vocale optionnelle
            if (ttsFlag) {
                try {
                    val voice = com.suzosky.coursier.services.VoiceGuidanceService(this)
                    val destination = message.data["adresse_arrivee"] ?: "destination"
                    voice.announceNewOrder("client", destination)
                    // Arrêt du TTS quelques secondes après l'annonce pour libérer la ressource
                    android.os.Handler(mainLooper).postDelayed({
                        try { voice.shutdown() } catch (_: Exception) {}
                    }, 5000L)
                } catch (e: Exception) {
                    Log.e(TAG, "Error triggering TTS announce", e)
                }
            }
            try {
                val broadcast = Intent(ACTION_REFRESH_DATA).apply {
                    putExtra(EXTRA_ORDER_ID, orderId)
                }
                // Limit broadcast delivery to this app only
                broadcast.`package` = packageName
                sendBroadcast(broadcast)
            } catch (e: Exception) {
                Log.e(TAG, "Error sending refresh broadcast", e)
            }
         }
         showNotification(title, body)
     }

     private fun showNotification(title: String, body: String) {
         // Android 13+ (Tiramisu): vérifier explicitement la permission POST_NOTIFICATIONS
         if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
             val granted = ContextCompat.checkSelfPermission(
                 this,
                 android.Manifest.permission.POST_NOTIFICATIONS
             ) == PackageManager.PERMISSION_GRANTED
             if (!granted) {
                 Log.w(TAG, "POST_NOTIFICATIONS non accordée, notification ignorée")
                 return
             }
         }

         // Vérifier si les notifications sont globalement activées pour l'app
         try {
             if (!NotificationManagerCompat.from(this).areNotificationsEnabled()) {
                 Log.w(TAG, "Notifications désactivées par l'utilisateur, notification ignorée")
                 return
             }
         } catch (e: Exception) {
             // Ne pas bloquer si l'API sous-jacente pose problème
             Log.w(TAG, "Impossible de vérifier areNotificationsEnabled()", e)
         }

         // Canal SILENCIEUX: la sonnerie est assurée par OrderRingService (boucle 2s)
         val channelId = "orders_notify_silent"
         if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
             val channel = NotificationChannel(channelId, "Commandes (silencieux)", NotificationManager.IMPORTANCE_HIGH)
             channel.setSound(null, null)
             getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
         }

         val builder = NotificationCompat.Builder(this, channelId)
             .setSmallIcon(R.drawable.ic_notification)
             .setContentTitle(title)
             .setContentText(body)
             .setPriority(NotificationCompat.PRIORITY_HIGH)
             .setAutoCancel(true)
             .setSilent(true)
        try {
            NotificationManagerCompat.from(this).notify((System.currentTimeMillis() % Int.MAX_VALUE).toInt(), builder.build())
        } catch (se: SecurityException) {
            Log.e(TAG, "SecurityException lors de l'affichage de la notification (permission manquante?)", se)
        } catch (e: Exception) {
            Log.e(TAG, "Error showing notification", e)
        }
     }
 }
