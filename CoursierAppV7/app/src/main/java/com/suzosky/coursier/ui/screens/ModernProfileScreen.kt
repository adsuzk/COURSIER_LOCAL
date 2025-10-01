package com.suzosky.coursier.ui.screens

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

// Couleurs Suzosky
private val PrimaryGold = Color(0xFFD4A853)
private val PrimaryDark = Color(0xFF1A1A2E)
private val SecondaryBlue = Color(0xFF16213E)
private val SuccessGreen = Color(0xFF27AE60)
private val GlassBg = Color(0x14FFFFFF)

data class Badge(
    val id: String,
    val title: String,
    val icon: androidx.compose.ui.graphics.vector.ImageVector,
    val description: String,
    val unlocked: Boolean = true,
    val color: Color = PrimaryGold
)

data class CoursierStats(
    val totalCourses: Int = 0,
    val completedToday: Int = 0,
    val rating: Float = 5.0f,
    val totalEarnings: Int = 0,
    val level: Int = 1,
    val experiencePercent: Float = 0.45f,
    val memberSince: String = "Janvier 2025"
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ModernProfileScreen(
    coursierNom: String,
    coursierTelephone: String,
    stats: CoursierStats = CoursierStats(),
    onLogout: () -> Unit,
    onEditProfile: () -> Unit = {},
    onSettings: () -> Unit = {},
    modifier: Modifier = Modifier
) {
    LazyColumn(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        PrimaryDark,
                        SecondaryBlue.copy(alpha = 0.8f)
                    )
                )
            ),
        contentPadding = PaddingValues(bottom = 80.dp)
    ) {
        // Header avec photo de profil
        item {
            ProfileHeader(
                coursierNom = coursierNom,
                stats = stats,
                onEditProfile = onEditProfile
            )
        }
        
        // Stats principales
        item {
            MainStatsCards(stats = stats)
        }
        
        // Badges et réalisations
        item {
            BadgesSection()
        }
        
        // Niveau et progression
        item {
            LevelProgress(stats = stats)
        }
        
        // Actions de profil
        item {
            ProfileActions(
                onSettings = onSettings,
                onLogout = onLogout
            )
        }
        
        // Informations supplémentaires
        item {
            AdditionalInfo(
                telephone = coursierTelephone,
                memberSince = stats.memberSince
            )
        }
    }
}

@Composable
fun ProfileHeader(
    coursierNom: String,
    stats: CoursierStats,
    onEditProfile: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(bottom = 16.dp)
            .shadow(12.dp),
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark
        ),
        shape = RoundedCornerShape(bottomStart = 32.dp, bottomEnd = 32.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            // Photo de profil avec bordure dorée
            Box {
                Box(
                    modifier = Modifier
                        .size(120.dp)
                        .border(
                            width = 4.dp,
                            brush = Brush.linearGradient(
                                colors = listOf(PrimaryGold, PrimaryGold.copy(alpha = 0.6f))
                            ),
                            shape = CircleShape
                        )
                        .padding(4.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.radialGradient(
                                colors = listOf(
                                    SecondaryBlue,
                                    PrimaryDark
                                )
                            )
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = coursierNom.take(2).uppercase(),
                        fontSize = 36.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold
                    )
                }
                
                // Badge de niveau
                Box(
                    modifier = Modifier
                        .align(Alignment.BottomEnd)
                        .size(36.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.linearGradient(
                                colors = listOf(PrimaryGold, PrimaryGold.copy(alpha = 0.8f))
                            )
                        )
                        .border(3.dp, PrimaryDark, CircleShape),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = "${stats.level}",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryDark
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(16.dp))
            
            // Nom
            Text(
                text = coursierNom,
                fontSize = 26.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White
            )
            
            // Rating
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.padding(top = 8.dp)
            ) {
                repeat(5) { index ->
                    Icon(
                        if (index < stats.rating.toInt()) Icons.Filled.Star else Icons.Filled.StarOutline,
                        contentDescription = null,
                        tint = PrimaryGold,
                        modifier = Modifier.size(20.dp)
                    )
                }
                Text(
                    text = String.format("%.1f", stats.rating),
                    fontSize = 16.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = PrimaryGold
                )
            }
            
            // Bouton éditer
            OutlinedButton(
                onClick = onEditProfile,
                modifier = Modifier
                    .padding(top = 16.dp)
                    .height(40.dp),
                colors = ButtonDefaults.outlinedButtonColors(
                    contentColor = PrimaryGold
                ),
                border = ButtonDefaults.outlinedButtonBorder.copy(
                    brush = Brush.linearGradient(
                        colors = listOf(PrimaryGold, PrimaryGold.copy(alpha = 0.6f))
                    )
                ),
                shape = RoundedCornerShape(20.dp)
            ) {
                Icon(
                    Icons.Filled.Edit,
                    contentDescription = null,
                    modifier = Modifier.size(18.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text("Modifier le profil", fontSize = 14.sp)
            }
        }
    }
}

