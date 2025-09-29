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
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.ui.theme.*

/**
 * Header identique au design web coursier.php
 * Avec toutes les fonctionnalités avancées
 */
@Composable
fun SuzoskyHeader(
    titre: String,
    coursierNom: String,
    statut: String,
    onStatutChange: (String) -> Unit,
    onMenuClick: () -> Unit,
    onNotificationClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp)
        ) {
            // Première ligne: Logo + Menu + Notifications
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    IconButton(onClick = onMenuClick) {
                        Icon(
                            imageVector = Icons.AutoMirrored.Filled.Menu,
                            contentDescription = "Menu",
                            tint = Color.White
                        )
                    }
                    
                    Text(
                        text = "SUZOSKY",
                        style = SuzoskyTextStyles.brandTitle,
                        color = PrimaryGold
                    )
                }
                
                IconButton(onClick = onNotificationClick) {
                    Icon(
                        imageVector = Icons.AutoMirrored.Filled.Notifications,
                        contentDescription = "Notifications",
                        tint = Color.White
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Deuxième ligne: Nom et statut
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Column {
                    Text(
                        text = "Bonjour, $coursierNom",
                        style = SuzoskyTextStyles.greeting,
                        color = Color.White
                    )
                    Text(
                        text = titre,
                        style = SuzoskyTextStyles.subtitle,
                        color = Color.White.copy(alpha = 0.8f)
                    )
                }
                
                // Toggle statut identique au web
                SuzoskyToggleButton(
                    checked = statut == "EN_LIGNE",
                    onCheckedChange = { isOnline ->
                        onStatutChange(if (isOnline) "EN_LIGNE" else "HORS_LIGNE")
                    },
                    textOn = "En ligne",
                    textOff = "Hors ligne",
                    iconOn = Icons.AutoMirrored.Filled.RadioButtonChecked,
                    iconOff = Icons.AutoMirrored.Filled.RadioButtonUnchecked
                )
            }
        }
    }
}