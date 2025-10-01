package com.suzosky.coursier.ui.screens

import android.location.Location
import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
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
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.android.gms.maps.CameraUpdateFactory
import com.google.android.gms.maps.model.*
import com.google.maps.android.compose.*
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.ui.theme.*

/**
 * Écran Mes Courses UNIFIÉ - Navigation + Actions + Infos
 * Tout intégré dans un seul écran, pas de modal
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun UnifiedCoursesScreen(
    currentOrder: Commande?,
    deliveryStep: DeliveryStep,
    pendingOrdersCount: Int,
    courierLocation: LatLng?,
    onAcceptOrder: () -> Unit,
    onRejectOrder: () -> Unit,
    onStartDelivery: () -> Unit = {},
    onPickupPackage: () -> Unit = {},
    onMarkDelivered: () -> Unit = {},
    onPickupValidation: () -> Unit,
    onDeliveryValidation: () -> Unit,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val cameraPositionState = rememberCameraPositionState()
    var isVoiceGuidanceEnabled by remember { mutableStateOf(false) }
    
    // Conversion des coordonnées
    val pickupLatLng = currentOrder?.coordonneesEnlevement?.let {
        LatLng(it.latitude, it.longitude)
    }
    val deliveryLatLng = currentOrder?.coordonneesLivraison?.let {
        LatLng(it.latitude, it.longitude)
    }
    
    // Destination actuelle selon l'étape
    val currentDestination = when (deliveryStep) {
        DeliveryStep.PENDING -> null
        DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP, DeliveryStep.PICKUP_ARRIVED -> pickupLatLng
        DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY, DeliveryStep.DELIVERY_ARRIVED -> deliveryLatLng
        else -> null
    }
    
    // Calcul distance et ETA
    val distanceInfo = remember(courierLocation, currentDestination) {
        calculateDistanceAndETA(courierLocation, currentDestination)
    }
    
    // Centrer caméra
    LaunchedEffect(courierLocation, currentDestination, deliveryStep) {
        if (deliveryStep != DeliveryStep.PENDING && courierLocation != null && currentDestination != null) {
            val boundsBuilder = LatLngBounds.builder()
            boundsBuilder.include(courierLocation)
            boundsBuilder.include(currentDestination)
            
            try {
                val bounds = boundsBuilder.build()
                cameraPositionState.animate(
                    CameraUpdateFactory.newLatLngBounds(bounds, 200)
                )
            } catch (e: Exception) {
                cameraPositionState.animate(
                    CameraUpdateFactory.newLatLngZoom(currentDestination, 14f)
                )
            }
        }
    }
    
    Box(modifier = modifier.fillMaxSize()) {
        // FOND : Carte ou état vide
        if (currentOrder != null && deliveryStep != DeliveryStep.PENDING) {
            // CARTE PLEIN ÉCRAN
            GoogleMap(
                modifier = Modifier.fillMaxSize(),
                cameraPositionState = cameraPositionState,
                properties = MapProperties(mapType = MapType.NORMAL),
                uiSettings = MapUiSettings(
                    zoomControlsEnabled = false,
                    mapToolbarEnabled = false,
                    compassEnabled = true
                )
            ) {
                // Marqueur coursier
                courierLocation?.let {
                    Marker(
                        state = MarkerState(position = it),
                        title = "Vous êtes ici",
                        icon = BitmapDescriptorFactory.defaultMarker(BitmapDescriptorFactory.HUE_AZURE)
                    )
                }
                
                // Marqueur pickup
                pickupLatLng?.let {
                    Marker(
                        state = MarkerState(position = it),
                        title = "📦 Récupération",
                        snippet = currentOrder.adresseEnlevement,
                        icon = BitmapDescriptorFactory.defaultMarker(BitmapDescriptorFactory.HUE_RED)
                    )
                }
                
                // Marqueur delivery
                deliveryLatLng?.let {
                    Marker(
                        state = MarkerState(position = it),
                        title = "🎯 Livraison",
                        snippet = currentOrder.adresseLivraison,
                        icon = BitmapDescriptorFactory.defaultMarker(BitmapDescriptorFactory.HUE_GREEN)
                    )
                }
                
                // Ligne vers destination
                if (courierLocation != null && currentDestination != null) {
                    Polyline(
                        points = listOf(courierLocation, currentDestination),
                        color = PrimaryGold,
                        width = 8f,
                        pattern = listOf(Dot(), Gap(10f))
                    )
                }
            }
        } else {
            // ÉTAT VIDE
            EmptyCoursesState(
                pendingOrdersCount = pendingOrdersCount,
                modifier = Modifier.fillMaxSize()
            )
        }
        
        // OVERLAY : Panneau d'infos en haut
        if (currentOrder != null && deliveryStep != DeliveryStep.PENDING) {
            CourseInfoPanel(
                currentOrder = currentOrder,
                deliveryStep = deliveryStep,
                distanceInfo = distanceInfo,
                modifier = Modifier
                    .align(Alignment.TopCenter)
                    .padding(16.dp)
            )
        }
        
        // OVERLAY : Bouton guidage vocal (si en route)
        // Note: Le guidage vocal est géré AUTOMATIQUEMENT par le NavigationScreen
        // Ce bouton sert juste à activer/désactiver la fonctionnalité
        if (currentOrder != null && deliveryStep in listOf(
            DeliveryStep.ACCEPTED,
            DeliveryStep.EN_ROUTE_PICKUP,
            DeliveryStep.EN_ROUTE_DELIVERY
        )) {
            VoiceGuidanceButton(
                isEnabled = isVoiceGuidanceEnabled,
                onToggle = { enabled ->
                    isVoiceGuidanceEnabled = enabled
                    // Le guidage vocal est maintenant géré par NavigationScreen
                    // qui utilise l'API Text-to-Speech Android pour les instructions vocales
                },
                modifier = Modifier
                    .align(Alignment.TopEnd)
                    .padding(16.dp)
                    .offset(y = 200.dp)
            )
        }
        
        // OVERLAY : Panneau d'actions en bas
        if (currentOrder != null) {
            CourseActionPanel(
                currentOrder = currentOrder,
                deliveryStep = deliveryStep,
                onAcceptOrder = onAcceptOrder,
                onRejectOrder = onRejectOrder,
                onStartDelivery = onStartDelivery,
                onPickupPackage = onPickupPackage,
                onMarkDelivered = onMarkDelivered,
                onPickupValidation = onPickupValidation,
                onDeliveryValidation = onDeliveryValidation,
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .fillMaxWidth()
            )
        }
    }
}

/**
 * État vide quand aucune commande
 */
