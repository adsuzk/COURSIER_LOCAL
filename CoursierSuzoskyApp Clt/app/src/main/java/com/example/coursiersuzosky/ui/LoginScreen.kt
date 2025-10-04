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
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursierclient.net.ApiService
import com.suzosky.coursierclient.net.ApiConfig
import com.suzosky.coursierclient.BuildConfig
import com.suzosky.coursierclient.ui.theme.*
import kotlinx.coroutines.launch

@Composable
fun LoginScreen(
    onLoggedIn: () -> Unit,
    showMessage: (String) -> Unit
) {
    val scope = rememberCoroutineScope()
    val focusManager = LocalFocusManager.current
    
    var login by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var agentMode by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(false) }
    var passwordVisible by remember { mutableStateOf(false) }
    var visible by remember { mutableStateOf(false) }
    
    var loginError by remember { mutableStateOf<String?>(null) }
    var passwordError by remember { mutableStateOf<String?>(null) }
    
    LaunchedEffect(Unit) {
        kotlinx.coroutines.delay(100)
        visible = true
    }
    
    fun validate(): Boolean {
        var hasError = false
        
        if (login.isBlank()) {
            loginError = if (agentMode) "Matricule requis" else "Email ou téléphone requis"
            hasError = true
        } else if (!agentMode) {
            val emailRegex = "[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}".toRegex()
            val phoneRegex = "\\+?225\\d{10}|\\d{10}".toRegex()
            if (!login.matches(emailRegex) && !login.matches(phoneRegex)) {
                loginError = "Format invalide"
                hasError = true
            } else {
                loginError = null
            }
        } else if (agentMode && login.length < 3) {
            loginError = "Matricule invalide"
            hasError = true
        } else {
            loginError = null
        }
        
        if (password.isBlank()) {
            passwordError = "Mot de passe requis"
            hasError = true
        } else if (password.length < 5) {
            passwordError = "Minimum 5 caractères"
            hasError = true
        } else {
            passwordError = null
        }
        
        return !hasError
    }
    
    fun doLogin() {
        if (!validate()) return
        focusManager.clearFocus()
        scope.launch {
            loading = true
            try {
                val resp = if (agentMode) {
                    ApiService.agentLogin(login, password)
                } else {
                    ApiService.login(login, password)
                }
                if (resp.success) {
                    showMessage("Connexion réussie")
                    onLoggedIn()
                } else {
                    showMessage(resp.error ?: resp.message ?: "Identifiants invalides")
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
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(800)) + scaleIn(
                    initialScale = 0.8f,
                    animationSpec = tween(800)
                )
            ) {
                Box(
                    modifier = Modifier
                        .size(120.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.radialGradient(
                                colors = listOf(Gold, GoldLight)
                            )
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.LocalShipping,
                        contentDescription = "Logo",
                        modifier = Modifier.size(60.dp),
                        tint = Dark
                    )
                }
            }
            
            Spacer(Modifier.height(24.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 200)) + 
                        slideInVertically(initialOffsetY = { 30 })
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "SUZOSKY",
                        fontSize = 36.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold,
                        letterSpacing = 4.sp
                    )
                    Spacer(Modifier.height(8.dp))
                    Text(
                        text = "Livraison Premium",
                        fontSize = 14.sp,
                        fontWeight = FontWeight.Light,
                        color = Color.White.copy(alpha = 0.7f),
                        letterSpacing = 2.sp
                    )
                }
            }
            
            Spacer(Modifier.height(48.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 400))
            ) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(16.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = Color.White.copy(alpha = 0.1f)
                    ),
                    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.2f))
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column {
                            Text(
                                text = if (agentMode) "Agent" else "Client",
                                fontSize = 18.sp,
                                fontWeight = FontWeight.Bold,
                                color = Color.White
                            )
                            Text(
                                text = if (agentMode) "Coursier / Livreur" else "Passer une commande",
                                fontSize = 12.sp,
                                color = Color.White.copy(alpha = 0.6f)
                            )
                        }
                        Switch(
                            checked = agentMode,
                            onCheckedChange = { agentMode = it },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Gold,
                                checkedTrackColor = GoldLight,
                                uncheckedThumbColor = Color.White.copy(alpha = 0.6f),
                                uncheckedTrackColor = Color.White.copy(alpha = 0.2f)
                            )
                        )
                    }
                }
            }
            
            Spacer(Modifier.height(32.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 600))
            ) {
                OutlinedTextField(
                    value = login,
                    onValueChange = { login = it; loginError = null },
                    label = { Text(if (agentMode) "Matricule" else "Email ou Téléphone") },
                    leadingIcon = {
                        Icon(
                            if (agentMode) Icons.Default.Badge else Icons.Default.Person,
                            contentDescription = null
                        )
                    },
                    singleLine = true,
                    isError = loginError != null,
                    supportingText = loginError?.let { { Text(it, color = AccentRed) } },
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
                        keyboardType = if (agentMode) KeyboardType.Text else KeyboardType.Email,
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { focusManager.moveFocus(FocusDirection.Down) }
                    )
                )
            }
            
            Spacer(Modifier.height(16.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 800))
            ) {
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it; passwordError = null },
                    label = { Text("Mot de passe") },
                    leadingIcon = { Icon(Icons.Default.Lock, contentDescription = null) },
                    trailingIcon = {
                        IconButton(onClick = { passwordVisible = !passwordVisible }) {
                            Icon(
                                imageVector = if (passwordVisible) Icons.Default.Visibility else Icons.Default.VisibilityOff,
                                contentDescription = null,
                                tint = Color.White.copy(alpha = 0.6f)
                            )
                        }
                    },
                    visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                    singleLine = true,
                    isError = passwordError != null,
                    supportingText = passwordError?.let { { Text(it, color = AccentRed) } },
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
                        keyboardType = KeyboardType.Password,
                        imeAction = ImeAction.Done
                    ),
                    keyboardActions = KeyboardActions(
                        onDone = { doLogin() }
                    )
                )
            }
            
            Spacer(Modifier.height(32.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 1000))
            ) {
                Button(
                    onClick = { doLogin() },
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
                                text = "Connexion...",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                color = Dark
                            )
                        }
                    } else {
                        Text(
                            text = "Se connecter",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Dark,
                            letterSpacing = 1.sp
                        )
                    }
                }
            }
            
            Spacer(Modifier.height(24.dp))
            
            if (BuildConfig.DEBUG) {
                AnimatedVisibility(
                    visible = visible,
                    enter = fadeIn(animationSpec = tween(1000, delayMillis = 1200))
                ) {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(12.dp),
                        colors = CardDefaults.cardColors(
                            containerColor = Color.White.copy(alpha = 0.05f)
                        ),
                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
                    ) {
                        Column(
                            modifier = Modifier.padding(16.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(
                                    imageVector = Icons.Default.Info,
                                    contentDescription = null,
                                    tint = Gold,
                                    modifier = Modifier.size(16.dp)
                                )
                                Spacer(Modifier.width(8.dp))
                                Text(
                                    text = "Mode Debug",
                                    fontSize = 14.sp,
                                    fontWeight = FontWeight.Bold,
                                    color = Gold
                                )
                            }
                            Spacer(Modifier.height(8.dp))
                            Text(
                                text = "Backend: " + ApiConfig.BASE_URL,
                                fontSize = 11.sp,
                                color = Color.White.copy(alpha = 0.5f),
                                textAlign = TextAlign.Center,
                                modifier = Modifier.fillMaxWidth()
                            )
                        }
                    }
                }
            }
            
            Spacer(Modifier.height(32.dp))
        }
    }
}