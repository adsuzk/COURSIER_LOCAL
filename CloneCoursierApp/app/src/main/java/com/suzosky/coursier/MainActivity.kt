package com.suzosky.coursier

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.core.view.WindowCompat
import com.suzosky.coursier.data.models.*
import com.suzosky.coursier.ui.screens.CoursierScreen
import com.suzosky.coursier.ui.screens.LoginScreen
import com.suzosky.coursier.ui.theme.SuzoskyTheme

/**
 * MainActivity - Point d'entrée de l'app Suzosky Coursier
 * Interface 100% identique au design web coursier.php
 */
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        
        // Configuration pour le mode edge-to-edge
        enableEdgeToEdge()
        WindowCompat.setDecorFitsSystemWindows(window, false)
        
        setContent {
            SuzoskyTheme {
                SuzoskyCoursierApp()
            }
        }
    }
}

@Composable
fun SuzoskyCoursierApp() {
    // État de l'application
    var coursierNom by remember { mutableStateOf("Jean Dupont") }
    var coursierStatut by remember { mutableStateOf("EN_LIGNE") }
    
    // Données de test pour les commandes
    val commandesTest = remember {
        listOf(
            Commande(
                id = "CMD001",
                clientNom = "Marie Kouassi",
                clientTelephone = "+225 07 12 34 56 78",
                adresseEnlevement = "Cocody Riviera, Abidjan",
                adresseLivraison = "Plateau, Centre-ville",
                distance = 8.5,
                tempsEstime = 25,
                prixTotal = 1700.0,
                prixLivraison = 1700.0,
                statut = "nouvelle",
                dateCommande = "2024-06-01",
                heureCommande = "10:00",
                description = "Livraison urgente de documents",
                typeCommande = "Express"
            )
        )
    }

    var isLoggedIn by remember { mutableStateOf(false) }
    if (!isLoggedIn) {
        LoginScreen(onLoginSuccess = { isLoggedIn = true })
    } else {
        var coursierStatut by remember { mutableStateOf("EN_LIGNE") }
        var commandes by remember { mutableStateOf<List<com.suzosky.coursier.network.CommandeApi>>(emptyList()) }
        var loading by remember { mutableStateOf(true) }
        var error by remember { mutableStateOf<String?>(null) }

        LaunchedEffect(isLoggedIn) {
            loading = true
            com.suzosky.coursier.network.ApiService.getCommandes { result, err ->
                if (result != null) {
                    commandes = result
                    error = null
                } else {
                    error = err
                }
                loading = false
            }
        }

        Scaffold(
            modifier = Modifier.fillMaxSize(),
            content = { paddingValues ->
                if (loading) {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator()
                    }
                } else if (error != null) {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        Text("Erreur: $error")
                    }
                } else {
                    CoursierScreen(
                        modifier = Modifier.padding(paddingValues),
                        coursierNom = coursierNom,
                        coursierStatut = coursierStatut,
                        commandes = commandes.map {
                            com.suzosky.coursier.data.models.Commande(
                                id = it.id,
                                                clientNom = it.clientNom ?: "",
                                                clientTelephone = it.clientTelephone ?: "",
                                                adresseEnlevement = it.adresseEnlevement ?: "",
                                                adresseLivraison = it.adresseLivraison ?: "",
                                                distance = it.distance ?: 0.0,
                                                tempsEstime = it.tempsEstime ?: 0,
                                                prixTotal = it.prixTotal ?: 0.0,
                                                prixLivraison = it.prixLivraison ?: 0.0,
                                                statut = it.statut ?: "",
                                                dateCommande = it.dateCommande ?: "",
                                                heureCommande = it.heureCommande ?: "",
                                                description = it.description ?: "",
                                                typeCommande = it.typeCommande ?: ""
                                            )
                                        },
                                        onStatutChange = { nouveauStatut ->
                                            coursierStatut = nouveauStatut
                                        },
                                        onCommandeAccept = { commandeId ->
                                            println("✅ Commande acceptée: $commandeId")
                                            // TODO: Implémenter l'acceptation de commande
                                        },
                                        onCommandeReject = { commandeId ->
                                            println("❌ Commande refusée: $commandeId")
                                            // TODO: Implémenter le refus de commande
                                        },
                                        onCommandeAttente = { commandeId ->
                                            println("⏳ Commande mise en attente: $commandeId")
                                            // TODO: Implémenter la mise en attente
                                        },
                                        onNavigateToProfile = {
                                            println("👤 Navigation vers profil")
                                            // TODO: Navigation vers écran profil
                                        },
                                        onNavigateToHistorique = {
                                            println("📋 Navigation vers historique")
                                            // TODO: Navigation vers historique
                                        },
                                        onNavigateToGains = {
                                            println("💰 Navigation vers gains")
                                            // TODO: Navigation vers gains
                                        },
                                        onLogout = {
                                            isLoggedIn = false
                                        }
                                    )
                                }
                            }
                        )
                    }
                }
