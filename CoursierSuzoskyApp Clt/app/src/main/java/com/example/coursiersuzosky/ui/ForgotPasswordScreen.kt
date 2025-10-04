package com.suzosky.coursierclient.ui

import androidx.compose.animation.*
import androidx.compose.animation.core.tween
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursierclient.net.ApiService
import com.suzosky.coursierclient.ui.theme.*
import kotlinx.coroutines.launch

@Composable
fun ForgotPasswordScreen(
    onBackToLogin: () -> Unit,
    showMessage: (String) -> Unit
) {
    val scope = rememberCoroutineScope()
    val focusManager = LocalFocusManager.current
    
    var contact by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var visible by remember { mutableStateOf(false) }
    var contactError by remember { mutableStateOf<String?>(null) }
    var success by remember { mutableStateOf(false) }
    
    LaunchedEffect(Unit) {
        kotlinx.coroutines.delay(100)
        visible = true
    }
    
    fun validate(): Boolean {
        if (contact.isBlank()) {
            contactError = "Email ou téléphone requis"
            return false
        }
        val emailRegex = "[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}".toRegex()
        val phoneRegex = "\\+?225\\d{10}|\\d{10}".toRegex()
        if (!contact.matches(emailRegex) && !contact.matches(phoneRegex)) {
            contactError = "Format invalide"
            return false
        }
        contactError = null
        return true
    }
    
    fun doReset() {
        if (!validate()) return
        focusManager.clearFocus()
        scope.launch {
            loading = true
            try {
                val resp = ApiService.forgotPassword(contact)
                if (resp.success) {
                    success = true
                    showMessage(resp.message ?: "Instructions envoyées")
                } else {
                    showMessage(resp.error ?: resp.message ?: "Erreur lors de la réinitialisation")
                }
            } catch (e: Exception) {
                showMessage(ApiService.friendlyError(e))
            } finally {
                loading = false
            }
        }
    }
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Dark, SecondaryBlue, Dark)
                )
            )
            .imePadding()
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(40.dp))
            
            // Bouton retour
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Start
            ) {
                IconButton(onClick = onBackToLogin) {
                    Icon(
                        imageVector = Icons.Default.ArrowBack,
                        contentDescription = "Retour",
                        tint = Gold,
                        modifier = Modifier.size(28.dp)
                    )
                }
            }
            
            Spacer(Modifier.height(20.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(800)) + scaleIn(
                    initialScale = 0.8f,
                    animationSpec = tween(800)
                )
            ) {
                Box(
                    modifier = Modifier
                        .size(100.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.radialGradient(
                                colors = listOf(Gold, GoldLight)
                            )
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.LockReset,
                        contentDescription = "Reset",
                        modifier = Modifier.size(50.dp),
                        tint = Dark
                    )
                }
            }
            
            Spacer(Modifier.height(24.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 200))
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "Mot de passe oublié ?",
                        fontSize = 28.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                    Spacer(Modifier.height(12.dp))
                    Text(
                        text = "Entrez votre email ou téléphone pour recevoir les instructions de réinitialisation",
                        fontSize = 14.sp,
                        color = Color.White.copy(alpha = 0.7f),
                        textAlign = TextAlign.Center
                    )
                }
            }
            
            Spacer(Modifier.height(40.dp))
            
            if (success) {
                AnimatedVisibility(
                    visible = visible,
                    enter = fadeIn(animationSpec = tween(800))
                ) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = Color(0xFF4CAF50).copy(alpha = 0.2f)
                        ),
                        border = BorderStroke(1.dp, Color(0xFF4CAF50))
                    ) {
                        Row(
                            modifier = Modifier.padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.CheckCircle,
                                contentDescription = null,
                                tint = Color(0xFF4CAF50),
                                modifier = Modifier.size(32.dp)
                            )
                            Spacer(Modifier.width(12.dp))
                            Text(
                                text = "Instructions envoyées ! Vérifiez votre email ou SMS.",
                                color = Color.White,
                                fontSize = 14.sp
                            )
                        }
                    }
                }
                
                Spacer(Modifier.height(24.dp))
                
                AnimatedVisibility(
                    visible = visible,
                    enter = fadeIn(animationSpec = tween(800, delayMillis = 400))
                ) {
                    Button(
                        onClick = onBackToLogin,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        shape = RoundedCornerShape(16.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Gold
                        )
                    ) {
                        Text(
                            text = "Retour à la connexion",
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            color = Dark
                        )
                    }
                }
            } else {
                AnimatedVisibility(
                    visible = visible,
                    enter = fadeIn(animationSpec = tween(1000, delayMillis = 400))
                ) {
                    OutlinedTextField(
                        value = contact,
                        onValueChange = { contact = it; contactError = null },
                        label = { Text("Email ou Téléphone") },
                        leadingIcon = { Icon(Icons.Default.ContactMail, contentDescription = null) },
                        singleLine = true,
                        isError = contactError != null,
                        supportingText = contactError?.let { { Text(it, color = AccentRed) } },
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(16.dp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor = Gold,
                            unfocusedBorderColor = Color.White.copy(alpha = 0.3f),
                            focusedTextColor = Color.White,
                            unfocusedTextColor = Color.White.copy(alpha = 0.8f),
                            cursorColor = Gold,
                            errorBorderColor = AccentRed,
                            focusedLabelColor = Gold,
                            unfocusedLabelColor = Color.White.copy(alpha = 0.6f),
                            focusedLeadingIconColor = Gold,
                            unfocusedLeadingIconColor = Color.White.copy(alpha = 0.6f)
                        ),
                        keyboardOptions = KeyboardOptions(
                            keyboardType = KeyboardType.Email,
                            imeAction = ImeAction.Done
                        ),
                        keyboardActions = KeyboardActions(
                            onDone = { doReset() }
                        )
                    )
                }
                
                Spacer(Modifier.height(32.dp))
                
                AnimatedVisibility(
                    visible = visible,
                    enter = fadeIn(animationSpec = tween(1000, delayMillis = 600))
                ) {
                    Button(
                        onClick = { doReset() },
                        enabled = !loading,
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(60.dp),
                        shape = RoundedCornerShape(16.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Gold,
                            disabledContainerColor = Gold.copy(alpha = 0.5f)
                        )
                    ) {
                        if (loading) {
                            Row(
                                horizontalArrangement = Arrangement.Center,
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                CircularProgressIndicator(
                                    modifier = Modifier.size(24.dp),
                                    strokeWidth = 3.dp,
                                    color = Dark
                                )
                                Spacer(Modifier.width(12.dp))
                                Text(
                                    text = "Envoi...",
                                    fontSize = 16.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Dark
                                )
                            }
                        } else {
                            Text(
                                text = "Réinitialiser le mot de passe",
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = Dark
                            )
                        }
                    }
                }
            }
            
            Spacer(Modifier.height(32.dp))
        }
    }
}
