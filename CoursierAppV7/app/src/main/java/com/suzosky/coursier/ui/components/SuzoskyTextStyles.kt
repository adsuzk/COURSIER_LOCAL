package com.suzosky.coursier.ui.components

import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

object SuzoskyTextStyles {
    val subtitle @Composable get() = MaterialTheme.typography.bodyMedium
    
    val sectionTitle @Composable get() = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 22.sp)
    
    val bodyText @Composable get() = MaterialTheme.typography.bodyMedium.copy(fontSize = 16.sp)
    
    val price @Composable get() = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 20.sp)
    
    val caption @Composable get() = MaterialTheme.typography.labelMedium.copy(fontSize = 13.sp)
    
    val buttonMedium @Composable get() = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Medium, fontSize = 16.sp)
    
    val statusBadge @Composable get() = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Bold, fontSize = 14.sp)
    
    val brandTitle @Composable get() = MaterialTheme.typography.headlineMedium.copy(fontWeight = FontWeight.ExtraBold, fontSize = 28.sp)
    
    val greeting @Composable get() = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Medium, fontSize = 18.sp)
    
    val avatarText @Composable get() = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 20.sp)
    
    val menuItem @Composable get() = MaterialTheme.typography.bodyLarge.copy(fontSize = 16.sp)
    
    val commandeTitle @Composable get() = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 18.sp)
    
    val commandeBody @Composable get() = MaterialTheme.typography.bodyMedium.copy(fontSize = 15.sp)
    
    val commandeSubtitle @Composable get() = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Medium, fontSize = 16.sp)
    
    val priceLarge @Composable get() = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 24.sp)
    
    val formLabel @Composable get() = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Medium, fontSize = 14.sp)
}
