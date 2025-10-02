package com.suzosky.coursier.ui.screens

import android.content.Context
import android.util.Log
import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.Saver
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.network.ApiService
import com.suzosky.coursier.utils.DeliveryStatusMapper
import com.suzosky.coursier.ui.components.BottomNavigationBar
import com.suzosky.coursier.ui.components.NavigationTab
import com.suzosky.coursier.ui.components.PaymentWebViewDialog
import com.suzosky.coursier.ui.components.DeliveryTimeline
import com.suzosky.coursier.ui.components.CashConfirmationDialog
import com.suzosky.coursier.ui.components.NoActiveOrderScreen
import com.suzosky.coursier.ui.components.TimelineBanner
import com.suzosky.coursier.ui.components.BannerSeverity
import com.suzosky.coursier.ui.screens.UnifiedCoursesScreen
import com.suzosky.coursier.ui.screens.ModernChatScreen
import com.suzosky.coursier.ui.screens.ModernWalletScreen
import com.suzosky.coursier.ui.screens.ModernProfileScreen
import com.suzosky.coursier.ui.screens.CoursierStats
import com.suzosky.coursier.ui.screens.DeliveryStep
import com.suzosky.coursier.data.models.ChatMessage
import com.suzosky.coursier.services.NotificationSoundService
import com.suzosky.coursier.viewmodel.MapViewModel
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.android.gms.maps.model.LatLng
import java.util.*

/**
 * Écran principal du coursier redesigné avec navigation en bas
 * Navigation: Courses | Portefeuille | Chat | Profil
 */
