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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import com.suzosky.coursier.ui.theme.*

/**
 * Menu drawer identique au design web coursier.php
 * Navigation principale avec design Suzosky
 */
@Composable
fun SuzoskyDrawerMenu(
    coursierNom: String,
    onDismiss: () -> Unit,
    onNavigateToProfile: () -> Unit = {},
    onNavigateToHistorique: () -> Unit = {},
    onNavigateToGains: () -> Unit = {},
    onNavigateToSupport: () -> Unit = {},
    onLogout: () -> Unit = {}
) {
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(
            usePlatformDefaultWidth = false
        )
    ) {
        Box(
            modifier = Modifier.fillMaxSize()
        ) {
            // Fond semi-transparent
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.6f))
                    .clickable { onDismiss() }
            )
            
            // Menu drawer
            Card(
                modifier = Modifier
                    .fillMaxHeight()
                    .width(280.dp)
                    .align(Alignment.CenterStart),
                colors = CardDefaults.cardColors(
                    containerColor = PrimaryDark
                ),
                shape = RoundedCornerShape(topEnd = 16.dp, bottomEnd = 16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(vertical = 24.dp)
                ) {
                    // Header du menu avec profil
                    DrawerHeader(
                        coursierNom = coursierNom,
                        onCloseClick = onDismiss
                    )
                    
                    Spacer(modifier = Modifier.height(20.dp))
                    
                    // Items de navigation identiques au web
                    DrawerMenuItem(
                        icon = Icons.AutoMirrored.Filled.Dashboard,
                        title = "Dashboard",
                        subtitle = "Commandes en cours",
                        onClick = onDismiss
                    )
                    
                    DrawerMenuItem(
                        icon = Icons.AutoMirrored.Filled.Person,
                        title = "Mon Profil",
                        subtitle = "Informations personnelles",
                        onClick = {
                            onNavigateToProfile()
                            onDismiss()
                        }
                    )
                    
                    DrawerMenuItem(
                        icon = Icons.AutoMirrored.Filled.History,
                        title = "Historique",
                        subtitle = "Mes livraisons",
                        onClick = {
                            onNavigateToHistorique()
                            onDismiss()
                        }
                    )
                    
                    DrawerMenuItem(
                        icon = Icons.AutoMirrored.Filled.AttachMoney,
                        title = "Mes Gains",
                        subtitle = "Revenus et statistiques",
                        onClick = {
                            onNavigateToGains()
                            onDismiss()
                        }
                    )
                    
                    DrawerMenuItem(
                        icon = Icons.AutoMirrored.Filled.Support,
                        title = "Support",
                        subtitle = "Aide et assistance",
                        onClick = {
                            onNavigateToSupport()
                            onDismiss()
                        }
                    )
                    
                    Spacer(modifier = Modifier.weight(1f))
                    
                    // Section logout
                    Divider(
                        color = GlassBorder,
                        modifier = Modifier.padding(horizontal = 16.dp)
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    DrawerMenuItem(
                        icon = Icons.AutoMirrored.Filled.Logout,
                        title = "Déconnexion",
                        subtitle = "Quitter l'application",
                        onClick = {
                            onLogout()
                            onDismiss()
                        },
                        isDestructive = true
                    )
                }
            }
        }
    }
}

/**
 * Header du drawer menu
 */
@Composable
fun DrawerHeader(
    coursierNom: String,
    onCloseClick: () -> Unit
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(GlassBg)
            .padding(16.dp)
    ) {
        IconButton(
            onClick = onCloseClick,
            modifier = Modifier.align(Alignment.TopEnd)
        ) {
            Icon(
                imageVector = Icons.Default.Close,
                contentDescription = "Fermer",
                tint = Color.White
            )
        }
        
        Column(
            modifier = Modifier.fillMaxWidth(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Avatar du coursier
            Box(
                modifier = Modifier
                    .size(80.dp)
                    .clip(RoundedCornerShape(40.dp))
                    .background(GradientGold),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = coursierNom.take(2).uppercase(),
                    style = SuzoskyTextStyles.avatarText,
                    color = PrimaryDark
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = coursierNom,
                style = SuzoskyTextStyles.greeting,
                color = Color.White,
                textAlign = TextAlign.Center
            )
            
            Text(
                text = "Coursier Suzosky",
                style = SuzoskyTextStyles.caption,
                color = PrimaryGold,
                textAlign = TextAlign.Center
            )
        }
    }
}

