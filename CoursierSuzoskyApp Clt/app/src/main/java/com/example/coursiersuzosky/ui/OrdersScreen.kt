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
import androidx.compose.material3.TabRowDefaults.tabIndicatorOffset
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursierclient.ui.theme.*

data class Order(
    val id: String,
    val status: OrderStatus,
    val fromAddress: String,
    val toAddress: String,
    val price: String,
    val date: String,
    val courierName: String? = null,
    val estimatedTime: String? = null
)

enum class OrderStatus {
    IN_PROGRESS,
    DELIVERED,
    CANCELLED
}

@Composable
fun OrdersScreen(modifier: Modifier = Modifier) {
    var selectedTab by remember { mutableStateOf(0) }
    
    // Demo data - à remplacer par des vraies données de l'API
    val orders = remember {
        listOf(
            Order(
                id = "CMD-2025-001",
                status = OrderStatus.IN_PROGRESS,
                fromAddress = "123 Avenue de la République, Abidjan",
                toAddress = "45 Rue du Commerce, Plateau",
                price = "2500 FCFA",
                date = "Aujourd'hui, 14:30",
                courierName = "Kouassi Jean",
                estimatedTime = "15 min"
            ),
            Order(
                id = "CMD-2025-002",
                status = OrderStatus.DELIVERED,
                fromAddress = "78 Boulevard Latrille, Cocody",
                toAddress = "12 Rue des Jardins, Marcory",
                price = "3500 FCFA",
                date = "Hier, 09:15",
                courierName = "Traoré Marie"
            ),
            Order(
                id = "CMD-2025-003",
                status = OrderStatus.DELIVERED,
                fromAddress = "56 Rue du Port, Zone 4",
                toAddress = "89 Avenue Houdaille, Treichville",
                price = "1800 FCFA",
                date = "2 Oct, 16:45",
                courierName = "Koné Ibrahim"
            )
        )
    }
    
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Dark, SecondaryBlue, Dark)
                )
            )
    ) {
        // Header with tabs
        Surface(
            color = Dark.copy(alpha = 0.7f),
            tonalElevation = 8.dp
        ) {
            Column {
                // Title
                Text(
                    text = "Mes Commandes",
                    fontSize = 28.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold,
                    modifier = Modifier.padding(24.dp)
                )
                
                // Tabs
                TabRow(
                    selectedTabIndex = selectedTab,
                    containerColor = Color.Transparent,
                    contentColor = Gold,
                    indicator = { tabPositions ->
                        if (selectedTab < tabPositions.size) {
                            TabRowDefaults.SecondaryIndicator(
                                modifier = Modifier.tabIndicatorOffset(tabPositions[selectedTab]),
                                height = 4.dp,
                                color = Gold
                            )
                        }
                    },
                    divider = {}
                ) {
                    Tab(
                        selected = selectedTab == 0,
                        onClick = { selectedTab = 0 },
                        text = {
                            Text(
                                "En cours",
                                fontWeight = if (selectedTab == 0) FontWeight.Bold else FontWeight.Normal
                            )
                        },
                        selectedContentColor = Gold,
                        unselectedContentColor = Color.White.copy(alpha = 0.5f)
                    )
                    Tab(
                        selected = selectedTab == 1,
                        onClick = { selectedTab = 1 },
                        text = {
                            Text(
                                "Historique",
                                fontWeight = if (selectedTab == 1) FontWeight.Bold else FontWeight.Normal
                            )
                        },
                        selectedContentColor = Gold,
                        unselectedContentColor = Color.White.copy(alpha = 0.5f)
                    )
                }
            }
        }
        
        // Content
        Box(modifier = Modifier.weight(1f)) {
            when (selectedTab) {
                0 -> ActiveOrdersContent(orders.filter { it.status == OrderStatus.IN_PROGRESS })
                1 -> HistoryOrdersContent(orders.filter { it.status != OrderStatus.IN_PROGRESS })
            }
        }
    }
}

@Composable
private fun ActiveOrdersContent(orders: List<Order>) {
    if (orders.isEmpty()) {
        EmptyState(
            icon = Icons.Filled.LocalShipping,
            title = "Aucune commande en cours",
            subtitle = "Vos commandes actives apparaîtront ici"
        )
    } else {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            items(orders) { order ->
                ActiveOrderCard(order)
            }
        }
    }
}

