package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.suzosky.coursier.ui.theme.*
import java.text.SimpleDateFormat
import java.util.*
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import androidx.lifecycle.repeatOnLifecycle

private data class WalletHistoryItem(
    val id: String,
    val clientNom: String,
    val adresseEnlevement: String,
    val adresseLivraison: String,
    val prix: Double,
    val statut: String,
    val dateCommande: String,
    val heureCommande: String,
    val distanceKm: Double
)

data class EarningsData(
    val period: String,
    val amount: Int,
    val ordersCount: Int
)

data class RechargeTransaction(
    val id: String,
    val amount: Int,
    val date: Date,
    val status: String,
    val method: String = "CinetPay"
)

enum class EarningsPeriod { DAILY, WEEKLY, MONTHLY }

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun WalletScreen(
    coursierId: Int,
    balance: Int,
    onRecharge: (Int) -> Unit,
    modifier: Modifier = Modifier
) {
    var showRechargeDialog by remember { mutableStateOf(false) }
    var showHistoryDialog by remember { mutableStateOf(false) }
    var showEarningsDialog by remember { mutableStateOf(false) }
    var selectedEarningsPeriod by remember { mutableStateOf(EarningsPeriod.DAILY) }

    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var historiqueCommandes by remember { mutableStateOf(listOf<WalletHistoryItem>()) }
    var earningsData by remember { mutableStateOf<Map<EarningsPeriod, List<EarningsData>>>(emptyMap()) }

    // Helper pour charger les données
    fun loadWalletData() {
        loading = true
        error = null
        com.suzosky.coursier.network.ApiService.getCoursierOrders(
            coursierId = coursierId,
            status = "all",
            limit = 100,
            offset = 0
        ) { data, _err ->
            if (data != null) {
                try {
                    val commandes = (data["commandes"] as? List<*>)?.mapNotNull { item ->
                        val m = item as? Map<*, *> ?: return@mapNotNull null
                        WalletHistoryItem(
                            id = (m["id"] as? String) ?: "",
                            clientNom = (m["clientNom"] as? String) ?: (m["client_nom"] as? String ?: ""),
                            adresseEnlevement = (m["adresseEnlevement"] as? String) ?: (m["adresse_depart"] as? String ?: ""),
                            adresseLivraison = (m["adresseLivraison"] as? String) ?: (m["adresse_livraison"] as? String ?: ""),
                            prix = (m["prix"] as? Number)?.toDouble() ?: 0.0,
                            statut = (m["statut"] as? String) ?: "",
                            dateCommande = (m["dateCommande"] as? String) ?: (m["date_creation"] as? String ?: "").split(" ").firstOrNull() ?: "",
                            heureCommande = (m["heureCommande"] as? String) ?: (m["date_creation"] as? String ?: "").split(" ").getOrNull(1) ?: "",
                            distanceKm = (m["distanceKm"] as? Number)?.toDouble() ?: (m["distance_km"] as? Number)?.toDouble() ?: 0.0
                        )
                    } ?: emptyList()
                    // Update historiqueCommandes only if it meaningfully changed (ids differ) to reduce UI churn
                    try {
                        val newIdsHash = commandes.joinToString(separator = ",") { (it as WalletHistoryItem).id }
                        val oldIdsHash = historiqueCommandes.joinToString(separator = ",") { it.id }
                        if (newIdsHash != oldIdsHash) {
                            historiqueCommandes = commandes
                        }
                    } catch (_: Exception) {
                        historiqueCommandes = commandes
                    }
                     earningsData = computeEarnings(commandes)
                } catch (e: Exception) {
                    historiqueCommandes = emptyList()
                    earningsData = emptyMap()
                }
            } else {
                historiqueCommandes = emptyList()
                earningsData = emptyMap()
                error = null
            }
            loading = false
        }
    }

    // Chargement initial + polling toutes 5 secondes (pause en arrière-plan)
    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(coursierId, lifecycleOwner) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.STARTED) {
            while (true) {
                loadWalletData()
                kotlinx.coroutines.delay(5000L)
            }
        }
    }

    // Contenu scrollable sans barre de défilement apparente
    Column(
        modifier = modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(16.dp)
    ) {
        // Header
        Text(
            text = "Mon Portefeuille",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold
        )

        Spacer(modifier = Modifier.height(24.dp))

        // Carte de solde principale avec gradient Suzosky
        Card(
            shape = RoundedCornerShape(20.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
        ) {
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(
                        Brush.linearGradient(
                            colors = listOf(PrimaryDark, SecondaryBlue, PrimaryGold.copy(alpha = 0.3f))
                        )
                    )
                    .padding(24.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.Top
                ) {
                    Column {
                        Text(
                            text = "Solde disponible",
                            color = Color.White.copy(alpha = 0.8f),
                            fontSize = 14.sp
                        )
                        Text(
                            text = formatFcfa(balance),
                            color = Color.White,
                            fontSize = 32.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text(
                            text = "Dernière recharge: ${SimpleDateFormat("dd/MM/yyyy", Locale.getDefault()).format(Date())}",
                            color = Color.White.copy(alpha = 0.6f),
                            fontSize = 12.sp
                        )
                    }

                    Icon(
                        imageVector = Icons.Default.AccountBalanceWallet,
                        contentDescription = null,
                        tint = PrimaryGold,
                        modifier = Modifier.size(32.dp)
                    )
                }

                Spacer(modifier = Modifier.height(24.dp))

                // Bouton de recharge supprimé - gardé seulement sur l'écran principal
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Section Gains avec périodes
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { showEarningsDialog = true },
            colors = CardDefaults.cardColors(containerColor = GlassBg),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
        ) {
            Column(
                modifier = Modifier.padding(20.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Mes Gains",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryGold
                        )
                        Text(
                            text = "Consultez vos revenus par période",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                        )
                    }

                    Icon(
                        Icons.Default.TrendingUp,
                        contentDescription = "Voir gains",
                        tint = SuccessGreen,
                        modifier = Modifier.size(32.dp)
                    )
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Aperçu gains aujourd'hui
                if (loading) {
                    LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                } else if (error != null) {
                    Text(text = error ?: "Erreur", color = Color.Red)
                } else {
                    val day = earningsData[EarningsPeriod.DAILY]?.getOrNull(0)
                    val week = earningsData[EarningsPeriod.WEEKLY]?.getOrNull(0)
                    val month = earningsData[EarningsPeriod.MONTHLY]?.getOrNull(0)
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Box(modifier = Modifier.weight(1f)) {
                            GainsSummaryItem(
                                period = "Aujourd'hui",
                                amount = formatFcfa(day?.amount ?: 0),
                                orders = "${day?.ordersCount ?: 0} courses",
                                icon = Icons.Default.CalendarToday
                            )
                        }
                        Box(modifier = Modifier.weight(1f)) {
                            GainsSummaryItem(
                                period = "Cette semaine",
                                amount = formatFcfa(week?.amount ?: 0),
                                orders = "${week?.ordersCount ?: 0} courses",
                                icon = Icons.Default.DateRange
                            )
                        }
                        Box(modifier = Modifier.weight(1f)) {
                            GainsSummaryItem(
                                period = "Ce mois",
                                amount = formatFcfa(month?.amount ?: 0),
                                orders = "${month?.ordersCount ?: 0} courses",
                                icon = Icons.Default.Event
                            )
                        }
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Actions rapides
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Mes statistiques
            QuickActionCard(
                title = "Statistiques",
                subtitle = "Mes performances",
                icon = Icons.Default.Analytics,
                onClick = { showEarningsDialog = true },
                modifier = Modifier.weight(1f)
            )

            // Recharge rapide
            QuickActionCard(
                title = "Recharger compte",
                subtitle = "",
                icon = Icons.Default.AddCard,
                onClick = { showRechargeDialog = true }, // Ouvrir le dialog de rechargement
                modifier = Modifier.weight(1f)
            )
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Section Historique des courses
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(containerColor = GlassBg),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
        ) {
            Column(
                modifier = Modifier.padding(20.dp)
            ) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Historique des courses",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryGold
                        )
                        Text(
                            text = if (loading) "Chargement..." else "${historiqueCommandes.size} courses au total",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                        )
                    }

                    Icon(
                        Icons.Default.History,
                        contentDescription = null,
                        tint = PrimaryGold,
                        modifier = Modifier.size(28.dp)
                    )
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Statistiques rapides
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceEvenly
                ) {
                    HistoryStatItem(
                        value = if (loading) "-" else historiqueCommandes.size.toString(),
                        label = "Total",
                        color = PrimaryGold
                    )
                    HistoryStatItem(
                        value = if (loading) "-" else historiqueCommandes.count { it.statut == "livree" }.toString(),
                        label = "Livrées",
                        color = SuccessGreen
                    )
                    HistoryStatItem(
                        value = if (loading) "-" else formatFcfa(historiqueCommandes.filter { it.statut == "livree" }.sumOf { it.prix }.toInt()),
                        label = "Total gagné",
                        color = SecondaryBlue
                    )
                }

                Spacer(modifier = Modifier.height(16.dp))

                // Aperçu inline des dernières courses (5 dernières)
                if (!loading && error == null && historiqueCommandes.isNotEmpty()) {
                    val latest = historiqueCommandes.take(5)
                    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        latest.forEach { c ->
                            Card(
                                colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.04f)),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                Row(
                                    modifier = Modifier
                                        .fillMaxWidth()
                                        .padding(horizontal = 12.dp, vertical = 10.dp),
                                    horizontalArrangement = Arrangement.SpaceBetween,
                                    verticalAlignment = Alignment.CenterVertically
                                ) {
                                    Column(modifier = Modifier.weight(1f)) {
                                        Text(
                                            text = c.clientNom.ifBlank { "Client" },
                                            style = MaterialTheme.typography.bodyMedium,
                                            fontWeight = FontWeight.SemiBold
                                        )
                                        Text(
                                            text = "${c.dateCommande} ${c.heureCommande}",
                                            style = MaterialTheme.typography.bodySmall,
                                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                                        )
                                    }
                                    Column(horizontalAlignment = Alignment.End) {
                                        Text(
                                            text = formatFcfa(c.prix.toInt()),
                                            style = MaterialTheme.typography.bodyMedium,
                                            color = PrimaryGold,
                                            fontWeight = FontWeight.Bold
                                        )
                                        Text(
                                            text = when (c.statut) {
                                                "livree" -> "Livrée"
                                                "annulee" -> "Annulée"
                                                "en_cours" -> "En cours"
                                                "acceptee" -> "Acceptée"
                                                "attente" -> "En attente"
                                                else -> c.statut
                                            },
                                            style = MaterialTheme.typography.labelSmall,
                                            color = when (c.statut) {
                                                "livree" -> SuccessGreen
                                                "annulee" -> Color.Red
                                                else -> MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                                            }
                                        )
                                    }
                                }
                            }
                        }
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                }

                Button(
                    onClick = { 
                        android.util.Log.d("WalletScreen", "🔵 CLICK sur bouton historique - commandes: ${historiqueCommandes.size}")
                        showHistoryDialog = true
                        android.util.Log.d("WalletScreen", "🔵 showHistoryDialog = $showHistoryDialog")
                    },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = PrimaryGold.copy(alpha = 0.1f),
                        contentColor = PrimaryGold
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Icon(
                        Icons.Default.List,
                        contentDescription = null,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        "Voir l'historique détaillé",
                        fontWeight = FontWeight.Bold
                    )
                }
            }
        }

        Spacer(modifier = Modifier.height(16.dp))

        // Informations CinetPay
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(containerColor = GlassBg),
            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
        ) {
            Column(
                modifier = Modifier.padding(16.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.Security,
                        contentDescription = null,
                        tint = PrimaryGold,
                        modifier = Modifier.size(24.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Paiements sécurisés avec CinetPay",
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Bold
                    )
                }

                Spacer(modifier = Modifier.height(8.dp))

                Text(
                    text = "Vos recharges sont sécurisées et traitées instantanément via CinetPay. Moyens de paiement acceptés : Mobile Money, cartes bancaires.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )
            }
        }
    }
    
    // Dialog de recharge
    if (showRechargeDialog) {
        com.suzosky.coursier.ui.components.RechargeDialog(
            onDismiss = { showRechargeDialog = false },
            onConfirm = { amount ->
                showRechargeDialog = false
                onRecharge(amount)
            }
        )
    }
    
    // Dialog historique des courses
    if (showHistoryDialog) {
        CourseHistoryDialog(
            commandes = historiqueCommandes,
            onDismiss = { showHistoryDialog = false }
        )
    }
    
    // Dialog des gains
    if (showEarningsDialog) {
        EarningsDialog(
            earningsData = earningsData,
            selectedPeriod = selectedEarningsPeriod,
            onPeriodChange = { selectedEarningsPeriod = it },
            onDismiss = { showEarningsDialog = false }
        )
    }
}

