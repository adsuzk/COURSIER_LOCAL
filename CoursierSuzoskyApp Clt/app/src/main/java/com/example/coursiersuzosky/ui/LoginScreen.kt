package com.suzosky.coursierclient.uipackage com.suzosky.coursierclient.uipackage com.suzosky.coursierclient.ui



import androidx.compose.animation.*

import androidx.compose.animation.core.tween

import androidx.compose.foundation.*import androidx.compose.animation.*import androidx.compose.animation.*

import androidx.compose.foundation.interaction.MutableInteractionSource

import androidx.compose.foundation.layout.*import androidx.compose.animation.core.*import androidx.compose.animation.core.*

import androidx.compose.foundation.relocation.BringIntoViewRequester

import androidx.compose.foundation.relocation.bringIntoViewRequesterimport androidx.compose.foundation.BorderStrokeimport androidx.compose.foundation.BorderStroke

import androidx.compose.foundation.shape.CircleShape

import androidx.compose.foundation.shape.RoundedCornerShapeimport androidx.compose.foundation.backgroundimport androidx.compose.foundation.background

import androidx.compose.foundation.text.KeyboardActions

import androidx.compose.foundation.text.KeyboardOptionsimport androidx.compose.foundation.layout.*import androidx.compose.foundation.layout.*

import androidx.compose.material.icons.Icons

import androidx.compose.material.icons.filled.*import androidx.compose.foundation.rememberScrollStateimport androidx.compose.foundation.rememberScrollState

import androidx.compose.material3.*

import androidx.compose.runtime.*import androidx.compose.foundation.verticalScrollimport androidx.compose.foundation.verticalScroll

import androidx.compose.ui.Alignment

import androidx.compose.ui.Modifierimport androidx.compose.foundation.relocation.BringIntoViewRequesterimport androidx.compose.foundation.relocation.BringIntoViewRequester

import androidx.compose.ui.draw.clip

import androidx.compose.ui.focus.FocusDirectionimport androidx.compose.foundation.relocation.bringIntoViewRequesterimport androidx.compose.foundation.relocation.bringIntoViewRequester

import androidx.compose.ui.focus.onFocusEvent

import androidx.compose.ui.graphics.Brushimport androidx.compose.foundation.shape.CircleShapeimport androidx.compose.foundation.shape.CircleShape

import androidx.compose.ui.graphics.Color

import androidx.compose.ui.platform.LocalFocusManagerimport androidx.compose.foundation.shape.RoundedCornerShapeimport androidx.compose.foundation.shape.RoundedCornerShape

import androidx.compose.ui.text.font.FontWeight

import androidx.compose.ui.text.input.ImeActionimport androidx.compose.foundation.ExperimentalFoundationApiimport androidx.compose.foundation.ExperimentalFoundationApi

import androidx.compose.ui.text.input.KeyboardType

import androidx.compose.ui.text.input.PasswordVisualTransformationimport androidx.compose.material3.*import androidx.compose.material3.*

import androidx.compose.ui.text.input.VisualTransformation

import androidx.compose.ui.text.style.TextAlignimport androidx.compose.runtime.*import androidx.compose.runtime.*

import androidx.compose.ui.unit.dp

import androidx.compose.ui.unit.spimport androidx.compose.ui.Alignmentimport androidx.compose.ui.Alignment

import com.suzosky.coursierclient.ApiService

import com.suzosky.coursierclient.BuildConfigimport androidx.compose.ui.Modifierimport androidx.compose.ui.Modifier

import com.suzosky.coursierclient.ClientStore

import com.suzosky.coursierclient.ui.theme.*import androidx.compose.ui.draw.clipimport androidx.compose.ui.draw.alpha

import kotlinx.coroutines.launch

import androidx.compose.ui.focus.onFocusEventimport androidx.compose.ui.draw.clip

@Composable

