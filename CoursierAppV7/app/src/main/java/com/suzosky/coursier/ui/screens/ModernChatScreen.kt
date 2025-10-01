package com.suzosky.coursier.ui.screens

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*

// Couleurs Suzosky
private val PrimaryGold = Color(0xFFD4A853)
private val PrimaryDark = Color(0xFF1A1A2E)
private val SecondaryBlue = Color(0xFF16213E)
private val SuccessGreen = Color(0xFF27AE60)
private val GlassBg = Color(0x14FFFFFF)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ModernChatScreen(
    coursierNom: String,
    messages: List<ChatMessage>,
    onSendMessage: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    var messageText by remember { mutableStateOf("") }
    val listState = rememberLazyListState()
    val scope = rememberCoroutineScope()
    
    // Auto-scroll to bottom when new message
    LaunchedEffect(messages.size) {
        if (messages.isNotEmpty()) {
            scope.launch {
                listState.animateScrollToItem(messages.size - 1)
            }
        }
    }
    
    Box(
        modifier = modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        PrimaryDark,
                        SecondaryBlue.copy(alpha = 0.8f)
                    )
                )
            )
    ) {
        Column(
            modifier = Modifier.fillMaxSize()
        ) {
            // Header moderne
            ChatHeader(coursierNom = coursierNom)
            
            // Messages list
            if (messages.isEmpty()) {
                EmptyChatState()
            } else {
                LazyColumn(
                    state = listState,
                    modifier = Modifier
                        .weight(1f)
                        .fillMaxWidth(),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(messages) { message ->
                        MessageBubble(message = message)
                    }
                }
            }
            
            // Input bar moderne
            ModernMessageInput(
                messageText = messageText,
                onMessageChange = { messageText = it },
                onSendClick = {
                    if (messageText.isNotBlank()) {
                        onSendMessage(messageText)
                        messageText = ""
                    }
                }
            )
        }
    }
}

@Composable
fun ChatHeader(coursierNom: String) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(8.dp),
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark
        ),
        shape = RoundedCornerShape(bottomStart = 24.dp, bottomEnd = 24.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Avatar support
            Box(
                modifier = Modifier
                    .size(56.dp)
                    .clip(CircleShape)
                    .background(
                        Brush.linearGradient(
                            colors = listOf(PrimaryGold, PrimaryGold.copy(alpha = 0.7f))
                        )
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.SupportAgent,
                    contentDescription = "Support",
                    tint = PrimaryDark,
                    modifier = Modifier.size(32.dp)
                )
            }
            
            Column(modifier = Modifier.weight(1f)) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    Text(
                        text = "Support Suzosky",
                        fontSize = 20.sp,
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                    // Badge en ligne
                    Box(
                        modifier = Modifier
                            .size(10.dp)
                            .clip(CircleShape)
                            .background(SuccessGreen)
                    )
                }
                Text(
                    text = "Réponse en quelques minutes",
                    fontSize = 13.sp,
                    color = Color.White.copy(alpha = 0.7f)
                )
            }
            
            // Actions
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                IconButton(
                    onClick = { /* Info */ },
                    modifier = Modifier
                        .size(40.dp)
                        .background(GlassBg, CircleShape)
                ) {
                    Icon(
                        Icons.Filled.Info,
                        contentDescription = "Info",
                        tint = PrimaryGold,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }
        }
    }
}

@Composable
fun EmptyChatState() {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        contentAlignment = Alignment.Center
    ) {
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            // Icône animée
            Box(
                modifier = Modifier
                    .size(120.dp)
                    .clip(CircleShape)
                    .background(
                        Brush.radialGradient(
                            colors = listOf(
                                PrimaryGold.copy(alpha = 0.3f),
                                Color.Transparent
                            )
                        )
                    ),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    Icons.Filled.ChatBubbleOutline,
                    contentDescription = null,
                    tint = PrimaryGold,
                    modifier = Modifier.size(60.dp)
                )
            }
            
            Text(
                text = "Démarrez une conversation",
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White
            )
            Text(
                text = "Notre équipe support est là pour vous aider\n24h/24, 7j/7",
                fontSize = 14.sp,
                color = Color.White.copy(alpha = 0.7f),
                textAlign = TextAlign.Center
            )
            
            Spacer(modifier = Modifier.height(8.dp))
            
            // Quick replies
            Column(
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.fillMaxWidth(0.8f)
            ) {
                Text(
                    text = "Questions fréquentes :",
                    fontSize = 13.sp,
                    color = PrimaryGold,
                    fontWeight = FontWeight.SemiBold
                )
                
                QuickReplyButton(
                    icon = Icons.Filled.Help,
                    text = "Comment fonctionne l'application ?"
                )
                QuickReplyButton(
                    icon = Icons.Filled.AccountBalanceWallet,
                    text = "Problème de paiement"
                )
                QuickReplyButton(
                    icon = Icons.Filled.Navigation,
                    text = "Aide à la navigation"
                )
                QuickReplyButton(
                    icon = Icons.Filled.Report,
                    text = "Signaler un problème"
                )
            }
        }
    }
}

