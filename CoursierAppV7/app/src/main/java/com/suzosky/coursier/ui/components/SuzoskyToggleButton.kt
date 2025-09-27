package com.suzosky.coursier.ui.components

import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.ui.theme.*

/**
 * Toggle button identique au web coursier.php
 * Animation et design premium Suzosky
 */
@Composable
fun SuzoskyToggleButton(
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    textOn: String,
    textOff: String,
    iconOn: ImageVector,
    iconOff: ImageVector,
    modifier: Modifier = Modifier
) {
    val animatedProgress by animateFloatAsState(
        targetValue = if (checked) 1f else 0f,
        animationSpec = tween(300),
        label = "toggle_animation"
    )
    
    val backgroundColor = if (checked) PrimaryGold else Color.Gray
    val textColor = if (checked) PrimaryDark else Color.White
    
    Card(
        modifier = modifier
            .clickable { onCheckedChange(!checked) },
        colors = CardDefaults.cardColors(
            containerColor = backgroundColor
        ),
        shape = RoundedCornerShape(20.dp)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(
                imageVector = if (checked) iconOn else iconOff,
                contentDescription = null,
                tint = textColor,
                modifier = Modifier.size(16.dp)
            )
            Text(
                text = if (checked) textOn else textOff,
                style = SuzoskyTextStyles.statusBadge,
                color = textColor
            )
        }
    }
}