package com.suzosky.coursier.ui.screens

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.BorderStroke
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.android.gms.maps.model.LatLng
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.network.ApiService
import com.suzosky.coursier.ui.components.*
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.utils.LocationUtils
import com.suzosky.coursier.viewmodel.MapViewModel
import kotlinx.coroutines.delay

// États simplifiés pour une seule étape à la fois
enum class CourseStep {
    WAITING_ACCEPTANCE,     // En attente d'acceptation
    GOING_TO_PICKUP,       // En route vers récupération  
    ARRIVED_PICKUP,        // Arrivé au pickup - peut valider récupération
    GOING_TO_DELIVERY,     // En route vers livraison
    ARRIVED_DELIVERY,      // Arrivé à la livraison - peut valider livraison
    COMPLETED              // Terminée
}

data class CourseData(
    val commande: Commande,
    val step: CourseStep = CourseStep.WAITING_ACCEPTANCE,
    val isActive: Boolean = false
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NewCoursesScreen(
    pendingOrders: List<Commande> = emptyList(),
    activeOrder: Commande? = null,
    currentStep: CourseStep = CourseStep.WAITING_ACCEPTANCE,
    onAcceptOrder: (String) -> Unit = {},
    onRejectOrder: (String) -> Unit = {},
    onValidateStep: (CourseStep) -> Unit = {},
    onNavigationLaunched: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    val mapViewModel: MapViewModel = hiltViewModel()
    val mapState by mapViewModel.uiState.collectAsState()
    val context = LocalContext.current
    
    // Démarrer le suivi de localisation
    LaunchedEffect(Unit) {
        mapViewModel.startLocationTracking()
    }

    Box(modifier = modifier.fillMaxSize()) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp)
        ) {
            // Header avec nombre de courses
            CoursesHeader(
                pendingCount = pendingOrders.size,
                hasActiveOrder = activeOrder != null
            )
            
            Spacer(modifier = Modifier.height(16.dp))

            // Carte principale - toujours visible
            MainMapSection(
                activeOrder = activeOrder,
                currentStep = currentStep,
                courierLocation = mapState.currentLocation,
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(if (activeOrder != null) 0.55f else 0.7f)
            )

            Spacer(modifier = Modifier.height(16.dp))

            // Section commande active ou liste des commandes en attente
            if (activeOrder != null) {
                ActiveOrderSection(
                    order = activeOrder,
                    step = currentStep,
                    courierLocation = mapState.currentLocation,
                    onValidateStep = onValidateStep,
                    onNavigationLaunched = onNavigationLaunched,
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(0.45f)
                )
            } else {
                PendingOrdersSection(
                    orders = pendingOrders,
                    onAcceptOrder = onAcceptOrder,
                    onRejectOrder = onRejectOrder,
                    modifier = Modifier
                        .fillMaxWidth()
                        .weight(0.3f)
                )
            }
        }

        // Notifications flottantes pour nouvelles commandes
        if (pendingOrders.isNotEmpty() && activeOrder == null) {
            NewOrderNotification(
                order = pendingOrders.first(),
                onAccept = { onAcceptOrder(pendingOrders.first().id) },
                onReject = { onRejectOrder(pendingOrders.first().id) },
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .padding(16.dp)
            )
        }
    }
}

@Composable
private fun CoursesHeader(
    pendingCount: Int,
    hasActiveOrder: Boolean
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = GlassBg),
        shape = RoundedCornerShape(16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column {
                Text(
                    text = "Mes Courses",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                Text(
                    text = if (hasActiveOrder) {
                        "Course active • $pendingCount en attente"
                    } else if (pendingCount > 0) {
                        "$pendingCount nouvelle${if (pendingCount > 1) "s" else ""} commande${if (pendingCount > 1) "s" else ""}"
                    } else {
                        "En attente de commandes"
                    },
                    style = MaterialTheme.typography.bodyMedium,
                    color = Color.White.copy(alpha = 0.8f)
                )
            }

            // Indicateur visuel
            StatusIndicator(
                isActive = hasActiveOrder,
                pendingCount = pendingCount
            )
        }
    }
}

