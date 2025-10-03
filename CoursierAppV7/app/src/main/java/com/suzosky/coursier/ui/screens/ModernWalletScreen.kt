package com.suzosky.coursier.ui.screens

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.network.ApiService
import kotlinx.coroutines.launch
import java.text.NumberFormat
import java.text.SimpleDateFormat
import java.util.*

/**
 * Écran Portefeuille moderne et pratique
 * Style Suzosky avec cartes glassmorphism et animations
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ModernWalletScreen(
    coursierId: Int,
    balance: Int,
    gainsDuJour: Int,
    gainsHebdo: Int = 0,
    gainsMensuel: Int = 0,
    onRecharge: (Int) -> Unit,
    onRetrait: () -> Unit = {},
    onHistorique: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    var showRechargeDialog by remember { mutableStateOf(false) }
    var selectedAmount by remember { mutableStateOf(0) }
    var showHistoryDialog by remember { mutableStateOf(false) }
    var allCommandes by remember { mutableStateOf<List<WalletHistoryItem>>(emptyList()) }
    var isLoadingHistory by remember { mutableStateOf(false) }
    
    val coroutineScope = rememberCoroutineScope()
    
    val currencyFormat = remember {
        NumberFormat.getCurrencyInstance(Locale.FRANCE).apply {
            currency = Currency.getInstance("XOF")
            maximumFractionDigits = 0
        }
    }
    
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(
                brush = Brush.verticalGradient(
                    colors = listOf(PrimaryDark, SecondaryBlue)
                )
            )
    ) {
        // Header avec solde principal
        WalletHeader(
            balance = balance,
            onRecharge = { showRechargeDialog = true },
            onRetrait = onRetrait,
            modifier = Modifier.padding(20.dp)
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Cartes de statistiques
        StatsCards(
            gainsDuJour = gainsDuJour,
            gainsHebdo = gainsHebdo,
            gainsMensuel = gainsMensuel,
            modifier = Modifier.padding(horizontal = 20.dp)
        )
        
        Spacer(modifier = Modifier.height(24.dp))
        
        // Section Actions rapides
        QuickActions(
            onRecharge = { showRechargeDialog = true },
            onHistorique = {
                android.util.Log.d("ModernWalletScreen", "🔍 Clic sur Historique - Chargement des données...")
                isLoadingHistory = true
                showHistoryDialog = true
                coroutineScope.launch {
                    ApiService.getCoursierOrders(
                        coursierId = coursierId,
                        status = "all",
                        limit = 200
                    ) { data, error ->
                        isLoadingHistory = false
                        if (error != null) {
                            android.util.Log.e("ModernWalletScreen", "❌ Erreur chargement historique: $error")
                        } else if (data != null) {
                            val commandes = data["commandes"] as? List<Map<String, Any>> ?: emptyList()
                            android.util.Log.d("ModernWalletScreen", "✅ ${commandes.size} commandes chargées")
                            allCommandes = commandes.map { cmd ->
                                val id = (cmd["id"] as? String)?.toIntOrNull() ?: (cmd["id"] as? Number)?.toInt() ?: 0
                                val clientNom = cmd["clientNom"] as? String ?: cmd["client_nom"] as? String ?: ""
                                val prix = (cmd["prix"] as? Number)?.toDouble()
                                    ?: (cmd["prix_livraison"] as? Number)?.toDouble() ?: 0.0
                                val datePart = cmd["dateCommande"] as? String ?: cmd["date_creation"] as? String ?: ""
                                val timePart = cmd["heureCommande"] as? String ?: ""
                                val date = if (datePart.isNotEmpty() && timePart.isNotEmpty()) "$datePart $timePart" else datePart.ifEmpty { timePart }
                                val statut = cmd["statut"] as? String ?: ""
                                val adrDep = cmd["adresseEnlevement"] as? String
                                    ?: cmd["adresse_enlevement"] as? String
                                    ?: cmd["adresse_depart"] as? String
                                    ?: ""
                                val adrArr = cmd["adresseLivraison"] as? String
                                    ?: cmd["adresse_livraison"] as? String
                                    ?: cmd["adresse_arrivee"] as? String
                                    ?: ""
                                val distance = (cmd["distanceKm"] as? Number)?.toDouble()
                                    ?: (cmd["distance"] as? Number)?.toDouble() ?: 0.0

                                WalletHistoryItem(
                                    id = id,
                                    clientNom = clientNom,
                                    montant = prix,
                                    date = date,
                                    statut = statut,
                                    adresseDepart = adrDep,
                                    adresseArrivee = adrArr,
                                    distance = distance
                                )
                            }
                        }
                    }
                }
            },
            modifier = Modifier.padding(horizontal = 20.dp)
        )
        
        Spacer(modifier = Modifier.height(24.dp))
        
        // Historique des transactions (preview)
        TransactionHistory(
            coursierId = coursierId,
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f)
                .padding(horizontal = 20.dp)
        )
    }
    
    // Dialog de recharge
    if (showRechargeDialog) {
        WalletRechargeDialog(
            onDismiss = { showRechargeDialog = false },
            onConfirm = { amount ->
                selectedAmount = amount
                onRecharge(amount)
                showRechargeDialog = false
            }
        )
    }
    
    // Modal historique complet
    if (showHistoryDialog) {
        CourseHistoryDialog(
            commandes = allCommandes,
            isLoading = isLoadingHistory,
            onDismiss = { showHistoryDialog = false }
        )
    }
}

/**
 * Header avec solde principal en grand
 */
