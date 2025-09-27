package com.suzosky.coursier.ui.components

import androidx.compose.foundation.background
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.ui.theme.*

@Composable
fun NoActiveOrderScreen(
    pendingOrdersCount: Int,
    modifier: Modifier = Modifier
) {
    GlassContainer(
        modifier = modifier
            .fillMaxSize()
            .padding(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            // Icône principale
            Box(
                modifier = Modifier
                    .size(120.dp)
                    .background(
                        color = Color(0xFF2196F3).copy(alpha = 0.1f),
                        shape = RoundedCornerShape(60.dp)
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = if (pendingOrdersCount > 0) Icons.Filled.HourglassEmpty else Icons.Filled.Assignment,
                    contentDescription = null,
                    modifier = Modifier.size(60.dp),
                    tint = if (pendingOrdersCount > 0) PrimaryGold else Color(0xFF2196F3)
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Titre principal
            Text(
                text = when {
                    pendingOrdersCount > 0 -> "En attente de nouvelles commandes"
                    else -> "Aucune commande active"
                },
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = PrimaryGold,
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Description
            Text(
                text = when {
                    pendingOrdersCount > 0 -> "Vous avez $pendingOrdersCount commande(s) en attente.\nElles apparaîtront ici dès qu'elles seront disponibles."
                    else -> "Vous n'avez actuellement aucune commande active.\nLes nouvelles commandes s'afficheront automatiquement ici."
                },
                style = MaterialTheme.typography.bodyLarge,
                color = Color(0xFFB0B0B0),
                textAlign = TextAlign.Center,
                lineHeight = 22.sp
            )
            
            Spacer(modifier = Modifier.height(32.dp))
            
            // Indicateurs d'état
            Row(
                horizontalArrangement = Arrangement.spacedBy(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                StatusIndicator(
                    icon = Icons.Filled.WifiTethering,
                    label = "Connecté",
                    color = Color(0xFF4CAF50)
                )
                
                if (pendingOrdersCount > 0) {
                    StatusIndicator(
                        icon = Icons.Filled.Schedule,
                        label = "$pendingOrdersCount en attente",
                        color = PrimaryGold
                    )
                }
                
                StatusIndicator(
                    icon = Icons.Filled.LocationOn,
                    label = "GPS actif",
                    color = Color(0xFF2196F3)
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Message d'encouragement
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = Color(0xFF1E88E5).copy(alpha = 0.1f)
                ),
                shape = RoundedCornerShape(12.dp)
            ) {
                Row(
                    modifier = Modifier
                        .padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Icon(
                        imageVector = Icons.Filled.TipsAndUpdates,
                        contentDescription = null,
                        tint = Color(0xFF2196F3),
                        modifier = Modifier.size(24.dp)
                    )
                    
                    Text(
                        text = "Votre statut est EN LIGNE. Vous recevrez automatiquement les nouvelles commandes dans votre zone.",
                        color = Color.White,
                        fontSize = 14.sp,
                        modifier = Modifier.weight(1f)
                    )
                }
            }
        }
    }
}

@Composable
private fun StatusIndicator(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    color: Color
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.spacedBy(4.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = color,
            modifier = Modifier.size(20.dp)
        )
        Text(
            text = label,
            color = color,
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium
        )
    }
}