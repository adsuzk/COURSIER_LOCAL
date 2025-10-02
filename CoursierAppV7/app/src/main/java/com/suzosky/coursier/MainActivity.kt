
package com.suzosky.coursier

import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.core.view.WindowCompat
import androidx.core.content.ContextCompat
import androidx.core.content.edit
import androidx.core.net.toUri
import androidx.lifecycle.lifecycleScope
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.data.models.SystemHealth
import com.suzosky.coursier.data.models.HealthStatus
import com.suzosky.coursier.network.ApiService
import com.suzosky.coursier.services.AutoUpdateService
import com.suzosky.coursier.services.OrderRingService
import com.suzosky.coursier.services.VoiceGuidanceService
import com.suzosky.coursier.telemetry.TelemetrySDK
import com.suzosky.coursier.telemetry.UpdateInfo
import com.suzosky.coursier.ui.components.PaymentStatusDialog
import com.suzosky.coursier.ui.components.PaymentWebViewDialog
import com.suzosky.coursier.ui.components.WaitingForOrdersScreen
import com.suzosky.coursier.ui.screens.CoursierScreenNew
import com.suzosky.coursier.ui.screens.LoginScreen
import com.suzosky.coursier.ui.theme.SuzoskyTheme
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import okhttp3.Call
import okhttp3.Callback
import okhttp3.FormBody
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.Response
import java.io.IOException
import dagger.hilt.android.AndroidEntryPoint
import com.google.firebase.messaging.FirebaseMessaging
import android.content.BroadcastReceiver
import android.content.Context
import android.content.IntentFilter
import androidx.localbroadcastmanager.content.LocalBroadcastManager
import com.suzosky.coursier.messaging.FCMService