@Composable
fun WalletHeader(
    balance: Int,
    onRecharge: () -> Unit,
    onRetrait: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .shadow(16.dp, RoundedCornerShape(24.dp)),
        colors = CardDefaults.cardColors(
            containerColor = Color.Transparent
        ),
        shape = RoundedCornerShape(24.dp)
    ) {
        Box(
            modifier = Modifier
                .background(
                    brush = Brush.linearGradient(
                        colors = listOf(PrimaryGold, PrimaryGoldLight, PrimaryGold)
                    )
                )
                .padding(24.dp)
        ) {
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = "💰 Solde disponible",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium,
                    color = PrimaryDark.copy(alpha = 0.7f)
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                Text(
                    text = "${balance.formatCurrency()} FCFA",
                    fontSize = 36.sp,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryDark
                )
                
                Spacer(modifier = Modifier.height(20.dp))
                
                // Bouton Recharger seul, pleine largeur
                Button(
                    onClick = onRecharge,
                    modifier = Modifier.fillMaxWidth().height(56.dp),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = PrimaryDark
                    ),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Icon(Icons.Filled.Add, contentDescription = null, tint = PrimaryGold, modifier = Modifier.size(24.dp))
                    Spacer(modifier = Modifier.width(12.dp))
                    Text("💳 Recharger mon compte", color = PrimaryGold, fontWeight = FontWeight.Bold, fontSize = 16.sp)
                }
            }
        }
    }
}

/**
 * Cartes de statistiques (gains jour/semaine/mois)
 */
@Composable
fun StatsCards(
    gainsDuJour: Int,
    gainsHebdo: Int,
    gainsMensuel: Int,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        StatWalletCard(
            icon = Icons.Filled.TrendingUp,
            label = "Aujourd'hui",
            value = "${gainsDuJour.formatCurrency()} F",
            color = SuccessGreen,
            modifier = Modifier.weight(1f)
        )
        
        StatWalletCard(
            icon = Icons.Filled.CalendarMonth,
            label = "Ce mois",
            value = "${gainsMensuel.formatCurrency()} F",
            color = AccentBlue,
            modifier = Modifier.weight(1f)
        )
    }
}

@Composable
fun StatWalletCard(
    icon: ImageVector,
    label: String,
    value: String,
    color: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.shadow(8.dp, RoundedCornerShape(20.dp)),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(20.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .background(color.copy(alpha = 0.2f), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    icon,
                    contentDescription = null,
                    tint = color,
                    modifier = Modifier.size(24.dp)
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = label,
                fontSize = 12.sp,
                color = Color.White.copy(alpha = 0.7f)
            )
            
            Spacer(modifier = Modifier.height(4.dp))
            
            Text(
                text = value,
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White
            )
        }
    }
}

/**
 * Actions rapides
 */
