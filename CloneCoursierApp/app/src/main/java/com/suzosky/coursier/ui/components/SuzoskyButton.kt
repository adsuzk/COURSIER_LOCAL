package com.suzosky.coursier.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.hapticfeedback.HapticFeedbackType
import androidx.compose.ui.platform.LocalHapticFeedback
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.ui.theme.*
import androidx.compose.ui.unit.sp

/**
 * Boutons Suzosky identiques au design web
 * Reproduit exactement les styles de coursier.php
 */

enum class SuzoskyButtonStyle {
    Primary,    // Gold gradient
    Success,    // Green gradient  
    Warning,    // Yellow/orange gradient
    Danger,     // Red gradient
    Secondary,  // Dark glass
    Ghost       // Transparent with border
}

@Composable
fun SuzoskyButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    style: SuzoskyButtonStyle = SuzoskyButtonStyle.Primary,
    icon: ImageVector? = null,
    enabled: Boolean = true,
    loading: Boolean = false
) {
    val haptic = LocalHapticFeedback.current
    
    val colors = when (style) {
        SuzoskyButtonStyle.Primary -> ButtonColors(
            background = GradientGold,
            contentColor = PrimaryDark,
            borderColor = PrimaryGold
        )
        SuzoskyButtonStyle.Success -> ButtonColors(
            background = GradientSuccess,
            contentColor = Color.White,
            borderColor = SuccessColor
        )
        SuzoskyButtonStyle.Warning -> ButtonColors(
            background = GradientWarning,
            contentColor = PrimaryDark,
            borderColor = WarningColor
        )
        SuzoskyButtonStyle.Danger -> ButtonColors(
            background = GradientDanger,
            contentColor = Color.White,
            borderColor = AccentRed
        )
        SuzoskyButtonStyle.Secondary -> ButtonColors(
            background = Brush.horizontalGradient(listOf(GlassBg, GlassBg)),
            contentColor = Color.White,
            borderColor = GlassBorder
        )
        SuzoskyButtonStyle.Ghost -> ButtonColors(
            background = Brush.horizontalGradient(listOf(Color.Transparent, Color.Transparent)),
            contentColor = Color.White,
            borderColor = GlassBorder
        )
    }
    
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(if (enabled) colors.background else Brush.horizontalGradient(listOf(Color.Gray.copy(alpha = 0.3f), Color.Gray.copy(alpha = 0.3f))))
            .border(
                1.dp,
                if (enabled) colors.borderColor else Color.Gray.copy(alpha = 0.5f),
                RoundedCornerShape(12.dp)
            )
            .clickable(enabled = enabled && !loading) {
                haptic.performHapticFeedback(HapticFeedbackType.LongPress)
                onClick()
            }
            .padding(horizontal = 20.dp, vertical = 14.dp),
        contentAlignment = Alignment.Center
    ) {
        if (loading) {
            CircularProgressIndicator(
                modifier = Modifier.size(20.dp),
                color = colors.contentColor,
                strokeWidth = 2.dp
            )
        } else {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                icon?.let {
                    Icon(
                        imageVector = it,
                        contentDescription = null,
                        tint = if (enabled) colors.contentColor else Color.Gray,
                        modifier = Modifier.size(18.dp)
                    )
                }
                
                Text(
                    text = text,
                    style = SuzoskyTextStyles.buttonMedium,
                    color = if (enabled) colors.contentColor else Color.Gray,
                    textAlign = TextAlign.Center
                )
            }
        }
    }
}

/**
 * Bouton floating action identique au web
 */
@Composable
fun SuzoskyFloatingActionButton(
    onClick: () -> Unit,
    icon: ImageVector,
    modifier: Modifier = Modifier,
    contentDescription: String? = null
) {
    Box(
        modifier = modifier
            .size(56.dp)
            .clip(RoundedCornerShape(28.dp))
            .background(GradientGold)
            .clickable { onClick() },
        contentAlignment = Alignment.Center
    ) {
        Icon(
            imageVector = icon,
            contentDescription = contentDescription,
            tint = PrimaryDark,
            modifier = Modifier.size(24.dp)
        )
    }
}

