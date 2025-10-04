@file:Suppress("DEPRECATION")
@file:OptIn(com.google.maps.android.compose.MapsComposeExperimentalApi::class)
package com.suzosky.coursierclient.ui

import androidx.core.net.toUri
import androidx.browser.customtabs.CustomTabsIntent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.background
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.clickable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
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
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.input.nestedscroll.nestedScroll
import androidx.compose.ui.input.nestedscroll.NestedScrollConnection
import androidx.compose.ui.input.nestedscroll.NestedScrollSource
import androidx.compose.ui.geometry.Offset
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.TextFieldValue
import androidx.compose.ui.text.TextRange
import androidx.compose.ui.unit.dp
import androidx.compose.ui.focus.FocusDirection
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.webkit.WebChromeClient
import android.webkit.CookieManager
import android.webkit.WebSettings
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.material3.LinearProgressIndicator
import com.suzosky.coursierclient.net.*
import com.suzosky.coursierclient.ui.theme.*
import kotlinx.coroutines.launch
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.CoroutineScope
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.togetherWith
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.focus.onFocusEvent
import androidx.compose.material3.AlertDialog
import androidx.core.graphics.createBitmap
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.ui.zIndex
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
import com.google.android.gms.maps.MapsInitializer
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
private const val CI_PHONE_PREFIX = "+225 "
private fun normalizeDigits(s: String): String = s.filter { it.isDigit() }

private fun extractCiPhoneDigits(input: String): String {
    val digits = normalizeDigits(input)
    var sanitized = digits
    if (sanitized.startsWith("00225")) {
        sanitized = sanitized.drop(5)
    }
    if (sanitized.startsWith("225")) {
        sanitized = sanitized.drop(3)
    }
    return sanitized.take(10)
}

private fun formatCiPhoneDigits(digits: String, enforcePrefix: Boolean = true): String {
    val trimmed = digits.take(10)
    val grouped = trimmed.chunked(2).joinToString(" ")
    return when {
        grouped.isNotEmpty() -> CI_PHONE_PREFIX + grouped
        enforcePrefix -> CI_PHONE_PREFIX
        else -> grouped
    }
}

