package com.suzosky.coursier.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import com.suzosky.coursier.ui.theme.*

/**
 * Menu drawer identique à l'interface web coursier.php
 * Navigation complète avec toutes les options
 */
@Composable
fun SuzoskyDrawerMenu(
    coursierNom: String,
    onDismiss: () -> Unit,
    onNavigateToProfile: () -> Unit,
    onNavigateToHistorique: () -> Unit,
    onNavigateToGains: () -> Unit,
    onLogout: () -> Unit,
    modifier: Modifier = Modifier
) {
    Dialog(onDismissRequest = onDismiss) {
        Card(
            modifier = modifier
                .fillMaxWidth()
                .padding(16.dp),
            colors = CardDefaults.cardColors(
                containerColor = GlassBg
            ),
            shape = RoundedCornerShape(20.dp)
        ) {
            Column(
                modifier = Modifier.padding(24.dp)
            ) {
                // Header du menu
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Menu",
                            style = SuzoskyTextStyles.sectionTitle,
                            color = Color.White
                        )
                        Text(
                            text = coursierNom,
                            style = SuzoskyTextStyles.subtitle,
                            color = PrimaryGold
                        )
                    }
                    IconButton(onClick = onDismiss) {
                        Icon(
                            imageVector = Icons.Filled.Close,
                            contentDescription = "Fermer",
                            tint = Color.White
                        )
                    }
                }
                
                Spacer(modifier = Modifier.height(24.dp))
                
                // Options du menu
                MenuOption(
                    icon = Icons.Filled.Person,
                    text = "Mon Profil",
                    onClick = {
                        onNavigateToProfile()
                        onDismiss()
                    }
                )
                
                MenuOption(
                    icon = Icons.Filled.History,
                    text = "Historique",
                    onClick = {
                        onNavigateToHistorique()
                        onDismiss()
                    }
                )
                
                MenuOption(
                    icon = Icons.Filled.AccountBalance,
                    text = "Mes Gains",
                    onClick = {
                        onNavigateToGains()
                        onDismiss()
                    }
                )
                
                Spacer(modifier = Modifier.height(16.dp))
                Divider(color = Color.White.copy(alpha = 0.2f))
                Spacer(modifier = Modifier.height(16.dp))
                
                MenuOption(
                    icon = Icons.AutoMirrored.Filled.ExitToApp,
                    text = "Déconnexion",
                    onClick = onLogout,
                    isDestructive = true
                )
            }
        }
    }
}

@Composable
private fun MenuOption(
    icon: ImageVector,
    text: String,
    onClick: () -> Unit,
    isDestructive: Boolean = false
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
            .padding(vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = if (isDestructive) Color.Red else Color.White,
            modifier = Modifier.size(24.dp)
        )
        Spacer(modifier = Modifier.width(16.dp))
        Text(
            text = text,
            style = SuzoskyTextStyles.bodyText,
            color = if (isDestructive) Color.Red else Color.White
        )
    }
}