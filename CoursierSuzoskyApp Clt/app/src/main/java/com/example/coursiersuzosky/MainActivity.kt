package com.example.coursiersuzosky

import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.consumeWindowInsets
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import com.example.coursiersuzosky.ui.LoginScreen
import com.example.coursiersuzosky.ui.OrderScreen
import com.example.coursiersuzosky.ui.theme.CoursierSuzoskyTheme
import com.example.coursiersuzosky.net.SessionManager
import com.example.coursiersuzosky.net.ApiClient
import kotlinx.coroutines.launch
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material3.ExperimentalMaterial3Api
import android.content.Context
import com.google.android.libraries.places.api.Places
import com.google.android.gms.common.ConnectionResult
import com.google.android.gms.common.GoogleApiAvailability
import androidx.compose.material.icons.filled.BugReport
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import android.content.pm.PackageManager
import android.os.Build
import java.security.MessageDigest
import java.util.Locale
import androidx.core.content.edit

@OptIn(ExperimentalMaterial3Api::class)
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        
        // Vérification Google Play Services
        val gapi = GoogleApiAvailability.getInstance()
        val resultCode = gapi.isGooglePlayServicesAvailable(this)
        if (resultCode != ConnectionResult.SUCCESS) {
            Log.e("MainActivity", "Google Play Services non disponible: $resultCode")
            if (gapi.isUserResolvableError(resultCode)) {
                gapi.getErrorDialog(this, resultCode, 9000)?.show()
            }
        } else {
            Log.d("MainActivity", "Google Play Services OK")
        }
        
        // Init API client (persistent cookies)
        ApiClient.init(applicationContext)
        
        // Init Google Places avec gestion d'erreur
        try {
            if (!Places.isInitialized()) {
                val apiKey = getString(R.string.google_places_key)
                Log.d("MainActivity", "Initialisation Places avec clé: ${apiKey.take(10)}...")
                Places.initialize(applicationContext, apiKey)
                Log.d("MainActivity", "Places initialisé avec succès")
            } else {
                Log.d("MainActivity", "Places déjà initialisé")
            }
        } catch (e: Exception) {
            Log.e("MainActivity", "Erreur initialisation Places", e)
        }
        setContent {
            CoursierSuzoskyTheme {
                val snackbarHostState = remember { SnackbarHostState() }
                val session = remember { SessionManager(this) }
                var isLoggedIn by remember { mutableStateOf(false) }
                LaunchedEffect(Unit) {
                    session.isLoggedIn.collect { isLoggedIn = it }
                }

                val scope = rememberCoroutineScope()
                val showMessage: (String) -> Unit = { msg -> scope.launch { snackbarHostState.showSnackbar(msg) } }
                var showDiagnostics by remember { mutableStateOf(false) }
                Scaffold(
                    modifier = Modifier.fillMaxSize(),
                    snackbarHost = { SnackbarHost(snackbarHostState) },
                    topBar = {
                        if (isLoggedIn) {
                            TopAppBar(title = { Text("") }, actions = {
                                // Debug-only diagnostics entry point
                                if (BuildConfig.DEBUG) {
                                    IconButton(onClick = { showDiagnostics = true }) {
                                        Icon(Icons.Filled.BugReport, contentDescription = "Diagnostics")
                                    }
                                }
                                IconButton(onClick = {
                                    scope.launch {
                                        session.setLoggedIn(false)
                                        // Clear cookies via KTX edit extension
                                        applicationContext.getSharedPreferences("cookies", Context.MODE_PRIVATE).edit {
                                            clear()
                                        }
                                        showMessage("Déconnecté")
                                    }
                                }) { Icon(Icons.AutoMirrored.Filled.Logout, contentDescription = "Se déconnecter") }
                            })
                        }
                    }
                ) { innerPadding ->
                    Box(Modifier
                        .padding(innerPadding)
                        .consumeWindowInsets(innerPadding)
                    ) {
                        if (!isLoggedIn) {
                            LoginScreen(
                                onLoggedIn = {
                                    scope.launch { session.setLoggedIn(true) }
                                    isLoggedIn = true
                                },
                                showMessage = showMessage
                            )
                        } else {
                            OrderScreen(showMessage = showMessage)
                        }
                        if (showDiagnostics) {
                            DiagnosticsDialog(
                                onDismiss = { showDiagnostics = false }
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun DiagnosticsDialog(onDismiss: () -> Unit) {
    val context = LocalContext.current
    val gapi = remember { GoogleApiAvailability.getInstance() }
    val gmsResult = remember { gapi.isGooglePlayServicesAvailable(context) }
    val gmsText = remember { gapi.getErrorString(gmsResult) }
    val pkgName = remember { context.packageName }
    val appId = BuildConfig.APPLICATION_ID
    val buildType = BuildConfig.BUILD_TYPE
    val mapsKey = try { context.getString(R.string.google_maps_key) } catch (_: Exception) { "" }
    val placesKey = try { context.getString(R.string.google_places_key) } catch (_: Exception) { "" }
    val mapsKeyPreview = if (mapsKey.isNotEmpty()) mapsKey.take(12) + "…" else "(absent)"
    val placesKeyPreview = if (placesKey.isNotEmpty()) placesKey.take(12) + "…" else "(absent)"
    val sha1 = remember { getSigningSha1(context) }
    val placesInit = remember { try { com.google.android.libraries.places.api.Places.isInitialized() } catch (_: Exception) { false } }

    AlertDialog(
        onDismissRequest = onDismiss,
        confirmButton = {
            TextButton(onClick = onDismiss) { Text("Fermer") }
        },
        title = { Text("Diagnostics") },
        text = {
            Column {
                Text("Package: $pkgName")
                Text("AppId: $appId ($buildType)")
                Spacer(Modifier.height(8.dp))
                Text("SHA-1: ${sha1.ifEmpty { "(indisponible)" }}")
                Spacer(Modifier.height(8.dp))
                Text("GMS status: $gmsResult ($gmsText)")
                Text("Places init: $placesInit")
                Spacer(Modifier.height(8.dp))
                Text("Maps key: $mapsKeyPreview")
                Text("Places key: $placesKeyPreview")
            }
        }
    )
}

@Suppress("DEPRECATION")
private fun getSigningSha1(context: Context): String {
    return try {
        val pm = context.packageManager
        val packageName = context.packageName
        val sigBytes: ByteArray? = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            val info = pm.getPackageInfo(packageName, PackageManager.GET_SIGNING_CERTIFICATES)
            info.signingInfo?.apkContentsSigners?.firstOrNull()?.toByteArray()
        } else {
            val info = pm.getPackageInfo(packageName, PackageManager.GET_SIGNATURES)
            @Suppress("DEPRECATION")
            info.signatures?.firstOrNull()?.toByteArray()
        }
        if (sigBytes == null) return ""
        val md = MessageDigest.getInstance("SHA1")
        md.update(sigBytes)
        val digest = md.digest()
        digest.joinToString(":") { b -> String.format(Locale.US, "%02X", b) }
    } catch (e: Exception) {
        Log.e("Diagnostics", "Impossible de calculer le SHA-1", e)
        ""
    }
}