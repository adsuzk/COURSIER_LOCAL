package com.suzosky.coursier.ui.screens

import android.location.Location
import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.android.gms.maps.CameraUpdateFactory
import com.google.android.gms.maps.model.*
import com.google.maps.android.compose.*
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.ui.theme.*
import kotlinx.coroutines.delay

/**
 * Écran de navigation intégré avec carte plein écran
 * et UI de progression moderne et pratique pour le coursier
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NavigationScreen(
    currentOrder: Commande,
    deliveryStep: DeliveryStep,
    courierLocation: LatLng?,
    onBack: () -> Unit,
    onPickupValidation: () -> Unit,
    onDeliveryValidation: () -> Unit,
    onStepAction: (DeliveryStep) -> Unit,
    modifier: Modifier = Modifier
) {
    var showBottomSheet by remember { mutableStateOf(false) }
    val cameraPositionState = rememberCameraPositionState()
    
    // Conversion des coordonnées
    val pickupLatLng = currentOrder.coordonneesEnlevement?.let {
        LatLng(it.latitude, it.longitude)
    }
    val deliveryLatLng = currentOrder.coordonneesLivraison?.let {
        LatLng(it.latitude, it.longitude)
    }
    
    // Déterminer la destination actuelle selon l'étape
    val currentDestination = when (deliveryStep) {
        DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP -> pickupLatLng
        DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY, DeliveryStep.DELIVERY_ARRIVED -> deliveryLatLng
        else -> null
    }
    
    // Calcul de la distance et ETA
    val distanceInfo = remember(courierLocation, currentDestination) {
        calculateDistanceAndETA(courierLocation, currentDestination)
    }
    
    // Centrer la caméra sur la position actuelle
    LaunchedEffect(courierLocation, currentDestination) {
        android.util.Log.d("NavigationScreen", "📍 LaunchedEffect MAPS - courierLocation=$courierLocation, currentDestination=$currentDestination")
        courierLocation?.let { courier ->
            currentDestination?.let { dest ->
                android.util.Log.d("NavigationScreen", "✅ Positions valides - Création bounds")
                // Créer un bounds qui inclut les deux points
                val boundsBuilder = LatLngBounds.builder()
                boundsBuilder.include(courier)
                boundsBuilder.include(dest)
                
                try {
                    val bounds = boundsBuilder.build()
                    val padding = 150 // pixels
                    android.util.Log.d("NavigationScreen", "🗺️ Animate bounds: SW=${bounds.southwest}, NE=${bounds.northeast}")
                    cameraPositionState.animate(
                        CameraUpdateFactory.newLatLngBounds(bounds, padding)
                    )
                } catch (e: Exception) {
                    android.util.Log.e("NavigationScreen", "❌ Erreur bounds: ${e.message}")
                    // Fallback : centrer sur la destination
                    cameraPositionState.animate(
                        CameraUpdateFactory.newLatLngZoom(dest, 14f)
                    )
                }
            } ?: run {
                android.util.Log.w("NavigationScreen", "⚠️ Pas de destination - Centrer sur coursier")
                // Pas de destination : centrer sur le coursier
                cameraPositionState.animate(
                    CameraUpdateFactory.newLatLngZoom(courier, 15f)
                )
            }
        } ?: run {
            android.util.Log.w("NavigationScreen", "❌ Pas de courierLocation")
        }
    }
    
    Box(modifier = modifier.fillMaxSize()) {
        // 1. CARTE PLEIN ÉCRAN
        GoogleMap(
            modifier = Modifier.fillMaxSize(),
            cameraPositionState = cameraPositionState,
            properties = MapProperties(
                mapType = MapType.NORMAL,
                isMyLocationEnabled = false
            ),
            uiSettings = MapUiSettings(
                zoomControlsEnabled = true,
                mapToolbarEnabled = false,
                myLocationButtonEnabled = false,
                compassEnabled = true
            )
        ) {
            // Marqueur du coursier
            courierLocation?.let {
                Marker(
                    state = MarkerState(position = it),
                    title = "Vous êtes ici",
                    icon = BitmapDescriptorFactory.defaultMarker(BitmapDescriptorFactory.HUE_AZURE)
                )
            }
            
            // Marqueur de récupération (rouge)
            pickupLatLng?.let {
                Marker(
                    state = MarkerState(position = it),
                    title = "📦 Récupération",
                    snippet = currentOrder.adresseEnlevement,
                    icon = BitmapDescriptorFactory.defaultMarker(BitmapDescriptorFactory.HUE_RED)
                )
            }
            
            // Marqueur de livraison (vert)
            deliveryLatLng?.let {
                Marker(
                    state = MarkerState(position = it),
                    title = "🎯 Livraison",
                    snippet = currentOrder.adresseLivraison,
                    icon = BitmapDescriptorFactory.defaultMarker(BitmapDescriptorFactory.HUE_GREEN)
                )
            }
            
            // Ligne entre coursier et destination
            if (courierLocation != null && currentDestination != null) {
                Polyline(
                    points = listOf(courierLocation, currentDestination),
                    color = PrimaryGold,
                    width = 8f,
                    pattern = listOf(Dot(), Gap(10f))
                )
            }
        }
        
        // 2. HEADER COMPACT EN HAUT
        TopAppBar(
            title = {
                Column {
                    Text(
                        text = when (deliveryStep) {
                            DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP -> "🚴 Vers le point de récupération"
                            DeliveryStep.PICKUP_ARRIVED -> "📦 Sur place - Récupération"
                            DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY -> "🚴 Vers le point de livraison"
                            DeliveryStep.DELIVERY_ARRIVED -> "🎯 Sur place - Livraison"
                            else -> "Navigation"
                        },
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    Text(
                        text = "Commande #${currentOrder.id}",
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.7f)
                    )
                }
            },
            navigationIcon = {
                IconButton(onClick = onBack) {
                    Icon(Icons.Filled.ArrowBack, contentDescription = "Retour", tint = Color.White)
                }
            },
            actions = {
                IconButton(onClick = { showBottomSheet = true }) {
                    Icon(Icons.Filled.Info, contentDescription = "Détails", tint = Color.White)
                }
            },
            colors = TopAppBarDefaults.topAppBarColors(
                containerColor = PrimaryDark.copy(alpha = 0.9f)
            )
        )
        
        // 3. PANNEAU INFOS DISTANCE/ETA (flottant en haut)
        AnimatedVisibility(
            visible = distanceInfo != null,
            enter = slideInVertically() + fadeIn(),
            exit = slideOutVertically() + fadeOut(),
            modifier = Modifier
                .align(Alignment.TopCenter)
                .padding(top = 80.dp)
        ) {
            Card(
                modifier = Modifier
                    .padding(horizontal = 16.dp)
                    .shadow(8.dp, RoundedCornerShape(24.dp)),
                colors = CardDefaults.cardColors(containerColor = Color.White),
                shape = RoundedCornerShape(24.dp)
            ) {
                Row(
                    modifier = Modifier
                        .padding(horizontal = 24.dp, vertical = 12.dp),
                    horizontalArrangement = Arrangement.spacedBy(32.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    // Distance
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(
                            Icons.Filled.DirectionsWalk,
                            contentDescription = null,
                            tint = PrimaryGold,
                            modifier = Modifier.size(24.dp)
                        )
                        Text(
                            text = distanceInfo?.distance ?: "--",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryDark
                        )
                        Text(
                            text = "Distance",
                            fontSize = 11.sp,
                            color = Color.Gray
                        )
                    }
                    
                    Divider(
                        modifier = Modifier
                            .height(40.dp)
                            .width(1.dp),
                        color = Color.LightGray
                    )
                    
                    // ETA
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Icon(
                            Icons.Filled.Schedule,
                            contentDescription = null,
                            tint = SuccessGreen,
                            modifier = Modifier.size(24.dp)
                        )
                        Text(
                            text = distanceInfo?.eta ?: "--",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryDark
                        )
                        Text(
                            text = "Arrivée",
                            fontSize = 11.sp,
                            color = Color.Gray
                        )
                    }
                }
            }
        }
        
        // 4. PANNEAU D'ACTION EN BAS (moderne et pratique)
        ProgressActionPanel(
            currentOrder = currentOrder,
            deliveryStep = deliveryStep,
            onPickupValidation = onPickupValidation,
            onDeliveryValidation = onDeliveryValidation,
            modifier = Modifier
                .align(Alignment.BottomCenter)
                .padding(16.dp)
        )
        
        // 5. BOTTOM SHEET DÉTAILS
        if (showBottomSheet) {
            ModalBottomSheet(
                onDismissRequest = { showBottomSheet = false },
                containerColor = PrimaryDark
            ) {
                OrderDetailsSheet(
                    currentOrder = currentOrder,
                    deliveryStep = deliveryStep,
                    modifier = Modifier.padding(16.dp)
                )
            }
        }
    }
}

/**
 * Panneau d'action moderne avec progression visuelle
 */