private fun formatCiPhone(input: String, enforcePrefix: Boolean = true): String =
    formatCiPhoneDigits(extractCiPhoneDigits(input), enforcePrefix)


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
    // Sender phone state (defaults to CI format)
    var senderDigits by rememberSaveable { mutableStateOf("") }
    var senderPhone by rememberSaveable { mutableStateOf(CI_PHONE_PREFIX) }
    var receiverDigits by rememberSaveable { mutableStateOf("") }
    var receiverPhone by rememberSaveable { mutableStateOf(CI_PHONE_PREFIX) }
    var receiverTf by rememberSaveable(stateSaver = TextFieldValue.Saver) {
        mutableStateOf(TextFieldValue(CI_PHONE_PREFIX, selection = TextRange(CI_PHONE_PREFIX.length)))
    }
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
    fun updateSenderFromStore(raw: String) {
        val digits = extractCiPhoneDigits(raw)
        val display = formatCiPhoneDigits(digits)
        senderDigits = digits
        senderPhone = display
    }

    // Lock sender phone to account phone and keep it synced with any profile change
    LaunchedEffect(Unit) {
        try {
            // Initial read
            com.suzosky.coursierclient.net.ClientStore.getClientPhone(context)?.let { p ->
                updateSenderFromStore(p)
            }
            // Observe future updates
            com.suzosky.coursierclient.net.ClientStore.observeClientPhone(context).collect { newPhone ->
                if (!newPhone.isNullOrBlank()) {
                    updateSenderFromStore(newPhone)
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
    fun phoneValid(p: String): Boolean = extractCiPhoneDigits(p).length == 10

    fun validateInputs(forSubmit: Boolean = false): Boolean {
        var ok = true
        if (departure.isBlank()) { departureError = "Adresse de départ requise"; ok = false } else departureError = null
        if (destination.isBlank()) { destinationError = "Adresse d'arrivée requise"; ok = false } else destinationError = null
        if (forSubmit) {
            if (!phoneValid(senderPhone)) { senderPhoneError = "Téléphone du compte invalide (+225 et 10 chiffres)"; ok = false } else senderPhoneError = null
            if (receiverDigits.length != 10) { receiverPhoneError = "Format CI requis: +225 suivi de 10 chiffres"; ok = false } else receiverPhoneError = null
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

    val handleDepartureChange: (String) -> Unit = { text ->
        departure = text
        departureError = null
        totalPrice = null
        distanceTxt = null
        durationTxt = null
        departureLatLng = null
        scheduleEstimateDebounced()
    }

    val handleDestinationChange: (String) -> Unit = { text ->
        destination = text
        destinationError = null
        totalPrice = null
        distanceTxt = null
        durationTxt = null
        destinationLatLng = null
        scheduleEstimateDebounced()
    }

    val handleDepartureSelected: (String) -> Unit = { selected ->
        departure = selected
        if (destination.isNotBlank() && !estimating) estimate()
    }

    val handleDestinationSelected: (String) -> Unit = { selected ->
        destination = selected
        if (departure.isNotBlank() && !estimating) estimate()
    }

    val handleReceiverPhoneChange: (String) -> Unit = { new ->
        val digits = extractCiPhoneDigits(new)
        receiverDigits = digits
        val display = formatCiPhoneDigits(digits)
        receiverPhone = display
        receiverTf = TextFieldValue(display, selection = TextRange(display.length))
        receiverPhoneError = null
    }

    val handleReceiverPhoneTfChange: (TextFieldValue) -> Unit = { tf ->
        val digits = extractCiPhoneDigits(tf.text)
        receiverDigits = digits
        val display = formatCiPhoneDigits(digits)
        receiverPhone = display
        // Keep cursor at end, but never before prefix
        receiverTf = TextFieldValue(display, selection = TextRange(display.length))
        receiverPhoneError = null
    }

    val handleDescriptionChange: (String) -> Unit = { value ->
        description = value
    }

    val handleDepartureFromMap: (LatLng, String?) -> Unit = { pos, resolved ->
    android.util.Log.d("OrderScreen", "Departure from map -> pos=$pos resolved=$resolved")
        departureLatLng = pos
        departureError = null
        departure = resolved ?: "${pos.latitude}, ${pos.longitude}"
        if (destination.isNotBlank() && !estimating) estimate()
    }

    val handleDestinationFromMap: (LatLng, String?) -> Unit = { pos, resolved ->
    android.util.Log.d("OrderScreen", "Destination from map -> pos=$pos resolved=$resolved")
        destinationLatLng = pos
        destinationError = null
        destination = resolved ?: "${pos.latitude}, ${pos.longitude}"
        if (departure.isNotBlank() && !estimating) estimate()
    }

    val submitEnabled = couriersAvailable &&
        !estimating &&
        !submitting &&
        departureError == null &&
        destinationError == null &&
        senderPhoneError == null &&
        receiverPhoneError == null &&
        departure.isNotBlank() &&
        destination.isNotBlank() &&
        senderDigits.length == 10 &&
        receiverDigits.length == 10

    val onPaymentMethodChange: (String) -> Unit = { newMethod ->
        // Only update selection; do NOT trigger any payment here.
        paymentMethod = newMethod
        pendingOnlineOrder = false
        showPaymentModal = false
    }

    val onSubmitOrder: () -> Unit = {
        scope.launch {
            if (!validateInputs(forSubmit = true)) {
                return@launch
            }
            submitting = true
            try {
                if (paymentMethod != "cash") {
                    // Use the latest estimated price for payment
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
                        receiverPhone = CI_PHONE_PREFIX.trim() + receiverDigits.chunked(2).joinToString(" ", prefix = " "),
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
    }

    val onDownloadUpdateClick: (() -> Unit)? = updateInfo?.download_url?.takeIf { it.isNotBlank() }?.let { url ->
        { val intent = CustomTabsIntent.Builder().build(); intent.launchUrl(context, url.toUri()) }
    }

    val scrollState = rememberScrollState()
    val focusManager = LocalFocusManager.current
    val keyboard = LocalSoftwareKeyboardController.current
    val receiverFocusRequester = remember { FocusRequester() }
    val receiverFocusRequester = remember { androidx.compose.ui.focus.FocusRequester() }
    // Hide keyboard only on user drag scroll (not programmatic bringIntoView)
    val hideOnUserScroll = remember {
        object : NestedScrollConnection {
            override fun onPreScroll(available: Offset, source: NestedScrollSource): Offset {
                if (source == NestedScrollSource.Drag && available.y != 0f) {
                    focusManager.clearFocus(force = true)
                    keyboard?.hide()
                }
                return Offset.Zero
            }
        }
    }
    
    // NOUVEAU DESIGN PREMIUM AVEC GRADIENT DARK/GOLD
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Dark, SecondaryBlue, Dark)
                )
            )
    ) {
        Column(
            Modifier
                .fillMaxSize()
                .nestedScroll(hideOnUserScroll)
                .verticalScroll(scrollState)
                .padding(24.dp)
                .imePadding()
        ) {
            OrderScreenHeader()

            Spacer(Modifier.height(24.dp))

            UpdateBanner(
                showUpdate = showUpdate,
                updateInfo = updateInfo,
                onDownloadClick = onDownloadUpdateClick
            )

            Spacer(Modifier.height(16.dp))

            ItinerarySection(
                departure = departure,
                destination = destination,
                departureError = departureError,
                destinationError = destinationError,
                onDepartureChange = handleDepartureChange,
                onDestinationChange = handleDestinationChange,
                onDepartureSelected = handleDepartureSelected,
                onDestinationSelected = handleDestinationSelected,
                onDepartureCoordinates = { ll ->
                    departureLatLng = ll
                    scheduleEstimateDebounced()
                },
                onDestinationCoordinates = { ll ->
                    destinationLatLng = ll
                    scheduleEstimateDebounced()
                },
                bringIntoViewRequester = bringIntoViewRequester,
                showMessage = showMessage
            )

            Spacer(Modifier.height(16.dp))

            // Move Map just below itinerary
            MapSection(
                departureLatLng = departureLatLng,
                destinationLatLng = destinationLatLng,
                onDepartureUpdate = handleDepartureFromMap,
                onDestinationUpdate = handleDestinationFromMap,
                reverseGeocode = { ll -> reverseGeocode(ll) },
                scope = scope,
                showMessage = showMessage
            )

            Spacer(Modifier.height(16.dp))

            ContactsSection(
                senderPhone = senderPhone,
                senderPhoneError = senderPhoneError,
                receiverField = receiverTf,
                receiverPhoneError = receiverPhoneError,
                description = description,
                onReceiverFieldChange = handleReceiverPhoneTfChange,
                onDescriptionChange = handleDescriptionChange,
                bringIntoViewRequester = bringIntoViewRequester,
                scope = scope,
                receiverFocusRequester = receiverFocusRequester,
                shouldAutoFocusReceiver = receiverDigits.isEmpty()
            )

            Spacer(Modifier.height(16.dp))

            PrioritySection(
                priority = priority,
                onPriorityChange = {
                    priority = it
                    if (departure.isNotBlank() && destination.isNotBlank() && !estimating) estimate()
                }
            )

            Spacer(Modifier.height(16.dp))

            PriceSection(
                estimating = estimating,
                totalPrice = totalPrice,
                distanceText = distanceTxt,
                durationText = durationTxt
            )

            Spacer(Modifier.height(16.dp))

            PaymentSection(
                paymentMethod = paymentMethod,
                onPaymentMethodChange = onPaymentMethodChange
            )

            Spacer(Modifier.height(24.dp))

            SubmitSection(
                submitting = submitting,
                enabled = submitEnabled,
                couriersAvailable = couriersAvailable,
                availabilityMessage = availabilityMessage,
                onSubmit = onSubmitOrder
            )

            // Map moved above
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
                            settings.domStorageEnabled = true
                            settings.mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                            settings.javaScriptCanOpenWindowsAutomatically = true
                            settings.setSupportMultipleWindows(true)
                            // Allow cookies and 3rd-party cookies for payment providers (e.g., 3DS)
                            CookieManager.getInstance().setAcceptCookie(true)
                            CookieManager.getInstance().setAcceptThirdPartyCookies(this, true)
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
                            webChromeClient = object : WebChromeClient() {
                                override fun onCreateWindow(
                                    view: WebView?,
                                    isDialog: Boolean,
                                    isUserGesture: Boolean,
                                    resultMsg: android.os.Message?
                                ): Boolean {
                                    // Try to open target window URL in the same WebView
                                    val result = view?.hitTestResult
                                    val data = result?.extra
                                    if (!data.isNullOrBlank()) {
                                        view.loadUrl(data)
                                    } else if (resultMsg != null) {
                                        try {
                                            val transport = resultMsg.obj as WebView.WebViewTransport
                                            transport.webView = this@apply
                                            resultMsg.sendToTarget()
                                        } catch (_: Exception) {}
                                    }
                                    return true
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
private fun OrderScreenHeader() {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Box(
            modifier = Modifier
                .size(56.dp)
                .clip(CircleShape)
                .background(Gold.copy(alpha = 0.15f)),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                imageVector = Icons.Filled.LocalShipping,
                contentDescription = null,
                tint = Gold,
                modifier = Modifier.size(32.dp)
            )
        }
        Spacer(Modifier.width(16.dp))
        Column {
            Text(
                text = "Nouvelle commande",
                fontSize = 26.sp,
                fontWeight = FontWeight.Bold,
                color = Gold
            )
            Text(
                text = "Livraison rapide et sécurisée",
                fontSize = 14.sp,
                color = Color.White.copy(alpha = 0.6f)
            )
        }
    }
}

@Composable
private fun UpdateBanner(
    showUpdate: Boolean,
    updateInfo: UpdateInfo?,
    onDownloadClick: (() -> Unit)?
) {
    AnimatedVisibility(visible = showUpdate, enter = expandVertically(), exit = shrinkVertically()) {
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = Info.copy(alpha = 0.15f)
            ),
            shape = RoundedCornerShape(16.dp),
            border = BorderStroke(1.dp, Info.copy(alpha = 0.3f))
        ) {
            Column(Modifier.padding(16.dp)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = Icons.Filled.Notifications,
                        contentDescription = null,
                        tint = Info
                    )
                    Spacer(Modifier.width(8.dp))
                    Text(
                        "Mise à jour disponible",
                        fontWeight = FontWeight.Bold,
                        color = Color.White
                    )
                }
                val versionName = updateInfo?.latest_version_name.orEmpty()
                if (versionName.isNotBlank()) {
                    Spacer(Modifier.height(4.dp))
                    Text("Version: $versionName", color = Color.White.copy(alpha = 0.8f), fontSize = 14.sp)
                }
                val downloadUrl = updateInfo?.download_url
                if (!downloadUrl.isNullOrBlank() && onDownloadClick != null) {
                    Spacer(Modifier.height(8.dp))
                    TextButton(
                        onClick = onDownloadClick,
                        colors = ButtonDefaults.textButtonColors(contentColor = Gold)
                    ) {
                        Text("Télécharger maintenant")
                    }
                }
            }
        }
    }
}

@Composable
private fun ItinerarySection(
    departure: String,
    destination: String,
    departureError: String?,
    destinationError: String?,
    onDepartureChange: (String) -> Unit,
    onDestinationChange: (String) -> Unit,
    onDepartureSelected: (String) -> Unit,
    onDestinationSelected: (String) -> Unit,
    onDepartureCoordinates: (LatLng?) -> Unit,
    onDestinationCoordinates: (LatLng?) -> Unit,
    bringIntoViewRequester: BringIntoViewRequester,
    showMessage: (String) -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.05f)
        ),
        shape = RoundedCornerShape(20.dp),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.15f))
    ) {
        Column(Modifier.padding(20.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(
                    imageVector = Icons.Filled.Place,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = "Itinéraire",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            }

            Spacer(Modifier.height(20.dp))

            AutocompleteTextField(
                label = "Adresse de départ",
                value = departure,
                onValueChange = onDepartureChange,
                isError = departureError != null,
                supportingError = departureError,
                modifier = Modifier
                    .fillMaxWidth()
                    .bringIntoViewRequester(bringIntoViewRequester),
                onCoordinates = onDepartureCoordinates,
                showError = showMessage,
                onSelected = onDepartureSelected
            )

            Spacer(Modifier.height(16.dp))

            AutocompleteTextField(
                label = "Adresse d'arrivée",
                value = destination,
                onValueChange = onDestinationChange,
                isError = destinationError != null,
                supportingError = destinationError,
                modifier = Modifier
                    .fillMaxWidth()
                    .bringIntoViewRequester(bringIntoViewRequester),
                onCoordinates = onDestinationCoordinates,
                showError = showMessage,
                onSelected = onDestinationSelected
            )
        }
    }
}