@Composable
private fun GainsSummaryItem(
    period: String,
    amount: String,
    orders: String,
    icon: ImageVector? = null
) {
    Card(
        colors = CardDefaults.cardColors(containerColor = GlassBg),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        shape = RoundedCornerShape(12.dp),
        modifier = Modifier.fillMaxWidth()
    ) {
        val visible = remember { mutableStateOf(false) }
        LaunchedEffect(Unit) { visible.value = true }
        Column(
            modifier = Modifier.padding(vertical = 12.dp, horizontal = 8.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            AnimatedVisibility(visible = visible.value, enter = fadeIn(animationSpec = tween(450))) {
                if (icon != null) {
                    Icon(icon, contentDescription = null, tint = PrimaryGold, modifier = Modifier.size(18.dp))
                }
            }
            Text(
                text = period,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
            )
            Text(
                text = amount,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = PrimaryGold
            )
            Text(
                text = orders,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
            )
        }
    }
}

@Composable
private fun QuickActionCard(
    title: String,
    subtitle: String,
    icon: ImageVector,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.clickable { onClick() },
        colors = CardDefaults.cardColors(containerColor = GlassBg),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = PrimaryGold,
                modifier = Modifier.size(32.dp)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = title,
                style = MaterialTheme.typography.titleSmall,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface
            )
            if (subtitle.isNotBlank()) {
                Text(
                    text = subtitle,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun RechargeDialog(
    onDismiss: () -> Unit,
    onRecharge: (Int) -> Unit
) {
    var customAmount by remember { mutableStateOf("") }
    
    Dialog(onDismissRequest = onDismiss) {
        Card(
            shape = RoundedCornerShape(16.dp),
            colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
        ) {
            Column(
                modifier = Modifier.padding(24.dp)
            ) {
                Text(
                    text = "Recharger mon compte",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                Text(
                    text = "Montants rapides:",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
                )
                
                Spacer(modifier = Modifier.height(12.dp))
                
                // Boutons de recharge rapide
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    listOf(2000, 5000, 10000, 20000).forEach { amount ->
                        Button(
                            onClick = { onRecharge(amount) },
                            modifier = Modifier.weight(1f),
                            colors = ButtonDefaults.buttonColors(
                                containerColor = PrimaryGold.copy(alpha = 0.1f),
                                contentColor = PrimaryGold
                            )
                        ) {
                            Text("${amount/1000}K")
                        }
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                // Montant personnalisé
                OutlinedTextField(
                    value = customAmount,
                    onValueChange = { customAmount = it },
                    label = { Text("Montant personnalisé (FCFA)") },
                    modifier = Modifier.fillMaxWidth(),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = PrimaryGold,
                        focusedLabelColor = PrimaryGold
                    )
                )
                
                Spacer(modifier = Modifier.height(24.dp))
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    OutlinedButton(
                        onClick = onDismiss,
                        modifier = Modifier.weight(1f)
                    ) {
                        Text("Annuler")
                    }
                    
                    Button(
                        onClick = {
                            customAmount.toIntOrNull()?.let { amount ->
                                if (amount > 0) onRecharge(amount)
                            }
                        },
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = PrimaryGold
                        )
                    ) {
                        Text("Recharger", color = PrimaryDark)
                    }
                }
            }
        }
    }
}

@Composable
private fun RechargeHistoryDialog(
    transactions: List<RechargeTransaction>,
    onDismiss: () -> Unit
) {
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(max = 400.dp),
            shape = RoundedCornerShape(16.dp)
        ) {
            Column(
                modifier = Modifier.padding(24.dp)
            ) {
                Text(
                    text = "Historique des recharges",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                LazyColumn(
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(transactions) { transaction ->
                        TransactionItem(transaction = transaction)
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                Button(
                    onClick = onDismiss,
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = PrimaryGold)
                ) {
                    Text("Fermer", color = PrimaryDark)
                }
            }
        }
    }
}

@Composable
private fun TransactionItem(transaction: RechargeTransaction) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = GlassBg)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = formatFcfa(transaction.amount),
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = SimpleDateFormat("dd/MM/yyyy HH:mm", Locale.getDefault()).format(transaction.date),
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                )
                Text(
                    text = transaction.method,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                )
            }
            
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = if (transaction.status == "Succès") SuccessGreen else AccentRed
                )
            ) {
                Text(
                    text = transaction.status,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp),
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White,
                    fontWeight = FontWeight.Bold
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun EarningsDialog(
    earningsData: Map<EarningsPeriod, List<EarningsData>>,
    selectedPeriod: EarningsPeriod,
    onPeriodChange: (EarningsPeriod) -> Unit,
    onDismiss: () -> Unit
) {
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(max = 500.dp),
            shape = RoundedCornerShape(16.dp)
        ) {
            Column(
                modifier = Modifier.padding(24.dp)
            ) {
                Text(
                    text = "Mes Gains",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                // Sélecteur de période
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    EarningsPeriod.values().forEach { period ->
                        FilterChip(
                            onClick = { onPeriodChange(period) },
                            label = { 
                                Text(
                                    when(period) {
                                        EarningsPeriod.DAILY -> "Jour"
                                        EarningsPeriod.WEEKLY -> "Semaine"
                                        EarningsPeriod.MONTHLY -> "Mois"
                                    }
                                ) 
                            },
                            selected = selectedPeriod == period,
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = PrimaryGold,
                                selectedLabelColor = PrimaryDark
                            )
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                // Données des gains
                LazyColumn(
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(earningsData[selectedPeriod] ?: emptyList()) { data ->
                        EarningsItem(data = data)
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                Button(
                    onClick = onDismiss,
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = PrimaryGold)
                ) {
                    Text("Fermer", color = PrimaryDark)
                }
            }
        }
    }
}

@Composable
private fun EarningsItem(data: EarningsData) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = GlassBg)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = data.period,
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    text = "${data.ordersCount} course${if(data.ordersCount > 1) "s" else ""}",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                )
            }
            
            Text(
                text = formatFcfa(data.amount),
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = SuccessGreen
            )
        }
    }
}