@Composable
fun ProgressActionPanel(
    currentOrder: Commande,
    deliveryStep: DeliveryStep,
    onPickupValidation: () -> Unit,
    onDeliveryValidation: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .shadow(16.dp, RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)
    ) {
        Column(
            modifier = Modifier
                .padding(24.dp)
                .fillMaxWidth()
        ) {
            // Indicateur de progression visuelle
            StepProgressIndicator(
                currentStep = deliveryStep,
                modifier = Modifier.fillMaxWidth()
            )
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Titre de l'étape actuelle
            Text(
                text = getStepTitle(deliveryStep),
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = PrimaryDark,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Instructions
            Text(
                text = getStepInstructions(deliveryStep),
                fontSize = 14.sp,
                color = Color.Gray,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
            )
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Bouton d'action principal
            when (deliveryStep) {
                DeliveryStep.PICKUP_ARRIVED -> {
                    Button(
                        onClick = onPickupValidation,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = PrimaryGold),
                        shape = RoundedCornerShape(16.dp)
                    ) {
                        Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = PrimaryDark)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            "✅ J'ai récupéré le colis",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = PrimaryDark
                        )
                    }
                }
                DeliveryStep.DELIVERY_ARRIVED -> {
                    Button(
                        onClick = onDeliveryValidation,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = SuccessGreen),
                        shape = RoundedCornerShape(16.dp)
                    ) {
                        Icon(Icons.Filled.CheckCircle, contentDescription = null)
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            "✅ Livraison effectuée",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold
                        )
                    }
                }
                else -> {
                    // État en cours de route : pas de bouton
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        colors = CardDefaults.cardColors(containerColor = PrimaryGold.copy(alpha = 0.1f)),
                        shape = RoundedCornerShape(16.dp)
                    ) {
                        Row(
                            modifier = Modifier.padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.Center
                        ) {
                            CircularProgressIndicator(
                                modifier = Modifier.size(20.dp),
                                color = PrimaryGold,
                                strokeWidth = 2.dp
                            )
                            Spacer(modifier = Modifier.width(12.dp))
                            Text(
                                text = "En route...",
                                fontSize = 14.sp,
                                color = PrimaryDark,
                                fontWeight = FontWeight.Medium
                            )
                        }
                    }
                }
            }
            
            // Info contact
            Spacer(modifier = Modifier.height(16.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                if (deliveryStep in listOf(DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP, DeliveryStep.PICKUP_ARRIVED)) {
                    ContactButton(
                        icon = Icons.Filled.Phone,
                        label = "Client",
                        phone = currentOrder.clientTelephone
                    )
                }
                if (deliveryStep in listOf(DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY, DeliveryStep.DELIVERY_ARRIVED) && 
                    currentOrder.telephoneDestinataire.isNotEmpty()) {
                    ContactButton(
                        icon = Icons.Filled.Phone,
                        label = "Destinataire",
                        phone = currentOrder.telephoneDestinataire
                    )
                }
            }
        }
    }
}