// Fonction utilitaire pour désactiver le token FCM côté serveur
fun deactivateFcmTokenOnServer(context: android.content.Context) {
    val prefs = context.getSharedPreferences("suzosky_prefs", android.content.Context.MODE_PRIVATE)
    val token = prefs.getString("fcm_token", null)
    if (token.isNullOrBlank()) return

    // Use ApiService helper which provides fallback and queuing behavior
    ApiService.deactivateDeviceToken(context, token, reEnqueueOnFailure = true, onSuccess = {
        Log.d("Logout", "✅ Token désactivé côté serveur via ApiService")
        println("✅ Token désactivé côté serveur via ApiService")
        try { prefs.edit { remove("fcm_token") } } catch (_: Exception) {}
    }, onFailure = { err ->
        Log.w("Logout", "🚨 Échec désactivation token via ApiService: $err")
        println("🚨 Échec désactivation token via ApiService: $err")
        // Make failure visible to user with a short Toast and a persistent log entry
        try {
            android.os.Handler(android.os.Looper.getMainLooper()).post {
                android.widget.Toast.makeText(context, "Échec désactivation token: $err", android.widget.Toast.LENGTH_LONG).show()
            }
        } catch (_: Exception) {}
    })
}
@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    // 🔊 Service de guidage vocal
    internal var voiceGuidance: VoiceGuidanceService? = null
    
    // BroadcastReceiver pour les nouvelles commandes
    private var commandeReceiver: BroadcastReceiver? = null
    
    // 🩺 Variables de monitoring système - initialisées à 0 pour forcer la première sync
    internal var lastSyncTimestamp = 0L
    internal var lastDatabaseCheck = false
    internal var lastFcmTokenCheck = false
    internal var lastSyncCheck = false
    
    // 🩺 Fonction pour calculer la santé du système
    internal fun calculateSystemHealth(prefs: android.content.SharedPreferences, hasRecentData: Boolean): SystemHealth {
        val now = System.currentTimeMillis()
        val timeSinceLastSync = (now - lastSyncTimestamp) / 1000
        
        // Vérifier la base de données (si on a reçu des données récemment)
        val databaseConnected = hasRecentData && timeSinceLastSync < 30
        lastDatabaseCheck = databaseConnected
        
        // Vérifier le token FCM
        val fcmToken = prefs.getString("fcm_token", null)
        val fcmTokenActive = !fcmToken.isNullOrBlank()
        lastFcmTokenCheck = fcmTokenActive
        
        // Vérifier la synchronisation (max 10s pour être considéré comme OK)
        val syncWorking = timeSinceLastSync < 10
        lastSyncCheck = syncWorking
        
        // Calculer le statut global
        val status = when {
            !databaseConnected -> HealthStatus.CRITICAL
            !fcmTokenActive -> HealthStatus.CRITICAL
            !syncWorking -> HealthStatus.WARNING
            else -> HealthStatus.HEALTHY
        }
        
        // Générer le message d'erreur si nécessaire
        val message = when {
            !databaseConnected -> "❌ Connexion à la base de données perdue"
            !fcmTokenActive -> "❌ Token FCM invalide ou expiré"
            !syncWorking -> "⚠️ Synchronisation lente (${timeSinceLastSync}s)"
            else -> "✅ Tous les systèmes opérationnels"
        }
        
        return SystemHealth(
            status = status,
            databaseConnected = databaseConnected,
            fcmTokenActive = fcmTokenActive,
            syncWorking = syncWorking,
            lastSyncTimestamp = lastSyncTimestamp,
            message = message
        )
    }
    
    // �🗺️ Fonction pour lancer Google Maps avec itinéraire
    fun launchGoogleMaps(depart: String, arrivee: String) {
        try {
            val uri = Uri.parse("https://www.google.com/maps/dir/?api=1&origin=${Uri.encode(depart)}&destination=${Uri.encode(arrivee)}&travelmode=driving")
            val intent = Intent(Intent.ACTION_VIEW, uri)
            intent.setPackage("com.google.android.apps.maps")
            startActivity(intent)
            Log.d("MainActivity", "🗺️ Google Maps lancé: $depart → $arrivee")
        } catch (e: Exception) {
            // Fallback: navigateur web
            try {
                val webUri = Uri.parse("https://www.google.com/maps/dir/?api=1&origin=${Uri.encode(depart)}&destination=${Uri.encode(arrivee)}")
                val webIntent = Intent(Intent.ACTION_VIEW, webUri)
                startActivity(webIntent)
                Log.d("MainActivity", "🌐 Maps via navigateur: $depart → $arrivee")
            } catch (e2: Exception) {
                Log.e("MainActivity", "❌ Impossible de lancer Maps", e2)
            }
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        try {
            println("🚀 MainActivity.onCreate - Début de l'initialisation")
            
            // 🔊 Initialiser le guidage vocal
            voiceGuidance = VoiceGuidanceService(this)
            Log.d("MainActivity", "🔊 Service de guidage vocal initialisé")
            
            // Configuration pour le mode edge-to-edge
            enableEdgeToEdge()
            WindowCompat.setDecorFitsSystemWindows(window, false)
            
            println("✅ Configuration edge-to-edge réussie")
            
            // Initialiser la télémétrie
            val telemetry = TelemetrySDK.initialize(
                context = this,
                baseUrl = try { com.suzosky.coursier.BuildConfig.PROD_BASE } catch (_: Throwable) { "https://coursier.conciergerie-privee-suzosky.com" },
                apiKey = "suzosky_telemetry_2025"
            )
            
            // Vérifier les mises à jour
            // Utilise une variable globale pour déclencher le dialog dans Compose
            val updateInfoToShow = arrayOfNulls<UpdateInfo>(1)
            lifecycleScope.launch {
                try {
                    val updateInfo = telemetry.checkForUpdates()
                    if (updateInfo?.isMandatory == true) {
                        // Déclencher l'affichage du dialog dans Compose
                        updateInfoToShow[0] = updateInfo
                    }
                } catch (e: Exception) {
                    println("Erreur vérification mises à jour: ${e.message}")
                }
            }
            // Passe updateInfoToShow à setContent pour affichage dans Compose
            
            val prefs = getSharedPreferences("suzosky_prefs", MODE_PRIVATE)

            // Demander les permissions essentielles : notifications + localisation
            val permissionsToRequest = mutableListOf<String>()
            
            // Android 13+ : permission notifications
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.POST_NOTIFICATIONS) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                    permissionsToRequest.add(android.Manifest.permission.POST_NOTIFICATIONS)
                }
            }
            
            // Permissions de localisation (obligatoires pour les coursiers)
            if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.ACCESS_FINE_LOCATION) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                permissionsToRequest.add(android.Manifest.permission.ACCESS_FINE_LOCATION)
            }
            if (ContextCompat.checkSelfPermission(this, android.Manifest.permission.ACCESS_COARSE_LOCATION) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                permissionsToRequest.add(android.Manifest.permission.ACCESS_COARSE_LOCATION)
            }

            // Android 14+ : explicit runtime permission for starting LOCATION foreground services
            if (android.os.Build.VERSION.SDK_INT >= 34) {
                if (ContextCompat.checkSelfPermission(this, "android.permission.FOREGROUND_SERVICE_LOCATION") != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                    permissionsToRequest.add("android.permission.FOREGROUND_SERVICE_LOCATION")
                }
            }
            
            // Demander toutes les permissions manquantes
            if (permissionsToRequest.isNotEmpty()) {
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.M) {
                    requestPermissions(permissionsToRequest.toTypedArray(), 1001)
                }
            }
            
            // Configuration du BroadcastReceiver pour les nouvelles commandes
            setupCommandeReceiver()
            
            // Démarrer le service de mise à jour automatique avec protection Android 14
            val canStartService = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                ContextCompat.checkSelfPermission(this, android.Manifest.permission.POST_NOTIFICATIONS) == android.content.pm.PackageManager.PERMISSION_GRANTED
            } else true
            
            if (canStartService) {
                // Protection spéciale pour Android 14 (API 34)
                if (android.os.Build.VERSION.SDK_INT >= 34) {
                    // Android 14 : délai avant démarrage du service
                    android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                        try { 
                            AutoUpdateService.startService(this) 
                            println("🔄 Service démarré avec délai (Android 14)")
                        } catch (e: SecurityException) {
                            println("❌ Service bloqué sur Android 14: ${e.message}")
                        }
                    }, 2000) // Délai de 2 secondes
                } else {
                    // Android 13 : démarrage normal
                    try { 
                        AutoUpdateService.startService(this) 
                        println("🔄 Service démarré normalement (Android 13)")
                    } catch (e: SecurityException) {
                        println("❌ Service bloqué: ${e.message}")
                    }
                }
            }
            
            println("🔄 Permissions et services configurés")
            
            setContent {
                SuzoskyTheme {
                    SuzoskyCoursierApp(updateInfoToShow)
                }
            }
            // FORCER l'enregistrement FCM dès le démarrage - VERSION ROBUSTE
            try {
                Log.d("MainActivity", "🔥 DÉBUT forçage enregistrement FCM au démarrage")
                println("🔥 DÉBUT forçage enregistrement FCM au démarrage")
                
                FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                    if (task.isSuccessful) {
                        val token = task.result
                        Log.d("MainActivity", "✅ Token FCM récupéré: ${token.substring(0, 20)}...")
                        println("✅ Token FCM récupéré: ${token.substring(0, 20)}...")
                        
                        // Sauvegarder localement
                        prefs.edit().putString("fcm_token", token).apply()
                        
                        val existingId = prefs.getInt("coursier_id", -1)
                        if (existingId > 0) {
                            Log.d("MainActivity", "🚀 Enregistrement immédiat pour coursier $existingId")
                            println("🚀 Enregistrement immédiat pour coursier $existingId")
                            ApiService.registerDeviceToken(this, existingId, token)
                            
                            // ⚡ PING IMMÉDIAT pour ouvrir le formulaire à la seconde
                            Log.d("MainActivity", "⚡ MainActivity: Ping immédiat pour ouverture formulaire")
                            println("⚡ MainActivity: Ping immédiat pour ouverture formulaire")
                            ApiService.pingDeviceToken(this, token)
                            
                            // Démarrer le ForegroundService de tracking si on a un coursier connecté
                            try {
                                startLocationForegroundService(existingId)
                            } catch (e: Exception) {
                                Log.w("MainActivity", "Impossible de démarrer LocationForegroundService: ${e.message}")
                            }
                        } else {
                            Log.d("MainActivity", "⏸️ Token sauvé, en attente de connexion coursier")
                            println("⏸️ Token sauvé, en attente de connexion coursier")
                        }
                    } else {
                        Log.w("MainActivity", "❌ FCM token fetch failed: ${task.exception?.message}")
                        println("❌ FCM token fetch failed: ${task.exception?.message}")
                    }
                }
            } catch (e: Exception) {
                Log.e("MainActivity", "registerDeviceToken startup error", e)
            }

            // Process any pending token deactivations queued from previous offline failures
            try {
                ApiService.processPendingDeactivations(this)
            } catch (e: Exception) {
                Log.w("MainActivity", "Erreur processPendingDeactivations: ${e.message}")
            }
            
            println("✅ Application démarrée avec succès")
            
        } catch (e: Exception) {
            println("❌ CRASH dans MainActivity.onCreate: ${e.message}")
            e.printStackTrace()
            
            // Reporter le crash via télémétrie
            TelemetrySDK.getInstance()?.reportCrash(
                throwable = e,
                screenName = "MainActivity",
                userAction = "App startup"
            )
            
            // Fallback - Interface d'erreur simple
            setContent {
                SuzoskyTheme {
                    ErrorFallbackScreen(error = e.message ?: "Erreur inconnue")
                }
            }
        }
    }

    private fun startLocationForegroundService(coursierId: Int) {
        try {
            val intent = android.content.Intent(this, com.suzosky.coursier.services.LocationForegroundService::class.java)
            intent.action = com.suzosky.coursier.services.LocationForegroundService.ACTION_START
            intent.putExtra(com.suzosky.coursier.services.LocationForegroundService.EXTRA_COURSIER_ID, coursierId)
            if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                startForegroundService(intent)
            } else {
                startService(intent)
            }
        } catch (e: Exception) {
            Log.w("MainActivity", "startLocationForegroundService failed: ${e.message}")
        }
    }

    private fun stopLocationForegroundService() {
        try {
            val intent = android.content.Intent(this, com.suzosky.coursier.services.LocationForegroundService::class.java)
            intent.action = com.suzosky.coursier.services.LocationForegroundService.ACTION_STOP
            startService(intent)
        } catch (e: Exception) {
            Log.w("MainActivity", "stopLocationForegroundService failed: ${e.message}")
        }
    }
    // Callback de permission notifications (Android 13+)
    @Suppress("DEPRECATION")
    @Deprecated("Use ActivityResultContracts for permissions in new code")
    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == 1001) {
            // Vérifier quelles permissions ont été accordées
            val notificationGranted = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.TIRAMISU) {
                ContextCompat.checkSelfPermission(this, android.Manifest.permission.POST_NOTIFICATIONS) == android.content.pm.PackageManager.PERMISSION_GRANTED
            } else true
            
            val locationGranted = ContextCompat.checkSelfPermission(this, android.Manifest.permission.ACCESS_FINE_LOCATION) == android.content.pm.PackageManager.PERMISSION_GRANTED ||
                                 ContextCompat.checkSelfPermission(this, android.Manifest.permission.ACCESS_COARSE_LOCATION) == android.content.pm.PackageManager.PERMISSION_GRANTED

            val fgsLocationGranted = if (android.os.Build.VERSION.SDK_INT >= 34) {
                ContextCompat.checkSelfPermission(this, "android.permission.FOREGROUND_SERVICE_LOCATION") == android.content.pm.PackageManager.PERMISSION_GRANTED
            } else true
            
            // Démarrer le service si les notifications sont accordées
            if (notificationGranted) {
                // Protection Android 14 : délai avant démarrage
                if (android.os.Build.VERSION.SDK_INT >= 34) {
                    android.os.Handler(android.os.Looper.getMainLooper()).postDelayed({
                        try { 
                            AutoUpdateService.startService(this) 
                            println("🔄 Service démarré après permission (Android 14)")
                        } catch (e: SecurityException) {
                            println("❌ Service bloqué après permission: ${e.message}")
                        }
                    }, 2000)
                } else {
                    try { 
                        AutoUpdateService.startService(this) 
                        println("🔄 Service démarré après permission")
                    } catch (e: SecurityException) {
                        println("❌ Service bloqué après permission: ${e.message}")
                    }
                }
            }
            
            // Informer l'utilisateur si la localisation n'est pas accordée
            // If location + FGS-location were granted and we have a coursier id saved, start the foreground tracking service.
            try {
                val prefs = getSharedPreferences("suzosky_prefs", MODE_PRIVATE)
                val existingId = prefs.getInt("coursier_id", -1)
                if (locationGranted && fgsLocationGranted && existingId > 0) {
                    try {
                        startLocationForegroundService(existingId)
                    } catch (e: Exception) {
                        Log.w("MainActivity", "Failed to start LocationForegroundService after permission grant: ${e.message}")
                    }
                }
            } catch (e: Exception) {
                Log.w("MainActivity", "Error while starting location service post-permission: ${e.message}")
            }

            // TODO: Show Compose snackbar for location permission result
        }
    }

    // Configuration du BroadcastReceiver pour les nouvelles commandes
    private fun setupCommandeReceiver() {
        commandeReceiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context?, intent: Intent?) {
                if (intent?.action == FCMService.ACTION_REFRESH_DATA) {
                    val orderId = intent.getStringExtra(FCMService.EXTRA_ORDER_ID)
                    println("🔔 BroadcastReceiver: Nouvelle commande reçue - Order ID: $orderId")
                    Log.d("MainActivity", "🔔 BroadcastReceiver: Nouvelle commande reçue - Order ID: $orderId")
                    
                    // Déclencher un rafraîchissement des données API
                    lifecycleScope.launch {
                        try {
                            val prefs = getSharedPreferences("suzosky_prefs", MODE_PRIVATE)
                            val coursierId = prefs.getInt("coursier_id", -1)
                            if (coursierId > 0) {
                                println("🔄 Rafraîchissement des commandes depuis l'API...")
                                
                                // Appeler l'API pour récupérer les nouvelles commandes
                                ApiService.getCoursierDetails(coursierId) { data, error ->
                                    if (data != null && error == null) {
                                        println("✅ Nouvelles commandes récupérées de l'API")
                                        // Les données seront automatiquement mises à jour par le LaunchedEffect existant
                                    } else {
                                        println("❌ Erreur lors du rafraîchissement des commandes: $error")
                                    }
                                }
                            }
                        } catch (e: Exception) {
                            println("❌ Exception lors du rafraîchissement: ${e.message}")
                            Log.e("MainActivity", "Exception lors du rafraîchissement", e)
                        }
                    }
                }
            }
        }
        
        // Enregistrer le receiver pour les broadcasts locaux
        val filter = IntentFilter(FCMService.ACTION_REFRESH_DATA)
        registerReceiver(commandeReceiver, filter)
        
        println("✅ BroadcastReceiver configuré pour ACTION_REFRESH_DATA")
        Log.d("MainActivity", "✅ BroadcastReceiver configuré pour ACTION_REFRESH_DATA")
    }

    
    override fun onDestroy() {
        super.onDestroy()
        // Désinscrire le BroadcastReceiver
        commandeReceiver?.let {
            try {
                unregisterReceiver(it)
                println("✅ BroadcastReceiver désinscrit")
                Log.d("MainActivity", "✅ BroadcastReceiver désinscrit")
            } catch (e: Exception) {
                println("❌ Erreur lors de la désinscription du receiver: ${e.message}")
                Log.e("MainActivity", "❌ Erreur lors de la désinscription du receiver", e)
            }
        }
    }
}

