
package com.suzosky.coursier

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
import com.suzosky.coursier.network.ApiService
import com.suzosky.coursier.services.AutoUpdateService
import com.suzosky.coursier.services.OrderRingService
import com.suzosky.coursier.telemetry.TelemetrySDK
import com.suzosky.coursier.telemetry.UpdateInfo
import com.suzosky.coursier.ui.components.PaymentStatusDialog
import com.suzosky.coursier.ui.components.PaymentWebViewDialog
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

// Fonction utilitaire pour désactiver le token FCM côté serveur
fun deactivateFcmTokenOnServer(context: android.content.Context) {
    val prefs = context.getSharedPreferences("suzosky_prefs", android.content.Context.MODE_PRIVATE)
    val token = prefs.getString("fcm_token", null)
    if (token.isNullOrBlank()) return
    val client = OkHttpClient()
    val formBody = FormBody.Builder().add("token", token).build()
    val url = "https://coursier.conciergerie-privee-suzosky.com/COURSIER_LOCAL/deactivate_device_token.php"
    val request = Request.Builder().url(url).post(formBody).build()
    CoroutineScope(Dispatchers.IO).launch {
        try {
            client.newCall(request).enqueue(object : Callback {
                override fun onFailure(call: Call, e: IOException) {
                    Log.w("Logout", "Échec désactivation token côté serveur: ${e.message}")
                }
                override fun onResponse(call: Call, response: Response) {
                    Log.d("Logout", "Token désactivé côté serveur: ${response.code}")
                    response.close()
                }
            })
        } catch (e: Exception) {
            Log.w("Logout", "Erreur désactivation token: ${e.message}")
            import android.os.Bundle
            import android.util.Log
 * MainActivity - Point d'entrée de l'app Suzosky Coursier
 * Interface 100% identique au design web coursier.php
 * VERSION SÉCURISÉE avec gestion d'erreurs
 */
@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        try {
            println("🚀 MainActivity.onCreate - Début de l'initialisation")
            
            // Configuration pour le mode edge-to-edge
            enableEdgeToEdge()
            WindowCompat.setDecorFitsSystemWindows(window, false)
            
            println("✅ Configuration edge-to-edge réussie")
            
            // Initialiser la télémétrie
            val telemetry = TelemetrySDK.initialize(
                context = this,
                baseUrl = "https://coursier.conciergerie-privee-suzosky.com",
                apiKey = "suzosky_telemetry_2025"
            )
            
            // Vérifier les mises à jour
            lifecycleScope.launch {
                try {
                    val updateInfo = telemetry.checkForUpdates()
                    if (updateInfo?.isMandatory == true) {
                        // Gérer mise à jour obligatoire
                        showUpdateDialog(updateInfo)
                    }
                } catch (e: Exception) {
                    println("Erreur vérification mises à jour: ${e.message}")
                }
            }
            
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
            
            // Demander toutes les permissions manquantes
            if (permissionsToRequest.isNotEmpty()) {
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.M) {
                    requestPermissions(permissionsToRequest.toTypedArray(), 1001)
                }
            }
            
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
                    SuzoskyCoursierApp()
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
                        } else {
                            Log.d("MainActivity", "⏸️ Token sauvé, en attente de connexion coursier")
                            println("⏸️ Token sauvé, en attente de connexion coursier")
                        }
                    } else {
                        Log.w("MainActivity", "❌ FCM token fetch failed: ${'$'}{task.exception?.message}")
                        println("❌ FCM token fetch failed: ${'$'}{task.exception?.message}")
                    }
                }
            } catch (e: Exception) {
                Log.e("MainActivity", "registerDeviceToken startup error", e)
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
    // Callback de permission notifications (Android 13+)
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
            // TODO: Show Compose snackbar for location permission result
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
fun SuzoskyCoursierApp() {
    val context = LocalContext.current
    // Persist a simple login flag in SharedPreferences to stabilize navigation after login
    val prefs = remember { context.getSharedPreferences("suzosky_prefs", android.content.Context.MODE_PRIVATE) }
    
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

        // Charger les VRAIES données au login
        LaunchedEffect(isLoggedIn, coursierId) {
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
            loading = true

            ApiService.getCoursierData(coursierId) { data, err ->
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

                    // Convertir les commandes
                    @Suppress("UNCHECKED_CAST")
                    val commandesData = data["commandes"] as? List<Map<String, Any>> ?: emptyList()
                    commandesReelles = try {
                        commandesData.map { cmdMap ->
                            Commande(
                                id = toStringSafe(cmdMap["id"]),
                                clientNom = toStringSafe(cmdMap["clientNom"]),
                                clientTelephone = toStringSafe(cmdMap["clientTelephone"]),
                                adresseEnlevement = toStringSafe(cmdMap["adresseEnlevement"]),
                                adresseLivraison = toStringSafe(cmdMap["adresseLivraison"]),
                                distance = toDoubleSafe(cmdMap["distance"]).let { if (it.isFinite()) it else 0.0 },
                                tempsEstime = toIntFromDistanceKm(cmdMap["distance"]),
                                prixTotal = toDoubleSafe(cmdMap["prixLivraison"]).let { if (it.isFinite()) it else 0.0 },
                                prixLivraison = toDoubleSafe(cmdMap["prixLivraison"]).let { if (it.isFinite()) it else 0.0 },
                                // Ne pas forcer un statut test. Si vide, classer comme "inconnue" pour éviter faux positifs.
                                statut = toStringSafe(cmdMap["statut"]).ifBlank { "inconnue" },
                                // Éviter les labels de démo; utiliser les champs si présents, sinon vide
                                dateCommande = toStringSafe(cmdMap["dateCommande"]),
                                heureCommande = toStringSafe(cmdMap["heureCommande"]),
                                description = toStringSafe(cmdMap["description"]),
                                typeCommande = "Standard"
                            )
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
                            onCommandeAccept = {
                                try { OrderRingService.stop(context) } catch (_: Exception) {}
                                // TODO: Accept logic (API update)
                            },
                            onCommandeReject = {
                                try { OrderRingService.stop(context) } catch (_: Exception) {}
                                // TODO: Reject logic (API update)
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
}