/**
 * Indicateur de progression visuel moderne (4 étapes)
 */
@Composable
fun StepProgressIndicator(
    currentStep: DeliveryStep,
    modifier: Modifier = Modifier
) {
    val steps = listOf(
        StepInfo(DeliveryStep.ACCEPTED, "Accepté", Icons.Filled.CheckCircle),
        StepInfo(DeliveryStep.EN_ROUTE_PICKUP, "En route", Icons.Filled.DirectionsCar),
        StepInfo(DeliveryStep.PICKED_UP, "Récupéré", Icons.Filled.Inventory),
        StepInfo(DeliveryStep.DELIVERY_ARRIVED, "Livraison", Icons.Filled.Home)
    )
    
    val currentStepIndex = steps.indexOfFirst { it.step == currentStep }.takeIf { it >= 0 } ?: 0
    
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        steps.forEachIndexed { index, stepInfo ->
            Column(
                horizontalAlignment = Alignment.CenterHorizontally,
                modifier = Modifier.weight(1f)
            ) {
                // Icône
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .background(
                            color = if (index <= currentStepIndex) PrimaryGold else Color.LightGray.copy(alpha = 0.3f),
                            shape = CircleShape
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        stepInfo.icon,
                        contentDescription = null,
                        tint = if (index <= currentStepIndex) Color.White else Color.Gray,
                        modifier = Modifier.size(20.dp)
                    )
                }
                
                Spacer(modifier = Modifier.height(4.dp))
                
                // Label
                Text(
                    text = stepInfo.label,
                    fontSize = 10.sp,
                    color = if (index <= currentStepIndex) PrimaryDark else Color.Gray,
                    fontWeight = if (index == currentStepIndex) FontWeight.Bold else FontWeight.Normal,
                    textAlign = TextAlign.Center
                )
            }
            
            // Ligne de connexion
            if (index < steps.size - 1) {
                Box(
                    modifier = Modifier
                        .weight(0.5f)
                        .height(2.dp)
                        .background(
                            color = if (index < currentStepIndex) PrimaryGold else Color.LightGray.copy(alpha = 0.3f)
                        )
                )
            }
        }
    }
}

