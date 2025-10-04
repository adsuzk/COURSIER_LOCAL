@file:Suppress("DEPRECATION")
@file:OptIn(com.google.maps.android.compose.MapsComposeExperimentalApi::class)
package com.suzosky.coursierclient.ui

import androidx.core.net.toUri
import androidx.browser.customtabs.CustomTabsIntent
import androidx.compose.foundation.layout.*
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.relocation.BringIntoViewRequester
import androidx.compose.foundation.relocation.bringIntoViewRequester
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.material3.*
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.expandVertically
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.core.tween
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.saveable.listSaver
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.material3.LinearProgressIndicator
import com.suzosky.coursierclient.net.*
import kotlinx.coroutines.launch
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.focus.onFocusEvent
import androidx.compose.material3.AlertDialog
import androidx.core.graphics.createBitmap
import com.google.android.libraries.places.api.model.AutocompleteSessionToken
import com.google.android.libraries.places.api.net.FetchPlaceRequest
import com.google.android.libraries.places.api.net.FindAutocompletePredictionsRequest
import com.google.android.libraries.places.api.model.Place
import com.google.android.libraries.places.api.Places
import com.google.android.gms.maps.model.LatLng
import com.google.maps.android.compose.GoogleMap
import com.google.maps.android.compose.Marker
import com.google.maps.android.compose.MarkerState
import com.google.maps.android.compose.rememberCameraPositionState
import com.google.maps.android.compose.MapEffect
import com.google.android.gms.maps.CameraUpdateFactory
import com.google.android.gms.maps.GoogleMap
import com.google.android.gms.maps.model.LatLngBounds
import com.google.android.gms.maps.model.CameraPosition
import okhttp3.Request
import okhttp3.HttpUrl.Companion.toHttpUrl
import android.location.Geocoder
import java.util.Locale
import android.graphics.Bitmap
import android.graphics.Canvas
import android.graphics.Paint
import android.graphics.RectF
import android.graphics.Typeface
import com.google.android.gms.maps.model.BitmapDescriptorFactory
 
// Phone helpers (top-level)
private fun normalizeDigits(s: String): String = s.filter { it.isDigit() }
private fun formatCiPhone(input: String, enforcePrefix: Boolean = true): String {
    val digits = normalizeDigits(input)
    val raw = if (digits.startsWith("225")) digits.drop(3) else digits
    val trimmed = raw.take(10)
    val pairs = trimmed.chunked(2)
    val grouped = pairs.joinToString(" ") { it.padEnd(2, ' ') }.trim()
    return if (enforcePrefix || digits.startsWith("225") || input.startsWith("+225")) "+225 " + grouped else grouped
}