@Composable
fun QuickActions(
    onRecharge: () -> Unit,
    onHistorique: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(modifier = modifier) {
        Text(
            text = "Actions rapides",
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
            color = PrimaryGold
        )
        
        Spacer(modifier = Modifier.height(12.dp))
        
        Row(
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            QuickActionCard(
                icon = Icons.Filled.History,
                label = "Historique",
                onClick = onHistorique,
                modifier = Modifier.weight(1f)
            )
            
            QuickActionCard(
                icon = Icons.Filled.Receipt,
                label = "Factures",
                onClick = onHistorique,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

@Composable
fun QuickActionCard(
    icon: ImageVector,
    label: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .clickable(onClick = onClick)
            .shadow(4.dp, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = PrimaryGold,
                modifier = Modifier.size(28.dp)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = label,
                fontSize = 12.sp,
                color = Color.White,
                textAlign = TextAlign.Center
            )
        }
    }
}

/**
 * Historique des transactions (preview)
 */
@Composable
fun TransactionHistory(
    coursierId: Int,
    modifier: Modifier = Modifier
) {
    // TODO: Charger les vraies transactions depuis l'API
    val mockTransactions = remember {
        listOf(
            Transaction("1", "Course #135", "+2000 F", "Aujourd'hui 14:30", TransactionType.GAIN),
            Transaction("2", "Recharge", "+5000 F", "Hier 10:15", TransactionType.RECHARGE),
            Transaction("3", "Course #134", "+1500 F", "Hier 09:45", TransactionType.GAIN),
            Transaction("4", "Retrait", "-3000 F", "Il y a 2 jours", TransactionType.RETRAIT)
        )
    }
    
    Column(modifier = modifier) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = "Transactions récentes",
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = PrimaryGold
            )
            
            TextButton(onClick = { /* TODO: Voir tout */ }) {
                Text("Voir tout", color = PrimaryGold)
            }
        }
        
        Spacer(modifier = Modifier.height(12.dp))
        
        LazyColumn(
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            items(mockTransactions) { transaction ->
                TransactionItem(transaction)
            }
        }
    }
}

data class Transaction(
    val id: String,
    val title: String,
    val amount: String,
    val date: String,
    val type: TransactionType
)

enum class TransactionType {
    GAIN, RECHARGE, RETRAIT
}

@Composable
fun TransactionItem(transaction: Transaction) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(4.dp, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Row(
            modifier = Modifier
                .padding(16.dp)
                .fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .background(
                            color = when (transaction.type) {
                                TransactionType.GAIN -> SuccessGreen.copy(alpha = 0.2f)
                                TransactionType.RECHARGE -> AccentBlue.copy(alpha = 0.2f)
                                TransactionType.RETRAIT -> AccentRed.copy(alpha = 0.2f)
                            },
                            shape = CircleShape
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = when (transaction.type) {
                            TransactionType.GAIN -> Icons.Filled.AttachMoney
                            TransactionType.RECHARGE -> Icons.Filled.Add
                            TransactionType.RETRAIT -> Icons.Filled.Remove
                        },
                        contentDescription = null,
                        tint = when (transaction.type) {
                            TransactionType.GAIN -> SuccessGreen
                            TransactionType.RECHARGE -> AccentBlue
                            TransactionType.RETRAIT -> AccentRed
                        },
                        modifier = Modifier.size(20.dp)
                    )
                }
                
                Column {
                    Text(
                        text = transaction.title,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Medium,
                        color = Color.White
                    )
                    Text(
                        text = transaction.date,
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                }
            }
            
            Text(
                text = transaction.amount,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = when (transaction.type) {
                    TransactionType.GAIN, TransactionType.RECHARGE -> SuccessGreen
                    TransactionType.RETRAIT -> AccentRed
                }
            )
        }
    }
}

/**
 * Dialog de recharge pour le portefeuille
 */
