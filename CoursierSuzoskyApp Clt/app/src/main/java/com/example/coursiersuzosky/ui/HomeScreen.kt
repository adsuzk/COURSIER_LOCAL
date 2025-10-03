package com.suzosky.coursierclient.ui

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.blur
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursierclient.ui.theme.*
import kotlinx.coroutines.delay

@Composable
fun HomeScreen(
    onNavigateToOrder: () -> Unit,
    modifier: Modifier = Modifier
) {
    var showWelcome by remember { mutableStateOf(true) }
    var animateContent by remember { mutableStateOf(false) }
    
    LaunchedEffect(Unit) {
        delay(100)
        animateContent = true
        delay(2500)
        showWelcome = false
    }
    
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        Dark,
                        SecondaryBlue,
                        Dark
                    )
                )
            )
    ) {
        // Background animated particles
        AnimatedBackgroundParticles()
        
        // Main content
        AnimatedVisibility(
            visible = !showWelcome,
            enter = fadeIn(animationSpec = tween(800)) + slideInVertically(
                initialOffsetY = { it / 2 },
                animationSpec = tween(800)
            )
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Spacer(modifier = Modifier.height(32.dp))
                
                // Premium logo section
                PremiumLogoSection()
                
                Spacer(modifier = Modifier.height(48.dp))
                
                // Quick action card
                QuickOrderCard(onNavigateToOrder = onNavigateToOrder)
                
                Spacer(modifier = Modifier.height(32.dp))
                
                // Feature highlights
                FeatureHighlights()
                
                Spacer(modifier = Modifier.height(32.dp))
                
                // Stats section
                StatsSection()
                
                Spacer(modifier = Modifier.height(48.dp))
            }
        }
        
        // Welcome splash
        AnimatedVisibility(
            visible = showWelcome,
            exit = fadeOut(animationSpec = tween(500)) + scaleOut(
                targetScale = 1.2f,
                animationSpec = tween(500)
            )
        ) {
            WelcomeSplash()
        }
    }
}

@Composable
private fun AnimatedBackgroundParticles() {
    val infiniteTransition = rememberInfiniteTransition(label = "particles")
    
    repeat(5) { index ->
        val offsetY by infiniteTransition.animateFloat(
            initialValue = 0f,
            targetValue = 1000f,
            animationSpec = infiniteRepeatable(
                animation = tween(
                    durationMillis = (8000 + index * 2000),
                    easing = LinearEasing
                ),
                repeatMode = RepeatMode.Restart
            ),
            label = "particle_$index"
        )
        
        Box(
            modifier = Modifier
                .offset(
                    x = (50 + index * 80).dp,
                    y = offsetY.dp - 100.dp
                )
                .size((8 + index * 2).dp)
                .alpha(0.15f)
                .clip(CircleShape)
                .background(Gold)
                .blur(4.dp)
        )
    }
}

@Composable
private fun WelcomeSplash() {
    val scale by rememberInfiniteTransition(label = "splash").animateFloat(
        initialValue = 0.95f,
        targetValue = 1.05f,
        animationSpec = infiniteRepeatable(
            animation = tween(1500, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "scale"
    )
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Dark),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            // Animated logo
            Box(
                modifier = Modifier
                    .scale(scale)
                    .size(120.dp)
                    .clip(CircleShape)
                    .background(
                        Brush.radialGradient(
                            colors = listOf(Gold, GoldLight)
                        )
                    )
                    .border(3.dp, Gold.copy(alpha = 0.3f), CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = Icons.Filled.LocalShipping,
                    contentDescription = null,
                    modifier = Modifier.size(60.dp),
                    tint = Dark
                )
            }
            
            Spacer(modifier = Modifier.height(32.dp))
            
            Text(
                text = "SUZOSKY",
                fontSize = 42.sp,
                fontWeight = FontWeight.Black,
                color = Gold,
                letterSpacing = 4.sp
            )
            
            Text(
                text = "Livraison Premium",
                fontSize = 14.sp,
                fontWeight = FontWeight.Light,
                color = Gold.copy(alpha = 0.7f),
                letterSpacing = 2.sp
            )
        }
    }
}

