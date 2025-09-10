package com.suzosky.coursier.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

/**
 * Thème Suzosky pour Android
 * Compatible avec Material Design 3 et API 35
 */

private val DarkColorScheme = darkColorScheme(
    primary = PrimaryGold,
    onPrimary = PrimaryDark,
    primaryContainer = PrimaryDark,
    onPrimaryContainer = PrimaryGold,
    secondary = SecondaryBlue,
    onSecondary = Color.White,
    secondaryContainer = AccentBlue,
    onSecondaryContainer = Color.White,
    tertiary = AccentRed,
    onTertiary = Color.White,
    error = AccentRed,
    onError = Color.White,
    background = BackgroundPrimary,
    onBackground = Color.White,
    surface = GlassBg,
    onSurface = Color.White,
    surfaceVariant = GlassBg,
    onSurfaceVariant = Color.White.copy(alpha = 0.8f),
    outline = GlassBorder,
    outlineVariant = GlassBorder.copy(alpha = 0.5f)
)

private val LightColorScheme = lightColorScheme(
    primary = PrimaryGold,
    onPrimary = PrimaryDark,
    primaryContainer = PrimaryGold.copy(alpha = 0.1f),
    onPrimaryContainer = PrimaryDark,
    secondary = SecondaryBlue,
    onSecondary = Color.White,
    secondaryContainer = SecondaryBlue.copy(alpha = 0.1f),
    onSecondaryContainer = SecondaryBlue,
    tertiary = AccentRed,
    onTertiary = Color.White,
    error = AccentRed,
    onError = Color.White,
    background = Color.White,
    onBackground = PrimaryDark,
    surface = Color.White,
    onSurface = PrimaryDark,
    surfaceVariant = Color.Gray.copy(alpha = 0.1f),
    onSurfaceVariant = PrimaryDark.copy(alpha = 0.8f),
    outline = Color.Gray,
    outlineVariant = Color.Gray.copy(alpha = 0.5f)
)

@Composable
fun SuzoskyTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colorScheme = if (darkTheme) {
        DarkColorScheme
    } else {
        LightColorScheme
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = SuzoskyTypography,
        content = content
    )
}
