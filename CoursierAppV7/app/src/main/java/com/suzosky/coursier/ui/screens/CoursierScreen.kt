package com.suzosky.coursier.ui.screens

import android.content.Context
import android.widget.Toast
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AttachMoney
import androidx.compose.material.icons.filled.Assignment
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.ui.components.*
import com.suzosky.coursier.ui.components.SuzoskyTextStyles
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.utils.TarificationSuzosky
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.network.ApiService
import java.util.*

/**
 * Écran principal du coursier redesigné avec navigation en bas
 * Navigation: Courses | Portefeuille | Chat | Profil
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CoursierScreen(
    modifier: Modifier = Modifier,
    coursierId: Int = 1,
    coursierNom: String = "Jean Dupont",
    coursierStatut: String = "EN_LIGNE",
    commandes: List<Commande> = emptyList(),
    onStatutChange: (String) -> Unit = {},
    onCommandeAccept: (String) -> Unit = {},
    onCommandeReject: (String) -> Unit = {},
    onCommandeAttente: (String) -> Unit = {},
    onStartDelivery: (String) -> Unit = {},
    onPickupPackage: (String) -> Unit = {},
    onMarkDelivered: (String) -> Unit = {},
    onNavigateToProfile: () -> Unit = {},
    onNavigateToHistorique: () -> Unit = {},
    onNavigateToGains: () -> Unit = {},
    onLogout: () -> Unit = {},
    onRecharge: (Int) -> Unit = {}
) {
    val context = LocalContext.current
    var showNotifications by remember { mutableStateOf(false) }
    var showMenu by remember { mutableStateOf(false) }
    var showRecharge by remember { mutableStateOf(false) }
    var paymentUrl by remember { mutableStateOf<String?>(null) }
    var showPayment by remember { mutableStateOf(false) }
    var mockBalance by remember { mutableStateOf(25000) } // Mock balance pour demo

    Box(
        modifier = modifier
            .fillMaxSize()
            .background(BackgroundPrimary)
    ) {
        // Background blur effect comme sur le web
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colors = listOf(
                            PrimaryDark.copy(alpha = 0.95f),
                            PrimaryDark.copy(alpha = 0.98f)
                        )
                    )
                )
                .blur(8.dp)
        )

        // Un seul conteneur scrollable pour tout l'écran
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .clip(RoundedCornerShape(0, 0, 16, 16))
                .background(GlassBg)
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            item {
                SuzoskyHeader(
                    titre = "Dashboard Coursier",
                    coursierNom = coursierNom,
                    statut = coursierStatut,
                    onStatutChange = onStatutChange,
                    onMenuClick = { showMenu = true },
                    onNotificationClick = { showNotifications = true }
                )
            }
            item { Spacer(Modifier.height(4.dp)) }
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    StatCard(
                        title = "Solde Wallet",
                        value = "${mockBalance} FCFA",
                        icon = Icons.Default.AttachMoney
                    )
                    StatCard(
                        title = "Gains du Jour",
                        value = "${TarificationSuzosky.calculerGainsCoursier(commandes)} FCFA",
                        icon = Icons.Default.Assignment
                    )
                }
            }
            item {
                // Bouton Recharger (ouvre le dialog)
                SuzoskyButton(
                    text = "Recharger",
                    style = SuzoskyButtonStyle.Primary,
                    onClick = { showRecharge = true },
                    modifier = Modifier.fillMaxWidth()
                )
            }
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = GlassBg),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    if (commandes.isEmpty()) {
                        Box(Modifier.fillMaxWidth().height(160.dp), contentAlignment = Alignment.Center) {
                            Text(
                                text = "Aucune commande disponible",
                                style = SuzoskyTextStyles.subtitle,
                                color = Color.White.copy(alpha = 0.6f),
                                textAlign = TextAlign.Center
                            )
                        }
                    } else {
                        Column(modifier = Modifier.padding(12.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                            commandes.forEach { cmd ->
                                val data = CommandeData(
                                    id = cmd.id,
                                    statut = cmd.statut,
                                    typeCommande = cmd.typeCommande,
                                    nomClient = cmd.clientNom,
                                    telephone = cmd.clientTelephone,
                                    adresseRecuperation = cmd.adresseEnlevement,
                                    adresseLivraison = cmd.adresseLivraison,
                                    instructions = cmd.description.ifEmpty { cmd.instructions },
                                    distanceKm = cmd.distance.toFloat(),
                                    minutesAttente = cmd.tempsAttente,
                                    heureCreation = cmd.heureCommande
                                )
                                CommandeCard(
                                    commande = data,
                                    onAccepter = { onCommandeAccept(cmd.id) },
                                    onRefuser = { onCommandeReject(cmd.id) },
                                    onMettreEnAttente = { onCommandeAttente(cmd.id) },
                                    onVoirDetails = { /* TODO */ }
                                )
                            }
                        }
                    }
                }
            }
            item { Spacer(Modifier.height(80.dp)) }
        }

        // Menu et notifications
        if (showMenu) {
            SuzoskyDrawerMenu(
                coursierNom = coursierNom,
                onDismiss = { showMenu = false },
                onNavigateToProfile = onNavigateToProfile,
                onNavigateToHistorique = onNavigateToHistorique,
                onNavigateToGains = onNavigateToGains,
                onNavigateToSupport = { /* TODO: Support navigation */ },
                onLogout = { onLogout(); showMenu = false }
            )
        }
        if (showNotifications) {
            NotificationPanel(
                notifications = listOf(
                    "Nouvelle commande reçue",
                    "Mise à jour des tarifs",
                    "Votre statut est: $coursierStatut"
                ),
                onDismiss = { showNotifications = false }
            )
        }
        if (showRecharge) {
            RechargeDialog(
                onDismiss = { showRecharge = false },
                onConfirm = { amount ->
                    showRecharge = false
                    println("🔄 Début du processus de rechargement - Montant: $amount FCFA")
                    
                    // Initier la recharge via l'API serveur
                    ApiService.initRecharge(coursierId, amount.toDouble()) { url, error ->
                        println("📡 Réponse API reçue - URL: $url, Erreur: $error")
                        
                        if (url != null) {
                            println("✅ URL de paiement reçue: $url")
                            paymentUrl = url
                            showPayment = true
                            println("🚀 Ouverture du modal de paiement")
                        } else {
                            println("❌ Pas d'URL de paiement reçue. Erreur: $error")
                            Toast.makeText(context, error ?: "Erreur inconnue", Toast.LENGTH_LONG).show()
                            // Fallback: notifier via callback local si fourni
                            onRecharge(amount)
                        }
                    }
                }
            )
        }

        if (showPayment && paymentUrl != null) {
            PaymentWebViewDialog(
                url = paymentUrl!!,
                onDismiss = {
                    showPayment = false
                    paymentUrl = null
                },
                onCompleted = { success, transactionId ->
                    val message = if (success) {
                        mockBalance += 5000 // Demo: ajouter 5000 FCFA au solde local
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
    }
}