@Composable
private fun PremiumLogoSection() {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.animateContentSize()
    ) {
        // Icon with glow effect
        Box(
            modifier = Modifier
                .size(80.dp)
                .clip(CircleShape)
                .background(
                    Brush.radialGradient(
                        colors = listOf(
                            Gold.copy(alpha = 0.3f),
                            Color.Transparent
                        )
                    )
                )
                .blur(12.dp)
        )
        
        Box(
            modifier = Modifier
                .offset(y = (-70).dp)
                .size(70.dp)
                .clip(CircleShape)
                .background(
                    Brush.linearGradient(
                        colors = listOf(Gold, GoldLight)
                    )
                )
                .border(2.dp, Gold.copy(alpha = 0.5f), CircleShape),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Filled.Bolt,
                contentDescription = null,
                modifier = Modifier.size(36.dp),
                tint = Dark
            )
        }
        
        Spacer(modifier = Modifier.height((-50).dp))
        
        Text(
            text = "SUZOSKY",
            fontSize = 36.sp,
            fontWeight = FontWeight.Black,
            color = Gold,
            letterSpacing = 3.sp
        )
        
        Text(
            text = "Conciergerie Premium",
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium,
            color = Gold.copy(alpha = 0.7f),
            letterSpacing = 2.sp
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun QuickOrderCard(onNavigateToOrder: () -> Unit) {
    var isPressed by remember { mutableStateOf(false) }
    
    Card(
        onClick = {
            isPressed = true
            onNavigateToOrder()
        },
        modifier = Modifier
            .fillMaxWidth()
            .scale(if (isPressed) 0.98f else 1f),
        shape = RoundedCornerShape(28.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.05f)
        ),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    Brush.linearGradient(
                        colors = listOf(
                            Gold.copy(alpha = 0.15f),
                            Color.Transparent
                        )
                    )
                )
        ) {
            Column(
                modifier = Modifier
                    .padding(32.dp)
                    .fillMaxWidth(),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Icon(
                    imageVector = Icons.Filled.RocketLaunch,
                    contentDescription = null,
                    modifier = Modifier.size(56.dp),
                    tint = Gold
                )
                
                Spacer(modifier = Modifier.height(20.dp))
                
                Text(
                    text = "Nouvelle Commande",
                    fontSize = 24.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White,
                    textAlign = TextAlign.Center
                )
                
                Spacer(modifier = Modifier.height(8.dp))
                
                Text(
                    text = "Livraison express en 30 minutes",
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Normal,
                    color = Color.White.copy(alpha = 0.7f),
                    textAlign = TextAlign.Center
                )
                
                Spacer(modifier = Modifier.height(24.dp))
                
                Row(
                    horizontalArrangement = Arrangement.Center,
                    verticalAlignment = Alignment.CenterVertically,
                    modifier = Modifier
                        .clip(RoundedCornerShape(16.dp))
                        .background(Gold)
                        .padding(horizontal = 32.dp, vertical = 16.dp)
                ) {
                    Text(
                        text = "COMMANDER",
                        fontSize = 16.sp,
                        fontWeight = FontWeight.Bold,
                        color = Dark,
                        letterSpacing = 1.sp
                    )
                    
                    Spacer(modifier = Modifier.width(12.dp))
                    
                    Icon(
                        imageVector = Icons.Filled.ArrowForward,
                        contentDescription = null,
                        tint = Dark,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }
        }
    }
}

@Composable
private fun FeatureHighlights() {
    Column(
        modifier = Modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        Text(
            text = "Pourquoi nous choisir",
            fontSize = 20.sp,
            fontWeight = FontWeight.Bold,
            color = Gold,
            modifier = Modifier.padding(bottom = 8.dp)
        )
        
        FeatureItem(
            icon = Icons.Filled.Speed,
            title = "Livraison Express",
            description = "30 minutes en moyenne"
        )
        
        FeatureItem(
            icon = Icons.Filled.Verified,
            title = "Suivi en Temps Réel",
            description = "Géolocalisation précise"
        )
        
        FeatureItem(
            icon = Icons.Filled.Shield,
            title = "Sécurité Maximale",
            description = "Coursiers vérifiés et assurés"
        )
        
        FeatureItem(
            icon = Icons.Filled.SupportAgent,
            title = "Support 24/7",
            description = "Assistance instantanée"
        )
    }
}

@Composable
private fun FeatureItem(
    icon: ImageVector,
    title: String,
    description: String
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(Color.White.copy(alpha = 0.03f))
            .border(1.dp, Gold.copy(alpha = 0.1f), RoundedCornerShape(16.dp))
            .padding(20.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(48.dp)
                .clip(CircleShape)
                .background(Gold.copy(alpha = 0.15f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = Gold,
                modifier = Modifier.size(24.dp)
            )
        }
        
        Spacer(modifier = Modifier.width(16.dp))
        
        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = title,
                fontSize = 16.sp,
                fontWeight = FontWeight.SemiBold,
                color = Color.White
            )
            Text(
                text = description,
                fontSize = 13.sp,
                fontWeight = FontWeight.Normal,
                color = Color.White.copy(alpha = 0.6f)
            )
        }
    }
}

@Composable
private fun StatsSection() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        colors = CardDefaults.cardColors(
            containerColor = Gold.copy(alpha = 0.1f)
        ),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            horizontalArrangement = Arrangement.SpaceEvenly
        ) {
            StatItem(value = "10K+", label = "Livraisons")
            
            Box(
                modifier = Modifier
                    .width(1.dp)
                    .height(50.dp)
                    .background(Gold.copy(alpha = 0.3f))
            )
            
            StatItem(value = "4.9★", label = "Note")
            
            Box(
                modifier = Modifier
                    .width(1.dp)
                    .height(50.dp)
                    .background(Gold.copy(alpha = 0.3f))
            )
            
            StatItem(value = "30min", label = "Moyenne")
        }
    }
}

@Composable
private fun StatItem(value: String, label: String) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = value,
            fontSize = 28.sp,
            fontWeight = FontWeight.Black,
            color = Gold
        )
        Text(
            text = label,
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium,
            color = Color.White.copy(alpha = 0.7f)
        )
    }
}
