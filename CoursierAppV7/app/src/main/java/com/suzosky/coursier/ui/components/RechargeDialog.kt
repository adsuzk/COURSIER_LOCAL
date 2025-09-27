package com.suzosky.coursier.ui.components

import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AttachMoney
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.foundation.text.KeyboardOptions

@Composable
fun RechargeDialog(
    onDismiss: () -> Unit,
    onConfirm: (Int) -> Unit,
    defaultAmount: Int = 5000
) {
    var amountText by remember { mutableStateOf(defaultAmount.toString()) }
    val focusManager = LocalFocusManager.current

    AlertDialog(
        onDismissRequest = onDismiss,
        icon = { Icon(Icons.Default.AttachMoney, contentDescription = null) },
        title = { Text(text = "Recharger le solde") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(text = "Entrez le montant à recharger (FCFA)")
                OutlinedTextField(
                    value = amountText,
                    onValueChange = { new -> if (new.all { it.isDigit() }) amountText = new },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth()
                )
            }
        },
        confirmButton = {
            TextButton(onClick = {
                focusManager.clearFocus()
                val value = amountText.toIntOrNull() ?: 0
                if (value > 0) onConfirm(value) else onDismiss()
            }) { Text("Confirmer") }
        },
        dismissButton = {
            TextButton(onClick = {
                focusManager.clearFocus(); onDismiss()
            }) { Text("Annuler") }
        }
    )
}
