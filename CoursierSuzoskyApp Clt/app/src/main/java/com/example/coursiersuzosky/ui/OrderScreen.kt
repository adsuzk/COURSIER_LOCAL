@file:Suppress("DEPRECATION")
@file:OptIn(com.google.maps.android.compose.MapsComposeExperimentalApi::class)
package com.example.coursiersuzosky.ui

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
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import com.example.coursiersuzosky.net.*
import kotlinx.coroutines.launch
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Place
import androidx.compose.material.icons.filled.Phone
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
 

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun OrderScreen(showMessage: (String) -> Unit) {
    val scope = rememberCoroutineScope()
    var departure by remember { mutableStateOf("") }
    var destination by remember { mutableStateOf("") }
    var departureLatLng by remember { mutableStateOf<LatLng?>(null) }
    var destinationLatLng by remember { mutableStateOf<LatLng?>(null) }
    var senderPhone by remember { mutableStateOf("") }
    var receiverPhone by remember { mutableStateOf("") }
    var description by remember { mutableStateOf("") }
    var priority by remember { mutableStateOf("normale") }
    var payment by remember { mutableStateOf("cash") }

    var estimating by remember { mutableStateOf(false) }
    var submitting by remember { mutableStateOf(false) }
    var totalPrice by remember { mutableStateOf<Int?>(null) }
    var distanceTxt by remember { mutableStateOf<String?>(null) }
    var durationTxt by remember { mutableStateOf<String?>(null) }

    val context = LocalContext.current
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
    var senderPhoneError by remember { mutableStateOf<String?>(null) }
    var receiverPhoneError by remember { mutableStateOf<String?>(null) }

    // CI format: +225 followed by exactly 10 digits (separators allowed)
    fun phoneValid(p: String): Boolean = p.matches(Regex("^\\+225[\\s\\-()]*([0-9][\\s\\-()]*){10}$"))

    fun validateInputs(forSubmit: Boolean = false): Boolean {
        var ok = true
        if (departure.isBlank()) { departureError = "Adresse de départ requise"; ok = false } else departureError = null
        if (destination.isBlank()) { destinationError = "Adresse d'arrivée requise"; ok = false } else destinationError = null
        if (forSubmit) {
            if (!phoneValid(senderPhone)) { senderPhoneError = "Format CI requis: +225 suivi de 10 chiffres"; ok = false } else senderPhoneError = null
            if (!phoneValid(receiverPhone)) { receiverPhoneError = "Format CI requis: +225 suivi de 10 chiffres"; ok = false } else receiverPhoneError = null
            if (payment != "cash" && (totalPrice == null || (totalPrice ?: 0) <= 0)) {
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
                val r = ApiService.estimatePrice(departure, destination)
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
            onCoordinates = { ll -> departureLatLng = ll }
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
            onCoordinates = { ll -> destinationLatLng = ll }
            ,
            showError = { msg -> showMessage(msg) }
        ) { sel ->
            destination = sel
            if (departure.isNotBlank() && !estimating) estimate()
        }
        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text("Priorité:")
            Spacer(Modifier.width(8.dp))
            DropdownMenuBox(options = listOf("normale","urgente","express"), selected = priority) { priority = it; if (departure.isNotBlank() && destination.isNotBlank()) estimate() }
            Spacer(Modifier.width(16.dp))
            Text("Paiement:")
            Spacer(Modifier.width(8.dp))
            DropdownMenuBox(options = listOf("cash","orange_money","mtn_money","moov_money","card","wave"), selected = payment) { payment = it }
        }
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(
            value = senderPhone,
            onValueChange = { senderPhone = it; senderPhoneError = null },
            label = { Text("Téléphone expéditeur") },
            leadingIcon = { Icon(Icons.Default.Phone, contentDescription = null) },
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
            modifier = Modifier
                .fillMaxWidth()
                .bringIntoViewRequester(bringIntoViewRequester)
                .onFocusEvent { if (it.isFocused) {
                    scope.launch { bringIntoViewRequester.bringIntoView() }
                } },
            isError = senderPhoneError != null,
            supportingText = { if (senderPhoneError != null) Text(senderPhoneError!!, color = MaterialTheme.colorScheme.error) }
        )
        Spacer(Modifier.height(8.dp))
        OutlinedTextField(
            value = receiverPhone,
            onValueChange = { receiverPhone = it; receiverPhoneError = null },
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
        Spacer(Modifier.height(12.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Button(onClick = { estimate() }, enabled = !estimating) {
                if (estimating) {
                    CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text("Estimation…")
                } else {
                    Text("Estimer le prix")
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
        Button(
            onClick = {
                if (!validateInputs(forSubmit = true)) return@Button
                scope.launch {
                    submitting = true
                    try {
                        val req = OrderRequest(
                            departure = departure,
                            destination = destination,
                            senderPhone = senderPhone,
                            receiverPhone = receiverPhone,
                            packageDescription = description.ifBlank { null },
                            priority = priority,
                            paymentMethod = payment,
                            price = (totalPrice ?: 0).toDouble(),
                            distance = distanceTxt,
                            duration = durationTxt
                        )
                        val resp = ApiService.submitOrder(req)
                        if (resp.success) {
                            val oid = resp.data?.order_number ?: resp.data?.code_commande ?: "-"
                            showMessage("Commande créée: ${oid}")
                            val payUrl = resp.data?.payment_url
                            if (!payUrl.isNullOrBlank()) {
                                val customTabsIntent = CustomTabsIntent.Builder().build()
                                customTabsIntent.launchUrl(context, payUrl.toUri())
                            }
                        } else {
                            showMessage(resp.message ?: "Échec de la commande")
                        }
                    } catch (e: Exception) {
                        showMessage(ApiService.friendlyError(e))
                    } finally {
                        submitting = false
                    }
                }
            },
            modifier = Modifier.fillMaxWidth(),
            enabled = !estimating && !submitting && departureError == null && destinationError == null && senderPhoneError == null && receiverPhoneError == null &&
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
                        val cameraPositionState = rememberCameraPositionState()
                        LaunchedEffect(departureLatLng, destinationLatLng) {
                            val dep = departureLatLng
                            val dest = destinationLatLng
                            if (dep != null && dest != null) {
                                val bounds = LatLngBounds.builder().include(dep).include(dest).build()
                                cameraPositionState.move(CameraUpdateFactory.newLatLngBounds(bounds, 100))
                            } else if (dep != null) {
                                cameraPositionState.move(CameraUpdateFactory.newLatLngZoom(dep, 12f))
                            } else if (dest != null) {
                                cameraPositionState.move(CameraUpdateFactory.newLatLngZoom(dest, 12f))
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