@OptIn(ExperimentalFoundationApi::class)
@Composable
fun OrderScreen(showMessage: (String) -> Unit) {
    val scope = rememberCoroutineScope()
    var departure by rememberSaveable { mutableStateOf("") }
    var destination by rememberSaveable { mutableStateOf("") }
    // Saver for LatLng across process death/rotation
    val latLngSaver = remember {
        listSaver<LatLng?, Double>(
            save = { ll -> if (ll == null) emptyList() else listOf(ll.latitude, ll.longitude) },
            restore = { lst -> if (lst.size == 2) LatLng(lst[0], lst[1]) else null }
        )
    }
    var departureLatLng by rememberSaveable(stateSaver = latLngSaver) { mutableStateOf<LatLng?>(null) }
    var destinationLatLng by rememberSaveable(stateSaver = latLngSaver) { mutableStateOf<LatLng?>(null) }
    // Sender phone is locked to account; managed via ClientStore observer below
    var senderPhone by rememberSaveable { mutableStateOf("") }
    var receiverPhone by rememberSaveable { mutableStateOf("") }
    var description by rememberSaveable { mutableStateOf("") }
    var priority by rememberSaveable { mutableStateOf("normale") } // normale, urgente, express
    var paymentMethod by rememberSaveable { mutableStateOf("cash") } // cash, orange_money, mtn_money, moov_money, wave, card

    var estimating by rememberSaveable { mutableStateOf(false) }
    var submitting by rememberSaveable { mutableStateOf(false) }
    var totalPrice by rememberSaveable { mutableStateOf<Int?>(null) }
    var distanceTxt by rememberSaveable { mutableStateOf<String?>(null) }
    var durationTxt by rememberSaveable { mutableStateOf<String?>(null) }
    var showPaymentModal by rememberSaveable { mutableStateOf(false) }
    var paymentUrl by rememberSaveable { mutableStateOf<String?>(null) }
    var pendingOnlineOrder by rememberSaveable { mutableStateOf(false) }
    var couriersAvailable by rememberSaveable { mutableStateOf(true) }
    var availabilityMessage by rememberSaveable { mutableStateOf<String?>(null) }

    // Poll courier availability periodically (e.g., every 15s) and on screen start
    LaunchedEffect(Unit) {
        while (true) {
            try {
                val av = ApiService.getCourierAvailability()
                couriersAvailable = av.available
                availabilityMessage = av.message
            } catch (_: Exception) {
                // keep last known state
            }
            delay(15000)
        }
    }

    val context = LocalContext.current
    // Lock sender phone to account phone and keep it synced with any profile change
    LaunchedEffect(Unit) {
        try {
            // Initial read
            com.suzosky.coursierclient.net.ClientStore.getClientPhone(context)?.let { p ->
                senderPhone = formatCiPhone(p, enforcePrefix = true)
            }
            // Observe future updates
            com.suzosky.coursierclient.net.ClientStore.observeClientPhone(context).collect { newPhone ->
                if (!newPhone.isNullOrBlank()) {
                    senderPhone = formatCiPhone(newPhone, enforcePrefix = true)
                }
            }
        } catch (_: Exception) { }
    }
    val bringIntoViewRequester = remember { BringIntoViewRequester() }

    suspend fun reverseGeocode(ll: LatLng): String? = withContext(Dispatchers.IO) {
        try {
            val geocoder = Geocoder(context, Locale.getDefault())
            val list = geocoder.getFromLocation(ll.latitude, ll.longitude, 1)
            list?.firstOrNull()?.getAddressLine(0)
        } catch (_: Exception) {
            null
        }
    }

    suspend fun geocodeAddress(text: String): LatLng? = withContext(Dispatchers.IO) {
        // D'abord via Geocoder Android, puis fallback Nominatim
        try {
            val geocoder = Geocoder(context, Locale.getDefault())
            val list = geocoder.getFromLocationName(text, 1)
            val a = list?.firstOrNull()
            if (a != null) return@withContext LatLng(a.latitude, a.longitude)
        } catch (_: Exception) { /* ignore */ }
        try {
            val url = "https://nominatim.openstreetmap.org/search".toHttpUrl()
                .newBuilder()
                .addQueryParameter("format", "json")
                .addQueryParameter("limit", "1")
                .addQueryParameter("countrycodes", "ci")
                .addQueryParameter("q", text)
                .build()
            val req = Request.Builder().url(url)
                .header("User-Agent", "CoursierSuzosky/1.0")
                .get()
                .build()
            ApiClient.http.newCall(req).execute().use { resp ->
                val body = resp.body?.string().orEmpty()
                val arr = org.json.JSONArray(body)
                if (arr.length() > 0) {
                    val obj = arr.getJSONObject(0)
                    val lat = obj.optDouble("lat")
                    val lon = obj.optDouble("lon")
                    return@withContext LatLng(lat, lon)
                }
            }
        } catch (_: Exception) { /* ignore */ }
        return@withContext null
    }

    // Update banner state
    var updateInfo by remember { mutableStateOf<UpdateInfo?>(null) }
    var showUpdate by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) {
        try {
            val info = ApiService.getAppUpdate()
            updateInfo = info
            showUpdate = info.update_available
        } catch (_: Exception) {
            // ignore
        }
    }

    // Errors
    var departureError by remember { mutableStateOf<String?>(null) }
    var destinationError by remember { mutableStateOf<String?>(null) }
    var senderPhoneError by rememberSaveable { mutableStateOf<String?>(null) }
    var receiverPhoneError by remember { mutableStateOf<String?>(null) }

    // CI format: +225 followed by exactly 10 digits (separators allowed)
    fun phoneValid(p: String): Boolean = p.matches(Regex("^\\+225( \\d{2}){5}$"))

    fun validateInputs(forSubmit: Boolean = false): Boolean {
        var ok = true
        if (departure.isBlank()) { departureError = "Adresse de départ requise"; ok = false } else departureError = null
        if (destination.isBlank()) { destinationError = "Adresse d'arrivée requise"; ok = false } else destinationError = null
        if (forSubmit) {
            if (!phoneValid(senderPhone)) { senderPhoneError = "Téléphone du compte invalide (+225 et 10 chiffres)"; ok = false } else senderPhoneError = null
            if (!phoneValid(receiverPhone)) { receiverPhoneError = "Format CI requis: +225 suivi de 10 chiffres"; ok = false } else receiverPhoneError = null
            if (paymentMethod != "cash" && (totalPrice == null || (totalPrice ?: 0) <= 0)) {
                showMessage("Veuillez d'abord estimer le prix pour un paiement en ligne")
                ok = false
            }
        }
        return ok
    }

    fun estimate() {
        if (!validateInputs(forSubmit = false)) return
        scope.launch {
            estimating = true
            try {
                // Géocoder les champs saisis si coordonnées manquantes (affichage A/B comme sur l'index)
                if (departureLatLng == null) {
                    val ll = geocodeAddress(departure)
                    if (ll != null) departureLatLng = ll
                }
                if (destinationLatLng == null) {
                    val ll = geocodeAddress(destination)
                    if (ll != null) destinationLatLng = ll
                }
                val r = ApiService.estimatePrice(
                    departure = departure,
                    destination = destination,
                    depLat = departureLatLng?.latitude,
                    depLng = departureLatLng?.longitude,
                    dstLat = destinationLatLng?.latitude,
                    dstLng = destinationLatLng?.longitude
                )
                if (r.success && r.calculations != null) {
                    val calc = r.calculations[priority] ?: r.calculations.values.first()
                    totalPrice = calc.totalPrice
                    distanceTxt = r.distance?.text
                    durationTxt = r.duration?.text
                } else {
                    showMessage("Échec de l'estimation")
                }
            } catch (e: Exception) {
                showMessage(ApiService.friendlyError(e))
            } finally {
                estimating = false
            }
        }
    }

    // Debounce pour estimation automatique côté saisie
    var estimateJob by remember { mutableStateOf<Job?>(null) }
    fun scheduleEstimateDebounced() {
        val d = departure.trim()
        val a = destination.trim()
        if (d.isEmpty() || a.isEmpty()) return
        estimateJob?.cancel()
        estimateJob = scope.launch {
            delay(500)
            if (!estimating) estimate()
        }
    }

    // Auto-estimation: re-calc automatically when addresses or coordinates or priority change
    LaunchedEffect(departure, destination, departureLatLng, destinationLatLng, priority) {
        val d = departure.trim()
        val a = destination.trim()
        if (d.isNotEmpty() && a.isNotEmpty()) {
            // Debounce to avoid spamming API while user types/selects
            estimateJob?.cancel()
            delay(350)
            if (!estimating) estimate()
        }
    }

    val scrollState = rememberScrollState()
    Column(
        Modifier
            .fillMaxSize()
            .padding(16.dp)
            .verticalScroll(scrollState)
            .imePadding()
    ) {
        AnimatedVisibility(visible = showUpdate, enter = expandVertically(), exit = shrinkVertically()) {
            Surface(color = MaterialTheme.colorScheme.secondaryContainer, tonalElevation = 2.dp, modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(12.dp)) {
                    Text("Mise à jour disponible", style = MaterialTheme.typography.titleMedium)
                    val vName = updateInfo?.latest_version_name ?: ""
                    Text("Version: ${vName}")
                    val dl = updateInfo?.download_url
                    if (!dl.isNullOrBlank()) {
                        Spacer(Modifier.height(4.dp))
                        TextButton(onClick = { val intent = CustomTabsIntent.Builder().build(); intent.launchUrl(context, dl.toUri()) }) {
                            Text("Télécharger la nouvelle version")
                        }
                    }
                }
            }
        }
        Spacer(Modifier.height(8.dp))
        Text("Nouvelle commande", style = MaterialTheme.typography.headlineSmall)
        Spacer(Modifier.height(12.dp))
        AutocompleteTextField(
            label = "Adresse de départ",
            value = departure,
            onValueChange = { departure = it; departureError = null; totalPrice = null; distanceTxt = null; durationTxt = null; departureLatLng = null; scheduleEstimateDebounced() },
            isError = departureError != null,
            supportingError = departureError,
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
            ,
            onCoordinates = { ll ->
                departureLatLng = ll
                scheduleEstimateDebounced()
            }
            ,
            showError = { msg -> showMessage(msg) }
        ) { sel ->
            departure = sel
            if (destination.isNotBlank() && !estimating) estimate()
        }
        Spacer(Modifier.height(8.dp))
        AutocompleteTextField(
            label = "Adresse d'arrivée",
            value = destination,
            onValueChange = { destination = it; destinationError = null; totalPrice = null; distanceTxt = null; durationTxt = null; destinationLatLng = null; scheduleEstimateDebounced() },
            isError = destinationError != null,
            supportingError = destinationError,
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
            ,
            onCoordinates = { ll ->
                destinationLatLng = ll
                scheduleEstimateDebounced()
            }
            ,
            showError = { msg -> showMessage(msg) }
        ) { sel ->
            destination = sel
            if (departure.isNotBlank() && !estimating) estimate()
        }
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(
            value = senderPhone,
            onValueChange = { /* locked - no manual edit */ },
            label = { Text("Téléphone expéditeur (compte)") },
            leadingIcon = { Icon(Icons.Default.Phone, contentDescription = null) },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            readOnly = true,
            enabled = false,
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
                .onFocusEvent { if (it.isFocused) {
                    scope.launch { bringIntoViewRequester.bringIntoView() }
                } },
            isError = senderPhoneError != null,
            supportingText = {
                when {
                    senderPhoneError != null -> Text(senderPhoneError!!, color = MaterialTheme.colorScheme.error)
                    else -> Text("Modifiez votre numéro depuis Mon Profil pour le synchroniser", color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        )
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(
            value = receiverPhone,
            onValueChange = { new ->
                // Always keep +225 and format progressively
                val hasPrefix = new.trim().startsWith("+225")
                receiverPhone = formatCiPhone(new, enforcePrefix = true)
                receiverPhoneError = null
            },
            label = { Text("Téléphone destinataire") },
            leadingIcon = { Icon(Icons.Default.Phone, contentDescription = null) },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
                .onFocusEvent { if (it.isFocused) {
                    scope.launch { bringIntoViewRequester.bringIntoView() }
                } },
            isError = receiverPhoneError != null,
            supportingText = { if (receiverPhoneError != null) Text(receiverPhoneError!!, color = MaterialTheme.colorScheme.error) }
        )
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(
            value = description,
            onValueChange = { description = it },
            label = { Text("Description (optionnelle)") },
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
                .onFocusEvent { if (it.isFocused) {
                    scope.launch { bringIntoViewRequester.bringIntoView() }
                } }
        )
        
        Spacer(Modifier.height(24.dp))
        
        // Priority selector
        PrioritySelector(
            selectedPriority = priority,
            onPriorityChanged = {
                priority = it
                if (departure.isNotBlank() && destination.isNotBlank() && !estimating) estimate()
            },
            modifier = Modifier.fillMaxWidth()
        )
        
        Spacer(Modifier.height(16.dp))

        // Price and distance BEFORE payment methods
        Row(verticalAlignment = Alignment.CenterVertically) {
            // Manual recalculation remains available but is no longer required
            Button(onClick = { estimate() }, enabled = !estimating) {
                if (estimating) {
                    CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("Calcul…")
                } else {
                    Text("Recalculer le prix")
                }
            }
            Spacer(Modifier.width(12.dp))
            AnimatedContent(targetState = totalPrice, label = "priceAnim", transitionSpec = { fadeIn(tween(200)) togetherWith fadeOut(tween(200)) }) { price ->
                if (price != null) Text("Prix estimé: ${price} FCFA")
            }
        }
        if (distanceTxt != null || durationTxt != null) {
            Spacer(Modifier.height(8.dp))
            Text("Distance: ${distanceTxt ?: "-"} | Durée: ${durationTxt ?: "-"}")
        }
        Spacer(Modifier.height(16.dp))

        // Payment method selector (now after price/distance)
        PaymentMethodSelector(
            selectedMethod = paymentMethod,
            onMethodChanged = { paymentMethod = it },
            modifier = Modifier.fillMaxWidth()
        )
        Spacer(Modifier.height(16.dp))
        Button(
            onClick = {
                if (!validateInputs(forSubmit = true)) return@Button
                scope.launch {
                    submitting = true
                    try {
                        if (paymentMethod != "cash") {
                            // Online payment first: get URL then show WebView
                            val amount = (totalPrice ?: 0)
                            val orderNumber = "SZK" + System.currentTimeMillis()
                            val init = ApiService.initiatePaymentOnly(orderNumber, amount, null, senderPhone, null)
                            if (init.success && !init.payment_url.isNullOrBlank()) {
                                paymentUrl = init.payment_url
                                pendingOnlineOrder = true
                                showPaymentModal = true
                            } else {
                                showMessage(init.message ?: "Paiement indisponible")
                            }
                        } else {
                            val req = OrderRequest(
                                departure = departure,
                                destination = destination,
                                senderPhone = senderPhone,
                                receiverPhone = receiverPhone,
                                packageDescription = description.ifBlank { null },
                                priority = priority,
                                paymentMethod = paymentMethod,
                                price = (totalPrice ?: 0).toDouble(),
                                distance = distanceTxt,
                                duration = durationTxt,
                                departure_lat = departureLatLng?.latitude,
                                departure_lng = departureLatLng?.longitude,
                                arrival_lat = destinationLatLng?.latitude,
                                arrival_lng = destinationLatLng?.longitude
                            )
                            val resp = ApiService.submitOrder(req)
                            if (resp.success) {
                                val oid = resp.data?.order_number ?: resp.data?.code_commande ?: "-"
                                showMessage("Commande créée: ${oid}")
                            } else {
                                showMessage(resp.message ?: "Échec de la commande")
                            }
                        }
                    } catch (e: Exception) {
                        showMessage(ApiService.friendlyError(e))
                    } finally {
                        submitting = false
                    }
                }
            },
            modifier = Modifier.fillMaxWidth(),
        enabled = couriersAvailable && !estimating && !submitting && departureError == null && destinationError == null && senderPhoneError == null && receiverPhoneError == null &&
                    departure.isNotBlank() && destination.isNotBlank() && senderPhone.isNotBlank() && receiverPhone.isNotBlank()
        ) {
            if (submitting) {
                CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(8.dp))
                Text("Envoi…")
            } else {
                Text("Passer la commande")
            }
        }
        if (!couriersAvailable) {
            Spacer(Modifier.height(12.dp))
            Surface(color = MaterialTheme.colorScheme.errorContainer, tonalElevation = 1.dp, shape = MaterialTheme.shapes.medium) {
                Column(Modifier.fillMaxWidth().padding(12.dp)) {
                    Text(availabilityMessage ?: "Aucun coursier actif pour le moment", color = MaterialTheme.colorScheme.onErrorContainer)
                    Text("Le formulaire est temporairement désactivé", color = MaterialTheme.colorScheme.onErrorContainer.copy(alpha = 0.8f), style = MaterialTheme.typography.bodySmall)
                }
            }
        }

        Spacer(Modifier.height(24.dp))

        // Mini-carte pour visualiser les adresses
    var mapError by remember { mutableStateOf<String?>(null) }
    var mapLoaded by remember { mutableStateOf(false) }
        
        Surface(tonalElevation = 1.dp, shape = MaterialTheme.shapes.medium) {
            Box(Modifier.height(220.dp).fillMaxWidth()) {
                if (mapError != null) {
                    // Fallback si Google Maps échoue
                    Column(
                        modifier = Modifier.fillMaxSize().padding(16.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Text(
                            "Carte non disponible",
                            style = MaterialTheme.typography.titleMedium,
                            textAlign = TextAlign.Center
                        )
                        Text(
                            "Erreur: $mapError",
                            style = MaterialTheme.typography.bodySmall,
                            textAlign = TextAlign.Center,
                            color = MaterialTheme.colorScheme.error
                        )
                        Spacer(Modifier.height(8.dp))
                        if (departureLatLng != null && destinationLatLng != null) {
                            Text("Points A/B définis", style = MaterialTheme.typography.bodySmall)
                        }
                    }
                } else {
                    // Google Maps
                        // Set an initial camera without using CameraUpdateFactory to avoid initialization crashes
                        val cameraPositionState = rememberCameraPositionState {
                            // Center on Abidjan by default
                            position = CameraPosition.fromLatLngZoom(LatLng(5.3476, -4.0076), 12f)
                        }
                        // Abidjan defaults and bounds
                        val abidjanCenter = LatLng(5.3476, -4.0076)
                        val abidjanBounds = LatLngBounds(
                            LatLng(5.2, -4.2),   // Southwest
                            LatLng(5.5, -3.8)    // Northeast
                        )

                        // Only perform camera moves using CameraUpdateFactory after the map is fully loaded
                        LaunchedEffect(departureLatLng, destinationLatLng, mapLoaded) {
                            if (!mapLoaded) return@LaunchedEffect
                            val dep = departureLatLng
                            val dest = destinationLatLng
                            try {
                                if (dep != null && dest != null) {
                                    val bounds = LatLngBounds.builder().include(dep).include(dest).build()
                                    cameraPositionState.move(CameraUpdateFactory.newLatLngBounds(bounds, 100))
                                } else if (dep != null) {
                                    cameraPositionState.move(CameraUpdateFactory.newLatLngZoom(dep, 12f))
                                } else if (dest != null) {
                                    cameraPositionState.move(CameraUpdateFactory.newLatLngZoom(dest, 12f))
                                } else {
                                    // Default view centered on Abidjan
                                    cameraPositionState.move(CameraUpdateFactory.newLatLngZoom(abidjanCenter, 12f))
                                }
                            } catch (e: Exception) {
                                mapError = e.message ?: "Erreur de caméra"
                            }
                        }
                
                var showMapChoice by remember { mutableStateOf(false) }
                var pendingClick by remember { mutableStateOf<LatLng?>(null) }

                if (showMapChoice && pendingClick != null) {
                    AlertDialog(
                        onDismissRequest = { showMapChoice = false; pendingClick = null },
                        title = { Text("Placer un marqueur") },
                        text = { Text("Voulez-vous placer le marqueur A (Départ) ?\nAppuyez sur Annuler pour placer B (Arrivée)") },
                        confirmButton = {
                            TextButton(onClick = {
                                val pos = pendingClick!!
                                showMapChoice = false; pendingClick = null
                                departureLatLng = pos
                                scope.launch {
                                    val addr = reverseGeocode(pos)
                                    departure = addr ?: "${pos.latitude}, ${pos.longitude}"
                                    departureError = null
                                    if (destination.isNotBlank() && !estimating) estimate()
                                }
                            }) { Text("Placer A") }
                        },
                        dismissButton = {
                            TextButton(onClick = {
                                val pos = pendingClick!!
                                showMapChoice = false; pendingClick = null
                                destinationLatLng = pos
                                scope.launch {
                                    val addr = reverseGeocode(pos)
                                    destination = addr ?: "${pos.latitude}, ${pos.longitude}"
                                    destinationError = null
                                    if (departure.isNotBlank() && !estimating) estimate()
                                }
                            }) { Text("Placer B") }
                        }
                    )
                }

                GoogleMap(
                    cameraPositionState = cameraPositionState,
                    onMapClick = { ll -> pendingClick = ll; showMapChoice = true },
                    onMapLoaded = {
                        mapLoaded = true
                        showMessage("Carte prête")
                        try {
                            android.util.Log.d("OrderScreen", "GoogleMap onMapLoaded fired")
                        } catch (_: Exception) {}
                    }
                ) {
                    // Restrict camera to Abidjan bounds and sensible zoom range
                    MapEffect(Unit) { map ->
                        try {
                            map.setLatLngBoundsForCameraTarget(abidjanBounds)
                            map.setMinZoomPreference(9.5f)
                            map.setMaxZoomPreference(20f)
                        } catch (_: Exception) {}
                    }
                    // Attach a drag listener via MapEffect (maps-compose 4.4.1 n'a pas onDragEnd sur Marker)
                    MapEffect(key1 = departureLatLng, key2 = destinationLatLng) { map ->
                        map.setOnMarkerDragListener(object : GoogleMap.OnMarkerDragListener {
                            override fun onMarkerDragStart(marker: com.google.android.gms.maps.model.Marker) {}
                            override fun onMarkerDrag(marker: com.google.android.gms.maps.model.Marker) {}
                            override fun onMarkerDragEnd(marker: com.google.android.gms.maps.model.Marker) {
                                val pos = marker.position
                                val title = marker.title ?: ""
                                if (title.contains("Départ")) {
                                    departureLatLng = pos
                                    scope.launch {
                                        val addr = reverseGeocode(pos)
                                        departure = addr ?: "${pos.latitude}, ${pos.longitude}"
                                        departureError = null
                                        if (destination.isNotBlank() && !estimating) estimate()
                                    }
                                } else if (title.contains("Arrivée")) {
                                    destinationLatLng = pos
                                    scope.launch {
                                        val addr = reverseGeocode(pos)
                                        destination = addr ?: "${pos.latitude}, ${pos.longitude}"
                                        destinationError = null
                                        if (departure.isNotBlank() && !estimating) estimate()
                                    }
                                }
                            }
                        })
                    }

                    // Crée une icône personnalisée (A/B)
                    fun markerIcon(label: String, color: Int): com.google.android.gms.maps.model.BitmapDescriptor {
                        val width = 100
                        val height = 130
                        val bmp = createBitmap(width, height, android.graphics.Bitmap.Config.ARGB_8888)
                        val canvas = Canvas(bmp)
                        val paint = Paint(Paint.ANTI_ALIAS_FLAG)
                        // Pointeur style goutte
                        paint.color = color
                        val body = RectF(5f, 5f, (width-5).toFloat(), (height-20).toFloat())
                        canvas.drawRoundRect(body, 40f, 40f, paint)
                        // Pointe
                        val triPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color }
                        val path = android.graphics.Path().apply {
                            moveTo((width/2).toFloat(), (height-10).toFloat())
                            lineTo((width/2 - 15).toFloat(), (height-35).toFloat())
                            lineTo((width/2 + 15).toFloat(), (height-35).toFloat())
                            close()
                        }
                        canvas.drawPath(path, triPaint)
                        // Cercle blanc au centre
                        paint.color = 0xFFFFFFFF.toInt()
                        canvas.drawCircle((width/2).toFloat(), (height/2 - 10).toFloat(), 28f, paint)
                        // Lettre
                        paint.color = 0xFF000000.toInt()
                        paint.textSize = 42f
                        paint.typeface = Typeface.create(Typeface.DEFAULT_BOLD, Typeface.BOLD)
                        val textWidth = paint.measureText(label)
                        canvas.drawText(label, (width/2 - textWidth/2), (height/2 + 5).toFloat(), paint)
                        return BitmapDescriptorFactory.fromBitmap(bmp)
                    }

                    departureLatLng?.let { dll ->
                        Marker(
                            state = MarkerState(dll),
                            title = "Départ (A)",
                            draggable = true,
                            icon = markerIcon("A", 0xFF00FF00.toInt())
                        )
                    }
                    destinationLatLng?.let { dll ->
                        Marker(
                            state = MarkerState(dll),
                            title = "Arrivée (B)",
                            draggable = true,
                            icon = markerIcon("B", 0xFFFF0000.toInt())
                        )
                    }
                }
                // Small debug overlay to visualize map loaded state
                Box(Modifier.fillMaxSize()) {
                    Surface(color = MaterialTheme.colorScheme.surface.copy(alpha = 0.7f), shape = MaterialTheme.shapes.small, tonalElevation = 1.dp, modifier = Modifier
                        .align(Alignment.TopStart)
                        .padding(6.dp)) {
                        Text(
                            text = "Map loaded: " + (if (mapLoaded) "yes" else "no"),
                            style = MaterialTheme.typography.labelSmall,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                        )
                    }
                }
                }
            }
        }
    }

    if (showPaymentModal && !paymentUrl.isNullOrBlank()) {
        PaymentWebViewModal(url = paymentUrl!!, onClose = { success ->
            showPaymentModal = false
            if (success && pendingOnlineOrder) {
                // Create order after payment
                scope.launch {
                    try {
                        val resp = ApiService.createOrderAfterPayment(
                            ApiService.CreateAfterPaymentRequest(
                                departure = departure,
                                destination = destination,
                                latitude_retrait = departureLatLng?.latitude,
                                longitude_retrait = departureLatLng?.longitude,
                                latitude_livraison = destinationLatLng?.latitude,
                                longitude_livraison = destinationLatLng?.longitude,
                                distance_km = null,
                                prix_livraison = (totalPrice ?: 0),
                                telephone_destinataire = receiverPhone,
                                nom_destinataire = null,
                                notes_speciales = description.ifBlank { null },
                                client_name = null,
                                client_phone = senderPhone,
                                client_email = null
                            )
                        )
                        if (resp.success) {
                            showMessage("Commande crée: ${'$'}{resp.order_number ?: resp.order_id}")
                        } else {
                            showMessage(resp.message ?: "Erreur post-paiement")
                        }
                    } catch (e: Exception) {
                        showMessage(ApiService.friendlyError(e))
                    } finally {
                        pendingOnlineOrder = false
                    }
                }
            } else {
                pendingOnlineOrder = false
            }
        })
    }
}