@Composable
fun MainStatsCards(stats: CoursierStats) {
    Column(
        modifier = Modifier.padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text(
            text = "📊 Statistiques",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White,
            modifier = Modifier.padding(bottom = 8.dp)
        )
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            StatCard(
                icon = Icons.Filled.LocalShipping,
                value = "${stats.totalCourses}",
                label = "Courses totales",
                color = PrimaryGold,
                modifier = Modifier.weight(1f)
            )
            StatCard(
                icon = Icons.Filled.TrendingUp,
                value = "${stats.completedToday}",
                label = "Aujourd'hui",
                color = SuccessGreen,
                modifier = Modifier.weight(1f)
            )
        }
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            StatCard(
                icon = Icons.Filled.AccountBalanceWallet,
                value = "${stats.totalEarnings} F",
                label = "Gains totaux",
                color = PrimaryGold,
                modifier = Modifier.weight(1f)
            )
            StatCard(
                icon = Icons.Filled.EmojiEvents,
                value = "Niveau ${stats.level}",
                label = "Rang actuel",
                color = PrimaryGold,
                modifier = Modifier.weight(1f)
            )
        }
    }
}

@Composable
fun StatCard(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    value: String,
    label: String,
    color: Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .height(100.dp)
            .shadow(6.dp, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(12.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = color,
                modifier = Modifier.size(28.dp)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                text = value,
                fontSize = 18.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White
            )
            Text(
                text = label,
                fontSize = 11.sp,
                color = Color.White.copy(alpha = 0.7f),
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
fun BadgesSection() {
    val badges = listOf(
        Badge("1", "Débutant", Icons.Filled.EmojiEvents, "Première course complétée", true, PrimaryGold),
        Badge("2", "Pro", Icons.Filled.WorkspacePremium, "50 courses complétées", true, PrimaryGold),
        Badge("3", "Rapide", Icons.Filled.Speed, "Livraison en temps record", true, SuccessGreen),
        Badge("4", "5 étoiles", Icons.Filled.Stars, "100 avis 5 étoiles", false, Color.Gray),
        Badge("5", "VIP", Icons.Filled.Diamond, "Niveau 10 atteint", false, Color.Gray)
    )
    
    Column(
        modifier = Modifier.padding(16.dp)
    ) {
        Text(
            text = "🏆 Badges & Réalisations",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White,
            modifier = Modifier.padding(bottom = 12.dp)
        )
        
        LazyRow(
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(badges) { badge ->
                BadgeItem(badge = badge)
            }
        }
    }
}

@Composable
fun BadgeItem(badge: Badge) {
    Card(
        modifier = Modifier
            .size(100.dp)
            .shadow(4.dp, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(
            containerColor = if (badge.unlocked) GlassBg else Color.White.copy(alpha = 0.05f)
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(12.dp),
            verticalArrangement = Arrangement.Center,
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Box(
                modifier = Modifier
                    .size(40.dp)
                    .clip(CircleShape)
                    .background(
                        if (badge.unlocked) {
                            Brush.radialGradient(
                                colors = listOf(badge.color.copy(alpha = 0.3f), Color.Transparent)
                            )
                        } else {
                            Brush.radialGradient(
                                colors = listOf(Color.Gray.copy(alpha = 0.2f), Color.Transparent)
                            )
                        }
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    badge.icon,
                    contentDescription = badge.title,
                    tint = if (badge.unlocked) badge.color else Color.Gray,
                    modifier = Modifier.size(24.dp)
                )
            }
            Spacer(modifier = Modifier.height(6.dp))
            Text(
                text = badge.title,
                fontSize = 11.sp,
                fontWeight = FontWeight.SemiBold,
                color = if (badge.unlocked) Color.White else Color.Gray,
                textAlign = TextAlign.Center
            )
        }
    }
}

@Composable
fun LevelProgress(stats: CoursierStats) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp)
            .shadow(6.dp, RoundedCornerShape(20.dp)),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(20.dp)
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
                    text = "📈 Progression",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Text(
                    text = "Niveau ${stats.level}",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Barre de progression
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(12.dp)
                    .clip(RoundedCornerShape(6.dp))
                    .background(Color.White.copy(alpha = 0.2f))
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth(stats.experiencePercent)
                        .fillMaxHeight()
                        .clip(RoundedCornerShape(6.dp))
                        .background(
                            Brush.horizontalGradient(
                                colors = listOf(PrimaryGold, SuccessGreen)
                            )
                        )
                )
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = "${(stats.experiencePercent * 100).toInt()}% vers le niveau ${stats.level + 1}",
                fontSize = 13.sp,
                color = Color.White.copy(alpha = 0.7f)
            )
        }
    }
}

