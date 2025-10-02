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
                            val orders = data["orders"] as? List<Map<String, Any>> ?: emptyList()
                            android.util.Log.d("ModernWalletScreen", "✅ ${orders.size} commandes chargées")
                            allCommandes = orders.map { order ->
                                WalletHistoryItem(
                                    id = (order["id"] as? Number)?.toInt() ?: 0,
                                    clientNom = order["client_nom"] as? String ?: "",
                                    montant = (order["prix_livraison"] as? Number)?.toDouble() ?: 0.0,
                                    date = order["date_creation"] as? String ?: "",
                                    statut = order["statut"] as? String ?: "",
                                    adresseDepart = order["adresse_enlevement"] as? String ?: "",
                                    adresseArrivee = order["adresse_livraison"] as? String ?: "",
                                    distance = (order["distance"] as? Number)?.toDouble() ?: 0.0
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
