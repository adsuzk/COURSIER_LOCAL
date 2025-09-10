package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.sp
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.ui.components.*
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.utils.TarificationSuzosky

/**
 * Écran principal du coursier - copie conforme de coursier.php
 * Interface 100% identique au web
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun CoursierScreen(
    modifier: Modifier = Modifier,
    coursierNom: String = "Jean Dupont",
    coursierStatut: String = "EN_LIGNE",
    commandes: List<Commande> = emptyList(),
    onStatutChange: (String) -> Unit = {},
    onCommandeAccept: (String) -> Unit = {},
    onCommandeReject: (String) -> Unit = {},
    onCommandeAttente: (String) -> Unit = {},
    onNavigateToProfile: () -> Unit = {},
    onNavigateToHistorique: () -> Unit = {},
    onNavigateToGains: () -> Unit = {},
    onLogout: () -> Unit = {}
) {
    var showNotifications by remember { mutableStateOf(false) }
    var showMenu by remember { mutableStateOf(false) }
    
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(BackgroundPrimary)
    ) {
        // Background blur effect comme sur le web
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colors = listOf(
                            PrimaryDark.copy(alpha = 0.95f),
                            PrimaryDark.copy(alpha = 0.98f)
                        )
                    )
                )
        )
        
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp)
        ) {
            // Header identique au web
            SuzoskyHeader(
                titre = "Dashboard Coursier",
                coursierNom = coursierNom,
                statut = coursierStatut,
                onStatutChange = onStatutChange,
                onMenuClick = { showMenu = true },
                onNotificationClick = { showNotifications = true }
            )
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Cards de statistiques comme sur le web
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                StatCard(
                    title = "Commandes Aujourd'hui",
                    value = "${commandes.filter { it.dateCommande.contains("2024-01-20") }.size}",
                    icon = Icons.Default.Assignment,
                    modifier = Modifier.weight(1f)
                )
                
                StatCard(
                    title = "Gains du Jour",
                    value = "${TarificationSuzosky.calculerGainsCoursier(commandes)} FCFA",
                    icon = Icons.Default.AttachMoney,
                    modifier = Modifier.weight(1f)
                )
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            // Section des commandes identique au web
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .weight(1f),
                colors = CardDefaults.cardColors(
                    containerColor = GlassBg
                ),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(16.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Commandes Disponibles",
                            style = SuzoskyTextStyles.sectionTitle,
                            color = Color.White
                        )
                        
                        Badge(
                            containerColor = PrimaryGold,
                            contentColor = PrimaryDark
                        ) {
                            Text(
                                text = "${commandes.filter { it.statut == "nouvelle" }.size}",
                                style = SuzoskyTextStyles.statusBadge
                            )
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    if (commandes.isEmpty()) {
                        // État vide identique au web
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(200.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(
                                horizontalAlignment = Alignment.CenterHorizontally
                            ) {
                                Icon(
                                    imageVector = Icons.Default.Assignment,
                                    contentDescription = null,
                                    tint = Color.White.copy(alpha = 0.6f),
                                    modifier = Modifier.size(48.dp)
                                )
                                Spacer(modifier = Modifier.height(16.dp))
                                Text(
                                    text = "Aucune commande disponible",
                                    style = SuzoskyTextStyles.subtitle,
                                    color = Color.White.copy(alpha = 0.6f),
                                    textAlign = TextAlign.Center
                                )
                                Text(
                                    text = "Les nouvelles commandes apparaîtront ici",
                                    style = SuzoskyTextStyles.bodyText,
                                    color = Color.White.copy(alpha = 0.4f),
                                    textAlign = TextAlign.Center
                                )
                            }
                        }
                    } else {
                        // Liste des commandes
                        LazyColumn(
                            verticalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            items(commandes) { commande ->
                                val commandeData = CommandeData(
                                    id = commande.id,
                                    statut = commande.statut ?: "",
                                    typeCommande = commande.typeCommande ?: "",
                                    nomClient = if ("nomClient" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "nomClient" }.call(commande) as? String ?: "") else "",
                                    telephone = if ("telephone" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "telephone" }.call(commande) as? String ?: "") else "",
                                    adresseRecuperation = if ("adresseRecuperation" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "adresseRecuperation" }.call(commande) as? String ?: "") else "",
                                    adresseLivraison = if ("adresseLivraison" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "adresseLivraison" }.call(commande) as? String ?: "") else "",
                                    instructions = if ("instructions" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "instructions" }.call(commande) as? String ?: "") else "",
                                    distanceKm = if ("distanceKm" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "distanceKm" }.call(commande) as? Float ?: 0f) else 0f,
                                    minutesAttente = if ("minutesAttente" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "minutesAttente" }.call(commande) as? Int ?: 0) else 0,
                                    heureCreation = if ("heureCreation" in commande::class.members.map { it.name }) (commande::class.members.first { it.name == "heureCreation" }.call(commande) as? String ?: "") else ""
                                )
                                CommandeCard(
                                    commande = commandeData,
                                    onAccepter = { onCommandeAccept(commande.id) },
                                    onRefuser = { onCommandeReject(commande.id) },
                                    onMettreEnAttente = { onCommandeAttente(commande.id) },
                                    onVoirDetails = { /* TODO: implement details */ }
                                )
                            }
                        }
                    }
                }
            }
        }
        
        // Menu latéral comme sur le web
        if (showMenu) {
            SuzoskyDrawerMenu(
                coursierNom = coursierNom,
                onDismiss = { showMenu = false },
                onNavigateToProfile = onNavigateToProfile,
                onNavigateToHistorique = onNavigateToHistorique,
                onNavigateToGains = onNavigateToGains,
                onLogout = {
                    onLogout()
                    showMenu = false
                }
            )
        }
        
        // Notifications comme sur le web
        if (showNotifications) {
            NotificationPanel(
                notifications = listOf(
                    "Nouvelle commande reçue",
                    "Mise à jour des tarifs",
                    "Votre statut est: $coursierStatut"
                ),
                onDismiss = { showNotifications = false }
            )
        }
    }
}

/**
 * Header identique au design web
 */
@Composable
fun SuzoskyHeader(
    titre: String,
    coursierNom: String,
    statut: String,
    onStatutChange: (String) -> Unit,
    onMenuClick: () -> Unit,
    onNotificationClick: () -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
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
                            imageVector = Icons.Default.Menu,
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
                        imageVector = Icons.Default.Notifications,
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
                    iconOn = Icons.Default.RadioButtonChecked,
                    iconOff = Icons.Default.RadioButtonUnchecked
                )
            }
        }
    }
}

/**
 * Card de statistiques identique au web
 */
@Composable
fun StatCard(
    title: String,
    value: String,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(12.dp)
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
                tint = PrimaryGold,
                modifier = Modifier.size(24.dp)
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = value,
                style = SuzoskyTextStyles.price,
                color = Color.White,
                textAlign = TextAlign.Center
            )
            
            Text(
                text = title,
                style = SuzoskyTextStyles.caption,
                color = Color.White.copy(alpha = 0.7f),
                textAlign = TextAlign.Center
            )
        }
    }
}