@Composable
private fun ContactsSection(
    senderPhone: String,
    senderPhoneError: String?,
    receiverField: TextFieldValue,
    receiverPhoneError: String?,
    description: String,
    onReceiverFieldChange: (TextFieldValue) -> Unit,
    onDescriptionChange: (String) -> Unit,
    bringIntoViewRequester: BringIntoViewRequester,
    scope: CoroutineScope
) {
    val focusManager = LocalFocusManager.current
    val keyboard = LocalSoftwareKeyboardController.current
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.05f)),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.15f))
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(bottom = 16.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Phone,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = "Contacts",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            }

            OutlinedTextField(
                value = senderPhone,
                onValueChange = {},
                label = { Text("Téléphone expéditeur") },
                leadingIcon = { Icon(Icons.Default.Phone, contentDescription = null, tint = Gold) },
                // Verrouillage complet sans désactiver le champ: focus possible, pas d'édition
                // (permet la sélection/copie et évite la confusion côté IME)
                readOnly = true,
                enabled = true,
                singleLine = true,
                keyboardOptions = KeyboardOptions.Default,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Gold,
                    focusedLabelColor = Gold,
                    focusedTextColor = Color.White,
                    unfocusedBorderColor = Gold.copy(alpha = 0.5f),
                    unfocusedLabelColor = Color.White.copy(alpha = 0.7f),
                    unfocusedTextColor = Color.White,
                    focusedLeadingIconColor = Gold,
                    unfocusedLeadingIconColor = Gold.copy(alpha = 0.7f),
                    cursorColor = Color.Transparent,
                    errorBorderColor = MaterialTheme.colorScheme.error,
                    errorLeadingIconColor = MaterialTheme.colorScheme.error,
                    errorLabelColor = MaterialTheme.colorScheme.error,
                    errorSupportingTextColor = MaterialTheme.colorScheme.error
                ),
                trailingIcon = { Icon(Icons.Default.Lock, contentDescription = null, tint = Gold) },
                modifier = Modifier
                    .fillMaxWidth()
                    .focusProperties { canFocus = false }
                    // Ne prend pas le focus pour éviter toute interaction avec l'IME
                    .onFocusEvent {
                        // Si un OEM donne malgré tout le focus, on le rend tout de suite
                        if (it.isFocused) {
                            android.util.Log.d("OrderScreen","senderPhone focus unexpectedly acquired -> clearing focus")
                            focusManager.clearFocus(force = true)
                            keyboard?.hide()
                        }
                    },
                isError = senderPhoneError != null,
                supportingText = {
                    when {
                        senderPhoneError != null -> Text(senderPhoneError, color = MaterialTheme.colorScheme.error)
                        else -> Text(
                            "Numéro lié à votre compte (+225). Contactez le support pour le modifier.",
                            color = Color.White.copy(alpha = 0.6f)
                        )
                    }
                }
            )
            Spacer(Modifier.height(16.dp))

            OutlinedTextField(
                value = receiverField,
                onValueChange = onReceiverFieldChange,
                label = { Text("Téléphone destinataire") },
                leadingIcon = { Icon(Icons.Default.Phone, contentDescription = null, tint = Gold) },
                // Forcer un clavier numérique fiable (évite les soucis d'affichage en mode ADB)
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Done),
                keyboardActions = KeyboardActions(
                    onDone = {
                        focusManager.clearFocus(force = true)
                        keyboard?.hide()
                    }
                ),
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Gold,
                    focusedLabelColor = Gold,
                    focusedTextColor = Color.White,
                    unfocusedBorderColor = Gold.copy(alpha = 0.5f),
                    unfocusedLabelColor = Color.White.copy(alpha = 0.7f),
                    unfocusedTextColor = Color.White,
                    focusedLeadingIconColor = Gold,
                    unfocusedLeadingIconColor = Gold.copy(alpha = 0.7f),
                    cursorColor = Gold,
                    errorBorderColor = MaterialTheme.colorScheme.error,
                    errorLeadingIconColor = MaterialTheme.colorScheme.error,
                    errorLabelColor = MaterialTheme.colorScheme.error
                ),
                modifier = Modifier
                    .fillMaxWidth()
                    .focusRequester(receiverFocusRequester)
                    .bringIntoViewRequester(bringIntoViewRequester)
                    .onFocusEvent {
                        if (it.isFocused) {
                            android.util.Log.d("OrderScreen","receiver focus -> show keyboard")
                            keyboard?.show()
                            scope.launch { bringIntoViewRequester.bringIntoView() }
                        } else {
                            android.util.Log.d("OrderScreen","receiver focus lost -> hide keyboard")
                            keyboard?.hide()
                        }
                    },
                singleLine = true,
                isError = receiverPhoneError != null,
                supportingText = {
                    if (receiverPhoneError != null) {
                        Text(receiverPhoneError, color = MaterialTheme.colorScheme.error)
                    }
                }
            )

            // Autofocus du téléphone destinataire si vide (fiabilise l'ouverture IME sur certains OEM)
            LaunchedEffect(receiverDigits) {
                if (receiverDigits.isEmpty()) {
                    try {
                        delay(150)
                        receiverFocusRequester.requestFocus()
                        keyboard?.show()
                    } catch (_: Exception) { }
                }
            }

            Spacer(Modifier.height(16.dp))

            OutlinedTextField(
                value = description,
                onValueChange = onDescriptionChange,
                label = { Text("Description (optionnelle)") },
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = Gold,
                    focusedLabelColor = Gold,
                    focusedTextColor = Color.White,
                    unfocusedBorderColor = Gold.copy(alpha = 0.5f),
                    unfocusedLabelColor = Color.White.copy(alpha = 0.7f),
                    unfocusedTextColor = Color.White,
                    cursorColor = Gold
                ),
                modifier = Modifier
                    .fillMaxWidth()
                    .bringIntoViewRequester(bringIntoViewRequester)
                    .onFocusEvent {
                        if (it.isFocused) {
                            scope.launch { bringIntoViewRequester.bringIntoView() }
                        }
                    }
            )
        }
    }
}

