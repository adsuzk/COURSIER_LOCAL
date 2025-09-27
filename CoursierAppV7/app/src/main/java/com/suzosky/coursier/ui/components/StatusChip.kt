package com.suzosky.coursier.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.tween
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.ui.theme.*

@Composable
fun StatusChip(
    label: String,
    active: Boolean,
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null
) {
    val (bg, border, fg) = when (label.lowercase()) {
        "en_ligne" -> Triple(AccentBlue.copy(alpha = if (active) 0.45f else 0.20f), AccentBlue, Color.White)
        "hors_ligne" -> Triple(AccentRed.copy(alpha = if (active) 0.30f else 0.15f), AccentRed, Color.White)
        else -> Triple(GlassBg, GlassBorder, White80)
    }
    val animAlpha = remember { Animatable(if (active) 1f else 0.6f) }
    LaunchedEffect(active) {
        animAlpha.animateTo(if (active) 1f else 0.6f, animationSpec = tween(durationMillis = 280))
    }
    Box(
        modifier = modifier
            .background(bg, shape = androidx.compose.foundation.shape.RoundedCornerShape(Dimens.radius24))
            .border(1.dp, border, shape = androidx.compose.foundation.shape.RoundedCornerShape(Dimens.radius24))
            .then(if (onClick != null) Modifier.clickable { onClick() } else Modifier)
            .padding(horizontal = 14.dp, vertical = 6.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = label.replaceFirstChar { it.uppercase() },
            color = fg.copy(alpha = animAlpha.value),
            fontWeight = if (active) FontWeight.Bold else FontWeight.Medium
        )
    }
}