@Composable
fun WalletRechargeDialog(
    onDismiss: () -> Unit,
    onConfirm: (Int) -> Unit
) {
    var selectedAmount by remember { mutableStateOf(0) }
    var customAmount by remember { mutableStateOf("") }
    var isCustomMode by remember { mutableStateOf(false) }
    val amounts = listOf(1000, 2000, 5000, 10000, 20000, 50000)
    
    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(
                "💳 Recharger mon compte",
                fontWeight = FontWeight.Bold,
                color = PrimaryGold
            )
        },
        text = {
            Column {
                Text(
                    "Sélectionnez ou saisissez un montant :",
                    fontSize = 14.sp,
                    color = Color.White.copy(alpha = 0.8f)
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                
                // Montants prédéfinis
                amounts.chunked(3).forEach { rowAmounts ->
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(8.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        rowAmounts.forEach { amount ->
                            Button(
                                onClick = {
                                    selectedAmount = amount
                                    isCustomMode = false
                                    customAmount = ""
                                },
                                modifier = Modifier.weight(1f),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = if (selectedAmount == amount && !isCustomMode) PrimaryGold else GlassBg
                                ),
                                shape = RoundedCornerShape(12.dp)
                            ) {
                                Text(
                                    "${amount.formatCurrency()} F",
                                    fontSize = 11.sp,
                                    color = if (selectedAmount == amount && !isCustomMode) PrimaryDark else Color.White
                                )
                            }
                        }
                    }
                    Spacer(modifier = Modifier.height(8.dp))
                }
                
                Spacer(modifier = Modifier.height(8.dp))
                
                // Champ de saisie manuelle
                OutlinedTextField(
                    value = customAmount,
                    onValueChange = {
                        if (it.all { char -> char.isDigit() }) {
                            customAmount = it
                            isCustomMode = it.isNotEmpty()
                            selectedAmount = it.toIntOrNull() ?: 0
                        }
                    },
                    label = { Text("Montant personnalisé (FCFA)", color = Color.White.copy(alpha = 0.7f)) },
                    placeholder = { Text("Ex: 15000") },
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = PrimaryGold,
                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),
                        focusedTextColor = Color.White,
                        unfocusedTextColor = Color.White
                    ),
                    keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(
                        keyboardType = androidx.compose.ui.text.input.KeyboardType.Number
                    ),
                    singleLine = true
                )
            }
        },
        confirmButton = {
            Button(
                onClick = {
                    if (selectedAmount > 0) {
                        onConfirm(selectedAmount)
                    }
                },
                enabled = selectedAmount > 0,
                colors = ButtonDefaults.buttonColors(
                    containerColor = PrimaryGold
                )
            ) {
                Text("Confirmer", color = PrimaryDark, fontWeight = FontWeight.Bold)
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text("Annuler", color = Color.White)
            }
        },
        containerColor = PrimaryDark
    )
}

/**
 * Extension pour formater les nombres
 */
fun Int.formatCurrency(): String {
    return String.format(Locale.FRANCE, "%,d", this).replace(',', ' ')
}

/**
 * Data class pour représenter une course dans l'historique
 */
data class WalletHistoryItem(
    val id: Int,
    val clientNom: String,
    val montant: Double,
    val date: String,
    val statut: String,
    val adresseDepart: String = "",
    val adresseArrivee: String = "",
    val distance: Double = 0.0
)