// TODO: Replace with Compose-native UpdateDialog

@Composable
fun ErrorFallbackScreen(error: String) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(16.dp)
        ) {
            Text("❌ Erreur d'initialisation")
            Text("Détails: $error")
            Text("Veuillez redémarrer l'application")
        }
    }
}

@Composable
fun SuzoskyCoursierApp(updateInfoToShow: Array<UpdateInfo?>) {
    val context = LocalContext.current
    
    // Gestion de l'intent pour ouvrir une commande spécifique
    LaunchedEffect(Unit) {
        val activity = context as? ComponentActivity
        activity?.intent?.let { intent ->
            val openOrderId = intent.getStringExtra("open_order_id")
            if (!openOrderId.isNullOrBlank()) {
                println("🎯 Intent détecté: Ouverture commande ID $openOrderId")
                Log.d("MainActivity", "🎯 Intent détecté: Ouverture commande ID $openOrderId")
                
                // Forcer le rafraîchissement et l'ouverture de l'onglet Courses
                // Cette logique sera gérée par les paramètres de CoursierScreenNew
            }
        }
    }
    
    // Affichage du dialog de mise à jour si besoin
    val updateInfo = updateInfoToShow[0]
    val uriHandler = androidx.compose.ui.platform.LocalUriHandler.current
    if (updateInfo != null) {
        AlertDialog(
            onDismissRequest = { updateInfoToShow[0] = null },
            confirmButton = {
                TextButton(onClick = {
                    uriHandler.openUri(updateInfo.downloadUrl)
                    updateInfoToShow[0] = null
                }) { Text("Mettre à jour") }
            },
            dismissButton = {
                if (!updateInfo.isMandatory) {
                    TextButton(onClick = { updateInfoToShow[0] = null }) { Text("Plus tard") }
                }
            },
            title = { Text("Mise à jour disponible") },
            text = {
                Column {
                    Text("Version: ${updateInfo.versionName}")
                    Spacer(Modifier.height(8.dp))
                    Text(updateInfo.releaseNotes ?: "Une nouvelle version est disponible.")
                }
            }
        )
    }
    // Persist a simple login flag in SharedPreferences to stabilize navigation after login
    val prefs = remember { context.getSharedPreferences("suzosky_prefs", android.content.Context.MODE_PRIVATE) }
    
    // 🔥 Capturer référence à l'Activity pour Voice + Maps
    val activity = context as? MainActivity
    
    println("🔄 SuzoskyCoursierApp - Initialisation")
    
    // État global avec vraies données
        val coursierNomState = remember { mutableStateOf(prefs.getString("coursier_nom", "Coursier") ?: "Coursier") }
        val coursierIdState = remember { mutableStateOf(prefs.getInt("coursier_id", -1).takeIf { it > 0 } ?: -1) }
        val coursierStatutState = remember { mutableStateOf("EN_LIGNE") }
        val soldeReelState = remember { mutableStateOf(0.0) }
        val commandesReellesState = remember { mutableStateOf(emptyList<Commande>()) }
        val gainsDuJourState = remember { mutableStateOf(0.0) }
        val totalCommandesState = remember { mutableStateOf(0) }
        val noteGlobaleState = remember { mutableStateOf(0.0) }
        val coursierTelephoneState = remember { mutableStateOf("") }
        val coursierEmailState = remember { mutableStateOf("") }
        val dateInscriptionState = remember { mutableStateOf("") }
        val coursierMatriculeState = remember { mutableStateOf("") }
        
        // Variables pour le rafraîchissement automatique des commandes
        val shouldRefreshCommandesState = remember { mutableStateOf(false) }
        val newOrderIdState = remember { mutableStateOf<String?>(null) }
        
        var coursierNom by coursierNomState
        var coursierId by coursierIdState
        var coursierStatut by coursierStatutState
        var soldeReel by soldeReelState
        var commandesReelles by commandesReellesState
        var gainsDuJour by gainsDuJourState
        var totalCommandes by totalCommandesState
        var noteGlobale by noteGlobaleState
        var coursierTelephone by coursierTelephoneState
        var coursierEmail by coursierEmailState
        var dateInscription by dateInscriptionState
        var coursierMatricule by coursierMatriculeState
        var shouldRefreshCommandes by shouldRefreshCommandesState
        var newOrderId by newOrderIdState
        
    // Persist login UI state across recompositions/config changes + initialize from prefs
    var isLoggedIn by rememberSaveable { mutableStateOf(prefs.getBoolean("isLoggedIn", false)) }
    if (!isLoggedIn) {
        println("🔐 Affichage LoginScreen")
        LoginScreen(onLoginSuccess = {
            println("✅ Login réussi")
            try { prefs.edit { putBoolean("isLoggedIn", true) } } catch (_: Exception) {}
            isLoggedIn = true
            // Récupérer l'ID agent réel côté serveur
            ApiService.checkCoursierSession { id, err ->
                if ((id ?: 0) > 0) {
                    coursierId = id!!
                    try { prefs.edit { putInt("coursier_id", coursierId) } } catch (_: Exception) {}
                    // FORCER l'enregistrement token FCM après connexion - VERSION ROBUSTE  
                    try {
                        Log.d("MainActivity", "🔥 Post-connexion: Forçage FCM pour coursier $coursierId")
                        println("🔥 Post-connexion: Forçage FCM pour coursier $coursierId")
                        
                        // Essayer d'abord le token sauvé
                        val savedToken = prefs.getString("fcm_token", null)
                        if (savedToken != null) {
                            Log.d("MainActivity", "🎯 Utilisation token sauvé")
                            println("🎯 Utilisation token sauvé")
                            ApiService.registerDeviceToken(context, coursierId, savedToken)
                        }
                        
                        // Puis récupérer un nouveau token pour être sûr
                        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                            if (task.isSuccessful) {
                                val token = task.result
                                Log.d("MainActivity", "✨ Nouveau token post-connexion: ${token.substring(0, 20)}...")
                                println("✨ Nouveau token post-connexion: ${token.substring(0, 20)}...")
                                
                                prefs.edit().putString("fcm_token", token).apply()
                                ApiService.registerDeviceToken(context, coursierId, token)
                            } else {
                                Log.e("MainActivity", "❌ Erreur récupération token post-connexion")
                                println("❌ Erreur récupération token post-connexion")
                            }
                        }
                    } catch (e: Exception) {
                        Log.e("MainActivity", "💥 Exception FCM post-connexion", e)
                        println("💥 Exception FCM post-connexion: ${e.message}")
                    }
                } else {
                    println("⚠️ check_session a échoué: ${'$'}err")
                }
            }
        })
    } else {
        println("🏠 Affichage écran principal")

        // États pour les commandes
        var loading by remember { mutableStateOf(true) }
        var error by remember { mutableStateOf<String?>(null) }

    // Paiement CinetPay intégré
    var paymentUrl by remember { mutableStateOf<String?>(null) }
    var showPaymentDialog by remember { mutableStateOf(false) }
    var pendingRechargeAmount by remember { mutableStateOf<Double?>(null) }
    var isInitiatingPayment by remember { mutableStateOf(false) }
    var isPollingBalance by remember { mutableStateOf(false) }
    
    // Compteur pour forcer le rechargement
    var refreshTrigger by remember { mutableStateOf(System.currentTimeMillis()) }

        // Charger les VRAIES données au login - SE DÉCLENCHE À CHAQUE CHANGEMENT DE refreshTrigger
        LaunchedEffect(isLoggedIn, coursierId, refreshTrigger) {
            Log.d("MainActivity", "LaunchedEffect triggered - isLoggedIn=$isLoggedIn, coursierId=$coursierId, refreshTrigger=$refreshTrigger")
            if (!isLoggedIn) return@LaunchedEffect

            if (coursierId <= 0) {
                println("⚠️ coursierId invalide ou absent - vérification session côté serveur")
                loading = true
                ApiService.checkCoursierSession { id, err ->
                    if ((id ?: 0) > 0) {
                        coursierId = id!!
                        try { prefs.edit { putInt("coursier_id", coursierId) } } catch (_: Exception) {}
                    } else {
                        loading = false
                        error = err ?: "Session invalide"
                        println("⚠️ Impossible de récupérer l'ID coursier: ${'$'}err")
                    }
                }
                return@LaunchedEffect
            }

            println("🔄 Chargement des VRAIES données depuis l'API")
            Log.d("MainActivity", "Calling ApiService.getCoursierData for coursierId=$coursierId")
            loading = true

            ApiService.getCoursierData(coursierId) { data, err ->
                Log.d("MainActivity", "API Response - data: ${data != null}, error: $err")
                if (data != null) {
                    println("✅ Données réelles reçues")
                    fun toDoubleSafe(v: Any?): Double = when (v) {
                        null -> 0.0
                        is Number -> v.toDouble()
                        is String -> v.toDoubleOrNull() ?: 0.0
                        else -> 0.0
                    }
                    fun toStringSafe(v: Any?): String = when (v) {
                        null -> ""
                        is String -> v
                        else -> v.toString()
                    }
                    fun toIntFromDistanceKm(v: Any?): Int {
                        val km = toDoubleSafe(v)
                        return (km * 3.0).toInt()
                    }

                    // Charger le profil complet (nom, téléphone, stats)
                    ApiService.getCoursierProfile(coursierId) { pData, pErr ->
                        if (pData != null) {
                            val nomComplet = toStringSafe(pData["nom_complet"]).ifBlank { coursierNom }
                            if (nomComplet.isNotBlank()) {
                                coursierNom = nomComplet
                                try { prefs.edit { putString("coursier_nom", nomComplet) } } catch (_: Exception) {}
                            }
                            totalCommandes = ((pData["total_commandes"]) as? Int) ?: (toDoubleSafe(pData["total_commandes"]).toInt())
                            noteGlobale = toDoubleSafe(pData["note_globale"]).let { if (it.isFinite()) it else 0.0 }
                            coursierTelephone = toStringSafe(pData["telephone"])
                            coursierEmail = toStringSafe(pData["email"]).ifBlank { coursierEmail }
                            dateInscription = toStringSafe(pData["date_inscription"])
                        }
                    }

                    soldeReel = toDoubleSafe(data["balance"]) 
                        .let { if (it.isFinite()) it else 0.0 }
                    gainsDuJour = toDoubleSafe(data["gains_du_jour"]) 
                        .let { if (it.isFinite()) it else 0.0 }
                    
                    // Récupérer le matricule réel depuis l'API
                    val matriculeFromApi = toStringSafe(data["matricule"])
                    if (matriculeFromApi.isNotBlank()) {
                        coursierMatricule = matriculeFromApi
                        try { prefs.edit { putString("coursier_matricule", matriculeFromApi) } } catch (_: Exception) {}
                    }

                    // Convertir les commandes
                    @Suppress("UNCHECKED_CAST")
                    val commandesData = data["commandes"] as? List<Map<String, Any>> ?: emptyList()
                    Log.d("MainActivity", "Commandes data received: ${commandesData.size} orders")
                    commandesReelles = try {
                        commandesData.mapIndexed { index, cmdMap ->
                            // DEBUG: Logger les données brutes
                            Log.d("MainActivity", "=== ORDER $index RAW DATA ===")
                            Log.d("MainActivity", "telephoneDestinataire raw: ${cmdMap["telephoneDestinataire"]}")
                            Log.d("MainActivity", "latitudeEnlevement raw: ${cmdMap["latitudeEnlevement"]}")
                            Log.d("MainActivity", "longitudeEnlevement raw: ${cmdMap["longitudeEnlevement"]}")
                            Log.d("MainActivity", "latitudeLivraison raw: ${cmdMap["latitudeLivraison"]}")
                            Log.d("MainActivity", "longitudeLivraison raw: ${cmdMap["longitudeLivraison"]}")
                            
                            // Extraire les coordonnées GPS
                            val latEnlevement = toDoubleSafe(cmdMap["latitudeEnlevement"])
                            val lonEnlevement = toDoubleSafe(cmdMap["longitudeEnlevement"])
                            val latLivraison = toDoubleSafe(cmdMap["latitudeLivraison"])
                            val lonLivraison = toDoubleSafe(cmdMap["longitudeLivraison"])
                            
                            Log.d("MainActivity", "Parsed: lat=$latEnlevement, lon=$lonEnlevement")
                            
                            val coordEnlevement = if (latEnlevement != 0.0 && lonEnlevement != 0.0 && 
                                                       latEnlevement.isFinite() && lonEnlevement.isFinite()) {
                                com.suzosky.coursier.data.models.Coordonnees(latEnlevement, lonEnlevement)
                            } else null
                            
                            val coordLivraison = if (latLivraison != 0.0 && lonLivraison != 0.0 && 
                                                     latLivraison.isFinite() && lonLivraison.isFinite()) {
                                com.suzosky.coursier.data.models.Coordonnees(latLivraison, lonLivraison)
                            } else null
                            
                            Log.d("MainActivity", "Created coords: enlevement=$coordEnlevement, livraison=$coordLivraison")
                            
                            Commande(
                                id = toStringSafe(cmdMap["id"]),
                                clientNom = toStringSafe(cmdMap["clientNom"]),
                                clientTelephone = toStringSafe(cmdMap["clientTelephone"]),
                                telephoneDestinataire = toStringSafe(cmdMap["telephoneDestinataire"]),
                                adresseEnlevement = toStringSafe(cmdMap["adresseEnlevement"]),
                                adresseLivraison = toStringSafe(cmdMap["adresseLivraison"]),
                                coordonneesEnlevement = coordEnlevement,
                                coordonneesLivraison = coordLivraison,
                                distance = toDoubleSafe(cmdMap["distance"]).let { if (it.isFinite()) it else 0.0 },
                                tempsEstime = toIntFromDistanceKm(cmdMap["distance"]),
                                prixTotal = toDoubleSafe(cmdMap["prixLivraison"]).let { if (it.isFinite()) it else 0.0 },
                                prixLivraison = toDoubleSafe(cmdMap["prixLivraison"]).let { if (it.isFinite()) it else 0.0 },
                                methodePaiement = toStringSafe(cmdMap["methodePaiement"]).ifBlank { "especes" },
                                // Ne pas forcer un statut test. Si vide, classer comme "inconnue" pour éviter faux positifs.
                                statut = toStringSafe(cmdMap["statut"]).ifBlank { "inconnue" },
                                // Éviter les labels de démo; utiliser les champs si présents, sinon vide
                                dateCommande = toStringSafe(cmdMap["dateCommande"]),
                                heureCommande = toStringSafe(cmdMap["heureCommande"]),
                                description = toStringSafe(cmdMap["description"]),
                                typeCommande = "Standard"
                            )
                        }.filter { commande ->
                            // ⚠️ FILTRER LES COMMANDES TERMINÉES (livree, annulee, refusee, cash_recu)
                            val statut = commande.statut.lowercase()
                            val estActive = statut != "livree" && 
                                           statut != "annulee" && 
                                           statut != "refusee" &&
                                           statut != "cash_recu" &&
                                           statut != "terminee"
                            
                            if (!estActive) {
                                Log.d("MainActivity", "🚫 Commande ${commande.id} FILTRÉE (statut: $statut)")
                            }
                            
                            estActive
                        }
                    } catch (e: Exception) {
                        TelemetrySDK.getInstance()?.reportCrash(
                            throwable = e,
                            screenName = "MainActivity",
                            userAction = "parse_commandes",
                            additionalData = mapOf("payload_size" to commandesData.size)
                        )
                        println("❌ Erreur conversion commandes: ${e.message}")
                        emptyList()
                    }
                    Log.d("MainActivity", "Commandes loaded: ${commandesReelles.size} orders mapped successfully")
                    commandesReelles.forEach { cmd ->
                        Log.d("MainActivity", "  Order: id=${cmd.id}, statut=${cmd.statut}, client=${cmd.clientNom}")
                    }
                    error = null
                } else {
                    println("❌ Erreur chargement données: $err")
                    error = err
                }
                loading = false
            }
        }

        // Surveillance de session: déconnexion automatique si SESSION_REVOKED uniquement
        LaunchedEffect(isLoggedIn) {
            if (isLoggedIn) {
                var consecutiveErrors = 0
                while (isLoggedIn) {
                    kotlinx.coroutines.delay(30000) // toutes les 30s (moins agressif)
                    ApiService.checkCoursierSession { id, err ->
                        if (err != null) {
                            val e = err.uppercase()
                            // Seul SESSION_REVOKED force la déconnexion (pas NO_SESSION qui peut être temporaire)
                            if (e.contains("SESSION_REVOKED")) {
                                consecutiveErrors++
                                // Déconnexion uniquement après 2 erreurs consécutives pour éviter les faux positifs
                                if (consecutiveErrors >= 2) {
                                    try { prefs.edit { putBoolean("isLoggedIn", false) } } catch (_: Exception) {}
                                    isLoggedIn = false
                                    // TODO: Show Compose snackbar for session expiration
                                }
                            } else {
                                // Erreur temporaire, on ignore
                                consecutiveErrors = maxOf(0, consecutiveErrors - 1)
                            }
                        } else {
                            // Succès, reset du compteur d'erreurs
                            consecutiveErrors = 0
                        }
                    }
                }
            }
        }

        // 🔥 POLLING AUTOMATIQUE DES NOUVELLES COMMANDES - CHAQUE SECONDE !
        LaunchedEffect(isLoggedIn, coursierId) {
            if (isLoggedIn && coursierId > 0) {
                Log.d("MainActivity", "🔄 Démarrage du polling automatique ULTRA-RAPIDE (1s)")
                
                // 🔥 SET pour tracker les IDs déjà vus
                val commandesVues = mutableSetOf<String>()
                var premiereFois = true // Flag pour initialisation
                
                while (isLoggedIn && coursierId > 0) {
                    kotlinx.coroutines.delay(1000) // CHAQUE SECONDE !
                    
                    Log.d("MainActivity", "🔍 Polling: Vérification des nouvelles commandes...")
                    ApiService.getCoursierData(coursierId) { data, err ->
                        if (data != null) {
                            @Suppress("UNCHECKED_CAST")
                            val commandesData = data["commandes"] as? List<Map<String, Any>> ?: emptyList()
                            val nbCommandesRecues = commandesData.size
                            val nbCommandesActuelles = commandesReelles.size
                            
                            Log.d("MainActivity", "📊 Polling: ${nbCommandesRecues} commandes (avant: ${nbCommandesActuelles})")
                            
                            // 🩺 Mettre à jour le timestamp de dernière sync réussie
                            activity?.lastSyncTimestamp = System.currentTimeMillis()
                            
                            // 🔥 PREMIÈRE FOIS : Initialiser le Set avec toutes les commandes existantes (pas de notification)
                            if (premiereFois) {
                                commandesData.forEach { cmdMap ->
                                    val cmdId = cmdMap["id"]?.toString() ?: ""
                                    if (cmdId.isNotEmpty()) {
                                        commandesVues.add(cmdId)
                                    }
                                }
                                Log.d("MainActivity", "🎯 Initialisation: ${commandesVues.size} commandes enregistrées (pas de notification)")
                                premiereFois = false
                                refreshTrigger++
                                return@getCoursierData
                            }
                            
                            // 🔥 NOUVELLE DÉTECTION : Chercher les IDs nouvelles
                            Log.d("MainActivity", "🔍 CommandesVues (${commandesVues.size}): ${commandesVues.joinToString(",")}")
                            Log.d("MainActivity", "🔍 CommandesRecues (${commandesData.size}): ${commandesData.map { it["id"]?.toString() ?: "?" }.joinToString(",")}")
                            
                            val nouvellesCommandes = commandesData.filter { cmdMap ->
                                val cmdId = cmdMap["id"]?.toString() ?: ""
                                val isNew = cmdId.isNotEmpty() && !commandesVues.contains(cmdId)
                                if (isNew) {
                                    Log.d("MainActivity", "🆕 NOUVELLE COMMANDE TROUVÉE: ID=$cmdId")
                                }
                                isNew
                            }
                            
                            if (nouvellesCommandes.isNotEmpty()) {
                                Log.d("MainActivity", "🆕 ${nouvellesCommandes.size} NOUVELLES COMMANDES DÉTECTÉES ! Refresh + notification...")
                                
                                // Ajouter les nouveaux IDs au set
                                nouvellesCommandes.forEach { cmdMap ->
                                    val cmdId = cmdMap["id"]?.toString() ?: ""
                                    if (cmdId.isNotEmpty()) {
                                        commandesVues.add(cmdId)
                                    }
                                }
                                
                                // 🔔 NOTIFICATION SONORE + VIBRATION pour CHAQUE nouvelle commande
                                nouvellesCommandes.forEach { newCommande ->
                                    try {
                                        // 🔥 VIBRATION PUISSANTE - Pattern : 0ms, 500ms ON, 200ms OFF, 500ms ON, 200ms OFF, 500ms ON
                                        val vibrator = activity?.getSystemService(Context.VIBRATOR_SERVICE) as? android.os.Vibrator
                                        if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                                            val vibrationPattern = longArrayOf(
                                                0,      // Délai initial
                                                500,    // Vibration 1 (forte)
                                                200,    // Pause
                                                500,    // Vibration 2 (forte)
                                                200,    // Pause
                                                500     // Vibration 3 (forte)
                                            )
                                            vibrator?.vibrate(android.os.VibrationEffect.createWaveform(vibrationPattern, -1))
                                        } else {
                                            @Suppress("DEPRECATION")
                                            vibrator?.vibrate(1500) // 1.5 secondes continue
                                        }
                                        
                                        Log.d("MainActivity", "📳 Vibration déclenchée pour commande ${newCommande["id"]}")
                                        
                                        // Son de notification
                                        val notification = android.media.RingtoneManager.getDefaultUri(android.media.RingtoneManager.TYPE_NOTIFICATION)
                                        val ringtone = android.media.RingtoneManager.getRingtone(activity?.applicationContext, notification)
                                        ringtone.play()
                                        
                                        // 🔊 Annonce vocale
                                        val clientName = newCommande["client_nom"]?.toString() ?: "un client"
                                        val destination = newCommande["adresse_livraison"]?.toString() ?: "destination inconnue"
                                        activity?.voiceGuidance?.announceNewOrder(clientName, destination)
                                        
                                        Log.d("MainActivity", "🔔 Notification émise: commande ${newCommande["id"]} - $clientName vers $destination")
                                    } catch (e: Exception) {
                                        Log.e("MainActivity", "❌ Erreur notification", e)
                                    }
                                }
                                
                                refreshTrigger++
                            } else if (nbCommandesRecues != nbCommandesActuelles) {
                                // Nombre changé mais pas de nouvelles IDs → mise à jour statut
                                refreshTrigger++
                            } else {
                                // Vérifier si le statut d'une commande a changé
                                commandesData.forEach { cmdMap ->
                                    val cmdId = cmdMap["id"]?.toString() ?: ""
                                    val cmdStatut = cmdMap["statut"]?.toString() ?: ""
                                    val existante = commandesReelles.find { it.id == cmdId }
                                    if (existante != null && existante.statut != cmdStatut) {
                                        Log.d("MainActivity", "🔄 Commande $cmdId: statut changé de ${existante.statut} → $cmdStatut")
                                        refreshTrigger++
                                        return@forEach
                                    }
                                }
                            }
                        } else {
                            Log.d("MainActivity", "⚠️ Polling: Erreur API - $err")
                        }
                    }
                }
                Log.d("MainActivity", "⏹️ Arrêt du polling automatique")
            }
        }

        Scaffold(modifier = Modifier.fillMaxSize()) { paddingValues ->
            Box(modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
            ) {
                when {
                    loading -> {
                        println("⏳ État loading")
                        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator()
                        }
                    }
                    error != null -> {
                        println("❌ État erreur: $error")
                        Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                            Text("Erreur: $error")
                        }
                    }
                    // 🩺 SI AUCUNE COMMANDE ACTIVE → Écran d'attente avec voyant système
                    commandesReelles.isEmpty() -> {
                        println("⏸️ Aucune commande active - Affichage écran d'attente")
                        val systemHealth = activity?.calculateSystemHealth(prefs, hasRecentData = !loading) 
                            ?: SystemHealth(
                                status = HealthStatus.WARNING,
                                databaseConnected = false,
                                fcmTokenActive = false,
                                syncWorking = false,
                                lastSyncTimestamp = System.currentTimeMillis(),
                                message = "Impossible de calculer l'état système"
                            )
                        
                        WaitingForOrdersScreen(
                            systemHealth = systemHealth,
                            nbCommandesEnAttente = 0,
                            modifier = Modifier.fillMaxSize()
                        )
                    }
                    else -> {
                        println("✅ Affichage CoursierScreenNew avec VRAIES données")
                        // VRAIES DONNÉES de l'API
                        CoursierScreenNew(
                            modifier = Modifier.fillMaxSize(),
                            coursierId = coursierId,
                            coursierNom = coursierNom,
                            coursierStatut = coursierStatut,
                            totalCommandes = totalCommandes,
                            noteGlobale = noteGlobale,
                            coursierTelephone = coursierTelephone,
                            coursierEmail = coursierEmail,
                            dateInscription = dateInscription,
                            commandes = commandesReelles, // VRAIES commandes de l'API
                            balance = soldeReel.toInt(), // VRAI solde de l'API
                            gainsDuJour = gainsDuJour.toInt(), // VRAIS gains de l'API
                            onStatutChange = { nouveauStatut -> coursierStatut = nouveauStatut },
                            onCommandeAccept = { commandeId ->
                                try { OrderRingService.stop(context) } catch (_: Exception) {}
                                
                                // 🔊 Annonce vocale UNIQUEMENT (PAS DE MAPS SUR ACCEPT !)
                                activity?.voiceGuidance?.announceOrderAccepted()
                                
                                // Accepter la commande via API
                                ApiService.respondToOrder(commandeId, coursierId.toString(), "accept") { success, message ->
                                    if (success) {
                                        // Déclencher un rechargement des commandes
                                        shouldRefreshCommandes = true
                                    }
                                }
                            },
                            onCommandeReject = { commandeId ->
                                try { OrderRingService.stop(context) } catch (_: Exception) {}
                                // Refuser la commande via API
                                ApiService.respondToOrder(commandeId, coursierId.toString(), "refuse") { success, message ->
                                    if (success) {
                                        // Déclencher un rechargement des commandes
                                        shouldRefreshCommandes = true
                                    }
                                }
                            },
                            onCommandeAttente = { /* TODO: Waiting logic */ },
                            onNavigateToProfile = { /* TODO: Navigation */ },
                            onNavigateToHistorique = { /* TODO: Navigation */ },
                            onNavigateToGains = { /* TODO: Navigation */ },

                            onLogout = {
                                deactivateFcmTokenOnServer(context)
                                try { prefs.edit { putBoolean("isLoggedIn", false) } } catch (_: Exception) {}
                                isLoggedIn = false
                            },
                            // Ouvrir le paiement via Custom Tab et surveiller la confirmation
                            onRecharge = { amount ->
                                if (amount > 0) {
                                    pendingRechargeAmount = amount.toDouble()
                                    isInitiatingPayment = true
                                    ApiService.initRecharge(coursierId, amount.toDouble()) { url, error ->
                                        isInitiatingPayment = false
                                        if (url != null) {
                                            paymentUrl = url
                                            showPaymentDialog = true
                                        } else {
                                            // TODO: Show Compose snackbar for payment error
                                        }
                                    }
                                } else {
                                    // TODO: Show Compose snackbar for invalid amount
                                }
                            }
                        )
                        if (isInitiatingPayment) {
                            PaymentStatusDialog(
                                title = "Initialisation du paiement",
                                message = "Connexion sécurisée à CinetPay…",
                                cancellable = false
                            )
                        }

                        if (isPollingBalance) {
                            PaymentStatusDialog(
                                title = "Validation du paiement",
                                message = "Nous vérifions la confirmation et mettons à jour votre solde…",
                                cancellable = false
                            )
                        }

                        if (showPaymentDialog && paymentUrl != null) {
                            val initialBalanceSnapshot = soldeReel
                            PaymentWebViewDialog(
                                url = paymentUrl!!,
                                amount = pendingRechargeAmount,
                                onDismiss = {
                                    showPaymentDialog = false
                                    paymentUrl = null
                                },
                                onCompleted = { success, transactionId ->
                                    showPaymentDialog = false
                                    paymentUrl = null
                                    pendingRechargeAmount = null
                                    if (success) {
                                        isPollingBalance = true
                                        ApiService.pollBalanceUntilChange(
                                            coursierId = coursierId,
                                            initialBalance = initialBalanceSnapshot,
                                            onResult = { newBalance, updated ->
                                                isPollingBalance = false
                                                if (updated && newBalance != null) {
                                                    soldeReel = newBalance
                                                    // TODO: Show Compose snackbar for recharge success
                                                } else {
                                                    // TODO: Show Compose snackbar for payment info
                                                }
                                            }
                                        )
                                    } else {
                                        // TODO: Show Compose snackbar for recharge not confirmed
                                    }
                                }
                            )
                        }
                    }
                }
            }
        }
    }

    /*
    // Configuration du BroadcastReceiver pour les nouvelles commandes - DOUBLE DÉCLARATION COMMENTÉE
    private fun setupCommandeReceiver() {
        commandeReceiver = object : BroadcastReceiver() {
            override fun onReceive(context: Context?, intent: Intent?) {
                if (intent?.action == FCMService.ACTION_REFRESH_DATA) {
                    val orderId = intent.getStringExtra(FCMService.EXTRA_ORDER_ID)
                    println("🔔 BroadcastReceiver: Nouvelle commande reçue - Order ID: $orderId")
                    Log.d("MainActivity", "🔔 BroadcastReceiver: Nouvelle commande reçue - Order ID: $orderId")
                    
                    // Déclencher un rafraîchissement des données API
                    lifecycleScope.launch {
                        try {
                            val prefs = getSharedPreferences("suzosky_prefs", MODE_PRIVATE)
                            val coursierId = prefs.getInt("coursier_id", -1)
                            if (coursierId > 0) {
                                println("🔄 Rafraîchissement des commandes depuis l'API...")
                                
                                // Appeler l'API pour récupérer les nouvelles commandes
                                ApiService.getCoursierDetails(coursierId) { data, error ->
                                    if (data != null && error == null) {
                                        println("✅ Nouvelles commandes récupérées de l'API")
                                        // Les données seront automatiquement mises à jour par le LaunchedEffect existant
                                    } else {
                                        println("❌ Erreur lors du rafraîchissement des commandes: $error")
                                    }
                                }
                            }
                        } catch (e: Exception) {
                            println("❌ Exception lors du rafraîchissement: ${e.message}")
                            Log.e("MainActivity", "Exception lors du rafraîchissement", e)
                        }
                    }
                }
            }
        }
        
        // Enregistrer le receiver pour les broadcasts locaux
        val filter = IntentFilter(FCMService.ACTION_REFRESH_DATA)
        registerReceiver(commandeReceiver, filter)
        
        println("✅ BroadcastReceiver configuré pour ACTION_REFRESH_DATA")
        Log.d("MainActivity", "✅ BroadcastReceiver configuré pour ACTION_REFRESH_DATA")
    }

    override fun onDestroy() {
        super.onDestroy()
        
        // 🔊 Arrêter le TTS
        voiceGuidance?.shutdown()
        voiceGuidance = null
        Log.d("MainActivity", "🔊 Service vocal arrêté")
        
        // Désinscrire le BroadcastReceiver
        commandeReceiver?.let {
            try {
                unregisterReceiver(it)
                println("✅ BroadcastReceiver désinscrit")
                Log.d("MainActivity", "✅ BroadcastReceiver désinscrit")
            } catch (e: Exception) {
                println("❌ Erreur lors de la désinscription du receiver: ${e.message}")
                Log.e("MainActivity", "❌ Erreur lors de la désinscription du receiver", e)
            }
        }
    }
    */
}