@Composable
private fun StatusIndicator(
    isActive: Boolean,
    pendingCount: Int
) {
    Box(
        modifier = Modifier
            .size(56.dp)
            .clip(CircleShape)
            .background(
                when {
                    isActive -> SuccessGreen.copy(alpha = 0.2f)
                    pendingCount > 0 -> PrimaryGold.copy(alpha = 0.2f)
                    else -> Color.Gray.copy(alpha = 0.2f)
                }
            ),
        contentAlignment = Alignment.Center
    ) {
        when {
            isActive -> {
                Icon(
                    Icons.Filled.DirectionsCar,
                    contentDescription = null,
                    tint = SuccessGreen,
                    modifier = Modifier.size(24.dp)
                )
            }
            pendingCount > 0 -> {
                Text(
                    text = pendingCount.toString(),
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
            }
            else -> {
                Icon(
                    Icons.Filled.Schedule,
                    contentDescription = null,
                    tint = Color.Gray,
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}

@Composable
private fun MainMapSection(
    activeOrder: Commande?,
    currentStep: CourseStep,
    courierLocation: LatLng?,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = Color.Black)
    ) {
        Box(modifier = Modifier.fillMaxSize()) {
            // Localisation pour la navigation
            val pickupLocation = activeOrder?.coordonneesEnlevement?.let { 
                LatLng(it.latitude, it.longitude) 
            }
            val deliveryLocation = activeOrder?.coordonneesLivraison?.let { 
                LatLng(it.latitude, it.longitude) 
            }

            // Destination selon l'étape
            val currentDestination = when (currentStep) {
                CourseStep.GOING_TO_PICKUP, CourseStep.ARRIVED_PICKUP -> pickupLocation
                CourseStep.GOING_TO_DELIVERY, CourseStep.ARRIVED_DELIVERY -> deliveryLocation
                else -> null
            }

            SimpleMapView(
                pickupLocation = pickupLocation,
                deliveryLocation = currentDestination,
                modifier = Modifier.fillMaxSize()
            )

            // Overlay d'information sur la destination
            if (activeOrder != null && currentDestination != null) {
                DestinationOverlay(
                    step = currentStep,
                    destination = when (currentStep) {
                        CourseStep.GOING_TO_PICKUP, CourseStep.ARRIVED_PICKUP -> "Récupération"
                        CourseStep.GOING_TO_DELIVERY, CourseStep.ARRIVED_DELIVERY -> "Livraison"
                        else -> "Destination"
                    },
                    address = when (currentStep) {
                        CourseStep.GOING_TO_PICKUP, CourseStep.ARRIVED_PICKUP -> activeOrder.adresseEnlevement
                        CourseStep.GOING_TO_DELIVERY, CourseStep.ARRIVED_DELIVERY -> activeOrder.adresseLivraison
                        else -> ""
                    },
                    modifier = Modifier
                        .align(Alignment.TopEnd)
                        .padding(16.dp)
                )
            }

            // Message si pas de commande active
            if (activeOrder == null) {
                WaitingMessage(
                    modifier = Modifier.align(Alignment.Center)
                )
            }
        }
    }
}

@Composable
private fun DestinationOverlay(
    step: CourseStep,
    destination: String,
    address: String,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark.copy(alpha = 0.95f)
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(
                imageVector = when (step) {
                    CourseStep.GOING_TO_PICKUP -> Icons.Filled.Navigation
                    CourseStep.ARRIVED_PICKUP -> Icons.Filled.LocationOn
                    CourseStep.GOING_TO_DELIVERY -> Icons.Filled.Navigation
                    CourseStep.ARRIVED_DELIVERY -> Icons.Filled.LocationOn
                    else -> Icons.Filled.Place
                },
                contentDescription = null,
                tint = PrimaryGold,
                modifier = Modifier.size(20.dp)
            )
            Column {
                Text(
                    text = destination,
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                Text(
                    text = address.take(30) + if (address.length > 30) "..." else "",
                    style = MaterialTheme.typography.bodySmall,
                    color = Color.White.copy(alpha = 0.8f)
                )
            }
        }
    }
}

@Composable
private fun WaitingMessage(
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark.copy(alpha = 0.9f)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier.padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(
                Icons.Filled.Schedule,
                contentDescription = null,
                tint = PrimaryGold,
                modifier = Modifier.size(32.dp)
            )
            Text(
                text = "En attente de commandes",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = PrimaryGold,
                textAlign = TextAlign.Center
            )
            Text(
                text = "Les nouvelles commandes apparaîtront automatiquement",
                style = MaterialTheme.typography.bodySmall,
                color = Color.White.copy(alpha = 0.7f),
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
private fun ActiveOrderSection(
    order: Commande,
    step: CourseStep,
    courierLocation: LatLng?,
    onValidateStep: (CourseStep) -> Unit,
    onNavigationLaunched: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = GlassBg),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp)
        ) {
            // Header de la commande active
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Course Active",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                
                StepIndicator(step = step)
            }

            Spacer(modifier = Modifier.height(12.dp))

            // Informations client
            ClientInfo(order = order)

            Spacer(modifier = Modifier.height(16.dp))

            // Timeline simplifiée - UNE SEULE ÉTAPE
            CurrentStepCard(
                step = step,
                order = order,
                courierLocation = courierLocation,
                onValidateStep = onValidateStep,
                onNavigationLaunched = onNavigationLaunched,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

@Composable
private fun StepIndicator(step: CourseStep) {
    val (text, color, icon) = when (step) {
        CourseStep.GOING_TO_PICKUP -> Triple("Vers récupération", Color(0xFF2196F3), Icons.Filled.Navigation)
        CourseStep.ARRIVED_PICKUP -> Triple("Récupération", PrimaryGold, Icons.Filled.LocationOn)
        CourseStep.GOING_TO_DELIVERY -> Triple("Vers livraison", Color(0xFF2196F3), Icons.Filled.Navigation)
        CourseStep.ARRIVED_DELIVERY -> Triple("Livraison", PrimaryGold, Icons.Filled.LocationOn)
        CourseStep.COMPLETED -> Triple("Terminée", SuccessGreen, Icons.Filled.CheckCircle)
        else -> Triple("En cours", Color.Gray, Icons.Filled.Schedule)
    }

    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = color,
            modifier = Modifier.size(16.dp)
        )
        Text(
            text = text,
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.SemiBold,
            color = color
        )
    }
}

@Composable
private fun ClientInfo(order: Commande) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = order.clientNom,
                style = MaterialTheme.typography.bodyLarge,
                fontWeight = FontWeight.SemiBold,
                color = Color.White
            )
            Text(
                text = order.clientTelephone,
                style = MaterialTheme.typography.bodyMedium,
                color = Color.White.copy(alpha = 0.7f)
            )
        }
        
        Text(
            text = "${order.prixTotal.toInt()} FCFA",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
            color = PrimaryGold
        )
    }
}