@Composable
fun EmptyCoursesState(
    pendingOrdersCount: Int,
    modifier: Modifier = Modifier
) {
    Box(
        modifier = modifier.background(
            brush = Brush.verticalGradient(
                colors = listOf(PrimaryDark, SecondaryBlue)
            )
        ),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.padding(32.dp)
        ) {
            Icon(
                Icons.Filled.LocalShipping,
                contentDescription = null,
                tint = PrimaryGold.copy(alpha = 0.5f),
                modifier = Modifier.size(120.dp)
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            Text(
                text = if (pendingOrdersCount > 0) {
                    "🔔 $pendingOrdersCount commande(s) en attente"
                } else {
                    "✨ Aucune course en ce moment"
                },
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                color = PrimaryGold,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = if (pendingOrdersCount > 0) {
                    "Acceptez une commande pour commencer"
                } else {
                    "Les nouvelles courses apparaîtront automatiquement"
                },
                fontSize = 16.sp,
                color = Color.White.copy(alpha = 0.7f),
                textAlign = TextAlign.Center
            )
        }
    }
}

/**
 * Panneau d'infos en haut (ordre, distance, ETA, client)
 */
@Composable
fun CourseInfoPanel(
    currentOrder: Commande,
    deliveryStep: DeliveryStep,
    distanceInfo: DistanceInfo?,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.shadow(16.dp, RoundedCornerShape(20.dp)),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        shape = RoundedCornerShape(20.dp)
    ) {
        Column(modifier = Modifier.padding(16.dp)) {
            // En-tête : Numéro commande + Statut
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Course #${currentOrder.id}",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryDark
                )
                
                StatusBadge(deliveryStep)
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Distance et ETA (si en route)
            if (distanceInfo != null && deliveryStep in listOf(
                DeliveryStep.ACCEPTED, 
                DeliveryStep.EN_ROUTE_PICKUP,
                DeliveryStep.PICKED_UP,
                DeliveryStep.EN_ROUTE_DELIVERY
            )) {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(16.dp)
                ) {
                    InfoChip(
                        icon = Icons.Filled.Place,
                        label = distanceInfo.distance,
                        color = PrimaryGold,
                        modifier = Modifier.weight(1f)
                    )
                    InfoChip(
                        icon = Icons.Filled.Schedule,
                        label = distanceInfo.eta,
                        color = SuccessGreen,
                        modifier = Modifier.weight(1f)
                    )
                }
                
                Spacer(modifier = Modifier.height(12.dp))
            }
            
            // Infos client
            Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                InfoRow(
                    icon = Icons.Filled.Person,
                    label = "Client",
                    value = currentOrder.clientNom
                )
                InfoRow(
                    icon = Icons.Filled.Phone,
                    label = "Tél. Client",
                    value = currentOrder.clientTelephone,
                    isPhoneNumber = true
                )
                
                if (currentOrder.telephoneDestinataire.isNotEmpty()) {
                    InfoRow(
                        icon = Icons.Filled.ContactPhone,
                        label = "Tél. Destinataire",
                        value = currentOrder.telephoneDestinataire,
                        isPhoneNumber = true
                    )
                }
                
                InfoRow(
                    icon = Icons.Filled.AttachMoney,
                    label = "Montant",
                    value = "${currentOrder.prixLivraison.toInt()} FCFA"
                )
            }
        }
    }
}

