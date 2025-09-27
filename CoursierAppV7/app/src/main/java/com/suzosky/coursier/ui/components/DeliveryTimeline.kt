package com.suzosky.coursier.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.ui.screens.DeliveryStep
import com.suzosky.coursier.ui.theme.*

// Types de bandeau d'état
enum class BannerSeverity { ERROR, WARNING, INFO, SUCCESS }

// Modèle de bandeau d'état pour la timeline
data class TimelineBanner(
    val message: String,
    val severity: BannerSeverity = BannerSeverity.INFO,
    val actionLabel: String? = null,
    val onAction: (() -> Unit)? = null
)

data class TimelineStepData(
    val step: DeliveryStep,
    val title: String,
    val description: String,
    val icon: ImageVector,
    val actionText: String? = null,
    val onAction: (() -> Unit)? = null
)

@Composable
fun DeliveryTimeline(
    currentStep: DeliveryStep,
    paymentMethod: String, // "especes" ou autre
    onStepAction: (DeliveryStep) -> Unit,
    modifier: Modifier = Modifier,
    banner: TimelineBanner? = null
) {
    val steps = remember(paymentMethod) {
        buildList {
            add(TimelineStepData(
                step = DeliveryStep.PENDING,
                title = "Commande reçue",
                description = "Nouvelle commande disponible",
                icon = Icons.Filled.Notifications,
                actionText = "Accepter",
                onAction = { onStepAction(DeliveryStep.ACCEPTED) }
            ))
            add(TimelineStepData(
                step = DeliveryStep.ACCEPTED,
                title = "Commande acceptée",
                description = "En route vers récupération",
                icon = Icons.Filled.CheckCircle,
                actionText = "Arrivé au pickup",
                onAction = { onStepAction(DeliveryStep.EN_ROUTE_PICKUP) }
            ))
            add(TimelineStepData(
                step = DeliveryStep.EN_ROUTE_PICKUP,
                title = "Arrivé au pickup",
                description = "Récupération du colis",
                icon = Icons.Filled.LocationOn,
                actionText = "Colis récupéré",
                onAction = { onStepAction(DeliveryStep.PICKED_UP) }
            ))
            add(TimelineStepData(
                step = DeliveryStep.PICKED_UP,
                title = "Colis récupéré",
                description = "En route vers livraison",
                icon = Icons.Filled.Inventory
            ))
            add(TimelineStepData(
                step = DeliveryStep.EN_ROUTE_DELIVERY,
                title = "En route livraison",
                description = "Direction le client",
                icon = Icons.Filled.LocalShipping,
                actionText = "Arrivé chez client",
                onAction = { onStepAction(DeliveryStep.DELIVERY_ARRIVED) }
            ))
            add(TimelineStepData(
                step = DeliveryStep.DELIVERY_ARRIVED,
                title = "Arrivé chez client",
                description = "Remise du colis",
                icon = Icons.Filled.Home,
                actionText = "Colis livré",
                onAction = { onStepAction(DeliveryStep.DELIVERED) }
            ))
            add(TimelineStepData(
                step = DeliveryStep.DELIVERED,
                title = "Colis livré",
                description = if (paymentMethod.equals("especes", ignoreCase = true)) 
                    "En attente confirmation cash" else "Livraison terminée",
                icon = Icons.Filled.CheckCircle
            ))
            
            // Étape supplémentaire pour paiement espèces
            if (paymentMethod.equals("especes", ignoreCase = true)) {
                add(TimelineStepData(
                    step = DeliveryStep.CASH_CONFIRMED,
                    title = "Cash récupéré",
                    description = "Paiement espèces confirmé",
                    icon = Icons.Filled.AttachMoney,
                    actionText = "Cash récupéré",
                    onAction = { onStepAction(DeliveryStep.CASH_CONFIRMED) }
                ))
            }
        }
    }

    GlassContainer(
        modifier = modifier.fillMaxWidth()
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Header avec type de paiement
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Suivi de livraison",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                
                PaymentMethodChip(paymentMethod)
            }

            // Bandeau d'état (erreur/avertissement/info)
            if (banner != null) {
                TimelineStatusBanner(banner)
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Timeline steps
            steps.forEachIndexed { index, stepData ->
                val isActive = currentStep.ordinal >= stepData.step.ordinal
                val isCurrent = currentStep == stepData.step
                val isCompleted = currentStep.ordinal > stepData.step.ordinal
                
                TimelineStepItem(
                    stepData = stepData,
                    isActive = isActive,
                    isCurrent = isCurrent,
                    isCompleted = isCompleted,
                    showConnector = index < steps.size - 1
                )
            }
        }
    }
}