@Composable
private fun CurrentStepCard(
    step: CourseStep,
    order: Commande,
    courierLocation: LatLng?,
    onValidateStep: (CourseStep) -> Unit,
    onNavigationLaunched: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark.copy(alpha = 0.3f)
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Étape actuelle
            val (title, description, actionText, nextStep) = when (step) {
                CourseStep.GOING_TO_PICKUP -> {
                    // La navigation est gérée par NavigationScreen dans l'app - pas de redirection externe
                    Quadruple(
                        "Direction récupération",
                        "Rendez-vous à l'adresse de récupération",
                        "Je suis arrivé",
                        CourseStep.ARRIVED_PICKUP
                    )
                }
                CourseStep.ARRIVED_PICKUP -> Quadruple(
                    "Récupération du colis",
                    "Récupérez le colis auprès de l'expéditeur",
                    "Colis récupéré",
                    CourseStep.GOING_TO_DELIVERY
                )
                CourseStep.GOING_TO_DELIVERY -> {
                    // La navigation est gérée par NavigationScreen dans l'app - pas de redirection externe
                    Quadruple(
                        "Direction livraison",
                        "Rendez-vous chez le client pour la livraison",
                        "Je suis arrivé",
                        CourseStep.ARRIVED_DELIVERY
                    )
                }
                CourseStep.ARRIVED_DELIVERY -> Quadruple(
                    "Livraison du colis",
                    "Remettez le colis au destinataire",
                    "Colis livré",
                    CourseStep.COMPLETED
                )
                else -> Quadruple("", "", "", CourseStep.COMPLETED)
            }

            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Icône de l'étape
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(PrimaryGold),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = when (step) {
                            CourseStep.GOING_TO_PICKUP, CourseStep.GOING_TO_DELIVERY -> Icons.Filled.Navigation
                            CourseStep.ARRIVED_PICKUP -> Icons.Filled.Inventory
                            CourseStep.ARRIVED_DELIVERY -> Icons.Filled.LocalShipping
                            else -> Icons.Filled.CheckCircle
                        },
                        contentDescription = null,
                        tint = PrimaryDark,
                        modifier = Modifier.size(20.dp)
                    )
                }

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = title,
                        style = MaterialTheme.typography.titleSmall,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                    Text(
                        text = description,
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.8f)
                    )
                }
            }

            // Bouton d'action - seulement pour les étapes "arrivé"
            if (step == CourseStep.ARRIVED_PICKUP || step == CourseStep.ARRIVED_DELIVERY) {
                GradientButton(
                    text = actionText,
                    onClick = { onValidateStep(nextStep) },
                    modifier = Modifier.fillMaxWidth()
                )
            }

            // Indication de navigation automatique
            if (step == CourseStep.GOING_TO_PICKUP || step == CourseStep.GOING_TO_DELIVERY) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        Icons.Filled.Navigation,
                        contentDescription = null,
                        tint = Color(0xFF2196F3),
                        modifier = Modifier.size(16.dp)
                    )
                    Text(
                        text = "Navigation lancée automatiquement",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color(0xFF2196F3),
                        fontStyle = androidx.compose.ui.text.font.FontStyle.Italic
                    )
                }
            }
        }
    }
}

