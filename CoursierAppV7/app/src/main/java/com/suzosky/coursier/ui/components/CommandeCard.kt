package com.suzosky.coursier.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.ui.theme.StatusColors_nouvelle
import com.suzosky.coursier.ui.theme.StatusColors_attente
import com.suzosky.coursier.ui.theme.StatusColors_acceptee
import com.suzosky.coursier.ui.theme.StatusColors_enCours
import com.suzosky.coursier.ui.theme.StatusColors_livree
import com.suzosky.coursier.ui.theme.StatusColors_annulee
import com.suzosky.coursier.ui.theme.StatusColors_probleme
import com.suzosky.coursier.ui.theme.SuzoskyTextStyles
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.utils.TarificationSuzosky
// Helper to get status color from status string
private fun getStatusColor(status: String): Color {
    return when (status.lowercase()) {
        "nouvelle" -> StatusColors_nouvelle
        "attente" -> StatusColors_attente
        "acceptee" -> StatusColors_acceptee
        "en cours", "en_cours" -> StatusColors_enCours
        "livree" -> StatusColors_livree
        "annulee" -> StatusColors_annulee
        "probleme" -> StatusColors_probleme
        else -> Color.Gray
    }
}

/**
 * Carte de commande identique à l'interface web coursier.php
 * Reproduit exactement le design et les fonctionnalités
 */
@Composable
fun CommandeCard(
    commande: CommandeData,
    onAccepter: () -> Unit,
    onRefuser: () -> Unit,
    onMettreEnAttente: () -> Unit,
    onVoirDetails: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 8.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.suzoskyGlass()
        ),
        border = BorderStroke(1.dp, GlassBorder),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier.padding(20.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // En-tête avec statut et type
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                StatusBadge(
                    status = commande.statut,
                    type = commande.typeCommande
                )
                Text(
                    text = commande.heureCreation,
                    style = SuzoskyTextStyles.caption,
                    color = Color.White.copy(alpha = 0.7f)
                )
            }
            // Informations client et adresses
            ClientInfoSection(commande)
            // Détails de livraison
            DeliveryDetailsSection(commande)
            // Tarification
            TarificationSection(commande)
            // Boutons d'action selon le statut
            ActionButtonsSection(
                statut = commande.statut,
                onAccepter = onAccepter,
                onRefuser = onRefuser,
                onMettreEnAttente = onMettreEnAttente,
                onVoirDetails = onVoirDetails
            )
        }
    }
}

@Composable
private fun StatusBadge(status: String, type: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // Badge statut
        Box(
            modifier = Modifier
                .background(
                    color = getStatusColor(status).copy(alpha = 0.2f),
                    shape = RoundedCornerShape(20.dp)
                )
                .border(
                    1.dp,
                    getStatusColor(status),
                    RoundedCornerShape(20.dp)
                )
                .padding(horizontal = 12.dp, vertical = 6.dp)
        ) {
            Text(
                text = status.uppercase(),
                style = SuzoskyTextStyles.statusBadge,
                color = getStatusColor(status)
            )
        }
        
        // Badge type
        Icon(
            imageVector = when (type.lowercase()) {
                "classique" -> Icons.Default.DirectionsBike
                "business" -> Icons.Default.Business
                else -> Icons.Default.LocalShipping
            },
            contentDescription = type,
            tint = Color.suzoskyGold(),
            modifier = Modifier.size(20.dp)
        )
    }
}

@Composable
private fun ClientInfoSection(commande: CommandeData) {
    Column(
        verticalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        // Nom client
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(
                imageVector = Icons.Default.Person,
                contentDescription = "Client",
                tint = Color.suzoskyGold(),
                modifier = Modifier.size(18.dp)
            )
            Text(
                text = commande.nomClient,
                style = SuzoskyTextStyles.commandeTitle,
                color = Color.White
            )
        }
        
        // Téléphone
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(
                imageVector = Icons.Default.Phone,
                contentDescription = "Téléphone",
                tint = Color.suzoskyGold(),
                modifier = Modifier.size(18.dp)
            )
            Text(
                text = commande.telephone,
                style = SuzoskyTextStyles.commandeBody,
                color = Color.White.copy(alpha = 0.9f)
            )
        }
    }
}

@Composable
private fun DeliveryDetailsSection(commande: CommandeData) {
    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        // Adresse de récupération
        AdresseRow(
            icon = Icons.Default.LocationOn,
            label = "Récupération",
            adresse = commande.adresseRecuperation,
            iconColor = Color.suzoskySuccess()
        )
        
        // Ligne de connexion
        Row(
            modifier = Modifier.padding(start = 24.dp)
        ) {
            repeat(3) {
                Box(
                    modifier = Modifier
                        .size(4.dp)
                        .background(
                            Color.suzoskyGold().copy(alpha = 0.5f),
                            RoundedCornerShape(2.dp)
                        )
                )
                if (it < 2) Spacer(modifier = Modifier.width(4.dp))
            }
        }
        
        // Adresse de livraison
        AdresseRow(
            icon = Icons.Default.Flag,
            label = "Livraison",
            adresse = commande.adresseLivraison,
            iconColor = AccentRed
        )
        
        // Instructions spéciales si présentes
        if (commande.instructions.isNotEmpty()) {
            Row(
                verticalAlignment = Alignment.Top,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Info,
                    contentDescription = "Instructions",
                    tint = Color.suzoskyWarning(),
                    modifier = Modifier.size(18.dp)
                )
                Text(
                    text = "Instructions: ${commande.instructions}",
                    style = SuzoskyTextStyles.commandeBody,
                    color = Color.White.copy(alpha = 0.8f)
                )
            }
        }
    }
}