@Composable
fun StatusBadge(step: DeliveryStep) {
    val (text, color) = when (step) {
        DeliveryStep.PENDING -> "Nouvelle" to WarningYellow
        DeliveryStep.ACCEPTED -> "Acceptée" to SuccessGreen
        DeliveryStep.EN_ROUTE_PICKUP -> "En route" to AccentBlue
        DeliveryStep.PICKUP_ARRIVED -> "Sur place" to PrimaryGold
        DeliveryStep.PICKED_UP -> "Récupéré" to SuccessGreen
        DeliveryStep.EN_ROUTE_DELIVERY -> "Livraison" to AccentBlue
        DeliveryStep.DELIVERY_ARRIVED -> "Arrivé" to PrimaryGold
        DeliveryStep.DELIVERED -> "Livré" to SuccessGreen
        DeliveryStep.CASH_CONFIRMED -> "Terminé" to SuccessGreen
    }
    
    Surface(
        color = color.copy(alpha = 0.15f),
        shape = RoundedCornerShape(8.dp)
    ) {
        Text(
            text = text,
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
            fontSize = 12.sp,
            fontWeight = FontWeight.Bold,
            color = color
        )
    }
}

@Composable
fun InfoChip(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    color: Color,
    modifier: Modifier = Modifier
) {
    Surface(
        modifier = modifier,
        color = color.copy(alpha = 0.1f),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier.padding(12.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(icon, contentDescription = null, tint = color, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text(label, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = color)
        }
    }
}

@Composable
fun InfoRow(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    value: String,
    isPhoneNumber: Boolean = false
) {
    val context = androidx.compose.ui.platform.LocalContext.current
    
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp),
        modifier = if (isPhoneNumber) Modifier.clickable {
            val intent = android.content.Intent(android.content.Intent.ACTION_DIAL).apply {
                data = android.net.Uri.parse("tel:$value")
            }
            context.startActivity(intent)
        } else Modifier
    ) {
        Icon(
            icon,
            contentDescription = null,
            tint = if (isPhoneNumber) SuccessGreen else PrimaryGold,
            modifier = Modifier.size(16.dp)
        )
        Text(
            text = "$label:",
            fontSize = 12.sp,
            color = Color.Gray
        )
        Text(
            text = value,
            fontSize = 13.sp,
            fontWeight = FontWeight.Bold,
            color = if (isPhoneNumber) SuccessGreen else PrimaryDark,
            modifier = Modifier.weight(1f)
        )
        if (isPhoneNumber) {
            Icon(
                Icons.Filled.Phone,
                contentDescription = "Appeler",
                tint = SuccessGreen,
                modifier = Modifier.size(20.dp)
            )
        }
    }
}

/**
 * Panneau d'actions en bas
 */
