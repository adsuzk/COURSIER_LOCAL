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

// Couleurs modernes Suzosky
private val PrimaryGold = Color(0xFFD4A853)
private val PrimaryDark = Color(0xFF1A1A2E)
private val SecondaryBlue = Color(0xFF16213E)
private val GlassBg = Color(0x14FFFFFF)

enum class NavigationTab {
    COURSES, WALLET, CHAT, PROFILE
}

data class BottomNavItem(
    val tab: NavigationTab,
    val title: String,
    val iconSelected: ImageVector,
    val iconUnselected: ImageVector
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
            title = "Courses",
            iconSelected = Icons.Filled.LocalShipping,
            iconUnselected = Icons.Outlined.LocalShipping
        ),
        BottomNavItem(
            tab = NavigationTab.WALLET,
            title = "Wallet",
            iconSelected = Icons.Filled.AccountBalanceWallet,
            iconUnselected = Icons.Outlined.AccountBalanceWallet
        ),
        BottomNavItem(
            tab = NavigationTab.CHAT,
            title = "Support",
            iconSelected = Icons.Filled.Chat,
            iconUnselected = Icons.Outlined.ChatBubbleOutline
        ),
        BottomNavItem(
            tab = NavigationTab.PROFILE,
            title = "Profil",
            iconSelected = Icons.Filled.Person,
            iconUnselected = Icons.Outlined.PersonOutline
        )
    )

    Card(
        modifier = modifier
            .fillMaxWidth()
            .shadow(16.dp, RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp)),
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark
        ),
        shape = RoundedCornerShape(topStart = 20.dp, topEnd = 20.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .height(70.dp)
                .padding(horizontal = 8.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.SpaceEvenly,
            verticalAlignment = Alignment.CenterVertically
        ) {
            bottomNavItems.forEach { item ->
                ModernNavItem(
                    item = item,
                    isSelected = currentTab == item.tab,
                    onClick = { onTabSelected(item.tab) },
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@Composable
fun ModernNavItem(
    item: BottomNavItem,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    val iconColor by animateColorAsState(
        targetValue = if (isSelected) PrimaryGold else Color.White.copy(alpha = 0.5f),
        animationSpec = tween(300),
        label = "iconColor"
    )
    
    val textColor by animateColorAsState(
        targetValue = if (isSelected) PrimaryGold else Color.White.copy(alpha = 0.5f),
        animationSpec = tween(300),
        label = "textColor"
    )
    
    val iconSize by animateDpAsState(
        targetValue = if (isSelected) 28.dp else 24.dp,
        animationSpec = tween(300),
        label = "iconSize"
    )

    Surface(
        onClick = onClick,
        modifier = modifier
            .fillMaxHeight()
            .padding(4.dp),
        color = if (isSelected) GlassBg else Color.Transparent,
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(vertical = 4.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Box(
                contentAlignment = Alignment.Center,
                modifier = Modifier
                    .size(iconSize + 8.dp)
                    .then(
                        if (isSelected) {
                            Modifier.background(
                                brush = Brush.radialGradient(
                                    colors = listOf(
                                        PrimaryGold.copy(alpha = 0.2f),
                                        Color.Transparent
                                    )
                                ),
                                shape = CircleShape
                            )
                        } else Modifier
                    )
            ) {
                Icon(
                    imageVector = if (isSelected) item.iconSelected else item.iconUnselected,
                    contentDescription = item.title,
                    tint = iconColor,
                    modifier = Modifier.size(iconSize)
                )
            }
            
            Spacer(modifier = Modifier.height(2.dp))
            
            Text(
                text = item.title,
                color = textColor,
                fontSize = if (isSelected) 11.sp else 10.sp,
                fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
            )
        }
    }
}