@Composable
private fun PaymentWebViewModal(url: String, onClose: (Boolean) -> Unit) {
    AlertDialog(
        onDismissRequest = { onClose(false) },
        confirmButton = {},
        title = { Text("Paiement sécurisé") },
        text = {
            Column(Modifier.fillMaxWidth()) {
                var loading by remember { mutableStateOf(true) }
                if (loading) {
                    LinearProgressIndicator(modifier = Modifier.fillMaxWidth())
                }
                AndroidView(
                    modifier = Modifier.height(480.dp),
                    factory = { ctx ->
                        WebView(ctx).apply {
                            settings.javaScriptEnabled = true
                            webViewClient = object : WebViewClient() {
                                override fun onPageFinished(view: WebView?, url: String?) {
                                    loading = false
                                }
                                override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                                    val u = request?.url?.toString().orEmpty()
                                    if (u.contains("cinetpay_callback.php") || u.contains("payment_success=1") || u.contains("status=success")) {
                                        onClose(true)
                                        return true
                                    }
                                    if (u.contains("payment_cancelled=1") || u.contains("status=failed") || u.contains("status=canceled")) {
                                        onClose(false)
                                        return true
                                    }
                                    return false
                                }
                            }
                            loadUrl(url)
                        }
                    }
                )
            }
        }
    )
}

