package com.suzosky.coursier.ui.components

import android.content.Context
import android.net.Uri
import androidx.browser.customtabs.CustomTabsIntent
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.window.DialogProperties
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.suzosky.coursier.network.ApiService
import kotlinx.coroutines.delay
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlin.coroutines.resume

/**
 * PaymentNativeDialog
 * Ouvre l'URL de paiement dans un onglet Chrome Custom Tab et sonde périodiquement
 * l'API pour détecter la mise à jour du solde. Pas de WebView embarqué.
 */
@Composable
fun PaymentNativeDialog(
    context: Context,
    paymentUrl: String,
    coursierId: Int,
    initialBalance: Double,
    pollIntervalMs: Long = 4_000,
    timeoutMs: Long = 2 * 60_000,
    onDismiss: () -> Unit,
    onCompleted: (success: Boolean, newBalance: Double?) -> Unit
) {
    var launched by remember { mutableStateOf(false) }
    var cancelled by remember { mutableStateOf(false) }
    var remainingMs by remember { mutableStateOf(timeoutMs) }

    // Ouvre la page de paiement une fois
    LaunchedEffect(Unit) {
        if (!launched) {
            launched = true
            openInCustomTab(context, paymentUrl)
        }
    }

    // Boucle de polling: vérifie si le solde a augmenté
    LaunchedEffect(launched, cancelled) {
        if (!launched || cancelled) return@LaunchedEffect
        val start = System.currentTimeMillis()
        var lastBalance = initialBalance
        while (!cancelled && System.currentTimeMillis() - start < timeoutMs) {
            val balance = fetchBalance(coursierId)
            if (balance != null) {
                // Considère succès si le solde augmente d'au moins 1 (ou toute variation positive)
                if (balance > initialBalance) {
                    onCompleted(true, balance)
                    return@LaunchedEffect
                }
                lastBalance = balance
            }
            delay(pollIntervalMs)
            remainingMs = timeoutMs - (System.currentTimeMillis() - start)
        }
        // Timeout ou annulé
        if (!cancelled) onCompleted(false, lastBalance)
    }

    AlertDialog(
        onDismissRequest = {
            cancelled = true
            onDismiss()
        },
        title = { Text("Paiement en cours") },
        text = {
            Column {
                CircularProgressIndicator()
                Spacer(modifier = Modifier.height(12.dp))
                Text("Veuillez finaliser le paiement dans la page ouverte.")
                val secondsLeft = (remainingMs / 1000).coerceAtLeast(0)
                Text("Vérification automatique… ${'$'}secondsLeft s")
            }
        },
        confirmButton = {
            TextButton(onClick = {
                cancelled = true
                onDismiss()
            }) { Text("Annuler") }
        },
        properties = DialogProperties(dismissOnBackPress = true, dismissOnClickOutside = false)
    )
}

private fun openInCustomTab(context: Context, url: String) {
    try {
        val intent = CustomTabsIntent.Builder()
            .setShowTitle(true)
            .build()
        intent.launchUrl(context, Uri.parse(url))
    } catch (_: Exception) {
        // Fallback navigateur système si Custom Tabs indisponible
        try {
            val viewIntent = android.content.Intent(android.content.Intent.ACTION_VIEW, Uri.parse(url))
            viewIntent.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK)
            context.startActivity(viewIntent)
        } catch (_: Exception) {}
    }
}

private suspend fun fetchBalance(coursierId: Int): Double? = suspendCancellableCoroutine { cont ->
    ApiService.getCoursierData(coursierId) { data, error ->
        if (error != null) {
            cont.resume(null)
        } else {
            val balance = try {
                when (val v = data?.get("balance")) {
                    is Number -> v.toDouble()
                    is String -> v.toDoubleOrNull()
                    else -> null
                }
            } catch (_: Exception) { null }
            cont.resume(balance)
        }
    }
}
