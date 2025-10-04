package com.suzosky.coursierclient.ui

import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import com.suzosky.coursierclient.net.ClientStore
import com.suzosky.coursierclient.net.ApiService
import com.suzosky.coursierclient.ui.theme.*
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

@Composable
fun OrderHistoryScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val phone = remember { ClientStore.getClientPhone(context) ?: "" }

    var items by remember { mutableStateOf(listOf<ApiService.OrderHistoryItem>()) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var filter by remember { mutableStateOf("all") } // all, pending, completed

    fun reload() {
        if (phone.isBlank()) return
        loading = true; error = null
        scope.launch {
            try { 
                val all = ApiService.getClientOrders(phone)
                items = when(filter) {
                    "pending" -> all.filter { it.statut in listOf("en_attente", "acceptee", "en_cours") }
                    "completed" -> all.filter { it.statut in listOf("livree", "terminee") }
                    else -> all
                }
            }
            catch (e: Exception) { error = ApiService.friendlyError(e) }
            finally { loading = false }
        }
    }

    LaunchedEffect(filter) { reload() }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Dark, SecondaryBlue, Dark)
                )
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp)
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Historique des commandes",
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                    Text(
                        text = "${items.size} commande(s)",
                        fontSize = 14.sp,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                }
            }

            Spacer(Modifier.height(20.dp))

            // Filter chips
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                FilterChip(
                    label = "Tout",
                    selected = filter == "all",
                    onClick = { filter = "all" }
                )
                FilterChip(
                    label = "En cours",
                    selected = filter == "pending",
                    onClick = { filter = "pending" }
                )
                FilterChip(
                    label = "Terminées",
                    selected = filter == "completed",
                    onClick = { filter = "completed" }
                )
            }

            Spacer(Modifier.height(24.dp))

            if (loading) {
                Box(
                    modifier = Modifier.fillMaxWidth(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Gold)
                }
            } else if (error != null) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = AccentRed.copy(alpha = 0.2f)
                    ),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Error,
                            contentDescription = null,
                            tint = AccentRed,
                            modifier = Modifier.size(24.dp)
                        )
                        Spacer(Modifier.width(12.dp))
                        Text(text = error!!, color = Color.White, fontSize = 14.sp)
                    }
                }
            } else if (items.isEmpty()) {
                // Empty state
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 60.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Icon(
                        imageVector = Icons.Filled.ShoppingBag,
                        contentDescription = null,
                        modifier = Modifier.size(80.dp),
                        tint = Gold.copy(alpha = 0.3f)
                    )
                    Spacer(Modifier.height(16.dp))
                    Text(
                        text = "Aucune commande",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                    Spacer(Modifier.height(8.dp))
                    Text(
                        text = "Vos commandes apparaîtront ici",
                        fontSize = 14.sp,
                        color = Color.White.copy(alpha = 0.4f)
                    )
                }
            } else {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(items) { order ->
                        OrderCard(order = order)
                    }
                }
            }
        }
    }
}

@Composable
private fun FilterChip(
    label: String,
    selected: Boolean,
    onClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(20.dp))
            .background(
                if (selected) Gold.copy(alpha = 0.2f)
                else Color.White.copy(alpha = 0.05f)
            )
            .border(
                width = 1.dp,
                color = if (selected) Gold else Color.White.copy(alpha = 0.2f),
                shape = RoundedCornerShape(20.dp)
            )
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
        Text(
            text = label,
            fontSize = 14.sp,
            fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal,
            color = if (selected) Gold else Color.White.copy(alpha = 0.7f)
        )
    }
}

@Composable
private fun OrderCard(order: ApiService.OrderHistoryItem) {
    var expanded by remember { mutableStateOf(false) }

    val statusColor = when(order.statut) {
        "livree", "terminee" -> Success
        "annulee" -> AccentRed
        "en_cours", "acceptee" -> Info
        else -> Color.White.copy(alpha = 0.6f)
    }

    val statusText = when(order.statut) {
        "en_attente" -> "En attente"
        "acceptee" -> "Acceptée"
        "en_cours" -> "En cours"
        "livree" -> "Livrée"
        "terminee" -> "Terminée"
        "annulee" -> "Annulée"
        else -> order.statut
    }

    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { expanded = !expanded },
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.05f)
        ),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.1f))
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp)
        ) {
            // Header row
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(48.dp)
                        .clip(CircleShape)
                        .background(statusColor.copy(alpha = 0.15f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = when(order.statut) {
                            "livree", "terminee" -> Icons.Filled.CheckCircle
                            "annulee" -> Icons.Filled.Cancel
                            "en_cours", "acceptee" -> Icons.Filled.LocalShipping
                            else -> Icons.Filled.Schedule
                        },
                        contentDescription = null,
                        tint = statusColor,
                        modifier = Modifier.size(24.dp)
                    )
                }
                Spacer(Modifier.width(16.dp))
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = "Commande #${order.numero_commande}",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                    Spacer(Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .clip(RoundedCornerShape(12.dp))
                                .background(statusColor.copy(alpha = 0.15f))
                                .padding(horizontal = 10.dp, vertical = 4.dp)
                        ) {
                            Text(
                                text = statusText,
                                fontSize = 12.sp,
                                fontWeight = FontWeight.Medium,
                                color = statusColor
                            )
                        }
                    }
                }
                Icon(
                    imageVector = if (expanded) Icons.Filled.KeyboardArrowUp else Icons.Filled.KeyboardArrowDown,
                    contentDescription = null,
                    tint = Gold.copy(alpha = 0.5f)
                )
            }

            AnimatedVisibility(visible = expanded) {
                Column(modifier = Modifier.padding(top = 16.dp)) {
                    Divider(color = Color.White.copy(alpha = 0.1f), thickness = 1.dp)
                    Spacer(Modifier.height(16.dp))

                    DetailRow(
                        icon = Icons.Filled.LocationOn,
                        label = "Départ",
                        value = order.adresse_depart ?: "N/A"
                    )
                    Spacer(Modifier.height(12.dp))
                    DetailRow(
                        icon = Icons.Filled.Place,
                        label = "Arrivée",
                        value = order.adresse_arrivee ?: "N/A"
                    )
                    Spacer(Modifier.height(12.dp))
                    DetailRow(
                        icon = Icons.Filled.Payment,
                        label = "Montant",
                        value = "${order.prix_estime} FCFA"
                    )
                    Spacer(Modifier.height(12.dp))
                    DetailRow(
                        icon = Icons.Filled.CalendarToday,
                        label = "Date",
                        value = formatDate(order.date_creation)
                    )
                </Column>
            }
        }
    }
}

@Composable
private fun DetailRow(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    value: String
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.Top
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Gold.copy(alpha = 0.6f),
            modifier = Modifier.size(20.dp)
        )
        Spacer(Modifier.width(12.dp))
        Column {
            Text(
                text = label,
                fontSize = 12.sp,
                color = Color.White.copy(alpha = 0.5f)
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = value,
                fontSize = 14.sp,
                color = Color.White.copy(alpha = 0.8f),
                fontWeight = FontWeight.Medium
            )
        }
    }
}

private fun formatDate(dateStr: String?): String {
    if (dateStr == null) return "N/A"
    return try {
        val input = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
        val output = SimpleDateFormat("dd MMM yyyy à HH:mm", Locale.FRENCH)
        val date = input.parse(dateStr)
        date?.let { output.format(it) } ?: dateStr
    } catch (e: Exception) {
        dateStr
    }
}