@Composable
fun CourseActionPanel(
    currentOrder: Commande,
    deliveryStep: DeliveryStep,
    onAcceptOrder: () -> Unit,
    onRejectOrder: () -> Unit,
    onStartDelivery: () -> Unit = {},
    onPickupPackage: () -> Unit = {},
    onMarkDelivered: () -> Unit = {},
    onPickupValidation: () -> Unit,
    onDeliveryValidation: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.shadow(24.dp, RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)),
        colors = CardDefaults.cardColors(containerColor = PrimaryDark),
        shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)
    ) {
        Column(
            modifier = Modifier.padding(24.dp)
        ) {
            when (deliveryStep) {
                DeliveryStep.PENDING -> {
                    // Accepter ou Refuser
                    Text(
                        "Nouvelle course disponible !",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth()
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Row(
                        horizontalArrangement = Arrangement.spacedBy(12.dp),
                        modifier = Modifier.fillMaxWidth()
                    ) {
                        OutlinedButton(
                            onClick = onRejectOrder,
                            modifier = Modifier.weight(1f).height(56.dp),
                            colors = ButtonDefaults.outlinedButtonColors(
                                contentColor = AccentRed
                            ),
                            shape = RoundedCornerShape(16.dp)
                        ) {
                            Icon(Icons.Filled.Close, contentDescription = null)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Refuser", fontWeight = FontWeight.Bold)
                        }
                        
                        Button(
                            onClick = onAcceptOrder,
                            modifier = Modifier.weight(1f).height(56.dp),
                            colors = ButtonDefaults.buttonColors(containerColor = SuccessGreen),
                            shape = RoundedCornerShape(16.dp)
                        ) {
                            Icon(Icons.Filled.Check, contentDescription = null)
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Accepter", fontWeight = FontWeight.Bold)
                        }
                    }
                }
                
                DeliveryStep.ACCEPTED -> {
                    // Bouton pour commencer la livraison (acceptee → en_cours)
                    ActionButton(
                        text = "🚀 Commencer la livraison",
                        icon = Icons.Filled.LocalShipping,
                        onClick = onStartDelivery,
                        color = SuccessGreen
                    )
                }
                
                DeliveryStep.EN_ROUTE_PICKUP -> {
                    // Bouton pour marquer le colis comme récupéré (en_cours → recuperee)
                    ActionButton(
                        text = "📦 J'ai récupéré le colis",
                        icon = Icons.Filled.ShoppingBag,
                        onClick = onPickupPackage,
                        color = PrimaryGold
                    )
                }
                
                DeliveryStep.PICKUP_ARRIVED -> {
                    ActionButton(
                        text = "✅ J'ai récupéré le colis",
                        icon = Icons.Filled.Inventory,
                        onClick = onPickupPackage,
                        color = PrimaryGold
                    )
                }
                
                DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY -> {
                    // Bouton pour marquer comme livrée (recuperee → livree)
                    ActionButton(
                        text = "🏁 Marquer comme livrée",
                        icon = Icons.Filled.CheckCircle,
                        onClick = onMarkDelivered,
                        color = SuccessGreen
                    )
                }
                
                DeliveryStep.DELIVERY_ARRIVED -> {
                    ActionButton(
                        text = "✅ Livraison effectuée",
                        icon = Icons.Filled.CheckCircle,
                        onClick = onDeliveryValidation,
                        color = SuccessGreen
                    )
                }
                
                else -> {
                    // En route : afficher progression
                    ProgressIndicator(deliveryStep)
                }
            }
        }
    }
}

@Composable
fun ActionButton(
    text: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    onClick: () -> Unit,
    color: Color
) {
    Button(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth().height(56.dp),
        colors = ButtonDefaults.buttonColors(containerColor = color),
        shape = RoundedCornerShape(16.dp)
    ) {
        Icon(icon, contentDescription = null, tint = if (color == PrimaryGold) PrimaryDark else Color.White)
        Spacer(modifier = Modifier.width(12.dp))
        Text(
            text,
            fontSize = 16.sp,
            fontWeight = FontWeight.Bold,
            color = if (color == PrimaryGold) PrimaryDark else Color.White
        )
    }
}

@Composable
fun ProgressIndicator(step: DeliveryStep) {
    val (title, subtitle) = when (step) {
        DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP -> 
            "En route vers le point de récupération" to "Suivez la carte pour rejoindre le client"
        DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY -> 
            "En route vers le point de livraison" to "Livrez le colis au destinataire"
        else -> "Navigation en cours" to ""
    }
    
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.fillMaxWidth()
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.Center
        ) {
            CircularProgressIndicator(
                modifier = Modifier.size(24.dp),
                color = PrimaryGold,
                strokeWidth = 3.dp
            )
            Spacer(modifier = Modifier.width(16.dp))
            Column {
                Text(
                    title,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                if (subtitle.isNotEmpty()) {
                    Text(
                        subtitle,
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.7f)
                    )
                }
            }
        }
    }
}

/**
 * Bouton flottant pour activer/désactiver le guidage vocal
 */
@Composable
fun VoiceGuidanceButton(
    isEnabled: Boolean,
    onToggle: (Boolean) -> Unit,
    modifier: Modifier = Modifier
) {
    @Suppress("DEPRECATION")
    FloatingActionButton(
        onClick = { onToggle(!isEnabled) },
        modifier = modifier.size(56.dp),
        containerColor = if (isEnabled) SuccessGreen else GlassBg,
        shape = CircleShape
    ) {
        Icon(
            imageVector = if (isEnabled) Icons.Filled.VolumeUp else Icons.Filled.VolumeOff,
            contentDescription = if (isEnabled) "Désactiver guidage vocal" else "Activer guidage vocal",
            tint = if (isEnabled) Color.White else PrimaryGold,
            modifier = Modifier.size(28.dp)
        )
    }
}

/**
 * NOTE: Le guidage vocal est maintenant géré automatiquement par NavigationScreen
 * qui utilise l'API Android Text-to-Speech (TTS) pour donner des instructions vocales
 * en temps réel pendant la navigation.
 * 
 * Les instructions incluent:
 * - Distance restante
 * - Direction à prendre (tourner à gauche/droite)
 * - Alertes de proximité ("Vous arrivez à destination")
 * 
 * Le système TTS est intégré dans l'application, pas besoin d'ouvrir Google Maps.
 */