@Composable
private fun DropdownMenuBox(options: List<String>, selected: String, onSelected: (String) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    Box {
        OutlinedButton(onClick = { expanded = true }) { Text(selected) }
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            options.forEach { opt ->
                DropdownMenuItem(text = { Text(opt) }, onClick = { onSelected(opt); expanded = false })
            }
        }
    }
}

@OptIn(ExperimentalFoundationApi::class)
@Composable
private fun AutocompleteTextField(
    label: String,
    value: String,
    onValueChange: (String) -> Unit,
    isError: Boolean,
    supportingError: String?,
    modifier: Modifier = Modifier,
    onCoordinates: (LatLng?) -> Unit = {},
    showError: (String) -> Unit = {},
    onSelected: (String) -> Unit,
) {
    val context = LocalContext.current
    val places = remember { Places.createClient(context) }
    val token = remember { AutocompleteSessionToken.newInstance() }
    var expanded by remember { mutableStateOf(false) }
    data class Suggestion(val text: String, val placeId: String)
    var suggestions by remember { mutableStateOf(listOf<Suggestion>()) }
    var loading by remember { mutableStateOf(false) }
    var errorMsg by remember { mutableStateOf<String?>(null) }
    var job by remember { mutableStateOf<Job?>(null) }
    val scope = rememberCoroutineScope()
    val fieldBringIntoView = remember { BringIntoViewRequester() }

    Column(Modifier.fillMaxWidth()) {
        OutlinedTextField(
            value = value,
            onValueChange = { text ->
                onValueChange(text)
                job?.cancel()
                if (text.length < 3) {
                    suggestions = emptyList(); expanded = false; return@OutlinedTextField
                }
                job = scope.launch {
                    loading = true
                    delay(250)
                    errorMsg = null
                    val request = FindAutocompletePredictionsRequest.builder()
                        .setQuery(text)
                        .setCountries(listOf("CI"))
                        .setSessionToken(token)
                        .build()
                    places.findAutocompletePredictions(request)
                        .addOnSuccessListener { response ->
                            val list = response.autocompletePredictions.map {
                                Suggestion(it.getFullText(null).toString(), it.placeId)
                            }
                            suggestions = list.take(5)
                            expanded = suggestions.isNotEmpty()
                            if (expanded) {
                                scope.launch { fieldBringIntoView.bringIntoView() }
                            }
                        }
                        .addOnFailureListener { err ->
                            // Fallback Nominatim
                            scope.launch {
                                try {
                                    val list = withContext(Dispatchers.IO) {
                                        val url = "https://nominatim.openstreetmap.org/search".toHttpUrl()
                                            .newBuilder()
                                            .addQueryParameter("format", "json")
                                            .addQueryParameter("limit", "5")
                                            .addQueryParameter("countrycodes", "ci")
                                            .addQueryParameter("q", text)
                                            .build()
                                        val req = Request.Builder().url(url)
                                            .header("User-Agent", "CoursierSuzosky/1.0")
                                            .get()
                                            .build()
                                        ApiClient.http.newCall(req).execute().use { resp ->
                                            val body = resp.body?.string().orEmpty()
                                            val arr = org.json.JSONArray(body)
                                            val l = mutableListOf<Suggestion>()
                                            for (i in 0 until minOf(arr.length(), 5)) {
                                                val obj = arr.getJSONObject(i)
                                                l.add(Suggestion(obj.optString("display_name"), ""))
                                            }
                                            l
                                        }
                                    }
                                    suggestions = list
                                    expanded = list.isNotEmpty()
                                    if (expanded) {
                                        scope.launch { fieldBringIntoView.bringIntoView() }
                                    }
                                } catch (e: Exception) {
                                    suggestions = emptyList(); expanded = false
                                    val msg = err.message ?: e.message ?: "Autocomplete indisponible"
                                    errorMsg = msg
                                    showError(msg)
                                } finally {
                                    loading = false
                                }
                            }
                        }
                        .addOnCompleteListener {
                            loading = false
                        }
                }
            },
            label = { Text(label) },
            leadingIcon = { Icon(Icons.Default.Place, contentDescription = null) },
            isError = isError,
            supportingText = {
                when {
                    supportingError != null -> Text(supportingError, color = MaterialTheme.colorScheme.error)
                    loading -> Text("Recherche…")
                    errorMsg != null -> Text(errorMsg!!, color = MaterialTheme.colorScheme.error)
                }
            },
            modifier = modifier
                .fillMaxWidth()
                .bringIntoViewRequester(fieldBringIntoView)
                .onFocusEvent { if (it.isFocused) {
                    scope.launch { fieldBringIntoView.bringIntoView() }
                } }
        )
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            suggestions.forEach { s ->
                DropdownMenuItem(text = { Text(s.text) }, onClick = {
                    if (s.placeId.isNotEmpty()) {
                        // Fetch details to obtain coordinates
                        val placeRequest = FetchPlaceRequest.newInstance(
                            s.placeId,
                            listOf(Place.Field.LAT_LNG, Place.Field.ADDRESS, Place.Field.NAME)
                        )
                        places.fetchPlace(placeRequest)
                            .addOnSuccessListener { result ->
                                onCoordinates(result.place.latLng)
                                onSelected(result.place.address ?: s.text)
                            }
                            .addOnFailureListener {
                                onCoordinates(null)
                                val msg = it.message ?: "Impossible d'obtenir les détails du lieu"
                                errorMsg = msg
                                showError(msg)
                                onSelected(s.text)
                            }
                    } else {
                        // Fallback: géocoder via Nominatim pour récupérer lat/lon
                        scope.launch {
                            try {
                                val coords = withContext(Dispatchers.IO) {
                                    val url = "https://nominatim.openstreetmap.org/search".toHttpUrl()
                                        .newBuilder()
                                        .addQueryParameter("format", "json")
                                        .addQueryParameter("limit", "1")
                                        .addQueryParameter("countrycodes", "ci")
                                        .addQueryParameter("q", s.text)
                                        .build()
                                    val req = Request.Builder().url(url)
                                        .header("User-Agent", "CoursierSuzosky/1.0")
                                        .get()
                                        .build()
                                    ApiClient.http.newCall(req).execute().use { resp ->
                                        val body = resp.body?.string().orEmpty()
                                        val arr = org.json.JSONArray(body)
                                        if (arr.length() > 0) {
                                            val obj = arr.getJSONObject(0)
                                            val lat = obj.optDouble("lat")
                                            val lon = obj.optDouble("lon")
                                            LatLng(lat, lon)
                                        } else null
                                    }
                                }
                                onCoordinates(coords)
                                onSelected(s.text)
                            } catch (e: Exception) {
                                onCoordinates(null)
                                onSelected(s.text)
                            }
                        }
                    }
                    expanded = false
                })
            }
        }
    }
}