@Composable
private fun PrioritySection(
    priority: String,
    onPriorityChange: (String) -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.05f)),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.15f))
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(bottom = 16.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Star,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = "Priorité",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            }

            PrioritySelector(
                selectedPriority = priority,
                onPriorityChanged = onPriorityChange,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

@Composable
private fun PriceSection(
    estimating: Boolean,
    totalPrice: Int?,
    distanceText: String?,
    durationText: String?
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Gold.copy(alpha = 0.1f)),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.3f))
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                if (estimating) {
                    CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(18.dp), color = Gold)
                    Spacer(Modifier.width(8.dp))
                }
                AnimatedContent(targetState = totalPrice, label = "priceAnim", transitionSpec = { fadeIn(tween(200)) togetherWith fadeOut(tween(200)) }) { price ->
                    val txt = if (price != null) "Prix estimé: ${price} FCFA" else "Prix en calcul…"
                    Text(txt, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = Gold)
                }
            }
            if (distanceText != null || durationText != null) {
                Spacer(Modifier.height(12.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Place, contentDescription = null, tint = Gold.copy(alpha = 0.7f), modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Distance: ${distanceText ?: "-"}", color = Color.White.copy(alpha = 0.8f))
                    Spacer(Modifier.width(16.dp))
                    Icon(Icons.Default.Info, contentDescription = null, tint = Gold.copy(alpha = 0.7f), modifier = Modifier.size(16.dp))
                    Spacer(Modifier.width(4.dp))
                    Text("Durée: ${durationText ?: "-"}", color = Color.White.copy(alpha = 0.8f))
                }
            }
        }
    }
}