@Composable
fun ProfileActions(
    onSettings: () -> Unit,
    onLogout: () -> Unit
) {
    Column(
        modifier = Modifier.padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        Text(
            text = "⚙️ Paramètres",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Color.White,
            modifier = Modifier.padding(bottom = 8.dp)
        )
        
        ProfileActionItem(
            icon = Icons.Filled.Settings,
            title = "Paramètres",
            subtitle = "Configuration de l'application",
            onClick = onSettings
        )
        
        ProfileActionItem(
            icon = Icons.Filled.Notifications,
            title = "Notifications",
            subtitle = "Gérer les alertes",
            onClick = { /* TODO */ }
        )
        
        ProfileActionItem(
            icon = Icons.Filled.Security,
            title = "Sécurité",
            subtitle = "Mot de passe et confidentialité",
            onClick = { /* TODO */ }
        )
        
        ProfileActionItem(
            icon = Icons.Filled.Help,
            title = "Aide & Support",
            subtitle = "Centre d'assistance",
            onClick = { /* TODO */ }
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        // Bouton déconnexion
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .clickable(onClick = onLogout)
                .shadow(4.dp, RoundedCornerShape(16.dp)),
            colors = CardDefaults.cardColors(
                containerColor = Color.Red.copy(alpha = 0.2f)
            ),
            shape = RoundedCornerShape(16.dp)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Icon(
                    Icons.Filled.ExitToApp,
                    contentDescription = null,
                    tint = Color.Red,
                    modifier = Modifier.size(24.dp)
                )
                Text(
                    text = "Se déconnecter",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = Color.Red
                )
            }
        }
    }
}

@Composable
fun ProfileActionItem(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .shadow(4.dp, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(CircleShape)
                    .background(PrimaryGold.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    icon,
                    contentDescription = null,
                    tint = PrimaryGold,
                    modifier = Modifier.size(24.dp)
                )
            }
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = Color.White
                )
                Text(
                    text = subtitle,
                    fontSize = 13.sp,
                    color = Color.White.copy(alpha = 0.6f)
                )
            }
            
            Icon(
                Icons.Filled.ChevronRight,
                contentDescription = null,
                tint = Color.White.copy(alpha = 0.5f),
                modifier = Modifier.size(24.dp)
            )
        }
    }
}

@Composable
fun AdditionalInfo(
    telephone: String,
    memberSince: String
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp)
            .shadow(4.dp, RoundedCornerShape(16.dp)),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Icon(
                    Icons.Filled.Phone,
                    contentDescription = null,
                    tint = PrimaryGold,
                    modifier = Modifier.size(20.dp)
                )
                Column {
                    Text(
                        text = "Téléphone",
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                    Text(
                        text = telephone,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White
                    )
                }
            }
            
            Divider(color = Color.White.copy(alpha = 0.1f))
            
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Icon(
                    Icons.Filled.CalendarMonth,
                    contentDescription = null,
                    tint = PrimaryGold,
                    modifier = Modifier.size(20.dp)
                )
                Column {
                    Text(
                        text = "Membre depuis",
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                    Text(
                        text = memberSince,
                        fontSize = 15.sp,
                        fontWeight = FontWeight.SemiBold,
                        color = Color.White
                    )
                }
            }
        }
    }
}