// ======================== PRIORITY SELECTION ========================
@Composable
private fun PrioritySelector(
    selectedPriority: String,
    onPriorityChanged: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val priorities = listOf(
        PriorityOption("normale", "🚶", "Normal", "1-2h", Color(0xFF10B981)),
        PriorityOption("urgente", "⚡", "Urgent", "30min", Color(0xFFF59E0B)),
        PriorityOption("express", "🚀", "Express", "15min", Color(0xFFEF4444))
    )
    
    Column(modifier = modifier) {
        Text(
            text = "Priorité de livraison",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = androidx.compose.ui.text.font.FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
        Spacer(Modifier.height(12.dp))
        
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            priorities.forEach { priority ->
                PriorityCard(
                    priority = priority,
                    isSelected = selectedPriority == priority.value,
                    onClick = { onPriorityChanged(priority.value) },
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PriorityCard(
    priority: PriorityOption,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        onClick = onClick,
        modifier = modifier,
        colors = CardDefaults.cardColors(
            containerColor = if (isSelected) 
                priority.color.copy(alpha = 0.15f) 
            else 
                MaterialTheme.colorScheme.surface
        ),
        border = androidx.compose.foundation.BorderStroke(
            width = if (isSelected) 2.dp else 1.dp,
            color = if (isSelected) priority.color else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
        ),
        shape = androidx.compose.foundation.shape.RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text(
                text = priority.emoji,
                style = MaterialTheme.typography.headlineMedium
            )
            Spacer(Modifier.height(4.dp))
            Text(
                text = priority.label,
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = if (isSelected) androidx.compose.ui.text.font.FontWeight.Bold else androidx.compose.ui.text.font.FontWeight.Normal,
                color = if (isSelected) priority.color else MaterialTheme.colorScheme.onSurface
            )
            Text(
                text = priority.time,
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.7f)
            )
        }
    }
}

private data class PriorityOption(
    val value: String,
    val emoji: String,
    val label: String,
    val time: String,
    val color: Color
)

// ======================== PAYMENT METHOD SELECTION ========================
@Composable
private fun PaymentMethodSelector(
    selectedMethod: String,
    onMethodChanged: (String) -> Unit,
    modifier: Modifier = Modifier
) {
    val paymentMethods = listOf(
        PaymentMethodOption("cash", "💵", "Espèces", "À la livraison • Sans frais", Color(0xFF10B981)),
        PaymentMethodOption("orange_money", "🟠", "Orange Money", "Instantané • Sans frais", Color(0xFFF97316)),
        PaymentMethodOption("mtn_money", "🟡", "MTN Money", "Instantané • Sans frais", Color(0xFFFBBF24)),
        PaymentMethodOption("moov_money", "🔵", "Moov Money", "Instantané • Sans frais", Color(0xFF3B82F6)),
        PaymentMethodOption("wave", "💙", "Wave", "Instantané • Sans frais", Color(0xFF06B6D4)),
        PaymentMethodOption("card", "💳", "Carte bancaire", "1-3 min • Frais 2.5%", Color(0xFF8B5CF6))
    )
    
    Column(modifier = modifier) {
        Text(
            text = "💳 Mode de paiement",
            style = MaterialTheme.typography.titleMedium,
            fontWeight = androidx.compose.ui.text.font.FontWeight.Bold,
            color = MaterialTheme.colorScheme.onSurface
        )
        Spacer(Modifier.height(12.dp))
        
        Column(
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            paymentMethods.forEach { method ->
                PaymentMethodCard(
                    method = method,
                    isSelected = selectedMethod == method.value,
                    onClick = { onMethodChanged(method.value) }
                )
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun PaymentMethodCard(
    method: PaymentMethodOption,
    isSelected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier
) {
    Card(
        onClick = onClick,
        modifier = modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = if (isSelected) 
                method.color.copy(alpha = 0.12f) 
            else 
                MaterialTheme.colorScheme.surface
        ),
        border = androidx.compose.foundation.BorderStroke(
            width = if (isSelected) 2.dp else 1.dp,
            color = if (isSelected) method.color else MaterialTheme.colorScheme.outline.copy(alpha = 0.3f)
        ),
        shape = androidx.compose.foundation.shape.RoundedCornerShape(12.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            horizontalArrangement = Arrangement.spacedBy(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Emoji icon
            Text(
                text = method.emoji,
                style = MaterialTheme.typography.headlineMedium
            )
            
            // Method details
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = method.label,
                    style = MaterialTheme.typography.bodyLarge,
                    fontWeight = if (isSelected) androidx.compose.ui.text.font.FontWeight.Bold else androidx.compose.ui.text.font.FontWeight.SemiBold,
                    color = if (isSelected) method.color else MaterialTheme.colorScheme.onSurface
                )
                Text(
                    text = method.info,
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f)
                )
            }
            
            // Selection indicator
            if (isSelected) {
                androidx.compose.material.icons.Icons.Filled.CheckCircle.let { icon ->
                    androidx.compose.material3.Icon(
                        imageVector = icon,
                        contentDescription = "Sélectionné",
                        tint = method.color,
                        modifier = Modifier.size(24.dp)
                    )
                }
            }
        }
    }
}

private data class PaymentMethodOption(
    val value: String,
    val emoji: String,
    val label: String,
    val info: String,
    val color: Color
)
