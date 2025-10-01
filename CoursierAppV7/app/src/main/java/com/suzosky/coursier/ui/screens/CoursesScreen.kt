package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.google.android.gms.maps.model.LatLng
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.ui.components.SimpleMapView
import com.suzosky.coursier.ui.components.MapNavigationCard
import com.suzosky.coursier.ui.components.launchTurnByTurn
import com.suzosky.coursier.ui.components.DeliveryTimeline
import com.suzosky.coursier.ui.components.TimelineBanner
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.network.ApiService
import androidx.hilt.navigation.compose.hiltViewModel
import com.suzosky.coursier.viewmodel.MapViewModel

enum class DeliveryStep {
    PENDING,              // En attente d'acceptation
    ACCEPTED,             // Accepté, en route vers récupération
    EN_ROUTE_PICKUP,      // En route vers récupération
    PICKUP_ARRIVED,       // Arrivé sur lieu de récupération
    PICKED_UP,            // Colis récupéré, en route vers livraison
    EN_ROUTE_DELIVERY,    // En route vers livraison
    DELIVERY_ARRIVED,     // Arrivé sur lieu de livraison
    DELIVERED,            // Livré
    CASH_CONFIRMED        // Cash récupéré (si paiement espèces)
}

@Composable
fun CoursesScreen(
    currentOrder: Commande?,
    deliveryStep: DeliveryStep,
    pendingOrdersCount: Int = 0,
    onAcceptOrder: () -> Unit,
    onRejectOrder: () -> Unit,
    onPickupValidation: () -> Unit,
    onDeliveryValidation: () -> Unit,
    onStepAction: (DeliveryStep) -> Unit = {},
    banner: TimelineBanner? = null,
    modifier: Modifier = Modifier
) {
    // Localisation en temps réel du coursier
    val mapViewModel: MapViewModel = hiltViewModel()
    val mapUi by mapViewModel.uiState.collectAsState()
    LaunchedEffect(Unit) {
        mapViewModel.startLocationTracking()
    }

    // Aucun OTP: la validation de livraison est déléguée au parent

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        // Header avec nombre de courses en attente
        Text(
            text = "Mes Courses",
            style = MaterialTheme.typography.headlineMedium,
            fontWeight = FontWeight.Bold,
            color = PrimaryGold
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Card du nombre de courses en attente
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(containerColor = GlassBg),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
            shape = RoundedCornerShape(16.dp)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(20.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Column {
                    Text(
                        text = "Courses en attente",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                    Text(
                        text = if (pendingOrdersCount > 0) {
                            "$pendingOrdersCount nouvelle${if (pendingOrdersCount > 1) "s" else ""} commande${if (pendingOrdersCount > 1) "s" else ""}"
                        } else {
                            "Aucune commande en attente"
                        },
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.White.copy(alpha = 0.8f)
                    )
                }
                
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = if (pendingOrdersCount > 0) SuccessGreen.copy(alpha = 0.2f) else PrimaryGold.copy(alpha = 0.2f)
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Box(
                        modifier = Modifier.padding(16.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            text = pendingOrdersCount.toString(),
                            style = MaterialTheme.typography.headlineLarge,
                            fontWeight = FontWeight.Bold,
                            color = if (pendingOrdersCount > 0) SuccessGreen else PrimaryGold
                        )
                    }
                }
            }
        }
        
        Spacer(modifier = Modifier.height(20.dp))
        
        // Carte Google Maps
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .weight(1f),
            shape = RoundedCornerShape(16.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 8.dp)
        ) {
            Box(
                modifier = Modifier.fillMaxSize()
            ) {
                // Position par défaut : Abidjan
                val defaultPosition = LatLng(5.3596966, -4.0082592)
                
                SimpleMapView(
                    pickupLocation = null,
                    deliveryLocation = null,
                    modifier = Modifier.fillMaxSize()
                )
                
                // Overlay d'information
                Card(
                    modifier = Modifier
                        .align(Alignment.TopStart)
                        .padding(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = PrimaryDark.copy(alpha = 0.9f)
                    ),
                    shape = RoundedCornerShape(8.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            Icons.Filled.MyLocation,
                            contentDescription = null,
                            tint = PrimaryGold,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Position actuelle",
                            style = MaterialTheme.typography.bodySmall,
                            color = Color.White,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
                
                // Message d'attente si aucune commande
                if (currentOrder == null && pendingOrdersCount == 0) {
                    Card(
                        modifier = Modifier
                            .align(Alignment.Center)
                            .padding(20.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = PrimaryDark.copy(alpha = 0.95f)
                        ),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Column(
                            modifier = Modifier.padding(20.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Icon(
                                Icons.Filled.Schedule,
                                contentDescription = null,
                                tint = PrimaryGold,
                                modifier = Modifier.size(32.dp)
                            )
                            Spacer(modifier = Modifier.height(8.dp))
                            Text(
                                text = "En attente de commandes...",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold,
                                color = PrimaryGold
                            )
                            Text(
                                text = "Les notifications apparaîtront automatiquement",
                                style = MaterialTheme.typography.bodySmall,
                                color = Color.White.copy(alpha = 0.7f)
                            )
                        }
                    }
                }
            }
        }
        
        // Si une commande est active, afficher les détails
        if (currentOrder != null) {
            Spacer(modifier = Modifier.height(16.dp))
            
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = GlassBg),
                elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        val isIncoming = currentOrder?.statut == "nouvelle" || currentOrder?.statut == "attente"
                        Text(
                            text = if (isIncoming) "Course entrante" else "Course active",
                            style = MaterialTheme.typography.titleLarge,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryGold
                        )
                        
                        Card(
                            colors = CardDefaults.cardColors(
                                containerColor = SuccessGreen.copy(alpha = 0.2f)
                            ),
                            shape = RoundedCornerShape(8.dp)
                        ) {
                            Text(
                                text = when (deliveryStep) {
                                    DeliveryStep.PENDING -> "Nouvelle"
                                    DeliveryStep.ACCEPTED -> "Acceptée"
                                    DeliveryStep.EN_ROUTE_PICKUP -> "En route (pickup)"
                                    DeliveryStep.PICKUP_ARRIVED -> "Sur place"
                                    DeliveryStep.PICKED_UP -> "Récupéré"
                                    DeliveryStep.EN_ROUTE_DELIVERY -> "En route (livraison)"
                                    DeliveryStep.DELIVERY_ARRIVED -> "Livraison"
                                    DeliveryStep.DELIVERED -> "Livrée"
                                    DeliveryStep.CASH_CONFIRMED -> "Cash confirmé"
                                },
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                style = MaterialTheme.typography.labelMedium,
                                color = SuccessGreen,
                                fontWeight = FontWeight.Bold
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    // Informations client
                    Text(
                        text = "📱 Client: ${currentOrder.clientNom}",
                        style = MaterialTheme.typography.bodyLarge,
                        color = Color.White,
                        fontWeight = FontWeight.Medium
                    )
                    if (currentOrder.clientTelephone.isNotBlank()) {
                        Text(
                            text = "    Tel: ${currentOrder.clientTelephone}",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Color.White.copy(alpha = 0.9f)
                        )
                    }
                    
                    // Informations destinataire
                    if (currentOrder.telephoneDestinataire.isNotBlank()) {
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            text = "📞 Destinataire:",
                            style = MaterialTheme.typography.bodyLarge,
                            color = Color.White,
                            fontWeight = FontWeight.Medium
                        )
                        Text(
                            text = "    Tel: ${currentOrder.telephoneDestinataire}",
                            style = MaterialTheme.typography.bodyMedium,
                            color = Color.White.copy(alpha = 0.9f)
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(12.dp))
                    Text(
                        text = "💰 Prix: ${currentOrder.prixTotal.toInt()} FCFA",
                        style = MaterialTheme.typography.bodyLarge,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Boutons d'action selon l'étape
                    when (deliveryStep) {
                        DeliveryStep.PENDING -> {
                            // Montrer Accepter/Refuser uniquement pour les nouvelles commandes
                            if (currentOrder?.statut == "nouvelle" || currentOrder?.statut == "attente") {
                                Row(
                                    modifier = Modifier.fillMaxWidth(),
                                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                                ) {
                                    Button(
                                        onClick = onRejectOrder,
                                        modifier = Modifier.weight(1f),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = Color.Red.copy(alpha = 0.8f)
                                        )
                                    ) {
                                        Text("Refuser")
                                    }
                                    Button(
                                        onClick = onAcceptOrder,
                                        modifier = Modifier.weight(1f),
                                        colors = ButtonDefaults.buttonColors(
                                            containerColor = SuccessGreen
                                        )
                                    ) {
                                        Text("Accepter")
                                    }
                                }
                            }
                        }
                        DeliveryStep.PICKUP_ARRIVED -> {
                            Button(
                                onClick = onPickupValidation,
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = PrimaryGold
                                )
                            ) {
                                Text("Valider récupération", color = PrimaryDark)
                            }
                        }
                        DeliveryStep.EN_ROUTE_PICKUP -> {
                            // Pas d'action spécifique ici
                        }
                        DeliveryStep.DELIVERY_ARRIVED -> {
                            Button(
                                onClick = onDeliveryValidation,
                                modifier = Modifier.fillMaxWidth(),
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = SuccessGreen
                                )
                            ) {
                                Text("Valider livraison")
                            }
                        }
                        DeliveryStep.EN_ROUTE_DELIVERY -> {
                            // Pas d'action spécifique ici
                        }
                        DeliveryStep.CASH_CONFIRMED -> {
                            // Pas d'action ici (terminée)
                        }
                        else -> {
                            // Autres étapes : pas de bouton d'action spécifique
                        }
                    }
                }
            }
        }

        // Timeline: uniquement après acceptation (ne pas occuper tout l'espace)
        if (currentOrder != null && deliveryStep != DeliveryStep.PENDING) {
            Spacer(modifier = Modifier.height(12.dp))
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(containerColor = GlassBg),
                elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text(
                        text = "Progression de la livraison",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                    Spacer(modifier = Modifier.height(8.dp))
                    DeliveryTimeline(
                        currentStep = deliveryStep,
                        paymentMethod = currentOrder.methodePaiement,
                        onStepAction = onStepAction,
                        modifier = Modifier.fillMaxWidth(),
                        banner = banner
                    )

                    // Carte + Navigation Google Maps
                    Spacer(modifier = Modifier.height(12.dp))
                    val pickup = currentOrder.coordonneesEnlevement?.let { LatLng(it.latitude, it.longitude) }
                    val dropoff = currentOrder.coordonneesLivraison?.let { LatLng(it.latitude, it.longitude) }
                    // Choisir la destination selon l'étape
                    val destination = when (deliveryStep) {
                        DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP, DeliveryStep.PICKUP_ARRIVED -> pickup
                        DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY, DeliveryStep.DELIVERY_ARRIVED, DeliveryStep.DELIVERED, DeliveryStep.CASH_CONFIRMED -> dropoff
                        else -> null
                    }

                    var path by remember { mutableStateOf<List<LatLng>>(emptyList()) }
                    // Origin: privilégie la position courante si disponible, sinon fallback pickup
                    val origin = mapUi.currentLocation ?: pickup

                    LaunchedEffect(origin, destination) {
                        path = emptyList()
                        val o = origin
                        val d = destination
                        if (o != null && d != null) {
                            ApiService.getDirections(o, d) { points, _ ->
                                if (points != null) path = points
                            }
                        }
                    }

                    val context = androidx.compose.ui.platform.LocalContext.current
                    // Bandeau d'état localisation si indisponible
                    if (mapUi.currentLocation == null) {
                        Card(
                            colors = CardDefaults.cardColors(containerColor = PrimaryDark.copy(alpha = 0.9f)),
                            shape = RoundedCornerShape(12.dp)
                        ) {
                            Row(
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .padding(12.dp),
                                horizontalArrangement = Arrangement.SpaceBetween,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Column(Modifier.weight(1f)) {
                                    Text(
                                        text = "Localisation inactive",
                                        style = MaterialTheme.typography.bodyMedium,
                                        color = PrimaryGold,
                                        fontWeight = FontWeight.Bold
                                    )
                                    Text(
                                        text = "Autorisez la localisation pour un guidage optimal",
                                        style = MaterialTheme.typography.bodySmall,
                                        color = Color.White.copy(alpha = 0.8f)
                                    )
                                }
                                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                    TextButton(onClick = { mapViewModel.getCurrentLocation() }) {
                                        Text("Réessayer", color = PrimaryGold)
                                    }
                                    TextButton(onClick = {
                                        // Ouvrir les paramètres de localisation du système
                                        val intent = android.content.Intent(android.provider.Settings.ACTION_LOCATION_SOURCE_SETTINGS)
                                        context.startActivity(intent)
                                    }) {
                                        Text("Paramètres", color = PrimaryGold)
                                    }
                                }
                            }
                        }
                        Spacer(Modifier.height(8.dp))
                    }

                    MapNavigationCard(
                        courierLocation = mapUi.currentLocation,
                        pickup = pickup,
                        dropoff = destination,
                        path = path,
                        onStartNavigation = { dest -> launchTurnByTurn(context, dest, label = "Destination") }
                    )
                }
            }
        }
    }

    // Pas d'OTP: aucun dialogue local ici
}
