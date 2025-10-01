package com.suzosky.coursier.ui.screens

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.ui.theme.*

/**
 * Écran Mes Courses - Modern & Practical
 * Affiche les commandes avec une UI claire et intuitive
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ModernCoursesScreen(
    commandes: List<Commande>,
    currentOrder: Commande?,
    deliveryStep: DeliveryStep,
    onOrderClick: (Commande) -> Unit,
    onAcceptOrder: (Commande) -> Unit,
    onRejectOrder: (Commande) -> Unit,
    onStartNavigation: () -> Unit,
    modifier: Modifier = Modifier
) {
    // Statistiques rapides
    val activeOrders = commandes.count { it.statut in listOf("acceptee", "en_cours", "recupere") }
    val pendingOrders = commandes.count { it.statut in listOf("nouvelle", "attente") }
    val totalToday = commandes.size
    
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        PrimaryDark,
                        PrimaryDark.copy(alpha = 0.95f)
                    )
                )
            )
    ) {
        // Header avec stats
        CoursesHeader(
            activeOrders = activeOrders,
            pendingOrders = pendingOrders,
            totalToday = totalToday
        )
        
        // Commande active en cours
        if (currentOrder != null && deliveryStep != DeliveryStep.PENDING) {
            ActiveOrderCard(
                order = currentOrder,
                deliveryStep = deliveryStep,
                onStartNavigation = onStartNavigation,
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)
            )
        }
        
        // Liste des commandes
        if (commandes.isEmpty()) {
            EmptyCoursesState(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(32.dp)
            )
        } else {
            LazyColumn(
                modifier = Modifier.fillMaxSize(),
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Commandes en attente d'acceptation
                val pendingList = commandes.filter { it.statut in listOf("nouvelle", "attente") }
                if (pendingList.isNotEmpty()) {
                    item {
                        SectionHeader("🔔 Nouvelles demandes", pendingList.size)
                    }
                    items(pendingList) { order ->
                        PendingOrderCard(
                            order = order,
                            onAccept = { onAcceptOrder(order) },
                            onReject = { onRejectOrder(order) }
                        )
                    }
                }
                
                // Commandes actives
                val activeList = commandes.filter { it.statut in listOf("acceptee", "en_cours", "recupere") }
                if (activeList.isNotEmpty()) {
                    item {
                        SectionHeader("🚴 En cours", activeList.size)
                    }
                    items(activeList) { order ->
                        ActiveOrderMiniCard(
                            order = order,
                            onClick = { onOrderClick(order) }
                        )
                    }
                }
                
                // Commandes terminées aujourd'hui
                val completedList = commandes.filter { it.statut == "livree" }
                if (completedList.isNotEmpty()) {
                    item {
                        SectionHeader("✅ Complétées aujourd'hui", completedList.size)
                    }
                    items(completedList) { order ->
                        CompletedOrderCard(order = order)
                    }
                }
            }
        }
    }
}

@Composable
fun CoursesHeader(
    activeOrders: Int,
    pendingOrders: Int,
    totalToday: Int,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .padding(16.dp)
    ) {
        Text(
            text = "Mes Courses",
            fontSize = 28.sp,
            fontWeight = FontWeight.Bold,
            color = PrimaryGold
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            StatCard(
                icon = Icons.Filled.DirectionsBike,
                value = activeOrders.toString(),
                label = "Actives",
                color = SuccessGreen,
                modifier = Modifier.weight(1f)
            )
            StatCard(
                icon = Icons.Filled.Notifications,
                value = pendingOrders.toString(),
                label = "Nouvelles",
                color = PrimaryGold,
                modifier = Modifier.weight(1f)
            )
            StatCard(
                icon = Icons.Filled.CheckCircle,
                value = totalToday.toString(),
                label = "Aujourd'hui",
                color = Color.White.copy(alpha = 0.7f),
                modifier = Modifier.weight(1f)
            )
        }
    }
}

@Composable
fun StatCard(
    icon: ImageVector,
    value: String,
    label: String,
    color: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(4.dp)
    ) {
        Column(
            modifier = Modifier
                .padding(12.dp)
                .fillMaxWidth(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(24.dp)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = value,
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White
            )
            Text(
                text = label,
                fontSize = 12.sp,
                color = Color.White.copy(alpha = 0.7f)
            )
        }
    }
}

@Composable
fun ActiveOrderCard(
    order: Commande,
    deliveryStep: DeliveryStep,
    onStartNavigation: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .shadow(8.dp, RoundedCornerShape(20.dp)),
        colors = CardDefaults.cardColors(
            containerColor = PrimaryGold
        ),
        shape = RoundedCornerShape(20.dp)
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
                        text = "🚴 Course active",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryDark
                    )
                    Text(
                        text = "Commande #${order.id}",
                        fontSize = 12.sp,
                        color = PrimaryDark.copy(alpha = 0.7f)
                    )
                }
                
                // Badge d'état
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = Color.White
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Text(
                        text = when (deliveryStep) {
                            DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP -> "Vers récupération"
                            DeliveryStep.PICKUP_ARRIVED -> "Sur place (pickup)"
                            DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY -> "Vers livraison"
                            DeliveryStep.DELIVERY_ARRIVED -> "Sur place (livraison)"
                            else -> "En cours"
                        },
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                        fontSize = 11.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryDark
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Divider(color = PrimaryDark.copy(alpha = 0.2f))
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Info rapide
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = order.clientNom,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryDark
                    )
                    Text(
                        text = if (deliveryStep in listOf(DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP, DeliveryStep.PICKUP_ARRIVED)) {
                            "📍 ${order.adresseEnlevement.take(30)}..."
                        } else {
                            "🎯 ${order.adresseLivraison.take(30)}..."
                        },
                        fontSize = 12.sp,
                        color = PrimaryDark.copy(alpha = 0.8f)
                    )
                }
                
                Text(
                    text = "${order.prixLivraison.toInt()} F",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryDark
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Bouton navigation
            Button(
                onClick = onStartNavigation,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(
                    containerColor = PrimaryDark
                ),
                shape = RoundedCornerShape(12.dp)
            ) {
                Icon(Icons.Filled.Navigation, contentDescription = null, tint = PrimaryGold)
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    "Ouvrir la navigation",
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
            }
        }
    }
}

@Composable
fun PendingOrderCard(
    order: Commande,
    onAccept: () -> Unit,
    onReject: () -> Unit,
    modifier: Modifier = Modifier
) {
    var isExpanded by remember { mutableStateOf(false) }
    
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable { isExpanded = !isExpanded },
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(6.dp)
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
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Box(
                            modifier = Modifier
                                .size(8.dp)
                                .background(WarningOrange, CircleShape)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Nouvelle demande",
                            fontSize = 12.sp,
                            color = WarningOrange,
                            fontWeight = FontWeight.Bold
                        )
                    }
                    Spacer(modifier = Modifier.height(4.dp))
                    Text(
                        text = order.clientNom,
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    Text(
                        text = order.clientTelephone,
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.7f)
                    )
                }
                
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = SuccessGreen.copy(alpha = 0.2f)
                    ),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Text(
                        text = "${order.prixLivraison.toInt()} F",
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = SuccessGreen
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Info trajet
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Filled.LocationOn,
                    contentDescription = null,
                    tint = ErrorRed,
                    modifier = Modifier.size(16.dp)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text(
                    text = order.adresseEnlevement.take(25) + "...",
                    fontSize = 12.sp,
                    color = Color.White.copy(alpha = 0.9f),
                    modifier = Modifier.weight(1f)
                )
            }
            
            Spacer(modifier = Modifier.height(4.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Filled.Flag,
                    contentDescription = null,
                    tint = SuccessGreen,
                    modifier = Modifier.size(16.dp)
                )
                Spacer(modifier = Modifier.width(4.dp))
                Text(
                    text = order.adresseLivraison.take(25) + "...",
                    fontSize = 12.sp,
                    color = Color.White.copy(alpha = 0.9f),
                    modifier = Modifier.weight(1f)
                )
            }
            
            // Détails expandables
            AnimatedVisibility(visible = isExpanded) {
                Column {
                    Spacer(modifier = Modifier.height(12.dp))
                    Divider(color = Color.White.copy(alpha = 0.2f))
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    if (order.description.isNotEmpty()) {
                        Text(
                            text = "📦 ${order.description}",
                            fontSize = 12.sp,
                            color = Color.White.copy(alpha = 0.8f)
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                    }
                    
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        InfoChip("💳 ${order.methodePaiement}", modifier = Modifier.weight(1f))
                        if (order.distance > 0) {
                            InfoChip("📏 ${order.distance} km", modifier = Modifier.weight(1f))
                        }
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Boutons d'action
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                OutlinedButton(
                    onClick = onReject,
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = ErrorRed
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Icon(Icons.Filled.Close, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Refuser", fontWeight = FontWeight.Bold)
                }
                
                Button(
                    onClick = onAccept,
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.buttonColors(
                        containerColor = SuccessGreen
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Icon(Icons.Filled.Check, contentDescription = null, modifier = Modifier.size(18.dp))
                    Spacer(modifier = Modifier.width(4.dp))
                    Text("Accepter", fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
fun ActiveOrderMiniCard(
    order: Commande,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(10.dp)
                    .background(SuccessGreen, CircleShape)
            )
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = order.clientNom,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Text(
                    text = "📍 ${order.adresseEnlevement.take(30)}...",
                    fontSize = 11.sp,
                    color = Color.White.copy(alpha = 0.7f)
                )
            }
            
            Icon(
                Icons.Filled.ChevronRight,
                contentDescription = null,
                tint = PrimaryGold
            )
        }
    }
}

@Composable
fun CompletedOrderCard(
    order: Commande,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg.copy(alpha = 0.5f)
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                Icons.Filled.CheckCircle,
                contentDescription = null,
                tint = SuccessGreen,
                modifier = Modifier.size(20.dp)
            )
            
            Spacer(modifier = Modifier.width(12.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = order.clientNom,
                    fontSize = 13.sp,
                    color = Color.White.copy(alpha = 0.9f)
                )
                Text(
                    text = order.heureCommande,
                    fontSize = 11.sp,
                    color = Color.White.copy(alpha = 0.6f)
                )
            }
            
            Text(
                text = "+${order.prixLivraison.toInt()} F",
                fontSize = 14.sp,
                fontWeight = FontWeight.Bold,
                color = SuccessGreen
            )
        }
    }
}

@Composable
fun InfoChip(text: String, modifier: Modifier = Modifier) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = PrimaryGold.copy(alpha = 0.2f)
        ),
        shape = RoundedCornerShape(8.dp)
    ) {
        Text(
            text = text,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
            fontSize = 11.sp,
            color = PrimaryGold
        )
    }
}

@Composable
fun SectionHeader(title: String, count: Int, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = title,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White
        )
        Card(
            colors = CardDefaults.cardColors(
                containerColor = PrimaryGold.copy(alpha = 0.3f)
            ),
            shape = CircleShape
        ) {
            Text(
                text = count.toString(),
                modifier = Modifier.padding(horizontal = 10.dp, vertical = 4.dp),
                fontSize = 12.sp,
                fontWeight = FontWeight.Bold,
                color = PrimaryGold
            )
        }
    }
}

@Composable
fun EmptyCoursesState(modifier: Modifier = Modifier) {
    Column(
        modifier = modifier,
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        Icon(
            Icons.Filled.DirectionsBike,
            contentDescription = null,
            tint = PrimaryGold.copy(alpha = 0.5f),
            modifier = Modifier.size(80.dp)
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text(
            text = "Aucune course pour le moment",
            fontSize = 18.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White.copy(alpha = 0.7f),
            textAlign = TextAlign.Center
        )
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            text = "Les nouvelles courses apparaîtront ici",
            fontSize = 14.sp,
            color = Color.White.copy(alpha = 0.5f),
            textAlign = TextAlign.Center
        )
    }
}