data class StepInfo(
    val step: DeliveryStep,
    val label: String,
    val icon: ImageVector
)

/**
 * Bouton de contact rapide
 */
@Composable
fun ContactButton(
    icon: ImageVector,
    label: String,
    phone: String,
    modifier: Modifier = Modifier
) {
    val context = androidx.compose.ui.platform.LocalContext.current
    
    OutlinedButton(
        onClick = {
            val intent = android.content.Intent(android.content.Intent.ACTION_DIAL).apply {
                data = android.net.Uri.parse("tel:$phone")
            }
            context.startActivity(intent)
        },
        modifier = modifier,
        colors = ButtonDefaults.outlinedButtonColors(contentColor = PrimaryDark),
        shape = RoundedCornerShape(12.dp)
    ) {
        Icon(icon, contentDescription = null, modifier = Modifier.size(16.dp))
        Spacer(modifier = Modifier.width(4.dp))
        Column {
            Text(label, fontSize = 10.sp)
            Text(phone, fontSize = 12.sp, fontWeight = FontWeight.Bold)
        }
    }
}

/**
 * Sheet des détails de commande
 */
@Composable
fun OrderDetailsSheet(
    currentOrder: Commande,
    deliveryStep: DeliveryStep,
    modifier: Modifier = Modifier
) {
    Column(modifier = modifier) {
        Text(
            "Détails de la commande #${currentOrder.id}",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = PrimaryGold
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        DetailRow("👤 Client", currentOrder.clientNom)
        DetailRow("📱 Téléphone client", currentOrder.clientTelephone)
        if (currentOrder.telephoneDestinataire.isNotEmpty()) {
            DetailRow("📱 Téléphone destinataire", currentOrder.telephoneDestinataire)
        }
        DetailRow("📦 Récupération", currentOrder.adresseEnlevement)
        DetailRow("🎯 Livraison", currentOrder.adresseLivraison)
        DetailRow("💰 Prix", "${currentOrder.prixLivraison.toInt()} FCFA")
        DetailRow("💳 Paiement", currentOrder.methodePaiement)
        if (currentOrder.description.isNotEmpty()) {
            DetailRow("📝 Description", currentOrder.description)
        }
        
        Spacer(modifier = Modifier.height(24.dp))
    }
}

@Composable
fun DetailRow(label: String, value: String) {
    Column(modifier = Modifier.padding(vertical = 8.dp)) {
        Text(label, fontSize = 12.sp, color = Color.Gray)
        Text(value, fontSize = 14.sp, color = Color.White, fontWeight = FontWeight.Medium)
    }
}

/**
 * Helpers
 */
fun getStepTitle(step: DeliveryStep): String = when (step) {
    DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP -> "En route vers récupération"
    DeliveryStep.PICKUP_ARRIVED -> "Sur le point de récupération"
    DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY -> "En route vers livraison"
    DeliveryStep.DELIVERY_ARRIVED -> "Sur le point de livraison"
    else -> "Navigation"
}

fun getStepInstructions(step: DeliveryStep): String = when (step) {
    DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP -> "Suivez la carte pour atteindre le point de récupération"
    DeliveryStep.PICKUP_ARRIVED -> "Récupérez le colis auprès du client"
    DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY -> "Suivez la carte pour atteindre le point de livraison"
    DeliveryStep.DELIVERY_ARRIVED -> "Remettez le colis au destinataire"
    else -> ""
}

data class DistanceInfo(val distance: String, val eta: String)

fun calculateDistanceAndETA(from: LatLng?, to: LatLng?): DistanceInfo? {
    if (from == null || to == null) return null
    
    val results = FloatArray(1)
    Location.distanceBetween(
        from.latitude, from.longitude,
        to.latitude, to.longitude,
        results
    )
    
    val distanceMeters = results[0]
    val distanceKm = distanceMeters / 1000
    val distanceText = if (distanceKm < 1) {
        "${distanceMeters.toInt()} m"
    } else {
        "%.1f km".format(distanceKm)
    }
    
    // ETA estimation : 20 km/h moyenne en ville
    val hours = distanceKm / 20
    val minutes = (hours * 60).toInt()
    val etaText = when {
        minutes < 1 -> "< 1 min"
        minutes < 60 -> "$minutes min"
        else -> "${minutes / 60}h ${minutes % 60}min"
    }
    
    return DistanceInfo(distanceText, etaText)
}