fun LoginScreen(import androidx.compose.ui.graphics.Brushimport androidx.compose.ui.draw.scale

    onLoggedIn: () -> Unit,

    showMessage: (String) -> Unitimport androidx.compose.ui.graphics.Colorimport androidx.compose.ui.focus.onFocusEvent

) {

    val scope = rememberCoroutineScope()import androidx.compose.ui.text.font.FontWeightimport androidx.compose.ui.graphics.Brush

    val focusManager = LocalFocusManager.current

    import androidx.compose.ui.text.input.PasswordVisualTransformationimport androidx.compose.ui.graphics.Color

    var login by remember { mutableStateOf("") }

    var password by remember { mutableStateOf("") }import androidx.compose.ui.text.input.VisualTransformationimport androidx.compose.ui.text.font.FontWeight

    var agentMode by remember { mutableStateOf(false) }

    var loading by remember { mutableStateOf(false) }import androidx.compose.ui.text.style.TextAlignimport androidx.compose.ui.text.input.PasswordVisualTransformation

    var passwordVisible by remember { mutableStateOf(false) }

    var visible by remember { mutableStateOf(false) }import androidx.compose.ui.unit.dpimport androidx.compose.ui.text.input.VisualTransformation

    

    val bringIntoViewRequester = remember { BringIntoViewRequester() }import androidx.compose.ui.unit.spimport androidx.compose.ui.text.style.TextAlign

    

    // Validation statesimport kotlinx.coroutines.launchimport androidx.compose.ui.unit.dp

    var loginError by remember { mutableStateOf<String?>(null) }

    var passwordError by remember { mutableStateOf<String?>(null) }import kotlinx.coroutines.delayimport androidx.compose.ui.unit.sp

    

    // Animation triggerimport com.suzosky.coursierclient.net.ApiServiceimport kotlinx.coroutines.launch

    LaunchedEffect(Unit) {

        kotlinx.coroutines.delay(100)import com.suzosky.coursierclient.net.ClientStoreimport kotlinx.coroutines.delay

        visible = true

    }import androidx.compose.ui.platform.LocalContextimport com.suzosky.coursierclient.net.ApiService

    

    fun validate(): Boolean {import androidx.compose.foundation.text.KeyboardOptionsimport com.suzosky.coursierclient.net.ClientStore

        var hasError = false

        import androidx.compose.foundation.text.KeyboardActionsimport androidx.compose.ui.platform.LocalContext

        // Validation login

        if (login.isBlank()) {import androidx.compose.ui.text.input.KeyboardTypeimport androidx.compose.foundation.text.KeyboardOptions

            loginError = if (agentMode) "Matricule requis" else "Email ou téléphone requis"

            hasError = trueimport androidx.compose.ui.text.input.ImeActionimport androidx.compose.foundation.text.KeyboardActions

        } else if (!agentMode) {

            val emailRegex = "[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}".toRegex()import androidx.compose.ui.focus.FocusDirectionimport androidx.compose.ui.text.input.KeyboardType

            val phoneRegex = "\\+?225\\d{10}|\\d{10}".toRegex()

            if (!login.matches(emailRegex) && !login.matches(phoneRegex)) {import androidx.compose.ui.platform.LocalFocusManagerimport androidx.compose.ui.text.input.ImeAction

                loginError = "Format email ou téléphone invalide"

                hasError = trueimport androidx.compose.material.icons.Iconsimport androidx.compose.ui.focus.FocusDirection

            } else {

                loginError = nullimport androidx.compose.material.icons.filled.*import androidx.compose.ui.platform.LocalFocusManager

            }

        } else if (agentMode && login.length < 3) {import com.suzosky.coursierclient.ui.theme.*import androidx.compose.material.icons.Icons

            loginError = "Matricule invalide"

            hasError = trueimport com.suzosky.coursierclient.net.ApiConfigimport androidx.compose.material.icons.filled.*

        } else {

            loginError = nullimport com.suzosky.coursierclient.BuildConfigimport com.suzosky.coursierclient.ui.theme.*

        }

        import com.suzosky.coursierclient.net.ApiConfig

        // Validation password

        if (password.isBlank()) {@OptIn(ExperimentalFoundationApi::class)import com.suzosky.coursierclient.BuildConfig

            passwordError = "Mot de passe requis"

            hasError = true@Composable

        } else if (password.length < 5) {

            passwordError = "Minimum 5 caractères"fun LoginScreen(onLoggedIn: () -> Unit, showMessage: (String) -> Unit) {@OptIn(ExperimentalFoundationApi::class)

            hasError = true

        } else {    val scope = rememberCoroutineScope()@Composable

            passwordError = null

        }    val context = LocalContext.currentfun LoginScreen(onLoggedIn: () -> Unit, showMessage: (String) -> Unit) {

        

        return !hasError    val focusManager = LocalFocusManager.current    val scope = rememberCoroutineScope()

    }

            val context = LocalContext.current

    Box(

        modifier = Modifier    var login by remember { mutableStateOf(if (BuildConfig.DEBUG) "test@test.com" else "") }    val focusManager = LocalFocusManager.current

            .fillMaxSize()

            .background(    var password by remember { mutableStateOf(if (BuildConfig.DEBUG) "abcde" else "") }    

                Brush.verticalGradient(

                    colors = listOf(Dark, SecondaryBlue, Dark)    var passwordVisible by remember { mutableStateOf(false) }    // État

                )

            )    var agentMode by remember { mutableStateOf(false) }    var login by remember { mutableStateOf(if (BuildConfig.DEBUG) "test@test.com" else "") }

            .imePadding()

    ) {    var loading by remember { mutableStateOf(false) }    var password by remember { mutableStateOf(if (BuildConfig.DEBUG) "abcde" else "") }

        Column(

            modifier = Modifier    var loginError by remember { mutableStateOf<String?>(null) }    var passwordVisible by remember { mutableStateOf(false) }

                .fillMaxSize()

                .verticalScroll(rememberScrollState())    var passwordError by remember { mutableStateOf<String?>(null) }    var agentMode by remember { mutableStateOf(false) }

                .padding(horizontal = 24.dp, vertical = 32.dp),

            horizontalAlignment = Alignment.CenterHorizontally    var loading by remember { mutableStateOf(false) }

        ) {

            Spacer(Modifier.height(40.dp))    var visible by remember { mutableStateOf(false) }    var loginError by remember { mutableStateOf<String?>(null) }

            

            // Logo animé avec icône camion    LaunchedEffect(Unit) {    var passwordError by remember { mutableStateOf<String?>(null) }

            AnimatedVisibility(

                visible = visible,        delay(100)

                enter = fadeIn(animationSpec = tween(800)) + scaleIn(

                    initialScale = 0.8f,        visible = true    // Animation d'apparition

                    animationSpec = tween(800)

                )    }    var visible by remember { mutableStateOf(false) }

            ) {

                Box(    LaunchedEffect(Unit) {

                    modifier = Modifier

                        .size(120.dp)    fun validate(): Boolean {        delay(100)

                        .clip(CircleShape)

                        .background(        var ok = true        visible = true

                            Brush.radialGradient(

                                colors = listOf(Gold, GoldLight)        if (agentMode) {    }

                            )

                        ),            val isMatricule = login.matches(Regex("^[A-Za-z0-9_-]{3,}$"))

                    contentAlignment = Alignment.Center

                ) {            val isPhoneAgent = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))    fun validate(): Boolean {

                    Icon(

                        imageVector = Icons.Default.LocalShipping,            if (login.isBlank() || !(isMatricule || isPhoneAgent)) {        var ok = true

                        contentDescription = "Logo",

                        modifier = Modifier.size(60.dp),                loginError = "Matricule ou téléphone requis"        if (agentMode) {

                        tint = Dark

                    )                ok = false            val isMatricule = login.matches(Regex("^[A-Za-z0-9_-]{3,}$"))

                }

            }            } else {            val isPhoneAgent = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))

            

            Spacer(Modifier.height(24.dp))                loginError = null            if (login.isBlank() || !(isMatricule || isPhoneAgent)) {

            

            // Titre et sous-titre            }                loginError = "Matricule ou téléphone requis"

            AnimatedVisibility(

                visible = visible,        } else {                ok = false

                enter = fadeIn(animationSpec = tween(1000, delayMillis = 200)) + 

                        slideInVertically(initialOffsetY = { 30 })            val isEmail = android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches()            } else {

            ) {

                Column(horizontalAlignment = Alignment.CenterHorizontally) {            val isPhone = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))                loginError = null

                    Text(

                        text = "SUZOSKY",            if (login.isBlank() || !(isEmail || isPhone)) {            }

                        fontSize = 36.sp,

                        fontWeight = FontWeight.Bold,                loginError = "Email ou téléphone invalide"        } else {

                        color = Gold,

                        letterSpacing = 4.sp                ok = false            val isEmail = android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches()

                    )

                                } else {            val isPhone = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))

                    Spacer(Modifier.height(8.dp))

                                    loginError = null            if (login.isBlank() || !(isEmail || isPhone)) {

                    Text(

                        text = "Livraison Premium",            }                loginError = "Email ou téléphone invalide"

                        fontSize = 14.sp,

                        fontWeight = FontWeight.Light,        }                ok = false

                        color = Color.White.copy(alpha = 0.7f),

                        letterSpacing = 2.sp        if (password.length != 5) {            } else {

                    )

                }            passwordError = "5 caractères requis"                loginError = null

            }

                        ok = false            }

            Spacer(Modifier.height(48.dp))

                    } else {        }

            // Sélecteur de mode

            AnimatedVisibility(            passwordError = null        if (password.length != 5) {

                visible = visible,

                enter = fadeIn(animationSpec = tween(1000, delayMillis = 400)) +         }            passwordError = "5 caractères requis"

                        slideInVertically(initialOffsetY = { 30 })

            ) {        return ok            ok = false

                Card(

                    modifier = Modifier.fillMaxWidth(),    }        } else {

                    shape = RoundedCornerShape(16.dp),

                    colors = CardDefaults.cardColors(            passwordError = null

                        containerColor = Color.White.copy(alpha = 0.1f)

                    ),    val scroll = rememberScrollState()        }

                    border = BorderStroke(1.dp, Color.White.copy(alpha = 0.2f))

                ) {    val bringIntoViewRequester = remember { BringIntoViewRequester() }        return ok

                    Row(

                        modifier = Modifier    }

                            .fillMaxWidth()

                            .padding(16.dp),    Box(

                        horizontalArrangement = Arrangement.SpaceBetween,

                        verticalAlignment = Alignment.CenterVertically        modifier = Modifier    val scroll = rememberScrollState()

                    ) {

                        Column {            .fillMaxSize()    val bringIntoViewRequester = remember { BringIntoViewRequester() }

                            Text(

                                text = if (agentMode) "Agent" else "Client",            .background(

                                fontSize = 18.sp,

                                fontWeight = FontWeight.Bold,                Brush.verticalGradient(    Box(

                                color = Color.White

                            )                    colors = listOf(Dark, SecondaryBlue.copy(alpha = 0.3f), Dark)        modifier = Modifier

                            Text(

                                text = if (agentMode) "Coursier / Livreur" else "Passer une commande",                )            .fillMaxSize()

                                fontSize = 12.sp,

                                color = Color.White.copy(alpha = 0.6f)            )            .background(

                            )

                        }    ) {                Brush.verticalGradient(

                        

                        Switch(        Column(                    colors = listOf(

                            checked = agentMode,

                            onCheckedChange = { agentMode = it },            modifier = Modifier                        Dark,

                            colors = SwitchDefaults.colors(

                                checkedThumbColor = Gold,                .fillMaxSize()                        SecondaryBlue.copy(alpha = 0.3f),

                                checkedTrackColor = GoldLight,

                                uncheckedThumbColor = Color.White.copy(alpha = 0.6f),                .verticalScroll(scroll)                        Dark

                                uncheckedTrackColor = Color.White.copy(alpha = 0.2f)

                            )                .padding(24.dp)                    )

                        )

                    }                .imePadding(),                )

                }

            }            horizontalAlignment = Alignment.CenterHorizontally            )

            

            Spacer(Modifier.height(32.dp))        ) {    ) {

            

            // Champ login            Spacer(Modifier.height(40.dp))        Column(

            AnimatedVisibility(

                visible = visible,            modifier = Modifier

                enter = fadeIn(animationSpec = tween(1000, delayMillis = 600)) + 

                        slideInVertically(initialOffsetY = { 30 })            AnimatedVisibility(                .fillMaxSize()

            ) {

                OutlinedTextField(                visible = visible,                .verticalScroll(scroll)

                    value = login,

                    onValueChange = { login = it; loginError = null },                enter = fadeIn(animationSpec = tween(800)) + scaleIn(initialScale = 0.8f)                .padding(24.dp)

                    label = { 

                        Text(            ) {                .imePadding(),

                            if (agentMode) "Matricule" else "Email ou Téléphone"

                        )                 Column(horizontalAlignment = Alignment.CenterHorizontally) {            horizontalAlignment = Alignment.CenterHorizontally

                    },

                    leadingIcon = {                    Box(        ) {

                        Icon(

                            if (agentMode) Icons.Default.Badge else Icons.Default.Person,                        modifier = Modifier            Spacer(Modifier.height(40.dp))

                            contentDescription = null

                        )                            .size(120.dp)

                    },

                    singleLine = true,                            .clip(CircleShape)            // Logo animé

                    isError = loginError != null,

                    supportingText = loginError?.let { { Text(it, color = AccentRed) } },                            .background(Brush.linearGradient(colors = listOf(Gold, GoldLight)))            AnimatedVisibility(

                    modifier = Modifier

                        .fillMaxWidth()                            .padding(4.dp)                visible = visible,

                        .bringIntoViewRequester(bringIntoViewRequester)

                        .onFocusEvent {                             .clip(CircleShape)                enter = fadeIn(animationSpec = tween(800)) + scaleIn(initialScale = 0.8f)

                            if (it.isFocused) {

                                scope.launch { bringIntoViewRequester.bringIntoView() }                            .background(Dark),            ) {

                            }

                        },                        contentAlignment = Alignment.Center                Column(horizontalAlignment = Alignment.CenterHorizontally) {

                    shape = RoundedCornerShape(16.dp),

                    colors = OutlinedTextFieldDefaults.colors(                    ) {                    Box(

                        focusedBorderColor = Gold,

                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),                        Icon(                        modifier = Modifier

                        focusedTextColor = Color.White,

                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),                            imageVector = Icons.Filled.LocalShipping,                            .size(120.dp)

                        cursorColor = Gold,

                        errorBorderColor = AccentRed,                            contentDescription = null,                            .clip(CircleShape)

                        errorTextColor = Color.White,

                        focusedLabelColor = Gold,                            modifier = Modifier.size(60.dp),                            .background(

                        unfocusedLabelColor = Color.White.copy(alpha = 0.6f),

                        focusedLeadingIconColor = Gold,                            tint = Gold                                Brush.linearGradient(

                        unfocusedLeadingIconColor = Color.White.copy(alpha = 0.6f)

                    ),                        )                                    colors = listOf(Gold, GoldLight)

                    keyboardOptions = KeyboardOptions(

                        keyboardType = if (agentMode) KeyboardType.Text else KeyboardType.Email,                    }                                )

                        imeAction = ImeAction.Next

                    ),                    Spacer(Modifier.height(24.dp))                            )

                    keyboardActions = KeyboardActions(

                        onNext = { focusManager.moveFocus(FocusDirection.Down) }                    Text(                            .padding(4.dp)

                    )

                )                        text = "SUZOSKY",                            .clip(CircleShape)

            }

                                    fontSize = 36.sp,                            .background(Dark),

            Spacer(Modifier.height(16.dp))

                                    fontWeight = FontWeight.Bold,                        contentAlignment = Alignment.Center

            // Champ password

            AnimatedVisibility(                        color = Gold,                    ) {

                visible = visible,

                enter = fadeIn(animationSpec = tween(1000, delayMillis = 800)) +                         letterSpacing = 4.sp                        Icon(

                        slideInVertically(initialOffsetY = { 30 })

            ) {                    )                            imageVector = Icons.Filled.LocalShipping,

                OutlinedTextField(

                    value = password,                    Text(                            contentDescription = null,

                    onValueChange = { password = it; passwordError = null },

                    label = { Text("Mot de passe") },                        text = "Livraison Premium",                            modifier = Modifier.size(60.dp),

                    leadingIcon = {

                        Icon(Icons.Default.Lock, contentDescription = null)                        fontSize = 14.sp,                            tint = Gold

                    },

                    trailingIcon = {                        color = Color.White.copy(alpha = 0.6f),                        )

                        IconButton(onClick = { passwordVisible = !passwordVisible }) {

                            Icon(                        letterSpacing = 2.sp                    }

                                imageVector = if (passwordVisible) Icons.Default.Visibility else Icons.Default.VisibilityOff,

                                contentDescription = if (passwordVisible) "Masquer" else "Afficher",                    )                    Spacer(Modifier.height(24.dp))

                                tint = Color.White.copy(alpha = 0.6f)

                            )                }                    Text(

                        }

                    },            }                        text = "SUZOSKY",

                    visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),

                    singleLine = true,                        fontSize = 36.sp,

                    isError = passwordError != null,

                    supportingText = passwordError?.let { { Text(it, color = AccentRed) } },            Spacer(Modifier.height(48.dp))                        fontWeight = FontWeight.Bold,

                    modifier = Modifier

                        .fillMaxWidth()                        color = Gold,

                        .bringIntoViewRequester(bringIntoViewRequester)

                        .onFocusEvent {             AnimatedVisibility(                        letterSpacing = 4.sp

                            if (it.isFocused) {

                                scope.launch { bringIntoViewRequester.bringIntoView() }                visible = visible,                    )

                            }

                        },                enter = fadeIn(animationSpec = tween(1000, delayMillis = 200)) + slideInVertically(initialOffsetY = { 50 })                    Text(

                    shape = RoundedCornerShape(16.dp),

                    colors = OutlinedTextFieldDefaults.colors(            ) {                        text = "Livraison Premium",

                        focusedBorderColor = Gold,

                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),                Card(                        fontSize = 14.sp,

                        focusedTextColor = Color.White,

                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),                    modifier = Modifier.fillMaxWidth(),                        color = Color.White.copy(alpha = 0.6f),

                        cursorColor = Gold,

                        errorBorderColor = AccentRed,                    shape = RoundedCornerShape(20.dp),                        letterSpacing = 2.sp

                        errorTextColor = Color.White,

                        focusedLabelColor = Gold,                    colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.05f)),                    )

                        unfocusedLabelColor = Color.White.copy(alpha = 0.6f),

                        focusedLeadingIconColor = Gold,                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))                }

                        unfocusedLeadingIconColor = Color.White.copy(alpha = 0.6f)

                    ),                ) {            }

                    keyboardOptions = KeyboardOptions(

                        keyboardType = KeyboardType.Password,                    Row(

                        imeAction = ImeAction.Done

                    ),                        modifier = Modifier.fillMaxWidth().padding(16.dp),            Spacer(Modifier.height(48.dp))

                    keyboardActions = KeyboardActions(

                        onDone = {                        horizontalArrangement = Arrangement.SpaceBetween,

                            focusManager.clearFocus()

                            if (validate() && !loading) {                        verticalAlignment = Alignment.CenterVertically            // Sélecteur de mode

                                scope.launch {

                                    loading = true                    ) {            AnimatedVisibility(

                                    try {

                                        val resp = if (agentMode) {                        Row(verticalAlignment = Alignment.CenterVertically) {                visible = visible,

                                            ApiService.agentLogin(login, password)

                                        } else {                            Icon(                enter = fadeIn(animationSpec = tween(1000, delayMillis = 200)) + 

                                            ApiService.login(login, password)

                                        }                                imageVector = if (agentMode) Icons.Filled.Badge else Icons.Filled.Person,                        slideInVertically(initialOffsetY = { 50 })

                                        if (resp.success) {

                                            showMessage("Connexion réussie")                                contentDescription = null,            ) {

                                            onLoggedIn()

                                        } else {                                tint = Gold,                Card(

                                            showMessage(resp.error ?: resp.message ?: "Identifiants invalides")

                                        }                                modifier = Modifier.size(24.dp)                    modifier = Modifier.fillMaxWidth(),

                                    } catch (e: Exception) {

                                        showMessage(ApiService.friendlyError(e))                            )                    shape = RoundedCornerShape(20.dp),

                                    } finally {

                                        loading = false                            Spacer(Modifier.width(12.dp))                    colors = CardDefaults.cardColors(

                                    }

                                }                            Text(                        containerColor = Color.White.copy(alpha = 0.05f)

                            }

                        }                                text = if (agentMode) "Mode Agent" else "Mode Client",                    ),

                    )

                )                                color = Color.White,                    border = BorderStroke(1.dp, Gold.copy(alpha = 0.2f))

            }

                                            fontWeight = FontWeight.Medium,                ) {

            Spacer(Modifier.height(32.dp))

                                            fontSize = 16.sp                    Row(

            // Bouton de connexion avec gradient

            AnimatedVisibility(                            )                        modifier = Modifier

                visible = visible,

                enter = fadeIn(animationSpec = tween(1000, delayMillis = 1000)) +                         }                            .fillMaxWidth()

                        slideInVertically(initialOffsetY = { 50 })

            ) {                        Switch(                            .padding(16.dp),

                Box(

                    modifier = Modifier                            checked = agentMode,                        horizontalArrangement = Arrangement.SpaceBetween,

                        .fillMaxWidth()

                        .height(60.dp)                            onCheckedChange = { agentMode = it },                        verticalAlignment = Alignment.CenterVertically

                        .clip(RoundedCornerShape(16.dp))

                        .background(                            colors = SwitchDefaults.colors(                    ) {

                            Brush.horizontalGradient(

                                colors = listOf(Gold, GoldLight)                                checkedThumbColor = Gold,                        Row(verticalAlignment = Alignment.CenterVertically) {

                            )

                        )                                checkedTrackColor = Gold.copy(alpha = 0.3f),                            Icon(

                ) {

                    Button(                                uncheckedThumbColor = Color.White.copy(alpha = 0.5f),                                imageVector = if (agentMode) Icons.Filled.Badge else Icons.Filled.Person,

                        onClick = {

                            focusManager.clearFocus()                                uncheckedTrackColor = Color.White.copy(alpha = 0.1f)                                contentDescription = null,

                            if (!validate()) return@Button

                            scope.launch {                            )                                tint = Gold,

                                loading = true

                                try {                        )                                modifier = Modifier.size(24.dp)

                                    val resp = if (agentMode) {

                                        ApiService.agentLogin(login, password)                    }                            )

                                    } else {

                                        ApiService.login(login, password)                }                            Spacer(Modifier.width(12.dp))

                                    }

                                    if (resp.success) {            }                            Text(

                                        showMessage("Connexion réussie")

                                        onLoggedIn()                                text = if (agentMode) "Mode Agent" else "Mode Client",

                                    } else {

                                        showMessage(resp.error ?: resp.message ?: "Identifiants invalides")            Spacer(Modifier.height(32.dp))                                color = Color.White,

                                    }

                                } catch (e: Exception) {                                fontWeight = FontWeight.Medium,

                                    showMessage(ApiService.friendlyError(e))

                                } finally {            AnimatedVisibility(                                fontSize = 16.sp

                                    loading = false

                                }                visible = visible,                            )

                            }

                        },                enter = fadeIn(animationSpec = tween(1000, delayMillis = 400)) + slideInVertically(initialOffsetY = { 50 })                        }

                        enabled = !loading,

                        modifier = Modifier.fillMaxSize(),            ) {                        Switch(

                        shape = RoundedCornerShape(16.dp),

                        colors = ButtonDefaults.buttonColors(                OutlinedTextField(                            checked = agentMode,

                            containerColor = Color.Transparent,

                            disabledContainerColor = Color.Transparent                    value = login,                            onCheckedChange = { agentMode = it },

                        ),

                        contentPadding = PaddingValues(0.dp)                    onValueChange = { login = it; loginError = null },                            colors = SwitchDefaults.colors(

                    ) {

                        if (loading) {                    label = { Text(if (agentMode) "Matricule ou Téléphone" else "Email ou Téléphone", color = Color.White.copy(alpha = 0.7f)) },                                checkedThumbColor = Gold,

                            Row(

                                horizontalArrangement = Arrangement.Center,                    leadingIcon = {                                checkedTrackColor = Gold.copy(alpha = 0.3f),

                                verticalAlignment = Alignment.CenterVertically

                            ) {                        Icon(                                uncheckedThumbColor = Color.White.copy(alpha = 0.5f),

                                CircularProgressIndicator(

                                    modifier = Modifier.size(24.dp),                            imageVector = if (agentMode) Icons.Filled.Badge else if (android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches()) Icons.Filled.Email else Icons.Filled.Phone,                                uncheckedTrackColor = Color.White.copy(alpha = 0.1f)

                                    strokeWidth = 3.dp,

                                    color = Dark                            contentDescription = null,                            )

                                )

                                Spacer(Modifier.width(12.dp))                            tint = Gold                        )

                                Text(

                                    text = "Connexion...",                        )                    }

                                    fontSize = 16.sp,

                                    fontWeight = FontWeight.Bold,                    },                }

                                    color = Dark

                                )                    singleLine = true,            }

                            }

                        } else {                    keyboardOptions = KeyboardOptions(keyboardType = if (agentMode) KeyboardType.Text else KeyboardType.Email, imeAction = ImeAction.Next),

                            Text(

                                text = "Se connecter",                    keyboardActions = KeyboardActions(onNext = { focusManager.moveFocus(FocusDirection.Down) }),            Spacer(Modifier.height(32.dp))

                                fontSize = 18.sp,

                                fontWeight = FontWeight.Bold,                    isError = loginError != null,

                                color = Dark,

                                letterSpacing = 1.sp                    supportingText = loginError?.let { { Text(it, color = AccentRed) } },            // Champ login

                            )

                        }                    modifier = Modifier.fillMaxWidth().bringIntoViewRequester(bringIntoViewRequester).onFocusEvent { if (it.isFocused) scope.launch { bringIntoViewRequester.bringIntoView() } },            AnimatedVisibility(

                    }

                }                    shape = RoundedCornerShape(16.dp),                visible = visible,

            }

                                colors = OutlinedTextFieldDefaults.colors(                enter = fadeIn(animationSpec = tween(1000, delayMillis = 400)) + 

            Spacer(Modifier.height(24.dp))

                                    focusedBorderColor = Gold,                        slideInVertically(initialOffsetY = { 50 })

            // Info debug

            if (BuildConfig.DEBUG) {                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),            ) {

                AnimatedVisibility(

                    visible = visible,                        focusedTextColor = Color.White,                OutlinedTextField(

                    enter = fadeIn(animationSpec = tween(1000, delayMillis = 1200))

                ) {                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),                    value = login,

                    Card(

                        modifier = Modifier.fillMaxWidth(),                        cursorColor = Gold,                    onValueChange = { login = it; loginError = null },

                        shape = RoundedCornerShape(12.dp),

                        colors = CardDefaults.cardColors(                        errorBorderColor = AccentRed,                    label = { 

                            containerColor = Color.White.copy(alpha = 0.05f)

                        ),                        errorTextColor = Color.White                        Text(

                        border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))

                    ) {                    )                            if (agentMode) "Matricule ou Téléphone" else "Email ou Téléphone",

                        Column(

                            modifier = Modifier.padding(16.dp),                )                            color = Color.White.copy(alpha = 0.7f)

                            horizontalAlignment = Alignment.CenterHorizontally

                        ) {            }                        ) 

                            Row(

                                verticalAlignment = Alignment.CenterVertically                    },

                            ) {

                                Icon(            Spacer(Modifier.height(20.dp))                    leadingIcon = {

                                    imageVector = Icons.Default.Info,

                                    contentDescription = null,                        Icon(

                                    tint = Gold,

                                    modifier = Modifier.size(16.dp)            AnimatedVisibility(                            imageVector = if (agentMode) Icons.Filled.Badge else 

                                )

                                Spacer(Modifier.width(8.dp))                visible = visible,                                if (android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches()) 

                                Text(

                                    text = "Mode Debug",                enter = fadeIn(animationSpec = tween(1000, delayMillis = 600)) + slideInVertically(initialOffsetY = { 50 })                                    Icons.Filled.Email 

                                    fontSize = 14.sp,

                                    fontWeight = FontWeight.Bold,            ) {                                else 

                                    color = Gold

                                )                OutlinedTextField(                                    Icons.Filled.Phone,

                            }

                                                value = password,                            contentDescription = null,

                            Spacer(Modifier.height(8.dp))

                                                onValueChange = { password = it; passwordError = null },                            tint = Gold

                            Text(

                                text = "Backend: ${ApiService.BASE_URL}",                    label = { Text("Mot de passe", color = Color.White.copy(alpha = 0.7f)) },                        )

                                fontSize = 11.sp,

                                color = Color.White.copy(alpha = 0.5f),                    leadingIcon = { Icon(imageVector = Icons.Filled.Lock, contentDescription = null, tint = Gold) },                    },

                                textAlign = TextAlign.Center,

                                modifier = Modifier.fillMaxWidth()                    trailingIcon = {                    singleLine = true,

                            )

                        }                        IconButton(onClick = { passwordVisible = !passwordVisible }) {                    keyboardOptions = KeyboardOptions(

                    }

                }                            Icon(                        keyboardType = if (agentMode) KeyboardType.Text else KeyboardType.Email,

            }

                                            imageVector = if (passwordVisible) Icons.Filled.Visibility else Icons.Filled.VisibilityOff,                        imeAction = ImeAction.Next

            Spacer(Modifier.height(32.dp))

        }                                contentDescription = if (passwordVisible) "Masquer" else "Afficher",                    ),

    }

}                                tint = Gold.copy(alpha = 0.7f)                    keyboardActions = KeyboardActions(


                            )                        onNext = { focusManager.moveFocus(FocusDirection.Down) }

                        }                    ),

                    },                    isError = loginError != null,

                    visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),                    supportingText = loginError?.let { { Text(it, color = AccentRed) } },

                    singleLine = true,                    modifier = Modifier

                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done),                        .fillMaxWidth()

                    keyboardActions = KeyboardActions(                        .bringIntoViewRequester(bringIntoViewRequester)

                        onDone = {                        .onFocusEvent { if (it.isFocused) scope.launch { bringIntoViewRequester.bringIntoView() } },

                            focusManager.clearFocus()                    shape = RoundedCornerShape(16.dp),

                            if (validate()) {                    colors = OutlinedTextFieldDefaults.colors(

                                scope.launch {                        focusedBorderColor = Gold,

                                    loading = true                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),

                                    try {                        focusedTextColor = Color.White,

                                        if (agentMode) {                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),

                                            val resp = ApiService.agentLogin(login, password)                        cursorColor = Gold,

                                            if (resp.success) {                        errorBorderColor = AccentRed,

                                                showMessage("Connexion réussie")                        errorTextColor = Color.White

                                                onLoggedIn()                    )

                                            } else {                )

                                                showMessage(resp.error ?: resp.message ?: "Identifiants invalides")            }

                                            }

                                        } else {            Spacer(Modifier.height(20.dp))

                                            val resp = ApiService.login(login, password)

                                            if (resp.success) {            // Champ mot de passe

                                                resp.client?.telephone?.let { ClientStore.saveClientPhone(context, it) }            AnimatedVisibility(

                                                showMessage("Bienvenue !")                visible = visible,

                                                onLoggedIn()                enter = fadeIn(animationSpec = tween(1000, delayMillis = 600)) + 

                                            } else {                        slideInVertically(initialOffsetY = { 50 })

                                                showMessage(resp.error ?: resp.message ?: "Identifiants invalides")            ) {

                                            }                OutlinedTextField(

                                        }                    value = password,

                                    } catch (e: Exception) {                    onValueChange = { password = it; passwordError = null },

                                        showMessage(ApiService.friendlyError(e))                    label = { Text("Mot de passe", color = Color.White.copy(alpha = 0.7f)) },

                                    } finally {                    leadingIcon = {

                                        loading = false                        Icon(

                                    }                            imageVector = Icons.Filled.Lock,

                                }                            contentDescription = null,

                            }                            tint = Gold

                        }                        )

                    ),                    },

                    isError = passwordError != null,                    trailingIcon = {

                    supportingText = passwordError?.let { { Text(it, color = AccentRed) } },                        IconButton(onClick = { passwordVisible = !passwordVisible }) {

                    modifier = Modifier.fillMaxWidth().bringIntoViewRequester(bringIntoViewRequester).onFocusEvent { if (it.isFocused) scope.launch { bringIntoViewRequester.bringIntoView() } },                            Icon(

                    shape = RoundedCornerShape(16.dp),                                imageVector = if (passwordVisible) Icons.Filled.Visibility else Icons.Filled.VisibilityOff,

                    colors = OutlinedTextFieldDefaults.colors(                                contentDescription = if (passwordVisible) "Masquer" else "Afficher",

                        focusedBorderColor = Gold,                                tint = Gold.copy(alpha = 0.7f)

                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),                            )

                        focusedTextColor = Color.White,                        }

                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),                    },

                        cursorColor = Gold,                    visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),

                        errorBorderColor = AccentRed,                    singleLine = true,

                        errorTextColor = Color.White                    keyboardOptions = KeyboardOptions(

                    )                        keyboardType = KeyboardType.Password,

                )                        imeAction = ImeAction.Done

            }                    ),

                    keyboardActions = KeyboardActions(

            Spacer(Modifier.height(32.dp))                        onDone = { 

                            focusManager.clearFocus()

            AnimatedVisibility(                            if (validate()) {

                visible = visible,                                scope.launch {

                enter = fadeIn(animationSpec = tween(1000, delayMillis = 800)) + slideInVertically(initialOffsetY = { 50 })                                    loading = true

            ) {                                    try {

                Box(                                        if (agentMode) {

                    modifier = Modifier                                            val resp = ApiService.agentLogin(login, password)

                        .fillMaxWidth()                                            if (resp.success) {

                        .height(60.dp)                                                showMessage("Connexion réussie")

                        .clip(RoundedCornerShape(16.dp))                                                onLoggedIn()

                        .background(Brush.horizontalGradient(colors = listOf(Gold, GoldLight)))                                            } else {

                ) {                                                showMessage(resp.error ?: resp.message ?: "Identifiants invalides")

                    Button(                                            }

                        onClick = {                                        } else {

                            focusManager.clearFocus()                                            val resp = ApiService.login(login, password)

                            if (!validate()) return@Button                                            if (resp.success) {

                            scope.launch {                                                resp.client?.telephone?.let { ClientStore.saveClientPhone(context, it) }

                                loading = true                                                showMessage("Bienvenue !")

                                try {                                                onLoggedIn()

                                    if (agentMode) {                                            } else {

                                        val resp = ApiService.agentLogin(login, password)                                                showMessage(resp.error ?: resp.message ?: "Identifiants invalides")

                                        if (resp.success) {                                            }

                                            showMessage("Connexion réussie")                                        }

                                            onLoggedIn()                                    } catch (e: Exception) {

                                        } else {                                        showMessage(ApiService.friendlyError(e))

                                            showMessage(resp.error ?: resp.message ?: "Identifiants invalides")                                    } finally {

                                        }                                        loading = false

                                    } else {                                    }

                                        val resp = ApiService.login(login, password)                                }

                                        if (resp.success) {                            }

                                            resp.client?.telephone?.let { ClientStore.saveClientPhone(context, it) }                        }

                                            showMessage("Bienvenue !")                    ),

                                            onLoggedIn()                    isError = passwordError != null,

                                        } else {                    supportingText = passwordError?.let { { Text(it, color = AccentRed) } },

                                            showMessage(resp.error ?: resp.message ?: "Identifiants invalides")                    modifier = Modifier

                                        }                        .fillMaxWidth()

                                    }                        .bringIntoViewRequester(bringIntoViewRequester)

                                } catch (e: Exception) {                        .onFocusEvent { if (it.isFocused) scope.launch { bringIntoViewRequester.bringIntoView() } },

                                    showMessage(ApiService.friendlyError(e))                    shape = RoundedCornerShape(16.dp),

                                } finally {                    colors = OutlinedTextFieldDefaults.colors(

                                    loading = false                        focusedBorderColor = Gold,

                                }                        unfocusedBorderColor = Color.White.copy(alpha = 0.3f),

                            }                        focusedTextColor = Color.White,

                        },                        unfocusedTextColor = Color.White.copy(alpha = 0.8f),

                        enabled = !loading,                        cursorColor = Gold,

                        modifier = Modifier.fillMaxSize(),                        errorBorderColor = AccentRed,

                        shape = RoundedCornerShape(16.dp),                        errorTextColor = Color.White

                        colors = ButtonDefaults.buttonColors(containerColor = Color.Transparent, disabledContainerColor = Color.Transparent),                    )

                        contentPadding = PaddingValues(0.dp)                )

                    ) {            }

                        if (loading) {

                            Row(            Spacer(Modifier.height(32.dp))

                                horizontalArrangement = Arrangement.Center,

                                verticalAlignment = Alignment.CenterVertically            // Bouton de connexion

                            ) {            AnimatedVisibility(

                                CircularProgressIndicator(                visible = visible,

                                    modifier = Modifier.size(24.dp),                enter = fadeIn(animationSpec = tween(1000, delayMillis = 800)) + 

                                    strokeWidth = 3.dp,                        slideInVertically(initialOffsetY = { 50 })

                                    color = Dark            ) {

                                )                Box(

                                Spacer(Modifier.width(12.dp))                    modifier = Modifier

                                Text(                        .fillMaxWidth()

                                    text = "Connexion...",                        .height(60.dp)

                                    fontSize = 16.sp,                        .clip(RoundedCornerShape(16.dp))

                                    fontWeight = FontWeight.Bold,                        .background(

                                    color = Dark                            Brush.horizontalGradient(

                                )                                colors = listOf(Gold, GoldLight)

                            }                            )

                        } else {                        )

                            Text(                ) {

                                text = "Se connecter",                    Button(

                                fontSize = 18.sp,                        onClick = {

                                fontWeight = FontWeight.Bold,                            focusManager.clearFocus()

                                color = Dark,                            if (!validate()) return@Button

                                letterSpacing = 1.sp                            scope.launch {

                            )                                loading = true

                        }                                try {

                    }                                    if (agentMode) {

                }                                        val resp = ApiService.agentLogin(login, password)

            }                                        if (resp.success) {

                                            showMessage("Connexion réussie")

            Spacer(Modifier.height(24.dp))                                            onLoggedIn()

                                        } else {

            AnimatedVisibility(                                            showMessage(resp.error ?: resp.message ?: "Identifiants invalides")

                visible = visible,                                        }

                enter = fadeIn(animationSpec = tween(1000, delayMillis = 1000))                                    } else {

            ) {                                        val resp = ApiService.login(login, password)

                Column(horizontalAlignment = Alignment.CenterHorizontally) {                                        if (resp.success) {

                    if (BuildConfig.DEBUG) {                                            resp.client?.telephone?.let { ClientStore.saveClientPhone(context, it) }

                        Card(                                            showMessage("Bienvenue !")

                            modifier = Modifier.fillMaxWidth(),                                            onLoggedIn()

                            shape = RoundedCornerShape(12.dp),                                        } else {

                            colors = CardDefaults.cardColors(containerColor = Info.copy(alpha = 0.1f)),                                            showMessage(resp.error ?: resp.message ?: "Identifiants invalides")

                            border = BorderStroke(1.dp, Info.copy(alpha = 0.3f))                                        }

                        ) {                                    }

                            Column(Modifier.padding(12.dp)) {                                } catch (e: Exception) {

                                Row(verticalAlignment = Alignment.CenterVertically) {                                    showMessage(ApiService.friendlyError(e))

                                    Icon(                                } finally {

                                        imageVector = Icons.Filled.DeveloperMode,                                    loading = false

                                        contentDescription = null,                                }

                                        tint = Info,                            }

                                        modifier = Modifier.size(16.dp)                        },

                                    )                        enabled = !loading,

                                    Spacer(Modifier.width(8.dp))                        modifier = Modifier.fillMaxSize(),

                                    Text(                        shape = RoundedCornerShape(16.dp),

                                        text = "Mode Debug",                        colors = ButtonDefaults.buttonColors(

                                        fontSize = 12.sp,                            containerColor = Color.Transparent,

                                        fontWeight = FontWeight.Bold,                            disabledContainerColor = Color.Transparent

                                        color = Info                        ),

                                    )                        contentPadding = PaddingValues(0.dp)

                                }                    ) {

                                Spacer(Modifier.height(4.dp))                        if (loading) {

                                Text(                            Row(

                                    text = "Backend: ${ApiConfig.BASE_URL}",                                horizontalArrangement = Arrangement.Center,

                                    fontSize = 11.sp,                                verticalAlignment = Alignment.CenterVertically

                                    color = Color.White.copy(alpha = 0.7f)                            ) {

                                )                                CircularProgressIndicator(

                            }                                    modifier = Modifier.size(24.dp),

                        }                                    strokeWidth = 3.dp,

                        Spacer(Modifier.height(16.dp))                                    color = Dark

                    }                                )

                                Spacer(Modifier.width(12.dp))

                    Text(                                Text(

                        text = if (agentMode) "Agent: Utilisez votre matricule ou téléphone" else "Test: test@test.com / abcde",                                    text = "Connexion...",

                        fontSize = 13.sp,                                    fontSize = 16.sp,

                        color = Color.White.copy(alpha = 0.5f),                                    fontWeight = FontWeight.Bold,

                        textAlign = TextAlign.Center,                                    color = Dark

                        modifier = Modifier.padding(horizontal = 16.dp)                                )

                    )                            }

                }                        } else {

            }                            Text(

                                text = "Se connecter",

            Spacer(Modifier.height(32.dp))                                fontSize = 18.sp,

        }                                fontWeight = FontWeight.Bold,

    }                                color = Dark,

}                                letterSpacing = 1.sp

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
