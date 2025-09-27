package com.suzosky.coursier.ui.theme

import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color

// Suzosky Brand Colors (compatibilité nouvelle UI)
val SuzoskyPrimary = Color(0xFF1A73E8)
val SuzoskySecondary = Color(0xFF6C47FF)
val SuzoskyAccent = Color(0xFFFFC107)

// Core Brand Palette
val PrimaryGold = Color(0xFFD4A853)
val PrimaryGoldLight = Color(0xFFF4E4B8)
val PrimaryDark = Color(0xFF1A1A2E)
val SecondaryBlue = Color(0xFF16213E)
val AccentBlue = Color(0xFF0F3460)
val AccentRed = Color(0xFFE94560)
val SuccessGreen = Color(0xFF27AE60) // Match --success-color from coursier.php
val SuccessGreenAlt = Color(0xFF28a745) // Alternative success color from coursier.php
val WarningYellow = Color(0xFFFFC107) // Match --warning-color from coursier.php

// Glass / Transparency
val GlassBg = Color(0x14FFFFFF) // ~8% white
val GlassBorder = Color(0x33FFFFFF) // ~20% white
val BackgroundPrimary = PrimaryDark

// Gradients (converted later into Brushes where needed)
val GradientGoldBrush = Brush.linearGradient(listOf(PrimaryGold, PrimaryGoldLight, PrimaryGold))
val GradientDarkBrush = Brush.linearGradient(listOf(PrimaryDark, SecondaryBlue))
val GradientDarkGoldBrush = Brush.linearGradient(listOf(PrimaryDark, PrimaryGold.copy(alpha = 0.35f), PrimaryDark))
val GradientDangerBrush = Brush.linearGradient(listOf(AccentRed, Color(0xFFFF6B6B)))

// Status Colors
val StatusNouvelle = WarningYellow
val StatusAttente = Color(0xFF17A2B8)
val StatusAcceptee = SuccessGreen
val StatusEnCours = Color(0xFF007BFF)
val StatusLivree = SuccessGreen
val StatusAnnulee = Color(0xFFDC3545)
val StatusProbleme = AccentRed

// Utility opacities
val White80 = Color(0xCCFFFFFF)
val White60 = Color(0x99FFFFFF)
val White40 = Color(0x66FFFFFF)
val White20 = Color(0x33FFFFFF)
val White10 = Color(0x1AFFFFFF)
val Black40 = Color(0x66000000)

// Extension utilitaires simples (non @Composable pour éviter erreurs si utilisées hors scope compose)
fun Color.Companion.suzoskyDark() = PrimaryDark
fun Color.Companion.suzoskySuccess() = SuccessGreen
fun Color.Companion.suzoskyWarning() = WarningYellow
fun Color.Companion.suzoskyDanger() = AccentRed
fun Color.Companion.suzoskyGlass() = GlassBg
fun Color.Companion.suzoskyGold() = PrimaryGold

// Compatibilité avec anciens noms pour éviter erreurs de compilation
val StatusColors_nouvelle = StatusNouvelle
val StatusColors_attente = StatusAttente  
val StatusColors_acceptee = StatusAcceptee
val StatusColors_enCours = StatusEnCours
val StatusColors_livree = StatusLivree
val StatusColors_annulee = StatusAnnulee
val StatusColors_probleme = StatusProbleme

val suzoskyGold = PrimaryGold
val GradientGold = GradientGoldBrush
val GradientSuccess = Brush.linearGradient(listOf(SuccessGreen, Color(0xFF2ECC71)))
val GradientWarning = Brush.linearGradient(listOf(WarningYellow, Color(0xFFFFD700)))
val GradientDanger = GradientDangerBrush
val SuccessColor = SuccessGreen
val WarningColor = WarningYellow

/**
 * Fonction utilitaire pour obtenir la couleur d'un statut
 */
fun getStatusColor(status: String): Color = when (status.lowercase()) {
    "nouvelle" -> StatusNouvelle
    "attente" -> StatusAttente
    "acceptee", "acceptée" -> StatusAcceptee
    "en_cours", "en cours" -> StatusEnCours
    "livree", "livrée" -> StatusLivree
    "annulee", "annulée" -> StatusAnnulee
    "probleme", "problème" -> StatusProbleme
    else -> Color.Gray
}
