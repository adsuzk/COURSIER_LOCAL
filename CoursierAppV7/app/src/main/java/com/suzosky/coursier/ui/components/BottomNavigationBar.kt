package com.suzosky.coursier.ui.components

import androidx.compose.animation.animateColorAsState
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil.compose.AsyncImage
import com.suzosky.coursier.ui.theme.*

enum class NavigationTab {
    COURSES, WALLET, CHAT, PROFILE
}

data class BottomNavItem(
    val tab: NavigationTab,
    val title: String,
    val icon: ImageVector,
    val selectedColor: androidx.compose.ui.graphics.Color = SuzoskyPrimary,
    val unselectedColor: androidx.compose.ui.graphics.Color = androidx.compose.ui.graphics.Color.Gray
)

@Composable
fun BottomNavigationBar(
    currentTab: NavigationTab,
    onTabSelected: (NavigationTab) -> Unit,
    coursierPhoto: String? = null,
    modifier: Modifier = Modifier
) {
    val bottomNavItems = listOf(
        BottomNavItem(
            tab = NavigationTab.COURSES,
            title = "Mes Courses",
            icon = Icons.Filled.DeliveryDining
        ),
        BottomNavItem(
            tab = NavigationTab.WALLET,
            title = "Portefeuille",
            icon = Icons.Filled.Wallet
        ),
        BottomNavItem(
            tab = NavigationTab.CHAT,
            title = "Support",
            icon = Icons.Filled.Chat
        ),
        BottomNavItem(
            tab = NavigationTab.PROFILE,
            title = "Mon Profil",
            icon = Icons.Filled.AccountCircle
        )
    )

    NavigationBar(
        modifier = modifier,
        containerColor = PrimaryDark.copy(alpha = 0.95f),
        tonalElevation = 12.dp
    ) {
        bottomNavItems.forEach { item ->
            NavigationBarItem(
                icon = {
                    if (item.tab == NavigationTab.PROFILE && !coursierPhoto.isNullOrEmpty()) {
                        // Photo du coursier pour l'onglet Profil
                        AsyncImage(
                            model = coursierPhoto,
                            contentDescription = "Photo coursier",
                            modifier = Modifier
                                .size(24.dp)
                                .clip(CircleShape),
                            contentScale = ContentScale.Crop
                        )
                    } else {
                        Icon(
                            imageVector = item.icon,
                            contentDescription = item.title,
                            tint = if (currentTab == item.tab) item.selectedColor else item.unselectedColor
                        )
                    }
                },
                label = {
                    Text(
                        text = item.title,
                        color = if (currentTab == item.tab) item.selectedColor else item.unselectedColor
                    )
                },
                selected = currentTab == item.tab,
                onClick = { onTabSelected(item.tab) },
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = item.selectedColor,
                    selectedTextColor = item.selectedColor,
                    unselectedIconColor = item.unselectedColor,
                    unselectedTextColor = item.unselectedColor,
                    indicatorColor = item.selectedColor.copy(alpha = 0.2f)
                )
            )
        }
    }
}