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
import com.suzosky.coursierclient.ui.theme.*
import kotlinx.coroutines.launch

@Composable
fun RegisterScreen(
    onBackToLogin: () -> Unit,
    onRegistered: () -> Unit,
    showMessage: (String) -> Unit
) {
    val scope = rememberCoroutineScope()
    val focusManager = LocalFocusManager.current
    
    var nom by remember { mutableStateOf("") }
    var prenoms by remember { mutableStateOf("") }
    var telephone by remember { mutableStateOf("") }
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var confirmPassword by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var passwordVisible by remember { mutableStateOf(false) }
    var confirmPasswordVisible by remember { mutableStateOf(false) }
    var visible by remember { mutableStateOf(false) }
    
    var nomError by remember { mutableStateOf<String?>(null) }
    var prenomsError by remember { mutableStateOf<String?>(null) }
    var telephoneError by remember { mutableStateOf<String?>(null) }
    var emailError by remember { mutableStateOf<String?>(null) }
    var passwordError by remember { mutableStateOf<String?>(null) }
    var confirmPasswordError by remember { mutableStateOf<String?>(null) }
    
    LaunchedEffect(Unit) {
        kotlinx.coroutines.delay(100)
        visible = true
    }
    
    fun validate(): Boolean {
        var hasError = false
        
        if (nom.isBlank()) {
            nomError = "Nom requis"
            hasError = true
        } else {
            nomError = null
        }
        
        if (prenoms.isBlank()) {
            prenomsError = "Prénom(s) requis"
            hasError = true
        } else {
            prenomsError = null
        }
        
        if (telephone.isBlank()) {
            telephoneError = "Téléphone requis"
            hasError = true
        } else if (!telephone.matches("\\+?225\\d{10}|\\d{10}".toRegex())) {
            telephoneError = "Format invalide (+225XXXXXXXXXX)"
            hasError = true
        } else {
            telephoneError = null
        }
        
        if (email.isBlank()) {
            emailError = "Email requis"
            hasError = true
        } else if (!email.matches("[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}".toRegex())) {
            emailError = "Email invalide"
            hasError = true
        } else {
            emailError = null
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
        
        if (confirmPassword.isBlank()) {
            confirmPasswordError = "Confirmation requise"
            hasError = true
        } else if (confirmPassword != password) {
            confirmPasswordError = "Les mots de passe ne correspondent pas"
            hasError = true
        } else {
            confirmPasswordError = null
        }
        
        return !hasError
    }
    
    fun doRegister() {
        if (!validate()) return
        focusManager.clearFocus()
        scope.launch {
            loading = true
            try {
                val resp = ApiService.register(
                    nom = nom,
                    prenoms = prenoms,
                    telephone = telephone,
                    email = email,
                    password = password
                )
                if (resp.success) {
                    showMessage("Inscription réussie ! Connexion automatique...")
                    kotlinx.coroutines.delay(1500)
                    onRegistered()
                } else {
                    showMessage(resp.error ?: resp.message ?: "Erreur lors de l'inscription")
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
            Spacer(Modifier.height(20.dp))
            
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
            
            Spacer(Modifier.height(10.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(800)) + scaleIn(
                    initialScale = 0.8f,
                    animationSpec = tween(800)
                )
            ) {
                Box(
                    modifier = Modifier
                        .size(90.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.radialGradient(
                                colors = listOf(Gold, GoldLight)
                            )
                        ),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.PersonAdd,
                        contentDescription = "Register",
                        modifier = Modifier.size(45.dp),
                        tint = Dark
                    )
                }
            }
            
            Spacer(Modifier.height(16.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 200))
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text(
                        text = "Créer un compte",
                        fontSize = 28.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                    Spacer(Modifier.height(8.dp))
                    Text(
                        text = "Rejoignez SUZOSKY",
                        fontSize = 13.sp,
                        color = Color.White.copy(alpha = 0.7f)
                    )
                }
            }
            
            Spacer(Modifier.height(32.dp))
            
            // Nom
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 300))
            ) {
                OutlinedTextField(
                    value = nom,
                    onValueChange = { nom = it; nomError = null },
                    label = { Text("Nom") },
                    leadingIcon = { Icon(Icons.Default.Person, contentDescription = null) },
                    singleLine = true,
                    isError = nomError != null,
                    supportingText = nomError?.let { { Text(it, color = AccentRed) } },
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
                        keyboardType = KeyboardType.Text,
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { focusManager.moveFocus(FocusDirection.Down) }
                    )
                )
            }
            
            Spacer(Modifier.height(16.dp))
            
            // Prénoms
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 400))
            ) {
                OutlinedTextField(
                    value = prenoms,
                    onValueChange = { prenoms = it; prenomsError = null },
                    label = { Text("Prénom(s)") },
                    leadingIcon = { Icon(Icons.Default.Person, contentDescription = null) },
                    singleLine = true,
                    isError = prenomsError != null,
                    supportingText = prenomsError?.let { { Text(it, color = AccentRed) } },
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
                        keyboardType = KeyboardType.Text,
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { focusManager.moveFocus(FocusDirection.Down) }
                    )
                )
            }
            
            Spacer(Modifier.height(16.dp))
            
            // Téléphone
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 500))
            ) {
                OutlinedTextField(
                    value = telephone,
                    onValueChange = { telephone = it; telephoneError = null },
                    label = { Text("Téléphone") },
                    leadingIcon = { Icon(Icons.Default.Phone, contentDescription = null) },
                    singleLine = true,
                    isError = telephoneError != null,
                    supportingText = telephoneError?.let { { Text(it, color = AccentRed) } },
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
                        keyboardType = KeyboardType.Phone,
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { focusManager.moveFocus(FocusDirection.Down) }
                    )
                )
            }
            
            Spacer(Modifier.height(16.dp))
            
            // Email
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 600))
            ) {
                OutlinedTextField(
                    value = email,
                    onValueChange = { email = it; emailError = null },
                    label = { Text("Email") },
                    leadingIcon = { Icon(Icons.Default.Email, contentDescription = null) },
                    singleLine = true,
                    isError = emailError != null,
                    supportingText = emailError?.let { { Text(it, color = AccentRed) } },
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
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { focusManager.moveFocus(FocusDirection.Down) }
                    )
                )
            }
            
            Spacer(Modifier.height(16.dp))
            
            // Password
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 700))
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
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { focusManager.moveFocus(FocusDirection.Down) }
                    )
                )
            }
            
            Spacer(Modifier.height(16.dp))
            
            // Confirm Password
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 800))
            ) {
                OutlinedTextField(
                    value = confirmPassword,
                    onValueChange = { confirmPassword = it; confirmPasswordError = null },
                    label = { Text("Confirmer le mot de passe") },
                    leadingIcon = { Icon(Icons.Default.Lock, contentDescription = null) },
                    trailingIcon = {
                        IconButton(onClick = { confirmPasswordVisible = !confirmPasswordVisible }) {
                            Icon(
                                imageVector = if (confirmPasswordVisible) Icons.Default.Visibility else Icons.Default.VisibilityOff,
                                contentDescription = null,
                                tint = Color.White.copy(alpha = 0.6f)
                            )
                        }
                    },
                    visualTransformation = if (confirmPasswordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                    singleLine = true,
                    isError = confirmPasswordError != null,
                    supportingText = confirmPasswordError?.let { { Text(it, color = AccentRed) } },
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
                        onDone = { doRegister() }
                    )
                )
            }
            
            Spacer(Modifier.height(32.dp))
            
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 900))
            ) {
                Button(
                    onClick = { doRegister() },
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
                                text = "Inscription...",
                                fontSize = 16.sp,
                                fontWeight = FontWeight.Bold,
                                color = Dark
                            )
                        }
                    } else {
                        Text(
                            text = "Créer mon compte",
                            fontSize = 18.sp,
                            fontWeight = FontWeight.Bold,
                            color = Dark,
                            letterSpacing = 1.sp
                        )
                    }
                }
            }
            
            Spacer(Modifier.height(32.dp))
        }
    }
}