@Composable
private fun HistoryStatItem(
    value: String,
    label: String,
    color: Color
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = value,
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = color
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CourseHistoryDialog(
    commandes: List<WalletHistoryItem>,
    onDismiss: () -> Unit
) {
    var selectedFilter by remember { mutableStateOf<String?>(null) }
    
    // Filtrer les commandes selon le statut sélectionné
    val filteredCommandes = if (selectedFilter == null) {
        commandes
    } else {
        commandes.filter { it.statut == selectedFilter }
    }
    
    // Compter les commandes par statut pour afficher dans les chips
    val statusCounts = commandes.groupBy { it.statut }
        .mapValues { it.value.size }
    
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .fillMaxHeight(0.9f)
                .padding(8.dp),
            shape = RoundedCornerShape(20.dp),
            colors = CardDefaults.cardColors(containerColor = PrimaryDark)
        ) {
            Column(
                modifier = Modifier.padding(20.dp)
            ) {
                // En-tête
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Historique Complet",
                            style = MaterialTheme.typography.headlineSmall,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryGold
                        )
                        Text(
                            text = "${filteredCommandes.size} course${if(filteredCommandes.size > 1) "s" else ""}",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Color.White.copy(alpha = 0.7f)
                        )
                    }
                    
                    IconButton(onClick = onDismiss) {
                        Icon(
                            Icons.Default.Close,
                            contentDescription = "Fermer",
                            tint = PrimaryGold,
                            modifier = Modifier.size(28.dp)
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                // Filtres par statut avec compteurs
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .horizontalScroll(rememberScrollState()),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    // Bouton "Tout"
                    FilterChip(
                        selected = selectedFilter == null,
                        onClick = { selectedFilter = null },
                        label = { 
                            Text("Tout (${commandes.size})") 
                        },
                        leadingIcon = {
                            Icon(
                                Icons.Default.List,
                                contentDescription = null,
                                modifier = Modifier.size(18.dp)
                            )
                        },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = PrimaryGold,
                            selectedLabelColor = PrimaryDark,
                            selectedLeadingIconColor = PrimaryDark
                        )
                    )
                    
                    // Filtre "Livrées"
                    if (statusCounts.containsKey("livree")) {
                        FilterChip(
                            selected = selectedFilter == "livree",
                            onClick = { selectedFilter = if (selectedFilter == "livree") null else "livree" },
                            label = { 
                                Text("Livrées (${statusCounts["livree"] ?: 0})") 
                            },
                            leadingIcon = {
                                Icon(
                                    Icons.Default.CheckCircle,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = SuccessGreen,
                                selectedLabelColor = Color.White,
                                selectedLeadingIconColor = Color.White
                            )
                        )
                    }
                    
                    // Filtre "En cours / Récupérée"
                    val enCoursCount = (statusCounts["en_cours"] ?: 0) + (statusCounts["recuperee"] ?: 0)
                    if (enCoursCount > 0) {
                        FilterChip(
                            selected = selectedFilter == "en_cours" || selectedFilter == "recuperee",
                            onClick = { 
                                selectedFilter = when (selectedFilter) {
                                    "en_cours", "recuperee" -> null
                                    else -> "en_cours"
                                }
                            },
                            label = { 
                                Text("En cours ($enCoursCount)") 
                            },
                            leadingIcon = {
                                Icon(
                                    Icons.Default.LocalShipping,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = SecondaryBlue,
                                selectedLabelColor = Color.White,
                                selectedLeadingIconColor = Color.White
                            )
                        )
                    }
                    
                    // Filtre "Acceptées"
                    if (statusCounts.containsKey("acceptee")) {
                        FilterChip(
                            selected = selectedFilter == "acceptee",
                            onClick = { selectedFilter = if (selectedFilter == "acceptee") null else "acceptee" },
                            label = { 
                                Text("Acceptées (${statusCounts["acceptee"] ?: 0})") 
                            },
                            leadingIcon = {
                                Icon(
                                    Icons.Default.ThumbUp,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = PrimaryGold,
                                selectedLabelColor = PrimaryDark,
                                selectedLeadingIconColor = PrimaryDark
                            )
                        )
                    }
                    
                    // Filtre "Annulées"
                    if (statusCounts.containsKey("annulee")) {
                        FilterChip(
                            selected = selectedFilter == "annulee",
                            onClick = { selectedFilter = if (selectedFilter == "annulee") null else "annulee" },
                            label = { 
                                Text("Annulées (${statusCounts["annulee"] ?: 0})") 
                            },
                            leadingIcon = {
                                Icon(
                                    Icons.Default.Cancel,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = AccentRed,
                                selectedLabelColor = Color.White,
                                selectedLeadingIconColor = Color.White
                            )
                        )
                    }
                    
                    // Filtre "Refusées"
                    if (statusCounts.containsKey("refusee")) {
                        FilterChip(
                            selected = selectedFilter == "refusee",
                            onClick = { selectedFilter = if (selectedFilter == "refusee") null else "refusee" },
                            label = { 
                                Text("Refusées (${statusCounts["refusee"] ?: 0})") 
                            },
                            leadingIcon = {
                                Icon(
                                    Icons.Default.Block,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = Color.Red.copy(alpha = 0.8f),
                                selectedLabelColor = Color.White,
                                selectedLeadingIconColor = Color.White
                            )
                        )
                    }
                    
                    // Filtre "Terminées"
                    if (statusCounts.containsKey("terminee")) {
                        FilterChip(
                            selected = selectedFilter == "terminee",
                            onClick = { selectedFilter = if (selectedFilter == "terminee") null else "terminee" },
                            label = { 
                                Text("Terminées (${statusCounts["terminee"] ?: 0})") 
                            },
                            leadingIcon = {
                                Icon(
                                    Icons.Default.Done,
                                    contentDescription = null,
                                    modifier = Modifier.size(18.dp)
                                )
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = SuccessGreen.copy(alpha = 0.7f),
                                selectedLabelColor = Color.White,
                                selectedLeadingIconColor = Color.White
                            )
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                // Liste des commandes filtrées
                if (filteredCommandes.isEmpty()) {
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .weight(1f),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Icon(
                                Icons.Default.SearchOff,
                                contentDescription = null,
                                tint = Color.White.copy(alpha = 0.3f),
                                modifier = Modifier.size(64.dp)
                            )
                            Spacer(modifier = Modifier.height(16.dp))
                            Text(
                                text = "Aucune course",
                                style = MaterialTheme.typography.titleMedium,
                                color = Color.White.copy(alpha = 0.5f)
                            )
                            Text(
                                text = "dans cette catégorie",
                                style = MaterialTheme.typography.bodySmall,
                                color = Color.White.copy(alpha = 0.3f)
                            )
                        }
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.weight(1f),
                        verticalArrangement = Arrangement.spacedBy(10.dp)
                    ) {
                        items(filteredCommandes) { commande ->
                            CourseHistoryItemDetailed(commande = commande)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun CourseHistoryItem(commande: WalletHistoryItem) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = GlassBg),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Course #${commande.id}",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                    Text(
                        text = commande.clientNom,
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = "${commande.dateCommande} à ${commande.heureCommande}",
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                    )
                }
                
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = "${commande.prix.toInt()} FCFA",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = if (commande.statut == "livree") SuccessGreen else Color.Red
                    )
                    
                    Card(
                        colors = CardDefaults.cardColors(
                            containerColor = when (commande.statut) {
                                "livree" -> SuccessGreen.copy(alpha = 0.1f)
                                "annulee" -> Color.Red.copy(alpha = 0.1f)
                                else -> PrimaryGold.copy(alpha = 0.1f)
                            }
                        ),
                        shape = RoundedCornerShape(6.dp)
                    ) {
                        Text(
                            text = when (commande.statut) {
                                "livree" -> "Livrée"
                                "annulee" -> "Annulée"
                                else -> commande.statut.replaceFirstChar { it.uppercase() }
                            },
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
                            style = MaterialTheme.typography.labelSmall,
                            color = when (commande.statut) {
                                "livree" -> SuccessGreen
                                "annulee" -> Color.Red
                                else -> PrimaryGold
                            },
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Default.LocationOn,
                            contentDescription = null,
                            tint = PrimaryGold,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = commande.adresseEnlevement,
                            style = MaterialTheme.typography.bodySmall,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(4.dp))
                    
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Default.Place,
                            contentDescription = null,
                            tint = SecondaryBlue,
                            modifier = Modifier.size(16.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = commande.adresseLivraison,
                            style = MaterialTheme.typography.bodySmall,
                            maxLines = 1,
                            overflow = TextOverflow.Ellipsis
                        )
                    }
                }
                
                Column(horizontalAlignment = Alignment.End) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Default.DirectionsRun,
                            contentDescription = null,
                            tint = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                            modifier = Modifier.size(14.dp)
                        )
                        Spacer(modifier = Modifier.width(4.dp))
                        Text(
                            text = "${commande.distanceKm} km",
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                        )
                    }
                }
            }
        }
    }
}