/**
 * Toggle button pour les statuts (En ligne/Hors ligne)
 */
@Composable
fun SuzoskyToggleButton(
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    textOn: String,
    textOff: String,
    modifier: Modifier = Modifier,
    iconOn: ImageVector? = null,
    iconOff: ImageVector? = null
) {
    val colors = if (checked) {
        ButtonColors(
            background = GradientSuccess,
            contentColor = Color.White,
            borderColor = SuccessColor
        )
    } else {
        ButtonColors(
            background = GradientDanger,
            contentColor = Color.White,
            borderColor = AccentRed
        )
    }
    
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(12.dp))
            .background(colors.background)
            .border(1.dp, colors.borderColor, RoundedCornerShape(12.dp))
            .clickable { onCheckedChange(!checked) }
            .padding(horizontal = 16.dp, vertical = 12.dp),
        contentAlignment = Alignment.Center
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            val icon = if (checked) iconOn else iconOff
            icon?.let {
                Icon(
                    imageVector = it,
                    contentDescription = null,
                    tint = colors.contentColor,
                    modifier = Modifier.size(18.dp)
                )
            }
            
            Text(
                text = if (checked) textOn else textOff,
                style = SuzoskyTextStyles.buttonMedium,
                color = colors.contentColor
            )
        }
    }
}

/**
 * Bouton de statut avec icône de statut
 */
@Composable
fun StatusButton(
    status: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val (color, text, icon) = when (status.lowercase()) {
        "nouvelle" -> Triple(StatusColors_nouvelle, "Nouvelle", "●")
        "attente" -> Triple(StatusColors_attente, "En attente", "⏳")
        "acceptee" -> Triple(StatusColors_acceptee, "Acceptée", "✓")
        "en_cours" -> Triple(StatusColors_enCours, "En cours", "🚴")
        "livree" -> Triple(StatusColors_livree, "Livrée", "✅")
        "annulee" -> Triple(StatusColors_annulee, "Annulée", "✗")
        else -> Triple(Color.Gray, status, "●")
    }
    
    Box(
        modifier = modifier
            .clip(RoundedCornerShape(20.dp))
            .background(color.copy(alpha = 0.2f))
            .border(1.dp, color, RoundedCornerShape(20.dp))
            .clickable { onClick() }
            .padding(horizontal = 12.dp, vertical = 6.dp),
        contentAlignment = Alignment.Center
    ) {
        Row(
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(6.dp)
        ) {
            Text(
                text = icon,
                color = color,
                style = SuzoskyTextStyles.statusBadge.copy(letterSpacing = 0.sp)
            )
            Text(
                text = text,
                style = SuzoskyTextStyles.statusBadge,
                color = color
            )
        }
    }
}

/**
 * Groupe de boutons radio style Suzosky
 */
@Composable
fun SuzoskyRadioGroup(
    options: List<String>,
    selectedOption: String,
    onOptionSelected: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(8.dp)
    ) {
        options.forEach { option ->
            val isSelected = option == selectedOption
            val colors = if (isSelected) {
                ButtonColors(
                    background = GradientGold,
                    contentColor = PrimaryDark,
                    borderColor = PrimaryGold
                )
            } else {
                ButtonColors(
                    background = Brush.horizontalGradient(listOf(Color.Transparent, Color.Transparent)),
                    contentColor = Color.White.copy(alpha = 0.7f),
                    borderColor = GlassBorder
                )
            }
            
            Box(
                modifier = Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .background(colors.background)
                    .border(1.dp, colors.borderColor, RoundedCornerShape(8.dp))
                    .clickable { onOptionSelected(option) }
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                contentAlignment = Alignment.Center
            ) {
                Text(
                    text = option,
                    style = SuzoskyTextStyles.buttonMedium,
                    color = colors.contentColor
                )
            }
        }
    }
}

/**
 * Classes de données pour les couleurs des boutons
 */
private data class ButtonColors(
    val background: Brush,
    val contentColor: Color,
    val borderColor: Color
)
