package com.suzosky.coursierclient.ui

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.relocation.BringIntoViewRequester
import androidx.compose.foundation.relocation.bringIntoViewRequester
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.scale
import androidx.compose.ui.focus.onFocusEvent
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import kotlinx.coroutines.delay
import com.suzosky.coursierclient.net.ApiService
import com.suzosky.coursierclient.net.ClientStore
import androidx.compose.ui.platform.LocalContext
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import com.suzosky.coursierclient.ui.theme.*
import com.suzosky.coursierclient.net.ApiConfig
import com.suzosky.coursierclient.BuildConfig

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun LoginScreen(onLoggedIn: () -> Unit, showMessage: (String) -> Unit) {
    val scope = rememberCoroutineScope()
    val context = LocalContext.current
    val focusManager = LocalFocusManager.current
    
    // État
    var login by remember { mutableStateOf(if (BuildConfig.DEBUG) "test@test.com" else "") }
    var password by remember { mutableStateOf(if (BuildConfig.DEBUG) "abcde" else "") }
    var passwordVisible by remember { mutableStateOf(false) }
    var agentMode by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(false) }
    var loginError by remember { mutableStateOf<String?>(null) }
    var passwordError by remember { mutableStateOf<String?>(null) }

    // Animation d'apparition
    var visible by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        delay(100)
        visible = true
    }

    fun validate(): Boolean {
        var ok = true
        if (agentMode) {
            val isMatricule = login.matches(Regex("^[A-Za-z0-9_-]{3,}$"))
            val isPhoneAgent = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))
            if (login.isBlank() || !(isMatricule || isPhoneAgent)) {
                loginError = "Matricule ou téléphone requis"
                ok = false
            } else {
                loginError = null
            }
        } else {
            val isEmail = android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches()
            val isPhone = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))
            if (login.isBlank() || !(isEmail || isPhone)) {
                loginError = "Email ou téléphone invalide"
                ok = false
            } else {
                loginError = null
            }
        }
        if (password.length != 5) {
            passwordError = "5 caractères requis"
            ok = false
        } else {
            passwordError = null
        }
        return ok
    }

    val scroll = rememberScrollState()
    val bringIntoViewRequester = remember { BringIntoViewRequester() }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        Dark,
                        SecondaryBlue.copy(alpha = 0.3f),
                        Dark
                    )
                )
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(scroll)
                .padding(24.dp)
                .imePadding(),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(40.dp))

            // Logo animé
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(800)) + scaleIn(initialScale = 0.8f)
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Box(
                        modifier = Modifier
                            .size(120.dp)
                            .clip(CircleShape)
                            .background(
                                Brush.linearGradient(
                                    colors = listOf(Gold, GoldLight)
                                )
                            )
                            .padding(4.dp)
                            .clip(CircleShape)
                            .background(Dark),
                        contentAlignment = Alignment.Center
                    ) {
                        Icon(
                            imageVector = Icons.Filled.LocalShipping,
                            contentDescription = null,
                            modifier = Modifier.size(60.dp),
                            tint = Gold
                        )
                    }
                    Spacer(Modifier.height(24.dp))
                    Text(
                        text = "SUZOSKY",
                        fontSize = 36.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold,
                        letterSpacing = 4.sp
                    )
                    Text(
                        text = "Livraison Premium",
                        fontSize = 14.sp,
                        color = Color.White.copy(alpha = 0.6f),
                        letterSpacing = 2.sp
                    )
                }
            }

            Spacer(Modifier.height(48.dp))

            // Sélecteur de mode
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 200)) + 
                        slideInVertically(initialOffsetY = { 50 })
            ) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(20.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = Color.White.copy(alpha = 0.05f)
                    ),
                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(16.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(
                                imageVector = if (agentMode) Icons.Filled.Badge else Icons.Filled.Person,
                                contentDescription = null,
                                tint = Gold,
                                modifier = Modifier.size(24.dp)
                            )
                            Spacer(Modifier.width(12.dp))
                            Text(
                                text = if (agentMode) "Mode Agent" else "Mode Client",
                                color = Color.White,
                                fontWeight = FontWeight.Medium,
                                fontSize = 16.sp
                            )
                        }
                        Switch(
                            checked = agentMode,
                            onCheckedChange = { agentMode = it },
                            colors = SwitchDefaults.colors(
                                checkedThumbColor = Gold,
                                checkedTrackColor = Gold.copy(alpha = 0.3f),
                                uncheckedThumbColor = Color.White.copy(alpha = 0.5f),
                                uncheckedTrackColor = Color.White.copy(alpha = 0.1f)
                            )
                        )
                    }
                }
            }

            Spacer(Modifier.height(32.dp))

            // Champ login
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 400)) + 
                        slideInVertically(initialOffsetY = { 50 })
            ) {
                OutlinedTextField(
                    value = login,
                    onValueChange = { login = it; loginError = null },
                    label = { 
                        Text(
                            if (agentMode) "Matricule ou Téléphone" else "Email ou Téléphone",
                            color = Color.White.copy(alpha = 0.7f)
                        ) 
                    },
                    leadingIcon = {
                        Icon(
                            imageVector = if (agentMode) Icons.Filled.Badge else 
                                if (android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches()) 
                                    Icons.Filled.Email 
                                else 
                                    Icons.Filled.Phone,
                            contentDescription = null,
                            tint = Gold
                        )
                    },
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(
                        keyboardType = if (agentMode) KeyboardType.Text else KeyboardType.Email,
                        imeAction = ImeAction.Next
                    ),
                    keyboardActions = KeyboardActions(
                        onNext = { focusManager.moveFocus(FocusDirection.Down) }
                    ),
                    isError = loginError != null,
                    supportingText = loginError?.let { { Text(it, color = AccentRed) } },
                    modifier = Modifier
                        .fillMaxWidth()
                        .bringIntoViewRequester(bringIntoViewRequester)
                        .onFocusEvent { if (it.isFocused) scope.launch { bringIntoViewRequester.bringIntoView() } },
                    shape = RoundedCornerShape(16.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),
                        focusedTextColor = Color.White,
                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),
                        cursorColor = Gold,
                        errorBorderColor = AccentRed,
                        errorTextColor = Color.White
                    )
                )
            }

            Spacer(Modifier.height(20.dp))

            // Champ mot de passe
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 600)) + 
                        slideInVertically(initialOffsetY = { 50 })
            ) {
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it; passwordError = null },
                    label = { Text("Mot de passe", color = Color.White.copy(alpha = 0.7f)) },
                    leadingIcon = {
                        Icon(
                            imageVector = Icons.Filled.Lock,
                            contentDescription = null,
                            tint = Gold
                        )
                    },
                    trailingIcon = {
                        IconButton(onClick = { passwordVisible = !passwordVisible }) {
                            Icon(
                                imageVector = if (passwordVisible) Icons.Filled.Visibility else Icons.Filled.VisibilityOff,
                                contentDescription = if (passwordVisible) "Masquer" else "Afficher",
                                tint = Gold.copy(alpha = 0.7f)
                            )
                        }
                    },
                    visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                    singleLine = true,
                    keyboardOptions = KeyboardOptions(
                        keyboardType = KeyboardType.Password,
                        imeAction = ImeAction.Done
                    ),
                    keyboardActions = KeyboardActions(
                        onDone = { 
                            focusManager.clearFocus()
                            if (validate()) {
                                scope.launch {
                                    loading = true
                                    try {
                                        if (agentMode) {
                                            val resp = ApiService.agentLogin(login, password)
                                            if (resp.success) {
                                                showMessage("Connexion réussie")
                                                onLoggedIn()
                                            } else {
                                                showMessage(resp.error ?: resp.message ?: "Identifiants invalides")
                                            }
                                        } else {
                                            val resp = ApiService.login(login, password)
                                            if (resp.success) {
                                                resp.client?.telephone?.let { ClientStore.saveClientPhone(context, it) }
                                                showMessage("Bienvenue !")
                                                onLoggedIn()
                                            } else {
                                                showMessage(resp.error ?: resp.message ?: "Identifiants invalides")
                                            }
                                        }
                                    } catch (e: Exception) {
                                        showMessage(ApiService.friendlyError(e))
                                    } finally {
                                        loading = false
                                    }
                                }
                            }
                        }
                    ),
                    isError = passwordError != null,
                    supportingText = passwordError?.let { { Text(it, color = AccentRed) } },
                    modifier = Modifier
                        .fillMaxWidth()
                        .bringIntoViewRequester(bringIntoViewRequester)
                        .onFocusEvent { if (it.isFocused) scope.launch { bringIntoViewRequester.bringIntoView() } },
                    shape = RoundedCornerShape(16.dp),
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = Gold,
                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),
                        focusedTextColor = Color.White,
                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),
                        cursorColor = Gold,
                        errorBorderColor = AccentRed,
                        errorTextColor = Color.White
                    )
                )
            }

            Spacer(Modifier.height(32.dp))

            // Bouton de connexion
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 800)) + 
                        slideInVertically(initialOffsetY = { 50 })
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(60.dp)
                        .clip(RoundedCornerShape(16.dp))
                        .background(
                            if (loading) Color.Gray.copy(alpha = 0.3f)
                            else Brush.horizontalGradient(
                                colors = listOf(Gold, GoldLight)
                            )
                        )
                ) {
                    Button(
                        onClick = {
                            focusManager.clearFocus()
                            if (!validate()) return@Button
                            scope.launch {
                                loading = true
                                try {
                                    if (agentMode) {
                                        val resp = ApiService.agentLogin(login, password)
                                        if (resp.success) {
                                            showMessage("Connexion réussie")
                                            onLoggedIn()
                                        } else {
                                            showMessage(resp.error ?: resp.message ?: "Identifiants invalides")
                                        }
                                    } else {
                                        val resp = ApiService.login(login, password)
                                        if (resp.success) {
                                            resp.client?.telephone?.let { ClientStore.saveClientPhone(context, it) }
                                            showMessage("Bienvenue !")
                                            onLoggedIn()
                                        } else {
                                            showMessage(resp.error ?: resp.message ?: "Identifiants invalides")
                                        }
                                    }
                                } catch (e: Exception) {
                                    showMessage(ApiService.friendlyError(e))
                                } finally {
                                    loading = false
                                }
                            }
                        },
                        enabled = !loading,
                        modifier = Modifier.fillMaxSize(),
                        shape = RoundedCornerShape(16.dp),
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Color.Transparent,
                            disabledContainerColor = Color.Transparent
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
                                    text = "Connexion en cours...",
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
            }

            Spacer(Modifier.height(24.dp))

            // Info debug et aide
            AnimatedVisibility(
                visible = visible,
                enter = fadeIn(animationSpec = tween(1000, delayMillis = 1000))
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    if (BuildConfig.DEBUG) {
                        Card(
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(12.dp),
                            colors = CardDefaults.cardColors(
                                containerColor = Info.copy(alpha = 0.1f)
                            ),
                            border = BorderStroke(1.dp, Info.copy(alpha = 0.3f))
                        ) {
                            Column(Modifier.padding(12.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(
                                        imageVector = Icons.Filled.DeveloperMode,
                                        contentDescription = null,
                                        tint = Info,
                                        modifier = Modifier.size(16.dp)
                                    )
                                    Spacer(Modifier.width(8.dp))
                                    Text(
                                        text = "Mode Debug",
                                        fontSize = 12.sp,
                                        fontWeight = FontWeight.Bold,
                                        color = Info
                                    )
                                </Row>
                                Spacer(Modifier.height(4.dp))
                                Text(
                                    text = "Backend: ${ApiConfig.BASE_URL}",
                                    fontSize = 11.sp,
                                    color = Color.White.copy(alpha = 0.7f)
                                )
                            }
                        }
                        Spacer(Modifier.height(16.dp))
                    }
                    
                    Text(
                        text = if (agentMode) 
                            "Agent: Utilisez votre matricule ou téléphone" 
                        else 
                            "Test: test@test.com / abcde",
                        fontSize = 13.sp,
                        color = Color.White.copy(alpha = 0.5f),
                        textAlign = TextAlign.Center,
                        modifier = Modifier.padding(horizontal = 16.dp)
                    )
                }
            }

            Spacer(Modifier.height(32.dp))
        }
    }
}