// Version détaillée pour le modal d'historique complet
@Composable
private fun CourseHistoryItemDetailed(commande: WalletHistoryItem) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = when (commande.statut) {
                "livree" -> SuccessGreen.copy(alpha = 0.08f)
                "terminee" -> SuccessGreen.copy(alpha = 0.05f)
                "recuperee" -> SecondaryBlue.copy(alpha = 0.08f)
                "en_cours" -> SecondaryBlue.copy(alpha = 0.08f)
                "acceptee" -> PrimaryGold.copy(alpha = 0.08f)
                "annulee" -> AccentRed.copy(alpha = 0.08f)
                "refusee" -> Color.Red.copy(alpha = 0.08f)
                else -> GlassBg
            }
        ),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier.padding(18.dp)
        ) {
            // Ligne 1 : ID + Statut
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        when (commande.statut) {
                            "livree", "terminee" -> Icons.Default.CheckCircle
                            "recuperee", "en_cours" -> Icons.Default.LocalShipping
                            "acceptee" -> Icons.Default.ThumbUp
                            "annulee" -> Icons.Default.Cancel
                            "refusee" -> Icons.Default.Block
                            else -> Icons.Default.Info
                        },
                        contentDescription = null,
                        tint = when (commande.statut) {
                            "livree", "terminee" -> SuccessGreen
                            "recuperee", "en_cours" -> SecondaryBlue
                            "acceptee" -> PrimaryGold
                            "annulee", "refusee" -> AccentRed
                            else -> Color.White.copy(alpha = 0.6f)
                        },
                        modifier = Modifier.size(24.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = "Course #${commande.id}",
                        style = MaterialTheme.typography.titleLarge,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                }
                
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = when (commande.statut) {
                            "livree", "terminee" -> SuccessGreen
                            "recuperee", "en_cours" -> SecondaryBlue
                            "acceptee" -> PrimaryGold
                            "annulee", "refusee" -> AccentRed
                            else -> Color.Gray
                        }
                    ),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text(
                        text = when (commande.statut) {
                            "livree" -> "✓ Livrée"
                            "terminee" -> "✓ Terminée"
                            "recuperee" -> "📦 Récupérée"
                            "en_cours" -> "🚚 En cours"
                            "acceptee" -> "👍 Acceptée"
                            "annulee" -> "✗ Annulée"
                            "refusee" -> "🚫 Refusée"
                            else -> commande.statut
                        },
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                        style = MaterialTheme.typography.labelMedium,
                        color = Color.White,
                        fontWeight = FontWeight.Bold
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Ligne 2 : Client + Prix
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Client",
                        style = MaterialTheme.typography.labelSmall,
                        color = Color.White.copy(alpha = 0.5f)
                    )
                    Text(
                        text = commande.clientNom.ifBlank { "Client anonyme" },
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White
                    )
                }
                
                Column(horizontalAlignment = Alignment.End) {
                    Text(
                        text = "Montant",
                        style = MaterialTheme.typography.labelSmall,
                        color = Color.White.copy(alpha = 0.5f)
                    )
                    Text(
                        text = formatFcfa(commande.prix.toInt()),
                        style = MaterialTheme.typography.headlineSmall,
                        fontWeight = FontWeight.Bold,
                        color = when (commande.statut) {
                            "livree", "terminee" -> SuccessGreen
                            "annulee", "refusee" -> AccentRed
                            else -> PrimaryGold
                        }
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Ligne 3 : Date et heure
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Default.CalendarToday,
                    contentDescription = null,
                    tint = Color.White.copy(alpha = 0.5f),
                    modifier = Modifier.size(16.dp)
                )
                Spacer(modifier = Modifier.width(6.dp))
                Text(
                    text = "${commande.dateCommande} à ${commande.heureCommande}",
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.7f)
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Ligne 4 : Trajet
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = Color.White.copy(alpha = 0.05f)
                ),
                shape = RoundedCornerShape(12.dp)
            ) {
                Column(
                    modifier = Modifier.padding(12.dp)
                ) {
                    // Départ
                    Row(verticalAlignment = Alignment.Top) {
                        Icon(
                            Icons.Default.LocationOn,
                            contentDescription = null,
                            tint = PrimaryGold,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Column {
                            Text(
                                text = "Départ",
                                style = MaterialTheme.typography.labelSmall,
                                color = Color.White.copy(alpha = 0.5f)
                            )
                            Text(
                                text = commande.adresseEnlevement.ifBlank { "Non spécifié" },
                                style = MaterialTheme.typography.bodyMedium,
                                color = Color.White,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(8.dp))
                    
                    // Ligne de séparation
                    Box(
                        modifier = Modifier
                            .padding(start = 10.dp)
                            .width(2.dp)
                            .height(20.dp)
                            .background(Color.White.copy(alpha = 0.2f))
                    )
                    
                    Spacer(modifier = Modifier.height(8.dp))
                    
                    // Arrivée
                    Row(verticalAlignment = Alignment.Top) {
                        Icon(
                            Icons.Default.Place,
                            contentDescription = null,
                            tint = SecondaryBlue,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Column {
                            Text(
                                text = "Arrivée",
                                style = MaterialTheme.typography.labelSmall,
                                color = Color.White.copy(alpha = 0.5f)
                            )
                            Text(
                                text = commande.adresseLivraison.ifBlank { "Non spécifié" },
                                style = MaterialTheme.typography.bodyMedium,
                                color = Color.White,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                }
            }
            
            // Ligne 5 : Distance
            if (commande.distanceKm > 0) {
                Spacer(modifier = Modifier.height(12.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.End,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        Icons.Default.DirectionsRun,
                        contentDescription = null,
                        tint = Color.White.copy(alpha = 0.5f),
                        modifier = Modifier.size(16.dp)
                    )
                    Spacer(modifier = Modifier.width(6.dp))
                    Text(
                        text = "${String.format("%.1f", commande.distanceKm)} km",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.White.copy(alpha = 0.7f),
                        fontWeight = FontWeight.Medium
                    )
                }
            }
        }
    }
}

private fun formatFcfa(amount: Int): String {
    val nf = java.text.NumberFormat.getInstance(Locale.FRANCE)
    return nf.format(amount.coerceAtLeast(0)) + " FCFA"
}

private fun computeEarnings(commandes: List<WalletHistoryItem>): Map<EarningsPeriod, List<EarningsData>> {
    val sdfDateTime = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
    val sdfDate = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
    val now = Calendar.getInstance()

    // Build list with parsed Date for grouping
    val items = commandes.mapNotNull { c ->
        try {
            val dateStr = if (c.dateCommande.isNotBlank() && c.heureCommande.isNotBlank()) "${c.dateCommande} ${c.heureCommande}" else "${c.dateCommande} 00:00:00"
            val d = sdfDateTime.parse(dateStr) ?: return@mapNotNull null
            Triple(d, c.prix.toInt(), 1)
        } catch (_: Exception) { null }
    }

    // Helper to check if date is today
    fun isSameDay(cal1: Calendar, cal2: Calendar): Boolean =
        cal1.get(Calendar.YEAR) == cal2.get(Calendar.YEAR) && cal1.get(Calendar.DAY_OF_YEAR) == cal2.get(Calendar.DAY_OF_YEAR)

    // Daily: today, yesterday, 2 days ago
    val dailyList = mutableListOf<EarningsData>()
    repeat(3) { idx ->
        val cal = Calendar.getInstance().apply { add(Calendar.DAY_OF_YEAR, -idx) }
        val dayItems = items.filter { (date, _, _) ->
            val c = Calendar.getInstance().apply { time = date }
            isSameDay(c, cal)
        }
        val amount = dayItems.sumOf { it.second }
        val count = dayItems.sumOf { it.third }
        val label = when (idx) {
            0 -> "Aujourd'hui"
            1 -> "Hier"
            else -> "Il y a 2 jours"
        }
        dailyList.add(EarningsData(label, amount, count))
    }

    // Weekly: this week, last week, two weeks ago
    fun weekOfYear(cal: Calendar) = cal.get(Calendar.WEEK_OF_YEAR) to cal.get(Calendar.YEAR)
    val weeklyList = mutableListOf<EarningsData>()
    repeat(3) { idx ->
        val cal = Calendar.getInstance().apply { add(Calendar.WEEK_OF_YEAR, -idx) }
        val (w, y) = weekOfYear(cal)
        val weekItems = items.filter { (date, _, _) ->
            val c = Calendar.getInstance().apply { time = date }
            val (ww, yy) = weekOfYear(c)
            ww == w && yy == y
        }
        val amount = weekItems.sumOf { it.second }
        val count = weekItems.sumOf { it.third }
        val label = when (idx) {
            0 -> "Cette semaine"
            1 -> "Semaine passée"
            else -> "Il y a 2 semaines"
        }
        weeklyList.add(EarningsData(label, amount, count))
    }

    // Monthly: this month, last month, two months ago
    val monthlyList = mutableListOf<EarningsData>()
    repeat(3) { idx ->
        val cal = Calendar.getInstance().apply { add(Calendar.MONTH, -idx) }
        val m = cal.get(Calendar.MONTH)
        val y = cal.get(Calendar.YEAR)
        val monthItems = items.filter { (date, _, _) ->
            val c = Calendar.getInstance().apply { time = date }
            c.get(Calendar.MONTH) == m && c.get(Calendar.YEAR) == y
        }
        val amount = monthItems.sumOf { it.second }
        val count = monthItems.sumOf { it.third }
        val label = when (idx) {
            0 -> "Ce mois"
            1 -> "Mois passé"
            else -> "Il y a 2 mois"
        }
        monthlyList.add(EarningsData(label, amount, count))
    }

    return mapOf(
        EarningsPeriod.DAILY to dailyList,
        EarningsPeriod.WEEKLY to weeklyList,
        EarningsPeriod.MONTHLY to monthlyList
    )
}