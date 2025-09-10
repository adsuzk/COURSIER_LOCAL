package com.suzosky.coursier.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color

/**
 * Thème Suzosky Coursier - Reproduction exacte de l'interface web
 * Basé sur coursier.php avec les couleurs officielles
 */

// Couleurs principales Suzosky (identiques à coursier.php)
val PrimaryGold = Color(0xFFD4A853)
val PrimaryDark = Color(0xFF1A1A2E)
val SecondaryBlue = Color(0xFF16213E)
val AccentBlue = Color(0xFF0F3460)
val AccentRed = Color(0xFFE94560)
val SuccessColor = Color(0xFF27AE60)
val WarningColor = Color(0xFFFFC107)
val DangerColor = Color(0xFFE94560)

// Couleurs de transparence (Glass morphism)
val BackgroundPrimary = PrimaryDark
val GlassBg = Color(0x14FFFFFF) // rgba(255,255,255,0.08)
val GlassBorder = Color(0x33FFFFFF) // rgba(255,255,255,0.2)

// Gradients Suzosky
val GradientGold = Brush.horizontalGradient(
    colors = listOf(
        Color(0xFFD4A853),
        Color(0xFFF4E4B8),
        Color(0xFFD4A853)
    )
)

val GradientDark = Brush.horizontalGradient(
    colors = listOf(
        Color(0xFF1A1A2E),
        Color(0xFF16213E)
    )
)

val GradientSuccess = Brush.horizontalGradient(
    colors = listOf(
        Color(0xFF28a745),
        Color(0xFF34ce57)
    )
)

val GradientWarning = Brush.horizontalGradient(
    colors = listOf(
        Color(0xFFFFC107),
        Color(0xFFFFCD39)
    )
)

val GradientDanger = Brush.horizontalGradient(
    colors = listOf(
        AccentRed,
        Color(0xFFFF6B6B)
    )
)

// Couleurs pour les statuts (identiques à coursier.php)
val StatusColors_nouvelle = Color(0xFFFFC107) // Jaune warning
val StatusColors_attente = Color(0xFF17A2B8) // Bleu info
val StatusColors_acceptee = Color(0xFF28A745) // Vert success
val StatusColors_enCours = Color(0xFF007BFF) // Bleu primary
val StatusColors_livree = Color(0xFF28A745) // Vert success
val StatusColors_annulee = Color(0xFFDC3545) // Rouge danger
val StatusColors_probleme = Color(0xFFE94560) // Rouge accent

// Schémas de couleurs Material 3
private val DarkColorScheme = darkColorScheme(
    primary = PrimaryGold,
    onPrimary = PrimaryDark,
    primaryContainer = SecondaryBlue,
    onPrimaryContainer = Color.White,
    
    secondary = AccentBlue,
    onSecondary = Color.White,
    secondaryContainer = Color(0xFF2A2A3E),
    onSecondaryContainer = Color.White,
    
    tertiary = AccentRed,
    onTertiary = Color.White,
    
    background = PrimaryDark,
    onBackground = Color.White,
    
    surface = SecondaryBlue,
    onSurface = Color.White,
    surfaceVariant = Color(0xFF2A2A3E),
    onSurfaceVariant = Color(0xFFE0E0E0),
    
    error = DangerColor,
    onError = Color.White,
    
    outline = GlassBorder,
    outlineVariant = Color(0xFF3A3A4E)
)

private val LightColorScheme = lightColorScheme(
    primary = PrimaryGold,
    onPrimary = Color.White,
    primaryContainer = Color(0xFFF4E4B8),
    onPrimaryContainer = PrimaryDark,
    
    secondary = AccentBlue,
    onSecondary = Color.White,
    secondaryContainer = Color(0xFFE3F2FD),
    onSecondaryContainer = AccentBlue,
    
    tertiary = AccentRed,
    onTertiary = Color.White,
    
    background = Color.White,
    onBackground = PrimaryDark,
    
    surface = Color.White,
    onSurface = PrimaryDark,
    surfaceVariant = Color(0xFFF5F5F5),
    onSurfaceVariant = Color(0xFF757575),
    
    error = DangerColor,
    onError = Color.White,
    
    outline = Color(0xFFE0E0E0),
    outlineVariant = Color(0xFFF0F0F0)
)

@Composable
fun SuzoskyCoursierTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    dynamicColor: Boolean = false, // Désactivé pour conserver les couleurs Suzosky
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        dynamicColor && android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.S -> {
            if (darkTheme) DarkColorScheme else LightColorScheme
        }
        darkTheme -> DarkColorScheme
        else -> LightColorScheme
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = SuzoskyTypography,
        content = content
    )
}

/**
 * Extensions pour faciliter l'usage des couleurs Suzosky
 */
@Composable
fun Color.Companion.suzoskyGold() = PrimaryGold

@Composable
fun Color.Companion.suzoskyDark() = PrimaryDark

@Composable
fun Color.Companion.suzoskySuccess() = SuccessColor

@Composable
fun Color.Companion.suzoskyWarning() = WarningColor

@Composable
fun Color.Companion.suzoskyDanger() = AccentRed

@Composable
fun Color.Companion.suzoskyGlass() = GlassBg

/**
 * Fonction utilitaire pour obtenir la couleur d'un statut
 */
@Composable
fun getStatusColor(status: String): Color {
    return when (status.lowercase()) {
        "nouvelle" -> StatusColors_nouvelle
        "attente" -> StatusColors_attente
        "acceptee", "acceptée" -> StatusColors_acceptee
        "en_cours", "en cours" -> StatusColors_enCours
        "livree", "livrée" -> StatusColors_livree
        "annulee", "annulée" -> StatusColors_annulee
        "probleme", "problème" -> StatusColors_probleme
        else -> Color.Gray
    }
}
