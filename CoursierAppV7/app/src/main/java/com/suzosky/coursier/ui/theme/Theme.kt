package com.suzosky.coursier.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

private val DarkColors = darkColorScheme(
    primary = PrimaryGold,
    onPrimary = PrimaryDark,
    primaryContainer = SecondaryBlue,
    onPrimaryContainer = White80,
    secondary = AccentBlue,
    onSecondary = Color.White,
    secondaryContainer = SecondaryBlue,
    onSecondaryContainer = White80,
    tertiary = AccentRed,
    onTertiary = Color.White,
    background = BackgroundPrimary,
    onBackground = White80,
    surface = SecondaryBlue,
    onSurface = White80,
    surfaceVariant = GlassBg,
    onSurfaceVariant = White60,
    error = AccentRed,
    onError = Color.White,
    outline = GlassBorder,
    outlineVariant = White40
)

private val LightColors = lightColorScheme(
    primary = PrimaryGold,
    onPrimary = PrimaryDark,
    primaryContainer = PrimaryGoldLight,
    onPrimaryContainer = PrimaryDark,
    secondary = AccentBlue,
    onSecondary = Color.White,
    secondaryContainer = SecondaryBlue.copy(alpha = 0.1f),
    onSecondaryContainer = AccentBlue,
    tertiary = AccentRed,
    onTertiary = Color.White,
    background = Color.White,
    onBackground = PrimaryDark,
    surface = Color.White,
    onSurface = PrimaryDark,
    surfaceVariant = Color(0xFFF5F5F5),
    onSurfaceVariant = Color(0xFF555555),
    error = AccentRed,
    onError = Color.White,
    outline = Color(0xFFE0E0E0),
    outlineVariant = Color(0xFFBDBDBD)
)

@Composable
fun SuzoskyTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit
) {
    val colors = if (darkTheme) DarkColors else LightColors
    MaterialTheme(
        colorScheme = colors,
        typography = SuzoskyTypography,
        content = content
    )
}