@Composable
private fun TimelineStatusBanner(banner: TimelineBanner) {
    val (bg, icon, title) = when (banner.severity) {
        BannerSeverity.ERROR -> Triple(Color(0xFFB00020), Icons.Filled.Error, "Erreur serveur")
        BannerSeverity.WARNING -> Triple(Color(0xFFFFA000), Icons.Filled.WarningAmber, "Avertissement")
        BannerSeverity.INFO -> Triple(Color(0xFF1976D2), Icons.Filled.Info, "Information")
        BannerSeverity.SUCCESS -> Triple(Color(0xFF2E7D32), Icons.Filled.CheckCircle, "Succès")
    }
    Card(
        colors = CardDefaults.cardColors(containerColor = bg.copy(alpha = 0.15f)),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(10.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(28.dp)
                    .clip(CircleShape)
                    .background(bg.copy(alpha = 0.9f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(icon, contentDescription = null, tint = Color.White)
            }
            Column(Modifier.weight(1f)) {
                Text(title, color = bg, fontWeight = FontWeight.Bold, fontSize = 13.sp)
                Text(banner.message, color = Color.White, fontSize = 13.sp)
            }
            if (banner.actionLabel != null && banner.onAction != null) {
                TextButton(onClick = { banner.onAction.invoke() }) {
                    Text(banner.actionLabel, color = bg, fontWeight = FontWeight.Bold)
                }
            }
        }
    }
}

@Composable
private fun PaymentMethodChip(paymentMethod: String) {
    val isEspeces = paymentMethod.equals("especes", ignoreCase = true)
    val backgroundColor = if (isEspeces) Color(0xFF4CAF50) else Color(0xFF2196F3)
    val icon = if (isEspeces) Icons.Filled.AttachMoney else Icons.Filled.CreditCard
    val text = if (isEspeces) "Espèces" else "Non-Espèces"
    
    Row(
        modifier = Modifier
            .background(backgroundColor, RoundedCornerShape(16.dp))
            .padding(horizontal = 12.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Color.White,
            modifier = Modifier.size(16.dp)
        )
        Text(
            text = text,
            color = Color.White,
            fontSize = 12.sp,
            fontWeight = FontWeight.SemiBold
        )
    }
}

@Composable
private fun TimelineStepItem(
    stepData: TimelineStepData,
    isActive: Boolean,
    isCurrent: Boolean,
    isCompleted: Boolean,
    showConnector: Boolean
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.Top
    ) {
        // Left side: Icon + Connector
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.width(48.dp)
        ) {
            // Step icon
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(
                        when {
                            isCompleted -> Color(0xFF4CAF50)
                            isCurrent -> PrimaryGold
                            isActive -> Color(0xFF2196F3)
                            else -> Color(0xFF757575)
                        }
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = if (isCompleted) Icons.Filled.Check else stepData.icon,
                    contentDescription = null,
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
            }
            
            // Connector line
            if (showConnector) {
                Box(
                    modifier = Modifier
                        .width(2.dp)
                        .height(32.dp)
                        .background(
                            if (isCompleted) Color(0xFF4CAF50) else Color(0xFFE0E0E0)
                        )
                )
            }
        }
        
        Spacer(modifier = Modifier.width(16.dp))
        
        // Right side: Content + Action
        Column(
            modifier = Modifier.weight(1f)
        ) {
            Text(
                text = stepData.title,
                fontWeight = if (isCurrent) FontWeight.Bold else FontWeight.SemiBold,
                color = when {
                    isCompleted -> Color(0xFF4CAF50)
                    isCurrent -> PrimaryGold
                    isActive -> Color.White
                    else -> Color(0xFF757575)
                },
                fontSize = 16.sp
            )
            
            Text(
                text = stepData.description,
                color = Color(0xFFB0B0B0),
                fontSize = 14.sp,
                modifier = Modifier.padding(top = 2.dp)
            )
            
            // Action button si disponible et étape courante
            AnimatedVisibility(
                visible = isCurrent && stepData.actionText != null && stepData.onAction != null,
                enter = expandVertically() + fadeIn(),
                exit = shrinkVertically() + fadeOut()
            ) {
                GradientButton(
                    text = stepData.actionText ?: "",
                    modifier = Modifier
                        .padding(top = 12.dp)
                        .height(40.dp),
                    onClick = { stepData.onAction?.invoke() }
                )
            }
        }
    }
}

@Composable
fun CashConfirmationDialog(
    isVisible: Boolean,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit
) {
    if (isVisible) {
        AlertDialog(
            onDismissRequest = onDismiss,
            containerColor = PrimaryDark,
            title = {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Icon(
                        imageVector = Icons.Filled.AttachMoney,
                        contentDescription = null,
                        tint = PrimaryGold
                    )
                    Text(
                        text = "Confirmation paiement",
                        color = PrimaryGold,
                        fontWeight = FontWeight.Bold
                    )
                }
            },
            text = {
                Text(
                    text = "Avez-vous bien récupéré le paiement en espèces auprès du client ?",
                    color = Color.White
                )
            },
            confirmButton = {
                GradientButton(
                    text = "Oui, cash récupéré",
                    onClick = onConfirm
                )
            },
            dismissButton = {
                TextButton(
                    onClick = onDismiss
                ) {
                    Text("Annuler", color = Color(0xFFB0B0B0))
                }
            }
        )
    }
}