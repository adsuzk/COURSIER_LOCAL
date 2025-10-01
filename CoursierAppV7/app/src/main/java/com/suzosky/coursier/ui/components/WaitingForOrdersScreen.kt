package com.suzosky.coursier.ui.components

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.data.models.HealthStatus
import com.suzosky.coursier.data.models.SystemHealth

@Composable
fun WaitingForOrdersScreen(
    systemHealth: SystemHealth,
    nbCommandesEnAttente: Int = 0,
    modifier: Modifier = Modifier
) {
    // Animation du voyant
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val scale by infiniteTransition.animateFloat(
        initialValue = 0.8f,
        targetValue = 1.2f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "scale"
    )
    
    // Couleur du voyant selon statut
    val (voyantColor, statusText, statusIcon) = when (systemHealth.status) {
        HealthStatus.HEALTHY -> Triple(Color(0xFF4CAF50), "Système opérationnel", "✅")
        HealthStatus.WARNING -> Triple(Color(0xFFFF9800), "Attention requise", "⚠️")
        HealthStatus.CRITICAL -> Triple(Color(0xFFF44336), "Problème détecté", "❌")
    }
    
    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center
    ) {
        // Voyant de statut animé
        Box(
            modifier = Modifier
                .size(120.dp)
                .scale(if (systemHealth.status == HealthStatus.HEALTHY) scale else 1f)
                .background(voyantColor, CircleShape),
            contentAlignment = Alignment.Center
        ) {
            Text(
                text = statusIcon,
                fontSize = 48.sp
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        // Statut système
        Text(
            text = statusText,
            fontSize = 24.sp,
            fontWeight = FontWeight.Bold,
            color = voyantColor
        )
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Message d'attente
        if (systemHealth.status == HealthStatus.HEALTHY) {
            Text(
                text = "En attente de nouvelles commandes...",
                fontSize = 18.sp,
                color = MaterialTheme.colorScheme.onSurfaceVariant
            )
            
            if (nbCommandesEnAttente > 0) {
                Spacer(modifier = Modifier.height(8.dp))
                Card(
                    colors = CardDefaults.cardColors(
                        containerColor = MaterialTheme.colorScheme.primaryContainer
                    )
                ) {
                    Text(
                        text = "🔔 $nbCommandesEnAttente commande(s) disponible(s)",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Medium,
                        modifier = Modifier.padding(16.dp)
                    )
                }
            }
        } else {
            // Message d'erreur détaillé
            Text(
                text = systemHealth.message,
                fontSize = 16.sp,
                color = MaterialTheme.colorScheme.error,
                modifier = Modifier.padding(horizontal = 16.dp)
            )
        }
        
        Spacer(modifier = Modifier.height(32.dp))
        
        // Détails de santé système
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surfaceVariant
            )
        ) {
            Column(
                modifier = Modifier.padding(16.dp)
            ) {
                Text(
                    text = "État du système",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold
                )
                
                Spacer(modifier = Modifier.height(12.dp))
                
                HealthRow(
                    label = "📡 Base de données",
                    isOk = systemHealth.databaseConnected
                )
                
                HealthRow(
                    label = "🔑 Token FCM",
                    isOk = systemHealth.fcmTokenActive
                )
                
                HealthRow(
                    label = "🔄 Synchronisation",
                    isOk = systemHealth.syncWorking
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                val timeSinceSync = (System.currentTimeMillis() - systemHealth.lastSyncTimestamp) / 1000
                Text(
                    text = "Dernière sync: il y a ${timeSinceSync}s",
                    fontSize = 14.sp,
                    color = if (timeSinceSync < 10) Color(0xFF4CAF50) else Color(0xFFFF9800)
                )
            }
        }
        
        Spacer(modifier = Modifier.height(16.dp))
        
        // Message d'assistance
        if (systemHealth.status != HealthStatus.HEALTHY) {
            Card(
                colors = CardDefaults.cardColors(
                    containerColor = MaterialTheme.colorScheme.errorContainer
                )
            ) {
                Text(
                    text = "⚠️ Si le problème persiste, contactez le support Suzosky",
                    fontSize = 14.sp,
                    modifier = Modifier.padding(12.dp)
                )
            }
        }
    }
}

@Composable
private fun HealthRow(label: String, isOk: Boolean) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(
            text = label,
            fontSize = 16.sp
        )
        
        Box(
            modifier = Modifier
                .size(16.dp)
                .background(
                    color = if (isOk) Color(0xFF4CAF50) else Color(0xFFF44336),
                    shape = CircleShape
                )
        )
    }
}
