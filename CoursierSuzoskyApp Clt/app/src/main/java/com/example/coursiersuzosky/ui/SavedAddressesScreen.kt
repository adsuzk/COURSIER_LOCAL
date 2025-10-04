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
fun SavedAddressesScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val phone = remember { ClientStore.getClientPhone(context) ?: "" }

    var items by remember { mutableStateOf(listOf<ApiService.SavedAddress>()) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var label by remember { mutableStateOf("") }
    var address by remember { mutableStateOf("") }

    fun reload() {
        if (phone.isBlank()) return
        loading = true; error = null
        scope.launch {
            try { items = ApiService.listSavedAddresses(phone) } 
            catch (e: Exception) { error = ApiService.friendlyError(e) } 
            finally { loading = false }
        }
    }

    LaunchedEffect(Unit) { reload() }

    Column(Modifier.fillMaxSize().padding(16.dp)) {
        Text("Adresses enregistrées", style = MaterialTheme.typography.titleLarge)
        Spacer(Modifier.height(8.dp))
        if (loading) LinearProgressIndicator(Modifier.fillMaxWidth())
        if (error != null) Text(error!!, color = MaterialTheme.colorScheme.error)
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(value = label, onValueChange = { label = it }, label = { Text("Label (ex: Maison)") }, modifier = Modifier.fillMaxWidth())
        Spacer(Modifier.height(4.dp))
        OutlinedTextField(value = address, onValueChange = { address = it }, label = { Text("Adresse") }, modifier = Modifier.fillMaxWidth())
        Spacer(Modifier.height(8.dp))
        Button(onClick = {
            if (phone.isBlank() || label.isBlank() || address.isBlank()) return@Button
            scope.launch {
                loading = true
                try {
                    val ok = ApiService.addSavedAddress(phone, label, address, null, null)
                    if (ok) { label = ""; address = ""; reload() }
                } finally { loading = false }
            }
        }) { Text("Ajouter") }
        Spacer(Modifier.height(12.dp))
        LazyColumn(Modifier.fillMaxSize()) {
            items(items) { itx ->
                Card(Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
                    Column(Modifier.padding(12.dp)) {
                        Text(itx.label, style = MaterialTheme.typography.titleMedium)
                        Text(itx.address, style = MaterialTheme.typography.bodyMedium)
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                            TextButton(onClick = {
                                scope.launch {
                                    val ok = ApiService.deleteSavedAddress(phone, itx.id)
                                    if (ok) reload()
                                }
                            }) { Text("Supprimer") }
                        }
                    }
                }
            }
        }
    }
}
