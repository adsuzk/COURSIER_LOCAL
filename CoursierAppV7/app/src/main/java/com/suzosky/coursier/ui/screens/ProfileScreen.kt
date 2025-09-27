package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.rememberScrollState
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
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProfileScreen(
    coursierNom: String = "",
    coursierMatricule: String = "",
    coursierEmail: String = "",
    coursierTelephone: String = "",
    coursierStatut: String = "EN_LIGNE",
    totalCommandes: Int = 0,
    noteGlobale: Float = 0.0f,
    dateInscription: String = "",
    onLogout: () -> Unit = {}
) {
    var showLogoutDialog by remember { mutableStateOf(false) }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(PrimaryDark)
    ) {
        // Arrière-plan avec gradient plus sombre
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colors = listOf(
                            PrimaryDark,
                            SecondaryBlue,
                            PrimaryDark
                        )
                    )
                )
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp)
        ) {
            // En-tête avec photo de profil
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 20.dp),
                colors = CardDefaults.cardColors(
                    containerColor = SecondaryBlue.copy(alpha = 0.8f)
                ),
                shape = RoundedCornerShape(20.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    // Photo de profil (icône pour l'instant)
                    Box(
                        modifier = Modifier
                            .size(100.dp)
                            .clip(CircleShape)
                            .background(PrimaryGold),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Default.Person,
                            contentDescription = "Photo de profil",
                            modifier = Modifier.size(60.dp),
                            tint = PrimaryDark
                        )
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Text(
                        text = coursierNom,
                        style = SuzoskyTextStyles.sectionTitle,
                        color = PrimaryGoldLight,
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold
                    )
                    
                    // Matricule
                    if (coursierMatricule.isNotBlank()) {
                        Text(
                            text = "Matricule: $coursierMatricule",
                            style = SuzoskyTextStyles.bodyText,
                            color = PrimaryGold,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Medium,
                            modifier = Modifier.padding(top = 4.dp)
                        )
                    }
                    
                    // Ligne Fonction: Coursier — <Nom complet>
                    Text(
                        text = "Fonction: Coursier — ${if (coursierNom.isNotBlank()) coursierNom else ""}",
                        style = SuzoskyTextStyles.bodyText,
                        color = Color.White.copy(alpha = 0.8f),
                        modifier = Modifier.padding(top = 6.dp)
                    )

                    // Badge de statut
                    Card(
                        modifier = Modifier.padding(top = 8.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = if (coursierStatut == "EN_LIGNE") 
                                Color.Green.copy(alpha = 0.2f) 
                            else 
                                Color.Red.copy(alpha = 0.2f)
                        ),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text(
                            text = if (coursierStatut == "EN_LIGNE") "En ligne" else "Hors ligne",
                            modifier = Modifier.padding(horizontal = 16.dp, vertical = 6.dp),
                            color = if (coursierStatut == "EN_LIGNE") Color.Green else Color.Red,
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }

            // Statistiques rapides
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                StatCard(
                    modifier = Modifier.weight(1f),
                    title = "Commandes",
                    value = totalCommandes.toString(),
                    icon = Icons.Default.Assignment,
                    color = PrimaryGold
                )
                
                StatCard(
                    modifier = Modifier.weight(1f),
                    title = "Note",
                    value = "${noteGlobale}/5",
                    icon = Icons.Default.Star,
                    color = Color(0xFFFFD700)
                )
            }
            
            Spacer(modifier = Modifier.height(20.dp))

            // Informations personnelles
            ProfileSection(
                title = "Informations personnelles",
                icon = Icons.Default.Person
            ) {
                ProfileInfoItem(
                    icon = Icons.Default.Work,
                    label = "Fonction",
                    value = "Coursier — ${if (coursierNom.isNotBlank()) coursierNom else ""}"
                )
                ProfileInfoItem(
                    icon = Icons.Default.Email,
                    label = "Email",
                    value = if (coursierEmail.isNotBlank()) coursierEmail else "—"
                )
                ProfileInfoItem(
                    icon = Icons.Default.Phone,
                    label = "Téléphone", 
                    value = if (coursierTelephone.isNotBlank()) coursierTelephone else "—"
                )
                ProfileInfoItem(
                    icon = Icons.Default.DateRange,
                    label = "Member depuis",
                    value = if (dateInscription.isNotBlank()) dateInscription else "—"
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))

            // Paramètres du compte
            ProfileSection(
                title = "Paramètres",
                icon = Icons.Default.Settings
            ) {
                ProfileActionItem(
                    icon = Icons.Default.Notifications,
                    title = "Notifications",
                    subtitle = "Gérer les notifications",
                    onClick = {}
                )
                
                ProfileActionItem(
                    icon = Icons.Default.Security,
                    title = "Sécurité",
                    subtitle = "Mot de passe et sécurité",
                    onClick = {}
                )
                
                ProfileActionItem(
                    icon = Icons.Default.Help,
                    title = "Aide",
                    subtitle = "Centre d'aide et support",
                    onClick = {}
                )
                
                // Bouton de déconnexion
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(vertical = 4.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = AccentRed.copy(alpha = 0.1f)
                    ),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    ProfileActionItem(
                        icon = Icons.Default.ExitToApp,
                        title = "Déconnexion",
                        subtitle = "Se déconnecter de l'application",
                        titleColor = AccentRed,
                        iconColor = AccentRed,
                        onClick = {
                            showLogoutDialog = true
                        }
                    )
                }
            }

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
    
    // Dialog de confirmation de déconnexion
    if (showLogoutDialog) {
        AlertDialog(
            onDismissRequest = { showLogoutDialog = false },
            icon = {
                Icon(
                    Icons.Default.ExitToApp,
                    contentDescription = null,
                    tint = AccentRed,
                    modifier = Modifier.size(32.dp)
                )
            },
            title = {
                Text(
                    text = "Confirmer la déconnexion",
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold
                )
            },
            text = {
                Text(
                    text = "Êtes-vous sûr de vouloir vous déconnecter de l'application ?",
                    style = MaterialTheme.typography.bodyMedium
                )
            },
            confirmButton = {
                Button(
                    onClick = {
                        showLogoutDialog = false
                        onLogout()
                    },
                    colors = ButtonDefaults.buttonColors(
                        containerColor = AccentRed
                    )
                ) {
                    Text("Déconnecter", color = Color.White)
                }
            },
            dismissButton = {
                OutlinedButton(
                    onClick = { showLogoutDialog = false }
                ) {
                    Text("Annuler")
                }
            }
        )
    }
}