/**
 * Modal full-screen pour afficher l'historique complet avec filtres
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CourseHistoryDialog(
    commandes: List<WalletHistoryItem>,
    isLoading: Boolean,
    onDismiss: () -> Unit
) {
    var selectedFilter by remember { mutableStateOf<String?>(null) }
    
    val filteredCommandes = if (selectedFilter == null) {
        commandes
    } else {
        commandes.filter { it.statut == selectedFilter }
    }
    
    val statusCounts = commandes.groupBy { it.statut }.mapValues { it.value.size }
    
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
                modifier = Modifier
                    .fillMaxSize()
                    .padding(16.dp)
            ) {
                // Header avec bouton fermer
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Historique Complet",
                            fontSize = 24.sp,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryGold
                        )
                        if (!isLoading) {
                            Text(
                                text = "${filteredCommandes.size} course${if (filteredCommandes.size > 1) "s" else ""}",
                                fontSize = 14.sp,
                                color = Color.White.copy(alpha = 0.6f)
                            )
                        }
                    }
                    IconButton(onClick = onDismiss) {
                        Icon(
                            imageVector = Icons.Default.Close,
                            contentDescription = "Fermer",
                            tint = PrimaryGold
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                if (isLoading) {
                    // Indicateur de chargement
                    Box(
                        modifier = Modifier.fillMaxSize(),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            CircularProgressIndicator(color = PrimaryGold)
                            Spacer(modifier = Modifier.height(16.dp))
                            Text(
                                text = "Chargement de l'historique...",
                                color = Color.White.copy(alpha = 0.7f)
                            )
                        }
                    }
                } else {
                    // Filtres horizontaux avec scroll
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .horizontalScroll(rememberScrollState()),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        FilterChip(
                            selected = selectedFilter == null,
                            onClick = { selectedFilter = null },
                            label = { Text("Tout (${commandes.size})") },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = PrimaryGold,
                                selectedLabelColor = PrimaryDark
                            )
                        )
                        
                        FilterChip(
                            selected = selectedFilter == "livree",
                            onClick = { selectedFilter = "livree" },
                            label = { Text("Livrées (${statusCounts["livree"] ?: 0})") },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = SuccessGreen,
                                selectedLabelColor = Color.White
                            )
                        )
                        
                        FilterChip(
                            selected = selectedFilter == "en_cours" || selectedFilter == "recuperee",
                            onClick = { 
                                selectedFilter = if (selectedFilter == "en_cours") "recuperee" else "en_cours"
                            },
                            label = { 
                                val count = (statusCounts["en_cours"] ?: 0) + (statusCounts["recuperee"] ?: 0)
                                Text("En cours ($count)") 
                            },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = SecondaryBlue,
                                selectedLabelColor = Color.White
                            )
                        )
                        
                        FilterChip(
                            selected = selectedFilter == "acceptee",
                            onClick = { selectedFilter = "acceptee" },
                            label = { Text("Acceptées (${statusCounts["acceptee"] ?: 0})") },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = PrimaryGold,
                                selectedLabelColor = PrimaryDark
                            )
                        )
                        
                        FilterChip(
                            selected = selectedFilter == "annulee",
                            onClick = { selectedFilter = "annulee" },
                            label = { Text("Annulées (${statusCounts["annulee"] ?: 0})") },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = AccentRed,
                                selectedLabelColor = Color.White
                            )
                        )
                        
                        FilterChip(
                            selected = selectedFilter == "refusee",
                            onClick = { selectedFilter = "refusee" },
                            label = { Text("Refusées (${statusCounts["refusee"] ?: 0})") },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = Color.Red,
                                selectedLabelColor = Color.White
                            )
                        )
                        
                        FilterChip(
                            selected = selectedFilter == "terminee",
                            onClick = { selectedFilter = "terminee" },
                            label = { Text("Terminées (${statusCounts["terminee"] ?: 0})") },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = SuccessGreen.copy(alpha = 0.7f),
                                selectedLabelColor = Color.White
                            )
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Liste des courses
                    if (filteredCommandes.isEmpty()) {
                        Box(
                            modifier = Modifier.fillMaxSize(),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Icon(
                                    imageVector = Icons.Default.SearchOff,
                                    contentDescription = null,
                                    modifier = Modifier.size(64.dp),
                                    tint = Color.White.copy(alpha = 0.3f)
                                )
                                Spacer(modifier = Modifier.height(16.dp))
                                Text(
                                    text = "Aucune course dans cette catégorie",
                                    color = Color.White.copy(alpha = 0.5f)
                                )
                            }
                        }
                    } else {
                        LazyColumn(
                            verticalArrangement = Arrangement.spacedBy(10.dp),
                            modifier = Modifier.fillMaxSize()
                        ) {
                            items(filteredCommandes) { commande ->
                                CourseHistoryItemDetailed(commande)
                            }
                        }
                    }
                }
            }
        }
    }
}

/**
 * Card détaillée pour une course dans l'historique
 */
