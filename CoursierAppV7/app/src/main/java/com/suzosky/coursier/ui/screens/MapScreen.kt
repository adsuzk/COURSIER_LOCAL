package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import com.google.android.gms.maps.CameraUpdateFactory
import com.google.android.gms.maps.GoogleMap
import com.google.android.gms.maps.MapsInitializer
import com.google.android.gms.maps.model.LatLng
import com.google.android.gms.maps.model.MarkerOptions
import com.google.android.gms.maps.model.PolylineOptions
import com.google.maps.android.PolyUtil
import com.suzosky.coursier.R
import com.suzosky.coursier.ui.theme.BackgroundPrimary
import com.suzosky.coursier.utils.MapViewContainer
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import okhttp3.OkHttpClient
import okhttp3.Request
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.coroutines.launch
import android.graphics.Color as AndroidColor

/**
 * Écran Map - Affiche la carte et le tarif calculé
 */
@Composable
fun MapScreen() {
    val ctx = LocalContext.current
    val tarifState = remember { mutableStateOf<com.suzosky.coursier.utils.TarificationResult?>(null) }
    val mapHolder = remember { MapViewContainer(ctx) }
    val scope = rememberCoroutineScope()
    DisposableEffect(mapHolder) {
        mapHolder.onStart()
        mapHolder.onResume()
        onDispose {
            mapHolder.onPause()
            mapHolder.onStop()
            mapHolder.onDestroy()
        }
    }

    Box(modifier = Modifier.fillMaxSize()) {
        AndroidView(
            factory = {
                mapHolder.mapView.apply {
                    getMapAsync { map ->
                        // Setup map
                        MapsInitializer.initialize(ctx)
                        val origin = LatLng(5.316667, -4.033333)
                        val destination = LatLng(5.3470, -4.0170)
                        map.mapType = GoogleMap.MAP_TYPE_NORMAL
                        map.moveCamera(CameraUpdateFactory.newLatLngZoom(origin, 12f))
                        map.addMarker(MarkerOptions().position(origin).title("Point A"))
                        map.addMarker(MarkerOptions().position(destination).title("Point B"))
                        // Call Directions API off main thread
                        val key = ctx.getString(R.string.google_maps_key)
                        val url = "https://maps.googleapis.com/maps/api/directions/json?origin=${origin.latitude},${origin.longitude}&destination=${destination.latitude},${destination.longitude}&key=$key"
                        scope.launch {
                            val response = withContext(Dispatchers.IO) {
                                val client = OkHttpClient()
                                val req = Request.Builder().url(url).build()
                                client.newCall(req).execute().use { res -> res.body?.string() }
                            }
                            response?.let { body ->
                                val root = Json.parseToJsonElement(body).jsonObject
                                val routes = root["routes"]?.jsonArray
                                if (routes != null && routes.isNotEmpty()) {
                                    // Draw route
                                    routes.firstOrNull()?.jsonObject
                                        ?.get("overview_polyline")?.jsonObject
                                        ?.get("points")?.jsonPrimitive
                                        ?.content?.let { pts ->
                                            val path = PolyUtil.decode(pts)
                                            map.addPolyline(PolylineOptions().addAll(path).width(10f).color(AndroidColor.BLUE))
                                        }
                                    // Compute tariff
                                    val leg = routes.firstOrNull()?.jsonObject
                                        ?.get("legs")?.jsonArray
                                        ?.firstOrNull()?.jsonObject
                                    val dist = leg?.get("distance")?.jsonObject
                                        ?.get("value")?.jsonPrimitive?.content?.toIntOrNull() ?: 0
                                    val dur = leg?.get("duration")?.jsonObject
                                        ?.get("value")?.jsonPrimitive?.content?.toIntOrNull() ?: 0
                                    val km = dist / 1000f
                                    val min = dur / 60
                                    tarifState.value = com.suzosky.coursier.utils.TarificationSuzosky.calculerTarif(km, min)
                                }
                            }
                        }
                    }
                }
            },
            modifier = Modifier
                .fillMaxSize()
                .background(BackgroundPrimary)
        )
        tarifState.value?.let { result ->
            Box(
                modifier = Modifier
                    .align(Alignment.BottomCenter)
                    .fillMaxWidth()
                    .background(Color.White.copy(alpha = 0.8f))
                    .padding(16.dp)
            ) {
                Text(text = result.genererResume(), color = Color.Black)
            }
        }
    }
}