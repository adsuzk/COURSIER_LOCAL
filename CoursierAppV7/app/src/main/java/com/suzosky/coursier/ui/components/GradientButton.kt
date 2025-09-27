
package com.suzosky.coursier.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.ui.theme.Dimens
import com.suzosky.coursier.ui.theme.GradientGoldBrush
import com.suzosky.coursier.ui.theme.PrimaryDark
import com.suzosky.coursier.ui.theme.White80

@Composable
fun GradientButton(
    text: String,
    modifier: Modifier = Modifier,
    gradient: Brush = GradientGoldBrush,
    enabled: Boolean = true,
    onClick: () -> Unit
) {
    Box(
        modifier = modifier
            .background(gradient, shape = androidx.compose.foundation.shape.RoundedCornerShape(Dimens.radius24))
            .clickable(enabled = enabled) { onClick() }
            .height(48.dp)
            .padding(horizontal = 22.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text = text,
            style = TextStyle(
                color = androidx.compose.ui.graphics.Color.Black,
                fontSize = 16.sp,
                fontWeight = FontWeight.ExtraBold
            ),
            maxLines = 1
        )
    }
}
