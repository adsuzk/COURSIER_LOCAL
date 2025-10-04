package com.suzosky.coursierclient.ui

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.suzosky.coursierclient.net.ClientStore
import com.suzosky.coursierclient.net.ApiService
import kotlinx.coroutines.launch

@Composable
fun OrderHistoryScreen() {
    val context = LocalContext.current
    val phone = remember { ClientStore.getClientPhone(context) ?: "" }
    var items by remember { mutableStateOf(listOf<ApiService.OrderHistoryItem>()) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(phone) {
        if (phone.isBlank()) return@LaunchedEffect
        loading = true; error = null
        try { items = ApiService.getClientOrders(phone) } 
        catch (e: Exception) { error = ApiService.friendlyError(e) } 
        finally { loading = false }
    }

    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Text("Historique de commandes", style = MaterialTheme.typography.titleLarge)
        if (loading) LinearProgressIndicator(Modifier.fillMaxWidth())
        if (error != null) Text(error!!, color = MaterialTheme.colorScheme.error)
        Spacer(Modifier.height(8.dp))
        LazyColumn(Modifier.fillMaxSize()) {
            items(items) { itx ->
                Card(Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
                    Column(Modifier.padding(12.dp)) {
                        Text("#${itx.numero_commande}", style = MaterialTheme.typography.titleMedium)
                        Text("De: ${itx.adresse_depart}")
                        Text("À: ${itx.adresse_arrivee}")
                        Text("Montant: ${itx.prix_estime} FCFA")
                        Text("Date: ${itx.date_creation}")
                    }
                }
            }
        }
    }
}