@Composable
fun QuickReplyButton(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    text: String,
    onClick: () -> Unit = {}
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Icon(
                icon,
                contentDescription = null,
                tint = PrimaryGold,
                modifier = Modifier.size(20.dp)
            )
            Text(
                text = text,
                fontSize = 14.sp,
                color = Color.White,
                modifier = Modifier.weight(1f)
            )
            Icon(
                Icons.Filled.ChevronRight,
                contentDescription = null,
                tint = Color.White.copy(alpha = 0.5f),
                modifier = Modifier.size(20.dp)
            )
        }
    }
}

@Composable
fun MessageBubble(message: ChatMessage) {
    val dateFormat = remember { SimpleDateFormat("HH:mm", Locale.getDefault()) }
    
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = if (message.isFromCoursier) Alignment.End else Alignment.Start
    ) {
        // Nom de l'expéditeur (seulement pour admin)
        if (!message.isFromCoursier) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(6.dp),
                modifier = Modifier.padding(start = 8.dp, bottom = 4.dp)
            ) {
                Box(
                    modifier = Modifier
                        .size(20.dp)
                        .clip(CircleShape)
                        .background(PrimaryGold),
                    contentAlignment = Alignment.Center
                ) {
                    Text(
                        text = message.senderName.first().uppercase(),
                        fontSize = 10.sp,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryDark
                    )
                }
                Text(
                    text = message.senderName,
                    fontSize = 12.sp,
                    fontWeight = FontWeight.SemiBold,
                    color = PrimaryGold
                )
            }
        }
        
        // Bulle de message
        Card(
            modifier = Modifier
                .widthIn(max = 280.dp)
                .shadow(
                    elevation = 4.dp,
                    shape = RoundedCornerShape(
                        topStart = if (message.isFromCoursier) 20.dp else 4.dp,
                        topEnd = if (message.isFromCoursier) 4.dp else 20.dp,
                        bottomStart = 20.dp,
                        bottomEnd = 20.dp
                    )
                ),
            colors = CardDefaults.cardColors(
                containerColor = if (message.isFromCoursier) {
                    PrimaryGold
                } else {
                    Color.White.copy(alpha = 0.15f)
                }
            ),
            shape = RoundedCornerShape(
                topStart = if (message.isFromCoursier) 20.dp else 4.dp,
                topEnd = if (message.isFromCoursier) 4.dp else 20.dp,
                bottomStart = 20.dp,
                bottomEnd = 20.dp
            )
        ) {
            Column(
                modifier = Modifier.padding(12.dp)
            ) {
                Text(
                    text = message.message,
                    fontSize = 15.sp,
                    color = if (message.isFromCoursier) PrimaryDark else Color.White,
                    lineHeight = 20.sp
                )
                
                Row(
                    modifier = Modifier
                        .align(Alignment.End)
                        .padding(top = 4.dp),
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(4.dp)
                ) {
                    Text(
                        text = dateFormat.format(message.timestamp),
                        fontSize = 11.sp,
                        color = if (message.isFromCoursier) {
                            PrimaryDark.copy(alpha = 0.6f)
                        } else {
                            Color.White.copy(alpha = 0.6f)
                        }
                    )
                    
                    // Double check pour les messages du coursier
                    if (message.isFromCoursier) {
                        Icon(
                            if (message.isRead) Icons.Filled.DoneAll else Icons.Filled.Done,
                            contentDescription = null,
                            tint = if (message.isRead) SuccessGreen else PrimaryDark.copy(alpha = 0.6f),
                            modifier = Modifier.size(16.dp)
                        )
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ModernMessageInput(
    messageText: String,
    onMessageChange: (String) -> Unit,
    onSendClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(12.dp),
        colors = CardDefaults.cardColors(
            containerColor = PrimaryDark
        ),
        shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.Bottom,
            horizontalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Bouton pièce jointe
            IconButton(
                onClick = { /* Attachments */ },
                modifier = Modifier
                    .size(44.dp)
                    .background(GlassBg, CircleShape)
            ) {
                Icon(
                    Icons.Filled.AttachFile,
                    contentDescription = "Joindre fichier",
                    tint = PrimaryGold,
                    modifier = Modifier.size(22.dp)
                )
            }
            
            // Champ de texte
            TextField(
                value = messageText,
                onValueChange = onMessageChange,
                modifier = Modifier
                    .weight(1f)
                    .heightIn(min = 44.dp, max = 120.dp),
                placeholder = {
                    Text(
                        text = "Écrivez votre message...",
                        color = Color.White.copy(alpha = 0.5f)
                    )
                },
                colors = TextFieldDefaults.colors(
                    focusedContainerColor = GlassBg,
                    unfocusedContainerColor = GlassBg,
                    focusedTextColor = Color.White,
                    unfocusedTextColor = Color.White,
                    cursorColor = PrimaryGold,
                    focusedIndicatorColor = Color.Transparent,
                    unfocusedIndicatorColor = Color.Transparent
                ),
                shape = RoundedCornerShape(22.dp),
                maxLines = 4
            )
            
            // Bouton envoyer
            IconButton(
                onClick = onSendClick,
                modifier = Modifier
                    .size(44.dp)
                    .background(
                        brush = Brush.linearGradient(
                            colors = listOf(PrimaryGold, PrimaryGold.copy(alpha = 0.8f))
                        ),
                        shape = CircleShape
                    ),
                enabled = messageText.isNotBlank()
            ) {
                Icon(
                    Icons.Filled.Send,
                    contentDescription = "Envoyer",
                    tint = PrimaryDark,
                    modifier = Modifier.size(22.dp)
                )
            }
        }
    }
}
