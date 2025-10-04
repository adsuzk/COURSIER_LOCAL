package com.suzosky.coursierclient.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import com.suzosky.coursierclient.net.ClientStore
import com.suzosky.coursierclient.net.ApiService
import com.suzosky.coursierclient.net.ClientInfo
import kotlinx.coroutines.launch

@Composable
fun ProfileInfoScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var clientPhone by remember { mutableStateOf(ClientStore.getClientPhone(context) ?: "") }
    var info by remember { mutableStateOf<ClientInfo?>(null) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(clientPhone) {
        if (clientPhone.isNotBlank()) {
            loading = true
            error = null
            try {
                val resp = ApiService.getClientInfo(clientPhone)
                if (resp.success) info = resp.data else error = resp.message ?: "Profil introuvable"
            } catch (e: Exception) {
                error = ApiService.friendlyError(e)
            } finally { loading = false }
        }
    }

    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Text("Informations personnelles", style = MaterialTheme.typography.titleLarge)
        Spacer(Modifier.height(12.dp))
        if (loading) LinearProgressIndicator(Modifier.fillMaxWidth())
        if (error != null) Text(error!!, color = MaterialTheme.colorScheme.error)
        info?.let { c: ClientInfo ->
            Text("Nom: ${c.prenoms} ${c.nom}")
            Text("Email: ${c.email}")
            Text("Téléphone: ${c.telephone}")
        }
    }
}