// Classe helper pour les quadruples
data class Quadruple<A, B, C, D>(val first: A, val second: B, val third: C, val fourth: D)

@Composable
private fun PendingOrdersSection(
    orders: List<Commande>,
    onAcceptOrder: (String) -> Unit,
    onRejectOrder: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    if (orders.isEmpty()) {
        EmptyPendingOrders(modifier = modifier)
        return
    }

    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = GlassBg),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Text(
                text = "Commandes en attente (${orders.size})",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = PrimaryGold
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            LazyColumn(
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(orders) { order ->
                    PendingOrderCard(
                        order = order,
                        onAccept = { onAcceptOrder(order.id) },
                        onReject = { onRejectOrder(order.id) }
                    )
                }
            }
        }
    }
}

@Composable
private fun EmptyPendingOrders(modifier: Modifier = Modifier) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(containerColor = GlassBg.copy(alpha = 0.5f)),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Icon(
                Icons.Filled.Inbox,
                contentDescription = null,
                tint = Color.Gray,
                modifier = Modifier.size(32.dp)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = "Aucune commande en attente",
                style = MaterialTheme.typography.bodyMedium,
                color = Color.Gray,
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
private fun PendingOrderCard(
    order: Commande,
    onAccept: () -> Unit,
    onReject: () -> Unit
) {
    Card(
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark.copy(alpha = 0.3f)
        ),
        shape = RoundedCornerShape(8.dp)
    ) {
        Column(
            modifier = Modifier.padding(12.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = order.clientNom,
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = Color.White
                )
                Text(
                    text = "${order.prixTotal.toInt()} FCFA",
                    style = MaterialTheme.typography.bodyMedium,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
            }
            
            Text(
                text = "${order.adresseEnlevement} → ${order.adresseLivraison}",
                style = MaterialTheme.typography.bodySmall,
                color = Color.White.copy(alpha = 0.7f),
                maxLines = 1
            )
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                OutlinedButton(
                    onClick = onReject,
                    modifier = Modifier.weight(1f),
                    colors = ButtonDefaults.outlinedButtonColors(
                        contentColor = Color.Red
                    )
                ) {
                    Text("Refuser", fontSize = 12.sp)
                }
                
                GradientButton(
                    text = "Accepter",
                    onClick = onAccept,
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@Composable
private fun NewOrderNotification(
    order: Commande,
    onAccept: () -> Unit,
    onReject: () -> Unit,
    modifier: Modifier = Modifier
) {
    var isVisible by remember { mutableStateOf(true) }
    
    AnimatedVisibility(
        visible = isVisible,
        enter = slideInVertically(initialOffsetY = { it }) + fadeIn(),
        exit = slideOutVertically(targetOffsetY = { it }) + fadeOut(),
        modifier = modifier
    ) {
        Card(
            colors = CardDefaults.cardColors(
                containerColor = PrimaryDark
            ),
            shape = RoundedCornerShape(16.dp),
            border = BorderStroke(2.dp, PrimaryGold)
        ) {
            Column(
                modifier = Modifier.padding(16.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        Icons.Filled.Notifications,
                        contentDescription = null,
                        tint = PrimaryGold,
                        modifier = Modifier.size(24.dp)
                    )
                    Text(
                        text = "Nouvelle commande !",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                }
                
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(
                        text = "Client: ${order.clientNom}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.White
                    )
                    Text(
                        text = "Prix: ${order.prixTotal.toInt()} FCFA",
                        style = MaterialTheme.typography.bodyMedium,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                    Text(
                        text = "${order.adresseEnlevement} → ${order.adresseLivraison}",
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.White.copy(alpha = 0.7f),
                        maxLines = 2
                    )
                }
                
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Button(
                        onClick = {
                            isVisible = false
                            onReject()
                        },
                        modifier = Modifier.weight(1f),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Color.Red.copy(alpha = 0.8f)
                        )
                    ) {
                        Text("Refuser")
                    }
                    
                    GradientButton(
                        text = "Accepter",
                        onClick = {
                            isVisible = false
                            onAccept()
                        },
                        modifier = Modifier.weight(1f)
                    )
                }
            }
        }
    }
}