@Composable
fun CoursierScreenNew(
    modifier: Modifier = Modifier,
    coursierId: Int = 0,
    coursierNom: String = "",
    coursierStatut: String = "",
    totalCommandes: Int = 0,
    noteGlobale: Double = 0.0,
    coursierTelephone: String = "",
    coursierEmail: String = "",
    dateInscription: String = "",
    commandes: List<Commande> = emptyList(),
    balance: Int = 0,
    gainsDuJour: Int = 0,
    onStatutChange: (String) -> Unit = {},
    onCommandeAccept: (String) -> Unit = {},
    onCommandeReject: (String) -> Unit = {},
    onCommandeAttente: (String) -> Unit = {},
    onNavigateToProfile: () -> Unit = {},
    onNavigateToHistorique: () -> Unit = {},
    onNavigateToGains: () -> Unit = {},
    onLogout: () -> Unit = {},
    onRecharge: (Int) -> Unit = {}
) {
    val context = LocalContext.current
    var currentTab by remember { mutableStateOf(NavigationTab.COURSES) }
    // Utiliser les vraies données au lieu des valeurs mockées
    var realBalance by remember { mutableStateOf(balance) }
    
    // ⚠️ Liste locale mutable des commandes (pour pouvoir retirer les terminées)
    var localCommandes by remember { mutableStateOf(commandes) }
    
    // Compter uniquement les commandes en attente d'acceptation (nouvelles/attente)
    var pendingOrdersCount by remember { mutableStateOf(localCommandes.count { it.statut == "nouvelle" || it.statut == "attente" }) }
    
    // Position du coursier (fournie par LocationForegroundService via MainActivity)
    val currentLocationFromService by com.suzosky.coursier.services.LocationForegroundService.currentLocation.collectAsState()
    
    // Convertir Location en LatLng pour Google Maps
    val courierLocation = currentLocationFromService?.let { loc ->
        com.google.android.gms.maps.model.LatLng(loc.latitude, loc.longitude)
    }
    
    // Log de debug pour vérifier la localisation
    LaunchedEffect(courierLocation) {
        android.util.Log.d("CoursierScreenNew", "📍 Courier location from LocationForegroundService: $courierLocation")
    }
    
    // Service de notification sonore
    val notificationService = remember { NotificationSoundService(context) }
    
    // État pour tracker les nouvelles commandes et déclencher le son
    var previousCommandesCount by remember { mutableStateOf(localCommandes.size) }
    var hasNewOrder by remember { mutableStateOf(false) }
    
    // États pour les courses - DÉCLARATION AVANT LaunchedEffect
    // Prioriser les commandes nouvelles/attente (pour afficher la modal), NE PAS prendre les anciennes courses terminées
    var currentOrder by remember { mutableStateOf<Commande?>(
        localCommandes.firstOrNull { 
            val statut = it.statut.lowercase()
            // Chercher toute commande ACTIVE (pas termin�e)
            statut == "nouvelle" || statut == "attente" || statut == "acceptee" || statut == "en_cours" || statut == "recuperee"
        }
    ) }
    // Initialiser deliveryStep selon le statut de la commande actuelle
    // Utiliser rememberSaveable avec Saver personnalisé pour survivre aux rotations d'écran
    var deliveryStep by rememberSaveable(
        stateSaver = Saver(
            save = { it.ordinal }, // Sauvegarder l'ordinal (Int)
            restore = { DeliveryStep.values()[it] } // Restaurer depuis l'ordinal
        )
    ) { mutableStateOf(
        when (currentOrder?.statut) {
            "acceptee" -> DeliveryStep.ACCEPTED
            "en_cours", "recuperee" -> DeliveryStep.PICKED_UP
            else -> DeliveryStep.PENDING
        }
    ) }
    
    // Synchroniser localCommandes avec commandes (quand de nouvelles arrivent)
    LaunchedEffect(commandes) {
        // Ajouter uniquement les nouvelles commandes (ne pas écraser les suppressions locales)
        val newCommands = commandes.filter { cmd -> 
            localCommandes.none { it.id == cmd.id }
        }
        if (newCommands.isNotEmpty()) {
            localCommandes = localCommandes + newCommands
            android.util.Log.d("CoursierScreenNew", "📥 ${newCommands.size} nouvelles commandes ajoutées")
        }
        
        // ⚠️ FIX CRITIQUE: Synchroniser currentOrder avec la version mise à jour dans localCommandes
        // Si currentOrder existe, la mettre à jour avec la version actuelle de la liste
        currentOrder?.let { current ->
            val updatedOrder = localCommandes.find { it.id == current.id }
            if (updatedOrder != null && updatedOrder !== current) {
                // La commande existe toujours mais a été mise à jour (changement de statut)
                currentOrder = updatedOrder
                android.util.Log.d("CoursierScreenNew", "🔄 currentOrder synchronized: ${updatedOrder.id} (statut: ${updatedOrder.statut})")
            }
        }
        
        pendingOrdersCount = localCommandes.count { it.statut == "nouvelle" || it.statut == "attente" }
    }
    
    // Détection de nouvelles commandes et déclenchement du son
    LaunchedEffect(localCommandes.size) {
        // Si le nombre de commandes augmente, il y a une nouvelle commande
        if (localCommandes.size > previousCommandesCount && previousCommandesCount > 0) {
            println("🔊 Nouvelle commande détectée! Démarrage du son")
            hasNewOrder = true
            notificationService.startNotificationSound()
        }
        previousCommandesCount = localCommandes.size
    }
    
    // Nettoyer le service de notification quand le composant est détruit
    DisposableEffect(Unit) {
        onDispose {
            notificationService.release()
        }
    }
    
    // Mettre à jour les valeurs réelles quand elles changent
    LaunchedEffect(balance) {
        realBalance = balance
    }
    
    // Synchroniser deliveryStep avec le statut de la commande actuelle
    // ⚠️ FIX: Ne synchroniser que si le statut serveur est plus avancé que l'état local
    LaunchedEffect(currentOrder?.statut) {
        currentOrder?.let { order ->
            val newStep = when (order.statut) {
                "acceptee" -> DeliveryStep.ACCEPTED
                "en_cours" -> DeliveryStep.PICKED_UP
                "recuperee" -> DeliveryStep.PICKED_UP
                "nouvelle", "attente" -> DeliveryStep.PENDING
                else -> deliveryStep
            }
            
            // Ne mettre à jour QUE si on progresse (pas de retour en arrière)
            val currentStepOrder = deliveryStep.ordinal
            val newStepOrder = newStep.ordinal
            
            if (newStepOrder >= currentStepOrder) {
                deliveryStep = newStep
                android.util.Log.d("CoursierScreenNew", "🔄 Synced deliveryStep to $deliveryStep for order ${order.id} (statut: ${order.statut})")
            } else {
                android.util.Log.d("CoursierScreenNew", "⚠️ Prevented backward step sync: server=${order.statut} (step=$newStep) < local=$deliveryStep")
            }
        }
    }
    
    // États pour le chat
    var chatMessages by remember { 
        mutableStateOf(
            listOf(
                ChatMessage(
                    id = "1",
                    message = "Bonjour ! Comment puis-je vous aider aujourd'hui ?",
                    isFromCoursier = false,
                    timestamp = Date().time,
                    senderName = "Support Suzosky"
                )
            )
        ) 
    }
    
    // États pour le paiement
    var showPayment by remember { mutableStateOf(false) }
    var paymentUrl by remember { mutableStateOf<String?>(null) }
    
    // États pour la timeline et cash dialog
    // Utiliser rememberSaveable pour survivre aux rotations
    var showCashDialog by rememberSaveable { mutableStateOf(false) }
    var timelineBanner by remember { mutableStateOf<TimelineBanner?>(null) }
    var bannerVersion by remember { mutableStateOf(0) }
    // Auto-dismiss des bannières après 8s
    LaunchedEffect(timelineBanner, bannerVersion) {
        if (timelineBanner != null) {
            kotlinx.coroutines.delay(8000)
            // Ne ferme que si pas remplacée depuis
            timelineBanner = null
        }
    }
    
    // Fonction pour réinitialiser vers prochaine commande
    fun resetToNextOrder() {
        // Désactiver l'ordre actif côté serveur (best-effort)
        currentOrder?.let { order ->
            if (coursierId > 0) {
                ApiService.setActiveOrder(coursierId, order.id, active = false) { _ -> }
            }
            // ⚠️ RETIRER LA COMMANDE TERMINÉE DE LA LISTE LOCALE
            localCommandes = localCommandes.filter { it.id != order.id }
            android.util.Log.d("CoursierScreenNew", "✅ Commande ${order.id} retirée de la liste locale")
        }
        // Passer à la prochaine commande en attente
        deliveryStep = DeliveryStep.PENDING
        currentOrder = localCommandes.firstOrNull { 
            val statut = it.statut.lowercase()
            // Chercher toute commande ACTIVE (pas termin�e)
            statut == "nouvelle" || statut == "attente" || statut == "acceptee" || statut == "en_cours" || statut == "recuperee"
        }
        pendingOrdersCount = localCommandes.count { it.statut == "nouvelle" || it.statut == "attente" }
        android.util.Log.d("CoursierScreenNew", "📋 Prochaine commande: ${currentOrder?.id ?: "AUCUNE"}, pending: $pendingOrdersCount")
    }
    // paymentUrl déjà déclaré plus haut

    Scaffold(
        bottomBar = {
            BottomNavigationBar(
                currentTab = currentTab,
                onTabSelected = { currentTab = it },
                coursierPhoto = null // TODO: Ajouter la photo du coursier
            )
        }
    ) { paddingValues ->
        Box(
            modifier = modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            when (currentTab) {
                NavigationTab.COURSES -> {
                    UnifiedCoursesScreen(
                        currentOrder = currentOrder,
                        deliveryStep = deliveryStep,
                        pendingOrdersCount = pendingOrdersCount,
                        courierLocation = courierLocation,
                        onStartDelivery = {
                            // Passage de acceptee → en_cours (démarrage navigation)
                            currentOrder?.let { order ->
                                deliveryStep = DeliveryStep.EN_ROUTE_PICKUP
                                val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.EN_ROUTE_PICKUP)
                                ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                    if (success) {
                                        timelineBanner = null
                                        Toast.makeText(context, "Navigation démarrée vers le point d'enlèvement", Toast.LENGTH_SHORT).show()
                                    } else {
                                        timelineBanner = TimelineBanner(
                                            message = "Statut 'En route' non synchronisé.",
                                            severity = BannerSeverity.ERROR,
                                            actionLabel = "Réessayer",
                                            onAction = {
                                                bannerVersion++
                                                ApiService.updateOrderStatus(order.id, serverStatus) { ok2 ->
                                                    if (ok2) timelineBanner = null
                                                }
                                            }
                                        )
                                        Toast.makeText(context, "Erreur synchronisation serveur", Toast.LENGTH_SHORT).show()
                                    }
                                }
                            }
                        },
                        onPickupPackage = {
                            // Passage de en_cours → recuperee (colis récupéré)
                            currentOrder?.let { order ->
                                deliveryStep = DeliveryStep.PICKED_UP
                                val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.PICKED_UP)
                                ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                    if (success) {
                                        timelineBanner = null
                                        Toast.makeText(context, "Colis récupéré ! Direction point de livraison", Toast.LENGTH_SHORT).show()
                                    } else {
                                        timelineBanner = TimelineBanner(
                                            message = "Statut 'Récupéré' non synchronisé.",
                                            severity = BannerSeverity.ERROR,
                                            actionLabel = "Réessayer",
                                            onAction = {
                                                bannerVersion++
                                                ApiService.updateOrderStatus(order.id, serverStatus) { ok2 ->
                                                    if (ok2) timelineBanner = null
                                                }
                                            }
                                        )
                                        Toast.makeText(context, "Erreur synchronisation serveur", Toast.LENGTH_SHORT).show()
                                    }
                                }
                            }
                        },
                        onMarkDelivered = {
                            // Marquer comme livrée (recuperee → livree)
                            currentOrder?.let { order ->
                                if (order.methodePaiement.equals("especes", ignoreCase = true)) {
                                    // Si paiement espèces, on passe par DELIVERED puis on affiche le dialogue cash
                                    deliveryStep = DeliveryStep.DELIVERED
                                    showCashDialog = true
                                } else {
                                    // Sinon, on marque directement comme livrée
                                    deliveryStep = DeliveryStep.CASH_CONFIRMED
                                    val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.DELIVERED)
                                    ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                        if (success) {
                                            timelineBanner = null
                                            Toast.makeText(context, "✅ Livraison terminée avec succès !", Toast.LENGTH_SHORT).show()
                                            resetToNextOrder()
                                        } else {
                                            timelineBanner = TimelineBanner(
                                                message = "Statut 'Livrée' non synchronisé.",
                                                severity = BannerSeverity.ERROR,
                                                actionLabel = "Réessayer",
                                                onAction = {
                                                    bannerVersion++
                                                    ApiService.updateOrderStatus(order.id, serverStatus) { ok2 ->
                                                        if (ok2) {
                                                            timelineBanner = null
                                                            resetToNextOrder()
                                                        }
                                                    }
                                                }
                                            )
                                            Toast.makeText(context, "Erreur synchronisation serveur", Toast.LENGTH_SHORT).show()
                                        }
                                    }
                                }
                            }
                        },
                        onConfirmCash = {
                            // Confirmation du paiement espèces après dialogue
                            currentOrder?.let { order ->
                                deliveryStep = DeliveryStep.CASH_CONFIRMED
                                val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.DELIVERED)
                                ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                    if (success) {
                                        timelineBanner = null
                                        Toast.makeText(context, "✅ Paiement espèces confirmé !", Toast.LENGTH_SHORT).show()
                                        resetToNextOrder()
                                    } else {
                                        timelineBanner = TimelineBanner(
                                            message = "Statut 'Livrée' non synchronisé.",
                                            severity = BannerSeverity.ERROR,
                                            actionLabel = "Réessayer",
                                            onAction = {
                                                bannerVersion++
                                                ApiService.updateOrderStatus(order.id, serverStatus) { ok2 ->
                                                    if (ok2) {
                                                        timelineBanner = null
                                                        resetToNextOrder()
                                                    }
                                                }
                                            }
                                        )
                                        Toast.makeText(context, "Erreur synchronisation serveur", Toast.LENGTH_SHORT).show()
                                    }
                                }
                            }
                        },
                        onAcceptOrder = {
                            currentOrder?.let { order ->
                                if (hasNewOrder) {
                                    notificationService.stopNotificationSound()
                                    notificationService.playActionSound()
                                    hasNewOrder = false
                                }
                                // Accepter la commande via API
                                ApiService.respondToOrder(order.id, coursierId.toString(), "accept") { ok, message ->
                                    if (!ok) {
                                        timelineBanner = TimelineBanner(
                                            message = message ?: "Erreur lors de l'acceptation",
                                            severity = BannerSeverity.ERROR,
                                            actionLabel = "Réessayer",
                                            onAction = {
                                                bannerVersion++
                                                // Retry accept
                                                ApiService.respondToOrder(order.id, coursierId.toString(), "accept") { ok2, message2 ->
                                                    if (!ok2) {
                                                        timelineBanner = TimelineBanner(message2 ?: "Erreur d'acceptation", BannerSeverity.ERROR, "Réessayer") {
                                                            bannerVersion++; /* re-click */
                                                        }
                                                    } else {
                                                        timelineBanner = null
                                                        deliveryStep = DeliveryStep.ACCEPTED
                                                        pendingOrdersCount = maxOf(0, pendingOrdersCount - 1)
                                                        onCommandeAccept(order.id)
                                                    }
                                                }
                                            }
                                        )
                                        Toast.makeText(context, message ?: "Erreur d'acceptation", Toast.LENGTH_LONG).show()
                                        return@respondToOrder
                                    }
                                    deliveryStep = DeliveryStep.ACCEPTED
                                    pendingOrdersCount = maxOf(0, pendingOrdersCount - 1)
                                    onCommandeAccept(order.id)
                                    ApiService.setActiveOrder(coursierId, order.id, active = true) { activeOk ->
                                        if (!activeOk) {
                                            timelineBanner = TimelineBanner(
                                                message = "Impossible d'activer le suivi en direct pour le client maintenant.",
                                                severity = BannerSeverity.WARNING,
                                                actionLabel = "Réessayer",
                                                onAction = {
                                                    bannerVersion++
                                                    ApiService.setActiveOrder(coursierId, order.id, active = true) { ok2 ->
                                                        if (ok2) timelineBanner = null
                                                    }
                                                }
                                            )
                                            Toast.makeText(context, "Impossible d'activer le suivi live maintenant", Toast.LENGTH_SHORT).show()
                                        }
                                    }
                                    if (DeliveryStatusMapper.requiresApiCall(DeliveryStep.ACCEPTED)) {
                                        val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.ACCEPTED)
                                        ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                            if (success) {
                                                timelineBanner = null
                                                Toast.makeText(context, DeliveryStatusMapper.getSuccessMessage(DeliveryStep.ACCEPTED, order.methodePaiement), Toast.LENGTH_SHORT).show()
                                            } else {
                                                timelineBanner = TimelineBanner(
                                                    message = "Statut 'Acceptée' non synchronisé avec le serveur.",
                                                    severity = BannerSeverity.ERROR,
                                                    actionLabel = "Réessayer",
                                                    onAction = {
                                                        bannerVersion++
                                                        ApiService.updateOrderStatus(order.id, serverStatus) { ok2 ->
                                                            if (ok2) timelineBanner = null
                                                        }
                                                    }
                                                )
                                                Toast.makeText(context, "Erreur synchronisation serveur", Toast.LENGTH_SHORT).show()
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        onRejectOrder = {
                            Toast.makeText(context, "Commande refusée", Toast.LENGTH_SHORT).show()
                        },
                        onPickupValidation = {
                            currentOrder?.let { order ->
                                deliveryStep = DeliveryStep.PICKED_UP
                                if (DeliveryStatusMapper.requiresApiCall(DeliveryStep.PICKED_UP)) {
                                    val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.PICKED_UP)
                                    ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                        if (success) {
                                            timelineBanner = null
                                            Toast.makeText(context, "Colis recupere ! Direction livraison", Toast.LENGTH_SHORT).show()
                                            deliveryStep = DeliveryStep.EN_ROUTE_DELIVERY
                                            
                                            // Lancer la navigation Google Maps vers l'adresse de livraison
                                            try {
                                                val coords = order.coordonneesLivraison
                                                if (coords != null) {
                                                    val destination = "${coords.latitude},${coords.longitude}"
                                                    val intent = android.content.Intent(android.content.Intent.ACTION_VIEW, android.net.Uri.parse("google.navigation:q=$destination&mode=d"))
                                                    intent.setPackage("com.google.android.apps.maps")
                                                    context.startActivity(intent)
                                                } else {
                                                    Toast.makeText(context, "Coordonnees de livraison manquantes", Toast.LENGTH_SHORT).show()
                                                }
                                            } catch (e: Exception) {
                                                Toast.makeText(context, "Google Maps non disponible", Toast.LENGTH_SHORT).show()
                                            }
                                        } else {
                                            timelineBanner = TimelineBanner(
                                                message = "Impossible d'envoyer 'Colis récupéré' au serveur.",
                                                severity = BannerSeverity.ERROR,
                                                actionLabel = "Réessayer",
                                                onAction = {
                                                    bannerVersion++
                                                    ApiService.updateOrderStatus(order.id, serverStatus) { ok2 ->
                                                        if (ok2) {
                                                            timelineBanner = null
                                                            deliveryStep = DeliveryStep.EN_ROUTE_DELIVERY
                                                        }
                                                    }
                                                }
                                            )
                                            Toast.makeText(context, "Erreur synchronisation serveur", Toast.LENGTH_SHORT).show()
                                        }
                                    }
                                }
                            }
                        },
                        onDeliveryValidation = {
                            currentOrder?.let { order ->
                                if (order.methodePaiement.equals("especes", ignoreCase = true)) {
                                    deliveryStep = DeliveryStep.DELIVERED
                                    showCashDialog = true
                                } else {
                                    deliveryStep = DeliveryStep.CASH_CONFIRMED
                                    val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.DELIVERED)
                                    ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                        if (success) {
                                            timelineBanner = null
                                            Toast.makeText(context, DeliveryStatusMapper.getSuccessMessage(DeliveryStep.DELIVERED, order.methodePaiement), Toast.LENGTH_SHORT).show()
                                            resetToNextOrder()
                                        } else {
                                            timelineBanner = TimelineBanner(
                                                message = "Statut 'Livrée' non synchronisé. Vérifiez la connexion et réessayez.",
                                                severity = BannerSeverity.ERROR,
                                                actionLabel = "Réessayer",
                                                onAction = {
                                                    bannerVersion++
                                                    ApiService.updateOrderStatus(order.id, serverStatus) { ok2 ->
                                                        if (ok2) {
                                                            timelineBanner = null
                                                            resetToNextOrder()
                                                        }
                                                    }
                                                }
                                            )
                                            Toast.makeText(context, "Erreur synchronisation serveur", Toast.LENGTH_SHORT).show()
                                        }
                                    }
                                }
                            }
                        }
                    )
                }
                
                NavigationTab.WALLET -> {
                    ModernWalletScreen(
                        coursierId = coursierId,
                        balance = balance,
                        gainsDuJour = 0,
                        gainsHebdo = 0,
                        gainsMensuel = 0,
                        onRecharge = onRecharge
                    )
                }
                
                NavigationTab.CHAT -> {
                    ModernChatScreen(
                        coursierNom = coursierNom,
                        messages = chatMessages,
                        onSendMessage = { message ->
                            // Ajouter le message du coursier
                            val newMessage = ChatMessage(
                                id = UUID.randomUUID().toString(),
                                message = message,
                                isFromCoursier = true,
                                timestamp = Date().time,
                                senderName = coursierNom
                            )
                            chatMessages = chatMessages + newMessage
                            
                            // TODO: Envoyer le message au serveur
                            // Simuler une réponse automatique pour le demo
                            if (message.contains("help", ignoreCase = true) || 
                                message.contains("aide", ignoreCase = true)) {
                                val autoReply = ChatMessage(
                                    id = UUID.randomUUID().toString(),
                                    message = "Je suis là pour vous aider ! Que puis-je faire pour vous ?",
                                    isFromCoursier = false,
                                    timestamp = Date().time,
                                    senderName = "Support Suzosky"
                                )
                                chatMessages = chatMessages + autoReply
                            }
                        }
                    )
                }
                
                NavigationTab.PROFILE -> {
                    ModernProfileScreen(
                        coursierNom = coursierNom,
                        coursierTelephone = coursierTelephone.ifBlank { "+225" },
                        coursierMatricule = "",
                        stats = CoursierStats(
                            totalCourses = totalCommandes,
                            rating = noteGlobale.toFloat(),
                            memberSince = if (dateInscription.isNotBlank()) dateInscription else "2025"
                        ),
                        onLogout = onLogout
                    )
                }
            } // end when

            // Dialog de paiement
            if (showPayment && paymentUrl != null) {
                PaymentWebViewDialog(
                    url = paymentUrl!!,
                    onDismiss = {
                        showPayment = false
                        paymentUrl = null
                    },
                    onCompleted = { success, transactionId ->
                        val message = if (success) {
                            // Ne pas modifier localement le solde ici. Laisser l'app recharger depuis le serveur.
                            "Recharge réussie! Transaction: $transactionId"
                        } else {
                            "Recharge échouée. Transaction: $transactionId"
                        }
                        Toast.makeText(context, message, Toast.LENGTH_LONG).show()
                        showPayment = false
                        paymentUrl = null
                    }
                )
            }
            
            // Dialog confirmation cash
            CashConfirmationDialog(
                isVisible = showCashDialog,
                onConfirm = {
                    showCashDialog = false
                    deliveryStep = DeliveryStep.CASH_CONFIRMED
                    
                    // Synchroniser avec serveur - commande terminée avec cash confirmé
                    val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.CASH_CONFIRMED)
                     ApiService.updateOrderStatusWithCash(
                        commandeId = currentOrder?.id ?: "",
                        statut = serverStatus,
                        cashCollected = true,
                        cashAmount = currentOrder?.prixLivraison // Utiliser le prix de la commande
                    ) { success ->
                        val message = if (success) {
                            DeliveryStatusMapper.getSuccessMessage(DeliveryStep.CASH_CONFIRMED, currentOrder?.methodePaiement ?: "")
                        } else {
                            "Erreur synchronisation serveur"
                        }
                        Toast.makeText(context, message, Toast.LENGTH_SHORT).show()
                        
                        if (success) {
                            timelineBanner = null
                            resetToNextOrder()
                        } else {
                            val cmdId = currentOrder?.id ?: ""
                            val retryStatus = serverStatus
                            timelineBanner = TimelineBanner(
                                message = "Confirmation de cash non synchronisée.",
                                severity = BannerSeverity.ERROR,
                                actionLabel = "Réessayer",
                                onAction = {
                                    bannerVersion++
                                    ApiService.updateOrderStatusWithCash(
                                        commandeId = cmdId,
                                        statut = retryStatus,
                                        cashCollected = true,
                                        cashAmount = currentOrder?.prixLivraison
                                    ) { ok2 ->
                                        if (ok2) {
                                            timelineBanner = null
                                            resetToNextOrder()
                                        }
                                    }
                                }
                            )
                        }
                    }
                },
                onDismiss = {
                    showCashDialog = false
                    // Rester sur l'étape DELIVERED sans confirmer le cash
                    Toast.makeText(context, "Confirmation cash en attente", Toast.LENGTH_SHORT).show()
                }
            )
        } // end Box
    } // end Scaffold content
}