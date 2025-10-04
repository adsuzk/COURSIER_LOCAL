package com.suzosky.coursierclient

import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import com.suzosky.coursierclient.ui.*
import com.suzosky.coursierclient.ui.theme.CoursierSuzoskyTheme
import com.suzosky.coursierclient.ui.theme.Dark
import com.suzosky.coursierclient.ui.theme.Gold
import com.suzosky.coursierclient.net.SessionManager
import com.suzosky.coursierclient.net.ApiClient
import kotlinx.coroutines.launch
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.ExperimentalMaterial3Api
import android.content.Context
import com.google.android.libraries.places.api.Places
import com.google.android.gms.common.ConnectionResult
import com.google.android.gms.common.GoogleApiAvailability
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import android.content.pm.PackageManager
import android.os.Build
import java.security.MessageDigest
import java.util.Locale
import androidx.core.content.edit
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.compose.currentBackStackEntryAsState

// Routes de navigation (simplified - 3 screens)
sealed class Screen(val route: String, val title: String, val icon: androidx.compose.ui.graphics.vector.ImageVector) {
    object Home : Screen("home", "Accueil", Icons.Filled.Home)
    object Orders : Screen("orders", "Commandes", Icons.Filled.Receipt)
    object Profile : Screen("profile", "Profil", Icons.Filled.Person)
    object ProfileInfo : Screen("profile_info", "Informations personnelles", Icons.Filled.Person)
    object SavedAddresses : Screen("saved_addresses", "Adresses", Icons.Filled.LocationOn)
    object OrderHistory : Screen("order_history", "Historique", Icons.Filled.History)
    object Cgu : Screen("cgu", "CGU", Icons.Filled.Gavel)
    
    // Order screen accessible via navigation action
    object Order : Screen("order", "Commander", Icons.Filled.ShoppingCart)
}

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
                val showMessage: (String) -> Unit = { msg -> 
                    scope.launch { snackbarHostState.showSnackbar(msg) } 
                }
                
                if (!isLoggedIn) {
                    // Écran de connexion
                    Scaffold(
                        modifier = Modifier.fillMaxSize(),
                        snackbarHost = { SnackbarHost(snackbarHostState) }
                    ) { innerPadding ->
                        Box(Modifier.padding(innerPadding)) {
                            LoginScreen(
                                onLoggedIn = {
                                    scope.launch { session.setLoggedIn(true) }
                                    isLoggedIn = true
                                },
                                showMessage = showMessage
                            )
                        }
                    }
                } else {
                    // Navigation principale après connexion
                    MainNavigation(
                        snackbarHostState = snackbarHostState,
                        onLogout = {
                            scope.launch {
                                session.setLoggedIn(false)
                                applicationContext.getSharedPreferences("cookies", Context.MODE_PRIVATE).edit {
                                    clear()
                                }
                                showMessage("Déconnexion réussie")
                            }
                        },
                        showMessage = showMessage
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MainNavigation(
    snackbarHostState: SnackbarHostState,
    onLogout: () -> Unit,
    showMessage: (String) -> Unit
) {
    val navController = rememberNavController()
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route
    var showDiagnostics by remember { mutableStateOf(false) }
    
    Scaffold(
        modifier = Modifier.fillMaxSize(),
        snackbarHost = { SnackbarHost(snackbarHostState) },
        topBar = {
            // Hide top bar for Home screen for immersive experience
            if (currentRoute != Screen.Home.route) {
                TopAppBar(
                    title = {
                        Text(
                            text = when (currentRoute) {
                                Screen.Orders.route -> "Mes Commandes"
                                Screen.Order.route -> "Nouvelle Commande"
                                Screen.Profile.route -> "Mon Profil"
                                else -> "SUZOSKY"
                            },
                            style = MaterialTheme.typography.titleLarge.copy(
                                fontWeight = FontWeight.Bold
                            )
                        )
                    },
                    colors = TopAppBarDefaults.topAppBarColors(
                        containerColor = Dark.copy(alpha = 0.95f),
                        titleContentColor = Gold
                    ),
                    navigationIcon = {
                        if (currentRoute == Screen.Order.route) {
                            IconButton(onClick = { navController.popBackStack() }) {
                                Icon(
                                    imageVector = Icons.Filled.ArrowBack,
                                    contentDescription = "Retour",
                                    tint = Gold
                                )
                            }
                        }
                    },
                    actions = {
                        if (BuildConfig.DEBUG) {
                            IconButton(onClick = { showDiagnostics = true }) {
                                Icon(
                                    imageVector = Icons.Filled.BugReport,
                                    contentDescription = "Diagnostics",
                                    tint = Color.White
                                )
                            }
                        }
                    }
                )
            }
        },
        bottomBar = {
            NavigationBar(
                containerColor = Dark,
                contentColor = Gold
            ) {
                listOf(
                    Screen.Home,
                    Screen.Orders,
                    Screen.Profile
                ).forEach { screen ->
                    NavigationBarItem(
                        icon = {
                            Icon(
                                imageVector = screen.icon,
                                contentDescription = screen.title
                            )
                        },
                        label = { Text(screen.title) },
                        selected = currentRoute == screen.route,
                        onClick = {
                            if (currentRoute != screen.route) {
                                navController.navigate(screen.route) {
                                    popUpTo(Screen.Home.route) { saveState = true }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            }
                        },
                        colors = NavigationBarItemDefaults.colors(
                            selectedIconColor = Gold,
                            selectedTextColor = Gold,
                            unselectedIconColor = Color.White.copy(alpha = 0.6f),
                            unselectedTextColor = Color.White.copy(alpha = 0.6f),
                            indicatorColor = Gold.copy(alpha = 0.2f)
                        )
                    )
                }
            }
        }
    ) { innerPadding ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
        ) {
            NavHost(
                navController = navController,
                startDestination = Screen.Home.route
            ) {
                composable(Screen.Home.route) {
                    HomeScreen(
                        onNavigateToOrder = { navController.navigate(Screen.Order.route) }
                    )
                }
                
                composable(Screen.Orders.route) {
                    OrdersScreen()
                }
                
                composable(Screen.Order.route) {
                    OrderScreen(showMessage = showMessage)
                }
                
                composable(Screen.Profile.route) {
                    ProfileScreen(
                        onLogout = onLogout,
                        onOpenInfo = { navController.navigate(Screen.ProfileInfo.route) },
                        onOpenSavedAddresses = { navController.navigate(Screen.SavedAddresses.route) },
                        onOpenHistory = { navController.navigate(Screen.OrderHistory.route) },
                        onOpenCgu = { navController.navigate(Screen.Cgu.route) }
                    )
                }
                composable(Screen.ProfileInfo.route) { ProfileInfoScreen() }
                composable(Screen.SavedAddresses.route) { SavedAddressesScreen() }
                composable(Screen.OrderHistory.route) { OrderHistoryScreen() }
                composable(Screen.Cgu.route) { CguScreen() }
            }
            
            if (showDiagnostics) {
                DiagnosticsDialog(
                    onDismiss = { showDiagnostics = false }
                )
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