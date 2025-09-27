package com.suzosky.coursier.ui.components

import android.annotation.SuppressLint
import android.graphics.Bitmap
import android.webkit.WebChromeClient
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.viewinterop.AndroidView

@SuppressLint("SetJavaScriptEnabled")
@Composable
fun PaymentWebViewDialog(
    url: String,
    onDismiss: () -> Unit,
    onCompleted: (success: Boolean, transactionId: String) -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Paiement CinetPay") },
        text = {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { context ->
                    WebView(context).apply {
                        settings.javaScriptEnabled = true
                        settings.domStorageEnabled = true
                        webChromeClient = WebChromeClient()
                        webViewClient = object : WebViewClient() {
                            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                                val uri = request?.url
                                if (uri != null && uri.toString().contains("/api/cinetpay_callback.php")) {
                                    val status = uri.getQueryParameter("status")?.lowercase()
                                    val transactionId = uri.getQueryParameter("transaction_id") ?: "N/A"
                                    val success = when (status) {
                                        null -> true // si pas de statut explicite, considérer comme succès
                                        "success", "accepted", "completed" -> true
                                        "failed", "canceled", "cancelled" -> false
                                        else -> false
                                    }
                                    onCompleted(success, transactionId)
                                    return true
                                }
                                return super.shouldOverrideUrlLoading(view, request)
                            }
                            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                                super.onPageStarted(view, url, favicon)
                            }
                        }
                        loadUrl(url)
                    }
                }
            )
        },
        confirmButton = {
            TextButton(onClick = onDismiss) { Text("Fermer") }
        }
    )
}