@Composable
private fun HistoryOrdersContent(orders: List<Order>) {
    if (orders.isEmpty()) {
        EmptyState(
            icon = Icons.Filled.History,
            title = "Aucun historique",
            subtitle = "Vos commandes passées s'afficheront ici"
        )
    } else {
        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(24.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            items(orders) { order ->
                HistoryOrderCard(order)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ActiveOrderCard(order: Order) {
    Card(
        onClick = { /* Navigate to order details */ },
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.05f)
        ),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    Brush.linearGradient(
                        colors = listOf(
                            Gold.copy(alpha = 0.1f),
                            Color.Transparent
                        )
                    )
                )
                .padding(20.dp)
        ) {
            // Header with status
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = order.id,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                
                StatusChip(
                    text = "En cours",
                    icon = Icons.Filled.LocalShipping,
                    color = AccentBlue
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Addresses
            AddressRow(
                icon = Icons.Filled.Circle,
                address = order.fromAddress,
                label = "Départ"
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            AddressRow(
                icon = Icons.Filled.LocationOn,
                address = order.toAddress,
                label = "Arrivée"
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Divider(color = Gold.copy(alpha = 0.2f))
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Footer info
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = order.courierName ?: "En attente...",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White
                    )
                    Text(
                        text = order.date,
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                }
                
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    if (order.estimatedTime != null) {
                        Row(
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.spacedBy(4.dp)
                        ) {
                            Icon(
                                imageVector = Icons.Filled.Timer,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(16.dp)
                            )
                            Text(
                                text = order.estimatedTime,
                                fontSize = 14.sp,
                                fontWeight = FontWeight.Bold,
                                color = Gold
                            )
                        }
                    }
                    
                    Text(
                        text = order.price,
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun HistoryOrderCard(order: Order) {
    Card(
        onClick = { /* Navigate to order details */ },
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.03f)
        ),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.15f))
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                modifier = Modifier.weight(1f),
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(48.dp)
                        .clip(CircleShape)
                        .background(
                            if (order.status == OrderStatus.DELIVERED)
                                Color(0xFF10B981).copy(alpha = 0.2f)
                            else
                                AccentRed.copy(alpha = 0.2f)
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = if (order.status == OrderStatus.DELIVERED)
                            Icons.Filled.CheckCircle
                        else
                            Icons.Filled.Cancel,
                        contentDescription = null,
                        tint = if (order.status == OrderStatus.DELIVERED)
                            Color(0xFF10B981)
                        else
                            AccentRed,
                        modifier = Modifier.size(24.dp)
                    )
                }
                
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = order.id,
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    Text(
                        text = order.toAddress,
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.6f),
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )
                    Text(
                        text = order.date,
                        fontSize = 11.sp,
                        color = Gold.copy(alpha = 0.7f)
                    )
                }
            }
            
            Text(
                text = order.price,
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = Gold
            )
        }
    }
}

@Composable
private fun AddressRow(
    icon: ImageVector,
    address: String,
    label: String
) {
    Row(
        verticalAlignment = Alignment.Top,
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Gold,
            modifier = Modifier
                .size(20.dp)
                .padding(top = 2.dp)
        )
        Column {
            Text(
                text = label,
                fontSize = 11.sp,
                fontWeight = FontWeight.Medium,
                color = Gold.copy(alpha = 0.7f),
                letterSpacing = 0.5.sp
            )
            Text(
                text = address,
                fontSize = 14.sp,
                fontWeight = FontWeight.Normal,
                color = Color.White
            )
        }
    }
}

@Composable
private fun StatusChip(
    text: String,
    icon: ImageVector,
    color: Color
) {
    Row(
        modifier = Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(color.copy(alpha = 0.2f))
            .padding(horizontal = 12.dp, vertical = 6.dp),
        horizontalArrangement = Arrangement.spacedBy(6.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = color,
            modifier = Modifier.size(14.dp)
        )
        Text(
            text = text,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold,
            color = color
        )
    }
}

@Composable
private fun EmptyState(
    icon: ImageVector,
    title: String,
    subtitle: String
) {
    Box(
        modifier = Modifier.fillMaxSize(),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                modifier = Modifier.size(80.dp),
                tint = Gold.copy(alpha = 0.3f)
            )
            Text(
                text = title,
                fontSize = 20.sp,
                fontWeight = FontWeight.SemiBold,
                color = Color.White.copy(alpha = 0.7f)
            )
            Text(
                text = subtitle,
                fontSize = 14.sp,
                color = Color.White.copy(alpha = 0.5f)
            )
        }
    }
}