@Composable
private fun PaymentSection(
    paymentMethod: String,
    onPaymentMethodChange: (String) -> Unit
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.05f)),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.15f))
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier.padding(bottom = 16.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.ShoppingCart,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = "Paiement",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            }

            PaymentMethodSelector(
                selectedMethod = paymentMethod,
                onMethodChanged = onPaymentMethodChange,
                modifier = Modifier.fillMaxWidth()
            )
        }
    }
}

@Composable
private fun SubmitSection(
    submitting: Boolean,
    enabled: Boolean,
    couriersAvailable: Boolean,
    availabilityMessage: String?,
    onSubmit: () -> Unit
) {
    Button(
        onClick = onSubmit,
        modifier = Modifier
            .fillMaxWidth()
            .height(60.dp),
        enabled = enabled,
        shape = RoundedCornerShape(16.dp),
        colors = ButtonDefaults.buttonColors(
            containerColor = Gold,
            contentColor = Dark,
            disabledContainerColor = Gold.copy(alpha = 0.3f),
            disabledContentColor = Dark.copy(alpha = 0.5f)
        ),
        elevation = ButtonDefaults.buttonElevation(
            defaultElevation = 8.dp,
            pressedElevation = 12.dp,
            disabledElevation = 0.dp
        )
    ) {
        if (submitting) {
            CircularProgressIndicator(strokeWidth = 2.dp, modifier = Modifier.size(22.dp), color = Dark)
            Spacer(Modifier.width(12.dp))
            Text("Envoi…", fontSize = 16.sp, fontWeight = FontWeight.Bold)
        } else {
            Icon(Icons.Default.CheckCircle, contentDescription = null, modifier = Modifier.size(24.dp))
            Spacer(Modifier.width(12.dp))
            Text("Passer la commande", fontSize = 16.sp, fontWeight = FontWeight.Bold)
        }
    }

    if (!couriersAvailable) {
        Spacer(Modifier.height(16.dp))
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(containerColor = AccentRed.copy(alpha = 0.2f)),
            border = BorderStroke(1.dp, AccentRed.copy(alpha = 0.5f)),
            shape = RoundedCornerShape(16.dp)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp)
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.Info, contentDescription = null, tint = AccentRed, modifier = Modifier.size(24.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(availabilityMessage ?: "Aucun coursier actif pour le moment", color = Color.White, fontWeight = FontWeight.Bold)
                }
                Spacer(Modifier.height(4.dp))
                Text("Le formulaire est temporairement désactivé", color = Color.White.copy(alpha = 0.8f), fontSize = 14.sp)
            }
        }
    }
}