@Composable
private fun AdresseRow(
    icon: ImageVector,
    label: String,
    adresse: String,
    iconColor: Color
) {
    Row(
        verticalAlignment = Alignment.Top,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = label,
            tint = iconColor,
            modifier = Modifier.size(18.dp)
        )
        Column {
            Text(
                text = label,
                style = SuzoskyTextStyles.formLabel,
                color = iconColor
            )
            Text(
                text = adresse,
                style = SuzoskyTextStyles.commandeBody,
                color = Color.White.copy(alpha = 0.9f),
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
        }
    }
}

@Composable
private fun TarificationSection(commande: CommandeData) {
    val tarif = TarificationSuzosky.calculerTarif(
        commande.distanceKm,
        commande.minutesAttente
    )
    
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                Color.suzoskyGold().copy(alpha = 0.1f),
                RoundedCornerShape(12.dp)
            )
            .border(
                1.dp,
                Color.suzoskyGold().copy(alpha = 0.3f),
                RoundedCornerShape(12.dp)
            )
            .padding(16.dp)
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "💰 Prix estimé:",
                    style = SuzoskyTextStyles.commandeSubtitle,
                    color = Color.suzoskyGold()
                )
                Text(
                    text = TarificationSuzosky.formaterTarif(tarif.tarifFinalFCFA),
                    style = SuzoskyTextStyles.priceLarge,
                    color = Color.suzoskyGold()
                )
            }
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween
            ) {
                Text(
                    text = "📏 Distance: ${TarificationSuzosky.formaterDistance(tarif.distanceKm)}",
                    style = SuzoskyTextStyles.commandeBody,
                    color = Color.White.copy(alpha = 0.9f)
                )
                Text(
                    text = "⏱️ Durée: ${TarificationSuzosky.formaterDuree(tarif.minutesEstimees)}",
                    style = SuzoskyTextStyles.commandeBody,
                    color = Color.White.copy(alpha = 0.9f)
                )
            }
            
            if (commande.minutesAttente > 0) {
                Text(
                    text = "⏳ Attente: ${commande.minutesAttente} min (+${TarificationSuzosky.formaterTarif(tarif.attenteFCFA)})",
                    style = SuzoskyTextStyles.commandeBody,
                    color = Color.suzoskyWarning()
                )
            }
        }
    }
}

@Composable
private fun ActionButtonsSection(
    statut: String,
    onAccepter: () -> Unit,
    onRefuser: () -> Unit,
    onMettreEnAttente: () -> Unit,
    onVoirDetails: () -> Unit
) {
    when (statut.lowercase()) {
        "nouvelle" -> {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Bouton Refuser
                SuzoskyButton(
                    text = "Refuser",
                    onClick = onRefuser,
                    style = SuzoskyButtonStyle.Danger,
                    icon = Icons.Default.Close,
                    modifier = Modifier.weight(1f)
                )
                
                // Bouton Mettre en attente
                SuzoskyButton(
                    text = "En attente",
                    onClick = onMettreEnAttente,
                    style = SuzoskyButtonStyle.Warning,
                    icon = Icons.Default.Schedule,
                    modifier = Modifier.weight(1f)
                )
                
                // Bouton Accepter
                SuzoskyButton(
                    text = "Accepter",
                    onClick = onAccepter,
                    style = SuzoskyButtonStyle.Success,
                    icon = Icons.Default.Check,
                    modifier = Modifier.weight(1f)
                )
            }
        }
        
        "attente" -> {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                SuzoskyButton(
                    text = "Refuser",
                    onClick = onRefuser,
                    style = SuzoskyButtonStyle.Danger,
                    icon = Icons.Default.Close,
                    modifier = Modifier.weight(1f)
                )
                
                SuzoskyButton(
                    text = "Accepter",
                    onClick = onAccepter,
                    style = SuzoskyButtonStyle.Success,
                    icon = Icons.Default.Check,
                    modifier = Modifier.weight(1f)
                )
            }
        }
        
        "acceptee", "en_cours" -> {
            SuzoskyButton(
                text = "Voir détails",
                onClick = onVoirDetails,
                style = SuzoskyButtonStyle.Primary,
                icon = Icons.Default.Visibility,
                modifier = Modifier.fillMaxWidth()
            )
        }
        
        else -> {
            SuzoskyButton(
                text = "Voir détails",
                onClick = onVoirDetails,
                style = SuzoskyButtonStyle.Secondary,
                icon = Icons.Default.Info,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

/**
 * Données d'une commande
 */
// The CommandeData class is already defined above, so this duplicate is removed.