@Composable
private fun CourseHistoryItemDetailed(commande: WalletHistoryItem) {
    val statusColor = when (commande.statut) {
        "livree" -> SuccessGreen
        "recuperee", "en_cours" -> SecondaryBlue
        "annulee" -> AccentRed
        "refusee" -> Color.Red
        "terminee" -> SuccessGreen.copy(alpha = 0.7f)
        else -> PrimaryGold
    }
    
    val statusIcon = when (commande.statut) {
        "livree" -> Icons.Default.CheckCircle
        "recuperee", "en_cours" -> Icons.Default.LocalShipping
        "acceptee" -> Icons.Default.ThumbUp
        "annulee" -> Icons.Default.Cancel
        "refusee" -> Icons.Default.Block
        "terminee" -> Icons.Default.CheckCircle
        else -> Icons.Default.HourglassEmpty
    }
    
    val statusLabel = when (commande.statut) {
        "livree" -> "✓ Livrée"
        "recuperee" -> "📦 Récupérée"
        "en_cours" -> "🚚 En cours"
        "acceptee" -> "👍 Acceptée"
        "annulee" -> "✗ Annulée"
        "refusee" -> "🚫 Refusée"
        "terminee" -> "✓ Terminée"
        else -> commande.statut
    }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = when (commande.statut) {
                "livree" -> SuccessGreen.copy(alpha = 0.08f)
                "recuperee", "en_cours" -> SecondaryBlue.copy(alpha = 0.08f)
                "annulee" -> AccentRed.copy(alpha = 0.08f)
                "refusee" -> Color.Red.copy(alpha = 0.08f)
                else -> GlassBg
            }
        ),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            // Header: Statut + ID
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = statusIcon,
                        contentDescription = null,
                        tint = statusColor,
                        modifier = Modifier.size(24.dp)
                    )
                    Spacer(modifier = Modifier.width(8.dp))
                    Text(
                        text = statusLabel,
                        fontWeight = FontWeight.Bold,
                        color = statusColor,
                        fontSize = 16.sp
                    )
                }
                Text(
                    text = "#${commande.id}",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Client + Montant
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = commande.clientNom.ifEmpty { "Client anonyme" },
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White,
                        fontSize = 15.sp
                    )
                }
                Text(
                    text = "${commande.montant.toInt()} FCFA",
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold,
                    fontSize = 16.sp
                )
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Date
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Default.CalendarToday,
                    contentDescription = null,
                    tint = Color.White.copy(alpha = 0.6f),
                    modifier = Modifier.size(16.dp)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text(
                    text = formatDate(commande.date),
                    color = Color.White.copy(alpha = 0.7f),
                    fontSize = 13.sp
                )
            }
            
            // Trajet si disponible
            if (commande.adresseDepart.isNotEmpty() || commande.adresseArrivee.isNotEmpty()) {
                Spacer(modifier = Modifier.height(12.dp))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = Color.White.copy(alpha = 0.05f)
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Column(
                        modifier = Modifier.padding(12.dp)
                    ) {
                        // Départ
                        if (commande.adresseDepart.isNotEmpty()) {
                            Row(verticalAlignment = Alignment.Top) {
                                Icon(
                                    imageVector = Icons.Default.LocationOn,
                                    contentDescription = null,
                                    tint = SuccessGreen,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = commande.adresseDepart,
                                    color = Color.White.copy(alpha = 0.8f),
                                    fontSize = 13.sp,
                                    modifier = Modifier.weight(1f)
                                )
                            }
                        }
                        
                        // Ligne de séparation
                        if (commande.adresseDepart.isNotEmpty() && commande.adresseArrivee.isNotEmpty()) {
                            Spacer(modifier = Modifier.height(4.dp))
                            Box(
                                modifier = Modifier
                                    .padding(start = 9.dp)
                                    .width(2.dp)
                                    .height(16.dp)
                                    .background(Color.White.copy(alpha = 0.3f))
                            )
                            Spacer(modifier = Modifier.height(4.dp))
                        }
                        
                        // Arrivée
                        if (commande.adresseArrivee.isNotEmpty()) {
                            Row(verticalAlignment = Alignment.Top) {
                                Icon(
                                    imageVector = Icons.Default.Place,
                                    contentDescription = null,
                                    tint = AccentRed,
                                    modifier = Modifier.size(18.dp)
                                )
                                Spacer(modifier = Modifier.width(8.dp))
                                Text(
                                    text = commande.adresseArrivee,
                                    color = Color.White.copy(alpha = 0.8f),
                                    fontSize = 13.sp,
                                    modifier = Modifier.weight(1f)
                                )
                            }
                        }
                        
                        // Distance si disponible
                        if (commande.distance > 0) {
                            Spacer(modifier = Modifier.height(8.dp))
                            Row(
                                modifier = Modifier.fillMaxWidth(),
                                horizontalArrangement = Arrangement.End,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = Icons.Default.DirectionsRun,
                                    contentDescription = null,
                                    tint = SecondaryBlue,
                                    modifier = Modifier.size(16.dp)
                                )
                                Spacer(modifier = Modifier.width(4.dp))
                                Text(
                                    text = "%.1f km".format(commande.distance),
                                    color = SecondaryBlue,
                                    fontSize = 12.sp,
                                    fontWeight = FontWeight.SemiBold
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

/**
 * Formatte une date ISO en format lisible
 */
private fun formatDate(isoDate: String): String {
    return try {
        val inputFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.FRANCE)
        val outputFormat = SimpleDateFormat("dd MMM yyyy à HH:mm", Locale.FRANCE)
        val date = inputFormat.parse(isoDate)
        date?.let { outputFormat.format(it) } ?: isoDate
    } catch (e: Exception) {
        isoDate
    }
}