@OptIn(com.google.maps.android.compose.MapsComposeExperimentalApi::class)
@Composable
private fun MapSection(
    departureLatLng: LatLng?,
    destinationLatLng: LatLng?,
    onDepartureUpdate: (LatLng, String?) -> Unit,
    onDestinationUpdate: (LatLng, String?) -> Unit,
    reverseGeocode: suspend (LatLng) -> String?,
    scope: CoroutineScope,
    showMessage: (String) -> Unit
) {
    val context = LocalContext.current
    var mapError by remember { mutableStateOf<String?>(null) }
    var mapLoaded by remember { mutableStateOf(false) }

    // Ensure Maps SDK is initialized before any CameraUpdateFactory usage
    LaunchedEffect(Unit) {
        try {
            MapsInitializer.initialize(context, MapsInitializer.Renderer.LATEST) { }
        } catch (e: Exception) {
            mapError = e.message ?: "Erreur d'initialisation de la carte"
        }
    }

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White.copy(alpha = 0.05f)),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.15f))
    ) {
        Column {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                modifier = Modifier
                    .padding(20.dp)
                    .padding(bottom = 0.dp)
            ) {
                Icon(
                    imageVector = Icons.Default.Place,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(24.dp)
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = "Aperçu carte",
                    fontSize = 20.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
                Spacer(Modifier.weight(1f))
                if (mapLoaded) {
                    Surface(
                        color = Success.copy(alpha = 0.2f),
                        shape = CircleShape
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Box(
                                modifier = Modifier
                                    .size(8.dp)
                                    .clip(CircleShape)
                                    .background(Success)
                            )
                            Spacer(Modifier.width(6.dp))
                            Text("Prête", fontSize = 12.sp, color = Success, fontWeight = FontWeight.Bold)
                        }
                    }
                }
            }

            Spacer(Modifier.height(16.dp))

            Box(
                Modifier
                    .height(220.dp)
                    .fillMaxWidth()
                    .padding(horizontal = 20.dp)
                    .padding(bottom = 20.dp)
                    .clip(RoundedCornerShape(16.dp))
            ) {
                if (mapError != null) {
                    Column(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(16.dp),
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.Center
                    ) {
                        Text(
                            "Carte non disponible",
                            style = MaterialTheme.typography.titleMedium,
                            textAlign = TextAlign.Center
                        )
                        Text(
                            "Erreur: ${mapError}",
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
                    val cameraPositionState = rememberCameraPositionState {
                        position = CameraPosition.fromLatLngZoom(LatLng(5.3476, -4.0076), 12f)
                    }
                    val abidjanCenter = LatLng(5.3476, -4.0076)
                    val defaultZoom = 12f

                    suspend fun animateCameraSafely(
                        label: String,
                        primary: com.google.android.gms.maps.CameraUpdate?,
                        fallback: com.google.android.gms.maps.CameraUpdate? = null
                    ) {
                        if (primary == null && fallback == null) return
                        val runFallback = {
                            fallback?.let {
                                android.util.Log.d("OrderScreen", "Camera fallback -> $label")
                                cameraPositionState.move(it)
                            }
                        }
                        try {
                            if (primary != null) {
                                android.util.Log.d("OrderScreen", "Camera animate -> $label")
                                cameraPositionState.animate(primary)
                                return
                            }
                        } catch (iae: IllegalArgumentException) {
                            android.util.Log.w("OrderScreen", "Camera update invalid -> $label", iae)
                        } catch (ise: IllegalStateException) {
                            android.util.Log.w("OrderScreen", "Camera update illegal state -> $label", ise)
                        } catch (rre: com.google.android.gms.maps.model.RuntimeRemoteException) {
                            android.util.Log.w("OrderScreen", "Camera update remote exception -> $label", rre)
                        } catch (e: Exception) {
                            android.util.Log.w("OrderScreen", "Camera update error -> $label", e)
                        }
                        runFallback()
                    }

                    LaunchedEffect(departureLatLng, destinationLatLng, mapLoaded) {
                        if (!mapLoaded) return@LaunchedEffect
                        mapError = null
                        android.util.Log.d(
                            "OrderScreen",
                            "Camera trigger. departure=$departureLatLng destination=$destinationLatLng"
                        )
                        when {
                            departureLatLng != null && destinationLatLng != null -> {
                                val mid = LatLng(
                                    (departureLatLng.latitude + destinationLatLng.latitude) / 2.0,
                                    (departureLatLng.longitude + destinationLatLng.longitude) / 2.0
                                )
                                val fallbackUpdate = CameraUpdateFactory.newLatLngZoom(mid, defaultZoom)
                                val boundsUpdate = try {
                                    val bounds = LatLngBounds.builder()
                                        .include(departureLatLng)
                                        .include(destinationLatLng)
                                        .build()
                                    android.util.Log.d("OrderScreen", "Attempt bounds fit -> $bounds")
                                    CameraUpdateFactory.newLatLngBounds(bounds, 100)
                                } catch (iae: IllegalArgumentException) {
                                    android.util.Log.w("OrderScreen", "Bounds build failed -> midpoint fallback", iae)
                                    null
                                }
                                animateCameraSafely("A+B", boundsUpdate, fallbackUpdate)
                            }
                            departureLatLng != null -> {
                                animateCameraSafely(
                                    "A only",
                                    CameraUpdateFactory.newLatLngZoom(departureLatLng, defaultZoom)
                                )
                            }
                            destinationLatLng != null -> {
                                animateCameraSafely(
                                    "B only",
                                    CameraUpdateFactory.newLatLngZoom(destinationLatLng, defaultZoom)
                                )
                            }
                            else -> {
                                animateCameraSafely(
                                    "default center",
                                    CameraUpdateFactory.newLatLngZoom(abidjanCenter, defaultZoom)
                                )
                            }
                        }
                    }

                    var showMapChoice by remember { mutableStateOf(false) }
                    var pendingClick by remember { mutableStateOf<LatLng?>(null) }

                    if (showMapChoice && pendingClick != null) {
                        AlertDialog(
                            onDismissRequest = {
                                showMapChoice = false
                                pendingClick = null
                            },
                            title = { Text("Placer un marqueur") },
                            text = { Text("Voulez-vous placer le marqueur A (Départ) ?\nAppuyez sur Annuler pour placer B (Arrivée)") },
                            confirmButton = {
                                TextButton(onClick = {
                                    val pos = pendingClick!!
                                    showMapChoice = false
                                    pendingClick = null
                                    scope.launch {
                                        val addr = reverseGeocode(pos)
                                        onDepartureUpdate(pos, addr)
                                    }
                                }) { Text("Placer A") }
                            },
                            dismissButton = {
                                TextButton(onClick = {
                                    val pos = pendingClick!!
                                    showMapChoice = false
                                    pendingClick = null
                                    scope.launch {
                                        val addr = reverseGeocode(pos)
                                        onDestinationUpdate(pos, addr)
                                    }
                                }) { Text("Placer B") }
                            }
                        )
                    }

                    GoogleMap(
                        modifier = Modifier.fillMaxSize(),
                        cameraPositionState = cameraPositionState,
                        onMapClick = { ll ->
                            android.util.Log.d("OrderScreen", "Map click at: $ll")
                            pendingClick = ll
                            showMapChoice = true
                        },
                        onMapLoaded = {
                            mapLoaded = true
                            showMessage("Carte prête")
                            try {
                                android.util.Log.d("OrderScreen", "GoogleMap onMapLoaded fired")
                            } catch (_: Exception) {}
                        }
                    ) {
                        MapEffect(Unit) { map ->
                            try {
                                // Keep safe zoom preferences without constraining camera target bounds
                                map.setMinZoomPreference(5.0f)
                                map.setMaxZoomPreference(20f)
                            } catch (_: Exception) {}
                        }
                        MapEffect(key1 = departureLatLng, key2 = destinationLatLng) { map ->
                            map.setOnMarkerDragListener(object : GoogleMap.OnMarkerDragListener {
                                override fun onMarkerDragStart(marker: com.google.android.gms.maps.model.Marker) {}
                                override fun onMarkerDrag(marker: com.google.android.gms.maps.model.Marker) {}
                                override fun onMarkerDragEnd(marker: com.google.android.gms.maps.model.Marker) {
                                    val pos = marker.position
                                    scope.launch {
                                        val addr = reverseGeocode(pos)
                                        if (marker.title.orEmpty().contains("Départ")) {
                                            onDepartureUpdate(pos, addr)
                                        } else if (marker.title.orEmpty().contains("Arrivée")) {
                                            onDestinationUpdate(pos, addr)
                                        }
                                    }
                                }
                            })
                        }

                        fun markerIcon(label: String, color: Int): com.google.android.gms.maps.model.BitmapDescriptor {
                            val width = 100
                            val height = 130
                            val bmp = createBitmap(width, height, android.graphics.Bitmap.Config.ARGB_8888)
                            val canvas = Canvas(bmp)
                            val paint = Paint(Paint.ANTI_ALIAS_FLAG)
                            paint.color = color
                            val body = RectF(5f, 5f, (width - 5).toFloat(), (height - 20).toFloat())
                            canvas.drawRoundRect(body, 40f, 40f, paint)
                            val triPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color }
                            val path = android.graphics.Path().apply {
                                moveTo((width / 2).toFloat(), (height - 10).toFloat())
                                lineTo((width / 2 - 15).toFloat(), (height - 35).toFloat())
                                lineTo((width / 2 + 15).toFloat(), (height - 35).toFloat())
                                close()
                            }
                            canvas.drawPath(path, triPaint)
                            paint.color = 0xFFFFFFFF.toInt()
                            canvas.drawCircle((width / 2).toFloat(), (height / 2 - 10).toFloat(), 28f, paint)
                            paint.color = 0xFF000000.toInt()
                            paint.textSize = 42f
                            paint.typeface = Typeface.create(Typeface.DEFAULT_BOLD, Typeface.BOLD)
                            val textWidth = paint.measureText(label)
                            canvas.drawText(label, (width / 2 - textWidth / 2), (height / 2 + 5).toFloat(), paint)
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

                    Box(Modifier.fillMaxSize()) {
                        Surface(
                            color = MaterialTheme.colorScheme.surface.copy(alpha = 0.7f),
                            shape = MaterialTheme.shapes.small,
                            tonalElevation = 1.dp,
                            modifier = Modifier
                                .align(Alignment.TopStart)
                                .padding(6.dp)
                        ) {
                            Text(
                                text = "Map loaded: " + if (mapLoaded) "yes" else "no",
                                style = MaterialTheme.typography.labelSmall,
                                modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                            )
                        }
                        if (mapError != null) {
                            Surface(
                                color = MaterialTheme.colorScheme.error.copy(alpha = 0.15f),
                                shape = MaterialTheme.shapes.small,
                                modifier = Modifier
                                    .align(Alignment.TopEnd)
                                    .padding(6.dp)
                            ) {
                                Text(
                                    text = mapError ?: "",
                                    style = MaterialTheme.typography.labelSmall,
                                    color = MaterialTheme.colorScheme.error,
                                    modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp)
                                )
                            }
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
    data class Suggestion(
        val primary: String,
        val secondary: String?,
        val placeId: String?,
        val lat: Double? = null,
        val lon: Double? = null
    )
    var suggestions by remember { mutableStateOf(emptyList<Suggestion>()) }
    var loading by remember { mutableStateOf(false) }
    var errorMsg by remember { mutableStateOf<String?>(null) }
    var job by remember { mutableStateOf<Job?>(null) }
    val scope = rememberCoroutineScope()
    val fieldBringIntoView = remember { BringIntoViewRequester() }
    val cache = remember { mutableStateMapOf<String, List<Suggestion>>() }
    val focusManager = LocalFocusManager.current
    val keyboard = LocalSoftwareKeyboardController.current

    Column(Modifier.fillMaxWidth()) {
        OutlinedTextField(
            value = value,
            onValueChange = { text ->
                onValueChange(text)
                job?.cancel()
                val key = text.trim().lowercase()
                if (text.length < 3) {
                    suggestions = emptyList(); expanded = false; errorMsg = null; loading = false; return@OutlinedTextField
                }
                job = scope.launch {
                    loading = true
                    errorMsg = null
                    // small debounce
                    delay(180)
                    cache[key]?.let { cached ->
                        suggestions = cached
                        expanded = cached.isNotEmpty()
                        loading = false
                        if (expanded) scope.launch { fieldBringIntoView.bringIntoView() }
                        return@launch
                    }
                    val request = FindAutocompletePredictionsRequest.builder()
                        .setQuery(text)
                        .setCountries(listOf("CI"))
                        .setSessionToken(token)
                        .build()
                    places.findAutocompletePredictions(request)
                        .addOnSuccessListener { response ->
                            val list = response.autocompletePredictions.map { p ->
                                val prim = p.getPrimaryText(null).toString()
                                val sec = p.getSecondaryText(null)?.toString()?.takeIf { it.isNotBlank() }
                                Suggestion(prim, sec, p.placeId)
                            }.take(6)
                            suggestions = list
                            cache[key] = list
                            expanded = list.isNotEmpty()
                            if (expanded) scope.launch { fieldBringIntoView.bringIntoView() }
                            loading = false
                        }
                        .addOnFailureListener { err ->
                            scope.launch {
                                try {
                                    val list = withContext(Dispatchers.IO) {
                                        val url = "https://nominatim.openstreetmap.org/search".toHttpUrl()
                                            .newBuilder()
                                            .addQueryParameter("format", "json")
                                            .addQueryParameter("limit", "6")
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
                                            for (i in 0 until minOf(arr.length(), 6)) {
                                                val obj = arr.getJSONObject(i)
                                                val dn = obj.optString("display_name")
                                                val parts = dn.split(", ")
                                                val prim = parts.firstOrNull().orEmpty()
                                                val sec = parts.drop(1).joinToString(", ").ifBlank { null }
                                                val lat = obj.optString("lat").toDoubleOrNull()
                                                val lon = obj.optString("lon").toDoubleOrNull()
                                                l.add(Suggestion(prim, sec, null, lat, lon))
                                            }
                                            l
                                        }
                                    }
                                    suggestions = list
                                    cache[key] = list
                                    expanded = list.isNotEmpty()
                                    if (expanded) scope.launch { fieldBringIntoView.bringIntoView() }
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
            colors = OutlinedTextFieldDefaults.colors(
                focusedTextColor = Color.White,
                unfocusedTextColor = Color.White,
                focusedBorderColor = Gold,
                unfocusedBorderColor = Gold.copy(alpha = 0.35f),
                focusedLabelColor = Gold,
                unfocusedLabelColor = Color.White.copy(alpha = 0.7f),
                focusedLeadingIconColor = Gold,
                unfocusedLeadingIconColor = Gold.copy(alpha = 0.7f),
                cursorColor = Gold,
                errorBorderColor = MaterialTheme.colorScheme.error,
                errorLeadingIconColor = MaterialTheme.colorScheme.error,
                errorLabelColor = MaterialTheme.colorScheme.error,
                disabledTextColor = Color.White.copy(alpha = 0.6f)
            ),
            trailingIcon = {
                if (loading) {
                    CircularProgressIndicator(modifier = Modifier.size(16.dp), strokeWidth = 2.dp, color = Gold)
                }
            },
            modifier = modifier
                .fillMaxWidth()
                .bringIntoViewRequester(fieldBringIntoView)
                .onFocusEvent { if (it.isFocused) { scope.launch { fieldBringIntoView.bringIntoView() } } }
        )

        Box(modifier = Modifier.fillMaxWidth()) {
        if (expanded) {
            Surface(
                color = Color(0xFF121212),
                tonalElevation = 6.dp,
                shape = RoundedCornerShape(12.dp),
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(max = 240.dp)
                    .zIndex(1f)
            ) {
                LazyColumn(
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(max = 240.dp)
                ) {
                    items(suggestions) { s ->
                        Column(
                            modifier = Modifier
                                .clickable {
                                    val selectedText = buildString {
                                        append(s.primary)
                                        if (!s.secondary.isNullOrBlank()) append(", ").append(s.secondary)
                                    }
                                    android.util.Log.d(
                                        "OrderScreen",
                                        "Suggestion picked for $label -> primary=${s.primary} secondary=${s.secondary} placeId=${s.placeId} lat=${s.lat} lon=${s.lon}"
                                    )
                                    if (!s.placeId.isNullOrBlank()) {
                                        val placeRequest = FetchPlaceRequest.newInstance(
                                            s.placeId,
                                            listOf(Place.Field.LAT_LNG, Place.Field.ADDRESS, Place.Field.NAME)
                                        )
                                        places.fetchPlace(placeRequest)
                                            .addOnSuccessListener { result ->
                                                android.util.Log.d(
                                                    "OrderScreen",
                                                    "FetchPlace success for $label -> latLng=${result.place.latLng} address=${result.place.address}"
                                                )
                                                onCoordinates(result.place.latLng)
                                                onSelected(result.place.address ?: selectedText)
                                                expanded = false
                                                focusManager.clearFocus(force = true)
                                                keyboard?.hide()
                                            }
                                            .addOnFailureListener { err ->
                                                android.util.Log.w(
                                                    "OrderScreen",
                                                    "FetchPlace failed for $label (${s.placeId}): $err"
                                                )
                                                onSelected(selectedText)
                                                expanded = false
                                                focusManager.clearFocus(force = true)
                                                keyboard?.hide()
                                            }
                                    } else {
                                        // Nominatim fallback with lat/lon included
                                        if (s.lat != null && s.lon != null) {
                                            android.util.Log.d(
                                                "OrderScreen",
                                                "Nominatim coordinates for $label -> lat=${s.lat} lon=${s.lon}"
                                            )
                                            onCoordinates(LatLng(s.lat, s.lon))
                                        }
                                        onSelected(selectedText)
                                        expanded = false
                                        focusManager.clearFocus(force = true)
                                        keyboard?.hide()
                                    }
                                }
                                .padding(horizontal = 12.dp, vertical = 10.dp)
                        ) {
                            Text(s.primary, color = Color.White, fontSize = 14.sp)
                            if (!s.secondary.isNullOrBlank()) {
                                Text(s.secondary!!, color = Color.White.copy(alpha = 0.85f), fontSize = 12.sp)
                            }
                        }
                        Divider(color = Color.White.copy(alpha = 0.06f))
                    }
                }
            }
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