/**
 * Item de menu du drawer
 */
@Composable
fun DrawerMenuItem(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit,
    isDestructive: Boolean = false
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onClick() }
            .padding(horizontal = 16.dp, vertical = 8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(
                    if (isDestructive) 
                        AccentRed.copy(alpha = 0.1f) 
                    else 
                        Color.Transparent
                )
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = if (isDestructive) AccentRed else PrimaryGold,
                modifier = Modifier.size(24.dp)
            )
            
            Column(
                modifier = Modifier.weight(1f)
            ) {
                Text(
                    text = title,
                    style = SuzoskyTextStyles.menuItem,
                    color = if (isDestructive) AccentRed else Color.White
                )
                Text(
                    text = subtitle,
                    style = SuzoskyTextStyles.caption,
                    color = if (isDestructive) 
                        AccentRed.copy(alpha = 0.7f) 
                    else 
                        Color.White.copy(alpha = 0.6f)
                )
            }
            
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = if (isDestructive) 
                    AccentRed.copy(alpha = 0.6f) 
                else 
                    Color.White.copy(alpha = 0.4f),
                modifier = Modifier.size(20.dp)
            )
        }
    }
}

/**
 * Panel de notifications identique au web
 */
@Composable
fun NotificationPanel(
    notifications: List<String>,
    onDismiss: () -> Unit
) {
    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(
            usePlatformDefaultWidth = false
        )
    ) {
        Box(
            modifier = Modifier.fillMaxSize()
        ) {
            // Fond semi-transparent
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .background(Color.Black.copy(alpha = 0.6f))
                    .clickable { onDismiss() }
            )
            
            // Panel notifications
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
                    .align(Alignment.TopCenter),
                colors = CardDefaults.cardColors(
                    containerColor = PrimaryDark
                ),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(20.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Notifications",
                            style = SuzoskyTextStyles.sectionTitle,
                            color = Color.White
                        )
                        
                        IconButton(onClick = onDismiss) {
                            Icon(
                                imageVector = Icons.Default.Close,
                                contentDescription = "Fermer",
                                tint = Color.White
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    if (notifications.isEmpty()) {
                        Text(
                            text = "Aucune notification",
                            style = SuzoskyTextStyles.bodyText,
                            color = Color.White.copy(alpha = 0.6f),
                            textAlign = TextAlign.Center,
                            modifier = Modifier.fillMaxWidth()
                        )
                    } else {
                        notifications.forEach { notification ->
                            NotificationItem(
                                message = notification,
                                time = "Il y a 2 min"
                            )
                            
                            if (notification != notifications.last()) {
                                Spacer(modifier = Modifier.height(12.dp))
                            }
                        }
                    }
                }
            }
        }
    }
}

/**
 * Item de notification
 */
@Composable
fun NotificationItem(
    message: String,
    time: String
) {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(GlassBg)
            .padding(16.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Icon(
                imageVector = Icons.Default.Notifications,
                contentDescription = null,
                tint = PrimaryGold,
                modifier = Modifier.size(20.dp)
            )
            
            Column(
                modifier = Modifier.weight(1f)
            ) {
                Text(
                    text = message,
                    style = SuzoskyTextStyles.bodyText,
                    color = Color.White
                )
                
                Spacer(modifier = Modifier.height(4.dp))
                
                Text(
                    text = time,
                    style = SuzoskyTextStyles.caption,
                    color = Color.White.copy(alpha = 0.6f)
                )
            }
        }
    }
}
