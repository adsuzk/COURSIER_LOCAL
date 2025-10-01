package com.suzosky.coursier.ui.screens

import android.content.Context
import android.util.Log
import android.widget.Toast
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
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
import com.suzosky.coursier.ui.screens.CoursesScreen
import com.suzosky.coursier.ui.screens.UnifiedCoursesScreen
import com.suzosky.coursier.ui.screens.ChatScreen
import com.suzosky.coursier.ui.screens.ModernChatScreen
import com.suzosky.coursier.ui.screens.WalletScreen
import com.suzosky.coursier.ui.screens.ModernWalletScreen
import com.suzosky.coursier.ui.screens.ProfileScreen
import com.suzosky.coursier.ui.screens.ModernProfileScreen
import com.suzosky.coursier.ui.screens.DeliveryStep
import com.suzosky.coursier.ui.screens.ChatMessage
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
    coursierMatricule: String = "",
    commandes: List<Commande> = emptyList(),
    balance: Int = 0,
    gainsDuJour: Int = 0,
    onStatutChange: (String) -> Unit = {},
    onCommandeAccept: (String) -> Unit = {},
    onCommandeReject: (String) -> Unit = {},
    onCommandeAttente: (String) -> Unit = {},
    onStartDelivery: (String) -> Unit = {},
    onPickupPackage: (String) -> Unit = {},
    onMarkDelivered: (String) -> Unit = {},
    onConfirmCash: (String) -> Unit = {},
    onNavigateToProfile: () -> Unit = {},
    onNavigateToHistorique: () -> Unit = {},
    onNavigateToGains: () -> Unit = {},
    onLogout: () -> Unit = {},
    onRecharge: (Int) -> Unit = {},
    // Nouveaux paramètres pour le rafraîchissement automatique
    shouldRefreshCommandes: Boolean = false,
    onCommandesRefreshed: () -> Unit = {}
) {
    val context = LocalContext.current
    var currentTab by remember { mutableStateOf(NavigationTab.COURSES) }
    // Utiliser les vraies données au lieu des valeurs mockées
    var realBalance by remember { mutableStateOf(balance) }
    // Compter uniquement les commandes en attente d'acceptation (nouvelles/attente)
    var pendingOrdersCount by remember { mutableStateOf(commandes.count { it.statut == "nouvelle" || it.statut == "attente" }) }
    
    // Service de notification sonore
    val notificationService = remember { NotificationSoundService(context) }
    
    // ViewModel pour la localisation en temps réel
    val mapViewModel: MapViewModel = hiltViewModel()
    val mapUi by mapViewModel.uiState.collectAsState()
    
    // Démarrer le suivi de localisation
    LaunchedEffect(Unit) {
        mapViewModel.startLocationTracking()
    }
    
    // Extraire la position du coursier
    val courierLocation = mapUi.currentLocation
    
    // État pour tracker les nouvelles commandes et déclencher le son
    var previousCommandesCount by remember { mutableStateOf(commandes.size) }
    var hasNewOrder by remember { mutableStateOf(false) }
    
    // Détection de nouvelles commandes et déclenchement du son
    LaunchedEffect(commandes.size) {
        // Si le nombre de commandes augmente, il y a une nouvelle commande
        if (commandes.size > previousCommandesCount && previousCommandesCount > 0) {
            println("🔊 Nouvelle commande détectée! Démarrage du son")
            hasNewOrder = true
            notificationService.startNotificationSound()
        }
        previousCommandesCount = commandes.size
    pendingOrdersCount = commandes.count { it.statut == "nouvelle" || it.statut == "attente" }
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
    LaunchedEffect(commandes) {
        pendingOrdersCount = commandes.count { it.statut == "nouvelle" || it.statut == "attente" }
    }
    
    // Rafraîchissement automatique des commandes quand une nouvelle arrive
    LaunchedEffect(shouldRefreshCommandes) {
        if (shouldRefreshCommandes) {
            println("🔄 Rafraîchissement automatique des commandes déclenché")
            
            // Démarrer la sonnerie pour nouvelle commande
            hasNewOrder = true
            notificationService.startNotificationSound()
            
            // Forcer le passage à l'onglet Courses
            currentTab = NavigationTab.COURSES
            
            // Signaler que le rafraîchissement a été traité
            onCommandesRefreshed()
        }
    }
    
    // États pour les courses
    // Sélectionner d'abord une commande réellement active (en_cours/acceptee), sinon prendre une nouvelle/attente
    var currentOrder by remember { mutableStateOf<Commande?>(
        commandes.firstOrNull { it.statut == "en_cours" || it.statut == "acceptee" }
            ?: commandes.firstOrNull { it.statut == "nouvelle" || it.statut == "attente" }
    ) }
    
    // Initialiser deliveryStep en fonction du statut de la commande actuelle
    var deliveryStep by remember { mutableStateOf(
        when (currentOrder?.statut) {
            "nouvelle", "attente" -> DeliveryStep.PENDING
            "acceptee" -> DeliveryStep.ACCEPTED
            "en_cours" -> DeliveryStep.EN_ROUTE_PICKUP  // En route vers récupération
            "recuperee" -> DeliveryStep.PICKED_UP  // Colis récupéré
            "livree" -> {
                // Si paiement espèces, attendre confirmation cash
                if (currentOrder?.methodePaiement?.lowercase() == "especes") {
                    DeliveryStep.DELIVERED
                } else {
                    DeliveryStep.CASH_CONFIRMED
                }
            }
            else -> DeliveryStep.PENDING
        }
    ) }
    
    // États pour le chat
    var chatMessages by remember { 
        mutableStateOf(
            listOf(
                ChatMessage(
                    id = "1",
                    message = "Bonjour ! Comment puis-je vous aider aujourd'hui ?",
                    isFromCoursier = false,
                    timestamp = Date(),
                    senderName = "Support Suzosky"
                )
            )
        ) 
    }
    
    // États pour le paiement
    var showPayment by remember { mutableStateOf(false) }
    var paymentUrl by remember { mutableStateOf<String?>(null) }
    
    // États pour la timeline et cash dialog
    var showCashDialog by remember { mutableStateOf(false) }
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
        }
        // Passer à la prochaine commande en attente
        currentOrder = commandes.firstOrNull { it.statut == "nouvelle" || it.statut == "attente" || it.statut == "acceptee" }
        // Mapper le statut de la commande au deliveryStep approprié
        deliveryStep = when (currentOrder?.statut) {
            "nouvelle", "attente" -> DeliveryStep.PENDING
            "acceptee" -> DeliveryStep.ACCEPTED
            "en_cours" -> DeliveryStep.EN_ROUTE_PICKUP  // En route vers récupération
            "recuperee" -> DeliveryStep.PICKED_UP  // Colis récupéré, en route vers livraison
            "livree" -> {
                // Si paiement espèces ET cash pas encore confirmé, montrer DELIVERED
                // Sinon montrer CASH_CONFIRMED
                if (currentOrder?.methodePaiement?.lowercase() == "especes") {
                    DeliveryStep.DELIVERED  // Attendre confirmation cash
                } else {
                    DeliveryStep.CASH_CONFIRMED  // Pas d'espèces, terminé
                }
            }
            else -> DeliveryStep.PENDING
        }
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
                    // Utiliser le nouvel écran unifié sans modal
                    UnifiedCoursesScreen(
                        currentOrder = currentOrder,
                        deliveryStep = deliveryStep,
                        pendingOrdersCount = pendingOrdersCount,
                        courierLocation = courierLocation,
                        onAcceptOrder = {
                            currentOrder?.let { order ->
                                if (hasNewOrder) {
                                    notificationService.stopNotificationSound()
                                    notificationService.playActionSound()
                                    hasNewOrder = false
                                }
                                
                                Log.d("CoursierScreenNew", "Acceptation de la commande ${order.id} par coursier $coursierId")
                                Toast.makeText(context, "Acceptation en cours...", Toast.LENGTH_SHORT).show()
                                
                                // Appeler la nouvelle API order_response.php
                                ApiService.respondToOrder(order.id, coursierId.toString(), "accept") { success, message ->
                                    if (success) {
                                        Log.d("CoursierScreenNew", "Commande acceptée: $message")
                                        Toast.makeText(context, "Commande acceptée !", Toast.LENGTH_SHORT).show()
                                        
                                        deliveryStep = DeliveryStep.ACCEPTED
                                        pendingOrdersCount = maxOf(0, pendingOrdersCount - 1)
                                        onCommandeAccept(order.id)
                                        
                                        // Activer le suivi en temps réel pour le client
                                        ApiService.setActiveOrder(coursierId, order.id, active = true) { activeOk ->
                                            if (!activeOk) {
                                                Log.w("CoursierScreenNew", "Impossible d'activer le suivi en direct")
                                            }
                                        }
                                    } else {
                                        Log.e("CoursierScreenNew", "Échec acceptation: $message")
                                        timelineBanner = TimelineBanner(
                                            message = message ?: "Erreur lors de l'acceptation",
                                            severity = BannerSeverity.ERROR,
                                            actionLabel = "Réessayer",
                                            onAction = { bannerVersion++ }
                                        )
                                        Toast.makeText(context, message ?: "Erreur", Toast.LENGTH_LONG).show()
                                    }
                                }
                            }
                        },
                        onRejectOrder = {
                            currentOrder?.let { order ->
                                if (hasNewOrder) {
                                    notificationService.stopNotificationSound()
                                    hasNewOrder = false
                                }
                                
                                Log.d("CoursierScreenNew", "Refus de la commande ${order.id} par coursier $coursierId")
                                Toast.makeText(context, "Refus en cours...", Toast.LENGTH_SHORT).show()
                                
                                // Appeler l'API pour refuser
                                ApiService.respondToOrder(order.id, coursierId.toString(), "refuse") { success, message ->
                                    if (success) {
                                        Log.d("CoursierScreenNew", "Commande refusée: $message")
                                        Toast.makeText(context, "Commande refusée", Toast.LENGTH_SHORT).show()
                                        pendingOrdersCount = maxOf(0, pendingOrdersCount - 1)
                                        onCommandeReject(order.id)
                                    } else {
                                        Log.e("CoursierScreenNew", "Échec refus: $message")
                                        Toast.makeText(context, "Erreur: ${message ?: "Impossible de refuser"}", Toast.LENGTH_LONG).show()
                                    }
                                }
                            }
                        },
                        onStartDelivery = {
                            currentOrder?.let { order ->
                                Toast.makeText(context, "Démarrage de la livraison...", Toast.LENGTH_SHORT).show()
                                onStartDelivery(order.id)
                                deliveryStep = DeliveryStep.EN_ROUTE_PICKUP
                            }
                        },
                        onPickupPackage = {
                            currentOrder?.let { order ->
                                Toast.makeText(context, "Colis récupéré!", Toast.LENGTH_SHORT).show()
                                onPickupPackage(order.id)
                                deliveryStep = DeliveryStep.PICKED_UP
                            }
                        },
                        onMarkDelivered = {
                            currentOrder?.let { order ->
                                Toast.makeText(context, "Commande livrée!", Toast.LENGTH_SHORT).show()
                                onMarkDelivered(order.id)
                                deliveryStep = DeliveryStep.DELIVERED
                            }
                        },
                        onConfirmCash = {
                            currentOrder?.let { order ->
                                Toast.makeText(context, "Cash récupéré confirmé!", Toast.LENGTH_SHORT).show()
                                onConfirmCash(order.id)
                                deliveryStep = DeliveryStep.CASH_CONFIRMED
                            }
                        },
                        onPickupValidation = {
                            currentOrder?.let { order ->
                                deliveryStep = DeliveryStep.PICKED_UP
                                if (DeliveryStatusMapper.requiresApiCall(DeliveryStep.PICKED_UP)) {
                                    val serverStatus = DeliveryStatusMapper.mapStepToServerStatus(DeliveryStep.PICKED_UP)
                                    ApiService.updateOrderStatus(order.id, serverStatus) { success ->
                                        if (success) {
                                            timelineBanner = null
                                            Toast.makeText(context, DeliveryStatusMapper.getSuccessMessage(DeliveryStep.PICKED_UP, order.methodePaiement), Toast.LENGTH_SHORT).show()
                                            deliveryStep = DeliveryStep.EN_ROUTE_DELIVERY
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
                        gainsDuJour = gainsDuJour,
                        gainsHebdo = 0, // TODO: Ajouter depuis l'API
                        gainsMensuel = 0, // TODO: Ajouter depuis l'API
                        onRecharge = onRecharge,
                        onRetrait = { Toast.makeText(context, "Retrait - À venir", Toast.LENGTH_SHORT).show() },
                        onHistorique = onNavigateToHistorique
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
                                timestamp = Date(),
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
                                    timestamp = Date(),
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
                        coursierMatricule = coursierMatricule.ifBlank { "C$coursierId" },
                        stats = CoursierStats(
                            totalCourses = totalCommandes,
                            completedToday = commandes.count { it.statut == "livree" },
                            rating = noteGlobale.toFloat(),
                            totalEarnings = balance,
                            level = (totalCommandes / 20) + 1, // 20 courses = 1 niveau
                            experiencePercent = ((totalCommandes % 20) / 20f),
                            memberSince = if (dateInscription.isNotBlank()) dateInscription else "2025"
                        ),
                        onLogout = onLogout,
                        onEditProfile = { Toast.makeText(context, "Édition du profil - À venir", Toast.LENGTH_SHORT).show() },
                        onSettings = { Toast.makeText(context, "Paramètres - À venir", Toast.LENGTH_SHORT).show() }
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