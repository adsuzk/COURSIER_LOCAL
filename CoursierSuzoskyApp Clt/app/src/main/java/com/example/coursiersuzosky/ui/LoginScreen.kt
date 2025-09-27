package com.example.coursiersuzosky.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.relocation.BringIntoViewRequester
import androidx.compose.foundation.relocation.bringIntoViewRequester
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.onFocusEvent
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.launch
import com.example.coursiersuzosky.net.ApiService
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.Phone
import com.example.coursiersuzosky.net.ApiConfig
import com.example.coursiersuzosky.BuildConfig

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun LoginScreen(onLoggedIn: () -> Unit, showMessage: (String) -> Unit) {
    val scope = rememberCoroutineScope()
    // Pré-remplir automatiquement en Debug pour accélérer les tests
    var login by remember { mutableStateOf(if (BuildConfig.DEBUG) "test@test.com" else "") } // email/téléphone client OU matricule/téléphone agent
    var password by remember { mutableStateOf(if (BuildConfig.DEBUG) "abcde" else "") }
    var agentMode by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(false) }

    var loginError by remember { mutableStateOf<String?>(null) }
    var passwordError by remember { mutableStateOf<String?>(null) }

    fun validate(): Boolean {
        var ok = true
        if (agentMode) {
            val isMatricule = login.matches(Regex("^[A-Za-z0-9_-]{3,}$"))
            val isPhoneAgent = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))
            if (login.isBlank() || !(isMatricule || isPhoneAgent)) {
                loginError = "Entrez votre matricule ou téléphone (+225XXXXXXXXXX)"
                ok = false
            } else {
                loginError = null
            }
        } else {
            // Validation email ou téléphone (simple)
            val isEmail = android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches()
            // CI format: +225 followed by exactly 10 digits, separators allowed
            val isPhone = login.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$", RegexOption.IGNORE_CASE))
            if (login.isBlank() || !(isEmail || isPhone)) {
                loginError = "Entrez un email ou un téléphone CI au format +225XXXXXXXXXX"
                ok = false
            } else {
                loginError = null
            }
        }
        // Backend par défaut exige 5 caractères
        if (password.length != 5) {
            passwordError = "Le mot de passe doit contenir exactement 5 caractères"
            ok = false
        } else {
            passwordError = null
        }
        return ok
    }

    val scroll = rememberScrollState()
    val bringIntoViewRequester = remember { BringIntoViewRequester() }
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(24.dp)
            .verticalScroll(scroll)
            .imePadding(),
        verticalArrangement = Arrangement.Top,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        if (BuildConfig.DEBUG) {
            Text(
                text = "Backend: ${ApiConfig.BASE_URL}",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.secondary
            )
            Spacer(Modifier.height(8.dp))
        }
        Row(verticalAlignment = Alignment.CenterVertically) {
            Checkbox(checked = agentMode, onCheckedChange = { agentMode = it })
            Spacer(Modifier.width(4.dp))
            Text("Je suis un agent/coursier")
        }
        Text("Connexion", style = MaterialTheme.typography.headlineMedium)
        Spacer(Modifier.height(16.dp))
        OutlinedTextField(
            value = login,
            onValueChange = { login = it; if (loginError != null) loginError = null },
            label = { Text(if (agentMode) "Matricule ou Téléphone" else "Email ou Téléphone") },
            singleLine = true,
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
                .onFocusEvent { if (it.isFocused) {
                    // Scroll doux vers le champ lorsqu'il reçoit le focus
                    scope.launch { bringIntoViewRequester.bringIntoView() }
                } },
            leadingIcon = {
                if (!agentMode && android.util.Patterns.EMAIL_ADDRESS.matcher(login).matches())
                    Icon(Icons.Default.Email, contentDescription = null)
                else
                    Icon(Icons.Default.Phone, contentDescription = null)
            },
            isError = loginError != null,
            supportingText = { if (loginError != null) Text(loginError!!, color = MaterialTheme.colorScheme.error) }
        )
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(
            value = password,
            onValueChange = { password = it; if (passwordError != null) passwordError = null },
            label = { Text("Mot de passe (5 caractères)") },
            singleLine = true,
            visualTransformation = PasswordVisualTransformation(),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
                .onFocusEvent { if (it.isFocused) {
                    scope.launch { bringIntoViewRequester.bringIntoView() }
                } },
            isError = passwordError != null,
            supportingText = { if (passwordError != null) Text(passwordError!!, color = MaterialTheme.colorScheme.error) }
        )
        Spacer(Modifier.height(16.dp))
        Button(
            onClick = {
                if (!validate()) return@Button
                scope.launch {
                    loading = true
                    try {
                        if (agentMode) {
                            val resp = ApiService.agentLogin(login, password)
                            if (resp.success) {
                                showMessage(resp.message ?: "Connexion réussie (agent)")
                                onLoggedIn()
                            } else {
                                val msg = resp.error ?: resp.message ?: "Identifiants invalides"
                                showMessage(msg)
                            }
                        } else {
                            val resp = ApiService.login(login, password)
                            if (resp.success) {
                                showMessage(resp.message ?: "Connexion réussie")
                                onLoggedIn()
                            } else {
                                val msg = resp.error ?: resp.message ?: "Identifiants invalides"
                                showMessage(msg)
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
            modifier = Modifier.fillMaxWidth()
        ) {
            if (loading) {
                CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text("Connexion…")
            } else {
                Text("Se connecter")
            }
        }
        Spacer(Modifier.height(8.dp))
        Text(
            text = if (agentMode) "Astuce: utilisez votre matricule (ex: CM2025xxxx) et mot de passe 5 car." else "Astuce: compte de test: test@test.com / abcde",
            style = MaterialTheme.typography.bodySmall,
            color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f),
            textAlign = TextAlign.Center
        )
        Spacer(Modifier.height(24.dp))
    }
}
