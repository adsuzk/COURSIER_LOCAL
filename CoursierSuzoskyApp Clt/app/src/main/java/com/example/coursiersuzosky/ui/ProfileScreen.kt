package com.suzosky.coursierclient.ui

import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursierclient.ui.theme.*

@Composable
fun ProfileScreen(
    onLogout: () -> Unit,
    onOpenInfo: () -> Unit = {},
    onOpenSavedAddresses: () -> Unit = {},
    onOpenHistory: () -> Unit = {},
    onOpenCgu: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    var showLogoutDialog by remember { mutableStateOf(false) }
    
    Column(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Dark, SecondaryBlue, Dark)
                )
            )
            .verticalScroll(rememberScrollState())
    ) {
        // Header with profile
        ProfileHeader()
        
        Spacer(modifier = Modifier.height(32.dp))
        
        // Menu items
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 24.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Text(
                text = "Mon Compte",
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = Gold.copy(alpha = 0.7f),
                modifier = Modifier.padding(vertical = 8.dp)
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.Person,
                title = "Informations personnelles",
                subtitle = "Nom, email, téléphone",
                onClick = onOpenInfo
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.LocationOn,
                title = "Adresses enregistrées",
                subtitle = "Gérer vos adresses favorites",
                onClick = onOpenSavedAddresses
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.History,
                title = "Historique complet",
                subtitle = "Toutes vos commandes",
                onClick = onOpenHistory
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = "Paiement & Portefeuille",
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = Gold.copy(alpha = 0.7f),
                modifier = Modifier.padding(vertical = 8.dp)
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.AccountBalanceWallet,
                title = "Mon Portefeuille",
                subtitle = "Solde: 0 Fr",
                onClick = { /* TODO */ },
                trailing = {
                    Text(
                        text = "Recharger",
                        fontSize = 12.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Gold,
                        modifier = Modifier
                            .clip(RoundedCornerShape(8.dp))
                            .background(Gold.copy(alpha = 0.15f))
                            .padding(horizontal = 12.dp, vertical = 6.dp)
                    )
                }
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.CreditCard,
                title = "Moyens de paiement",
                subtitle = "Cartes et méthodes",
                onClick = { /* TODO */ }
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = "Support & Informations",
                fontSize = 16.sp,
                fontWeight = FontWeight.Bold,
                color = Gold.copy(alpha = 0.7f),
                modifier = Modifier.padding(vertical = 8.dp)
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.Headset,
                title = "Centre d'aide",
                subtitle = "FAQ et support client",
                onClick = { /* TODO */ }
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.Description,
                title = "Conditions d'utilisation",
                subtitle = "CGU et politique de confidentialité",
                onClick = onOpenCgu
            )
            
            ProfileMenuItem(
                icon = Icons.Filled.Star,
                title = "Évaluer l'application",
                subtitle = "Donnez votre avis",
                onClick = { /* TODO */ }
            )
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Logout button
            Button(
                onClick = { showLogoutDialog = true },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp),
                shape = RoundedCornerShape(16.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = AccentRed.copy(alpha = 0.15f),
                    contentColor = AccentRed
                ),
                border = BorderStroke(1.dp, AccentRed.copy(alpha = 0.3f))
            ) {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.Logout,
                    contentDescription = null,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    text = "Se déconnecter",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Version info
            Text(
                text = "Version 1.0.0 • SUZOSKY Premium",
                fontSize = 12.sp,
                color = Color.White.copy(alpha = 0.4f),
                modifier = Modifier.align(Alignment.CenterHorizontally)
            )
            
            Spacer(modifier = Modifier.height(32.dp))
        }
    }
    
    // Logout confirmation dialog
    if (showLogoutDialog) {
        AlertDialog(
            onDismissRequest = { showLogoutDialog = false },
            icon = {
                Icon(
                    imageVector = Icons.AutoMirrored.Filled.Logout,
                    contentDescription = null,
                    tint = AccentRed,
                    modifier = Modifier.size(32.dp)
                )
            },
            title = {
                Text(
                    text = "Déconnexion",
                    fontWeight = FontWeight.Bold
                )
            },
            text = {
                Text("Êtes-vous sûr de vouloir vous déconnecter ?")
            },
            confirmButton = {
                TextButton(
                    onClick = {
                        showLogoutDialog = false
                        onLogout()
                    },
                    colors = ButtonDefaults.textButtonColors(
                        contentColor = AccentRed
                    )
                ) {
                    Text("Déconnexion", fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(
                    onClick = { showLogoutDialog = false }
                ) {
                    Text("Annuler")
                }
            },
            containerColor = Dark,
            titleContentColor = Color.White,
            textContentColor = Color.White.copy(alpha = 0.7f)
        )
    }
}

@Composable
private fun ProfileHeader() {
    Box(
        modifier = Modifier
            .fillMaxWidth()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        Gold.copy(alpha = 0.15f),
                        Color.Transparent
                    )
                )
            )
            .padding(24.dp)
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.fillMaxWidth()
        ) {
            Spacer(modifier = Modifier.height(16.dp))
            
            // Avatar
            Box(
                modifier = Modifier
                    .size(100.dp)
                    .clip(CircleShape)
                    .background(
                        Brush.linearGradient(
                            colors = listOf(Gold, GoldLight)
                        )
                    )
                    .border(3.dp, Gold.copy(alpha = 0.3f), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Filled.Person,
                    contentDescription = null,
                    modifier = Modifier.size(50.dp),
                    tint = Dark
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Text(
                text = "Client Premium",
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White
            )
            
            Text(
                text = "client@suzosky.com",
                fontSize = 14.sp,
                color = Color.White.copy(alpha = 0.6f)
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Stats row
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                ProfileStat(value = "12", label = "Commandes")
                
                Box(
                    modifier = Modifier
                        .width(1.dp)
                        .height(40.dp)
                        .background(Gold.copy(alpha = 0.3f))
                )
                
                ProfileStat(value = "4.8★", label = "Note")
                
                Box(
                    modifier = Modifier
                        .width(1.dp)
                        .height(40.dp)
                        .background(Gold.copy(alpha = 0.3f))
                )
                
                ProfileStat(value = "Gold", label = "Statut")
            }
        }
    }
}

@Composable
private fun ProfileStat(value: String, label: String) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = value,
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Gold
        )
        Text(
            text = label,
            fontSize = 12.sp,
            color = Color.White.copy(alpha = 0.6f)
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ProfileMenuItem(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit,
    trailing: @Composable (() -> Unit)? = null
) {
    Card(
        onClick = onClick,
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.03f)
        ),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.1f))
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Row(
                modifier = Modifier.weight(1f),
                horizontalArrangement = Arrangement.spacedBy(16.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(48.dp)
                        .clip(CircleShape)
                        .background(Gold.copy(alpha = 0.1f)),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = icon,
                        contentDescription = null,
                        tint = Gold,
                        modifier = Modifier.size(24.dp)
                    )
                }
                
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = title,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White
                    )
                    Text(
                        text = subtitle,
                        fontSize = 13.sp,
                        color = Color.White.copy(alpha = 0.5f)
                    )
                }
            }
            
            if (trailing != null) {
                trailing()
            } else {
                Icon(
                    imageVector = Icons.Filled.ChevronRight,
                    contentDescription = null,
                    tint = Gold.copy(alpha = 0.5f),
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}