@Composable
private fun StatCard(
    modifier: Modifier = Modifier,
    title: String,
    value: String,
    icon: ImageVector,
    color: Color
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = GlassBg.copy(alpha = 0.8f)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(24.dp)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = value,
                style = SuzoskyTextStyles.sectionTitle,
                color = Color.White,
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold
            )
            Text(
                text = title,
                style = SuzoskyTextStyles.bodyText,
                color = Color.White.copy(alpha = 0.7f),
                fontSize = 12.sp
            )
        }
    }
}

@Composable 
private fun ProfileSection(
    title: String,
    icon: ImageVector,
    content: @Composable ColumnScope.() -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg.copy(alpha = 0.8f)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = PrimaryGold,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(modifier = Modifier.width(12.dp))
                Text(
                    text = title,
                    style = SuzoskyTextStyles.sectionTitle,
                    color = Color.White,
                    fontSize = 18.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            content()
        }
    }
}

@Composable
private fun ProfileInfoItem(
    icon: ImageVector,
    label: String,
    value: String
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            tint = Color.White.copy(alpha = 0.7f),
            modifier = Modifier.size(20.dp)
        )
        Spacer(modifier = Modifier.width(16.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = label,
                color = Color.White.copy(alpha = 0.6f),
                fontSize = 12.sp
            )
            Text(
                text = value,
                color = Color.White,
                fontSize = 14.sp,
                fontWeight = FontWeight.Medium
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ProfileActionItem(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit,
    titleColor: Color = Color.White,
    iconColor: Color = PrimaryGold
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 4.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.05f)
        ),
        onClick = onClick,
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = iconColor,
                modifier = Modifier.size(24.dp)
            )
            Spacer(modifier = Modifier.width(16.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    color = titleColor,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Medium
                )
                Text(
                    text = subtitle,
                    color = Color.White.copy(alpha = 0.6f),
                    fontSize = 12.sp
                )
            }
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = Color.White.copy(alpha = 0.4f),
                modifier = Modifier.size(20.dp)
            )
        }
    }
}