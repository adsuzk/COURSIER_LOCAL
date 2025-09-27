package com.suzosky.coursier.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.padding
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.RectangleShape
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.ui.theme.Dimens
import com.suzosky.coursier.ui.theme.GlassBg
import com.suzosky.coursier.ui.theme.GlassBorder

@Composable
fun GlassContainer(
    modifier: Modifier = Modifier,
    cornerRadius: Dp = Dimens.radius16,
    contentPadding: Dp = Dimens.space16,
    borderColor: Color = GlassBorder,
    content: @Composable () -> Unit
) {
    val shape = androidx.compose.foundation.shape.RoundedCornerShape(cornerRadius)
    Box(
        modifier = modifier
            .clip(shape)
            .background(GlassBg)
            .border(1.dp, borderColor, shape)
            .drawBehind {
                // Ombre interne subtile
                drawRoundRect(
                    color = Color.Black.copy(alpha = 0.12f),
                    size = size,
                    cornerRadius = androidx.compose.ui.geometry.CornerRadius(cornerRadius.toPx(), cornerRadius.toPx()),
                    style = androidx.compose.ui.graphics.drawscope.Fill
                )
            }
            .padding(contentPadding)
    ) { content() }
}
