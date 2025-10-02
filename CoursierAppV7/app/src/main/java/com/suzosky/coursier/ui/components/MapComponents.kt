package com.suzosky.coursier.ui.components

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.MyLocation
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import android.Manifest
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.content.pm.PackageManager
import androidx.core.content.ContextCompat
import androidx.hilt.navigation.compose.hiltViewModel
import com.google.android.gms.maps.CameraUpdateFactory
import com.google.android.gms.maps.model.CameraPosition
import com.google.android.gms.maps.model.LatLng
import com.google.maps.android.compose.*
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.viewmodel.MapViewModel

/**
 * Composable principal pour afficher la carte Google Maps
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GoogleMapView(
    modifier: Modifier = Modifier,
    viewModel: MapViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    val locationPermissionGranted = hasLocationPermission(context)
    var searchQuery by remember { mutableStateOf("") }
    var showSearchResults by remember { mutableStateOf(false) }

    // Position par défaut sur Abidjan si pas de localisation
    val defaultLocation = LatLng(5.3600, -4.0083)
    val currentLocation = uiState.currentLocation ?: defaultLocation

    val cameraPositionState = rememberCameraPositionState {
        position = CameraPosition.fromLatLngZoom(currentLocation, 13f)
    }

    // Mettre à jour la caméra quand la position change
    LaunchedEffect(uiState.currentLocation) {
        uiState.currentLocation?.let { location ->
            cameraPositionState.animate(
                update = CameraUpdateFactory.newCameraPosition(
                    CameraPosition.fromLatLngZoom(location, 15f)
                )
            )
        }
    }

    Column(modifier = modifier) {
        // Barre de recherche
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(8.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(
                containerColor = MaterialTheme.colorScheme.surface
            )
        ) {
            Row(
                modifier = Modifier.padding(12.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    Icons.Default.Search,
                    contentDescription = "Rechercher",
                    tint = PrimaryGold,
                    modifier = Modifier.size(24.dp)
                )
                
                Spacer(modifier = Modifier.width(8.dp))
                
                OutlinedTextField(
                    value = searchQuery,
                    onValueChange = { query ->
                        searchQuery = query
                        if (query.isNotEmpty()) {
                            viewModel.searchPlaces(query)
                            showSearchResults = true
                        } else {
                            showSearchResults = false
                        }
                    },
                    label = { Text("Rechercher une adresse...") },
                    modifier = Modifier.weight(1f),
                    singleLine = true,
                    colors = OutlinedTextFieldDefaults.colors(
                        focusedBorderColor = PrimaryGold,
                        focusedLabelColor = PrimaryGold
                    )
                )
                
                Spacer(modifier = Modifier.width(8.dp))
                
                IconButton(
                    onClick = { viewModel.getCurrentLocation() }
                ) {
                    if (uiState.isLocationLoading) {
                        CircularProgressIndicator(
                            modifier = Modifier.size(20.dp),
                            color = PrimaryGold,
                            strokeWidth = 2.dp
                        )
                    } else {
                        Icon(
                            Icons.Default.MyLocation,
                            contentDescription = "Ma position",
                            tint = AccentBlue
                        )
                    }
                }
            }
        }

        // Résultats de recherche
        if (showSearchResults && uiState.searchResults.isNotEmpty()) {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 8.dp),
                elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
            ) {
                LazyColumn(
                    modifier = Modifier.heightIn(max = 200.dp)
                ) {
                    items(uiState.searchResults) { prediction ->
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                Icons.Default.LocationOn,
                                contentDescription = null,
                                tint = PrimaryGold,
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(modifier = Modifier.width(12.dp))
                            Column {
                                Text(
                                    text = prediction.getPrimaryText(null).toString(),
                                    style = MaterialTheme.typography.bodyMedium,
                                    fontWeight = FontWeight.Medium
                                )
                                Text(
                                    text = prediction.getSecondaryText(null).toString(),
                                    style = MaterialTheme.typography.bodySmall,
                                    color = MaterialTheme.colorScheme.onSurfaceVariant
                                )
                            }
                        }
                        if (prediction != uiState.searchResults.last()) {
                            Divider(thickness = 0.5.dp)
                        }
                    }
                }
            }
            Spacer(modifier = Modifier.height(8.dp))
        }

        // Carte Google Maps
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .height(300.dp)
        ) {
            GoogleMap(
                modifier = Modifier.fillMaxSize(),
                cameraPositionState = cameraPositionState,
                properties = MapProperties(
                    isMyLocationEnabled = false,
                    mapType = MapType.NORMAL
                ),
                uiSettings = MapUiSettings(
                    myLocationButtonEnabled = locationPermissionGranted,
                    zoomControlsEnabled = true,
                    mapToolbarEnabled = false
                )
            ) {
                // Marker pour la position actuelle
                uiState.currentLocation?.let { location ->
                    Marker(
                        state = MarkerState(position = location),
                        title = "Ma position",
                        snippet = "Position actuelle du coursier"
                    )
                }

                MapEffect(locationPermissionGranted) { googleMap ->
                    try {
                        googleMap.isMyLocationEnabled = locationPermissionGranted
                    } catch (_: SecurityException) {
                        googleMap.isMyLocationEnabled = false
                    }
                }
            }

            // Message d'erreur overlay
            uiState.errorMessage?.let { message ->
                Card(
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .padding(16.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = AccentRed.copy(alpha = 0.9f)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = message,
                            color = MaterialTheme.colorScheme.onError,
                            style = MaterialTheme.typography.bodySmall
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        TextButton(
                            onClick = { viewModel.clearError() }
                        ) {
                            Text(
                                "OK",
                                color = MaterialTheme.colorScheme.onError
                            )
                        }
                    }
                }
            }
        }
    }
}

/**
 * Composable pour afficher une carte simple avec markers
 */
@Composable
fun SimpleMapView(
    pickupLocation: LatLng?,
    deliveryLocation: LatLng?,
    modifier: Modifier = Modifier
) {
    val context = LocalContext.current
    val locationPermissionGranted = hasLocationPermission(context)
    val isPlayServicesAvailable = remember {
        com.google.android.gms.common.GoogleApiAvailability.getInstance()
            .isGooglePlayServicesAvailable(context) == com.google.android.gms.common.ConnectionResult.SUCCESS
    }

    if (isPlayServicesAvailable) {
        val defaultLocation = LatLng(5.3600, -4.0083)
        val centerLocation = pickupLocation ?: deliveryLocation ?: defaultLocation

        val cameraPositionState = rememberCameraPositionState {
            position = CameraPosition.fromLatLngZoom(centerLocation, 12f)
        }

        GoogleMap(
            modifier = modifier
                .fillMaxWidth()
                .height(200.dp),
            cameraPositionState = cameraPositionState,
            properties = MapProperties(
                mapType = MapType.NORMAL,
                isMyLocationEnabled = false
            ),
            uiSettings = MapUiSettings(
                zoomControlsEnabled = false,
                mapToolbarEnabled = false,
                myLocationButtonEnabled = locationPermissionGranted
            )
        ) {
            pickupLocation?.let { location ->
                Marker(
                    state = MarkerState(position = location),
                    title = "Récupération",
                    snippet = "Adresse de récupération"
                )
            }
            deliveryLocation?.let { location ->
                Marker(
                    state = MarkerState(position = location),
                    title = "Livraison",
                    snippet = "Adresse de livraison"
                )
            }

            MapEffect(locationPermissionGranted) { googleMap ->
                try {
                    googleMap.isMyLocationEnabled = locationPermissionGranted
                } catch (_: SecurityException) {
                    googleMap.isMyLocationEnabled = false
                }
            }
        }
    } else {
        androidx.compose.material3.Card(
            modifier = modifier
                .fillMaxWidth()
                .height(200.dp),
            colors = androidx.compose.material3.CardDefaults.cardColors(
                containerColor = com.suzosky.coursier.ui.theme.PrimaryDark.copy(alpha = 0.2f)
            )
        ) {
            androidx.compose.foundation.layout.Box(
                modifier = Modifier.fillMaxSize(),
                contentAlignment = androidx.compose.ui.Alignment.Center
            ) {
                androidx.compose.material3.Text(
                    text = "Carte non disponible",
                    color = androidx.compose.ui.graphics.Color.White.copy(alpha = 0.7f)
                )
            }
        }
    }
}

@Composable
fun MapNavigationCard(
    modifier: Modifier = Modifier,
    courierLocation: LatLng?,
    pickup: LatLng?,
    dropoff: LatLng?,
    path: List<LatLng> = emptyList(),
    onStartNavigation: (LatLng) -> Unit
) {
    val context = LocalContext.current
    val locationPermissionGranted = hasLocationPermission(context)
    Column(modifier) {
        GoogleMap(
            modifier = Modifier
                .fillMaxWidth()
                .height(200.dp)
                .clip(RoundedCornerShape(12.dp)),
            properties = MapProperties(isMyLocationEnabled = false),
            uiSettings = MapUiSettings(
                zoomControlsEnabled = false,
                mapToolbarEnabled = false,
                myLocationButtonEnabled = locationPermissionGranted
            )
        ) {
            courierLocation?.let { Marker(state = MarkerState(it), title = "Vous") }
            pickup?.let { Marker(state = MarkerState(it), title = "Pickup") }
            dropoff?.let { Marker(state = MarkerState(it), title = "Livraison") }
            if (path.isNotEmpty()) {
                Polyline(points = path, color = Color(0xFF0B57D0), width = 10f)
            }

            MapEffect(locationPermissionGranted) { googleMap ->
                try {
                    googleMap.isMyLocationEnabled = locationPermissionGranted
                } catch (_: SecurityException) {
                    googleMap.isMyLocationEnabled = false
                }
            }
        }
        Spacer(Modifier.height(8.dp))
        val dest = dropoff ?: pickup
        Button(
            onClick = { dest?.let { onStartNavigation(it) } },
            enabled = dest != null,
            modifier = Modifier.fillMaxWidth(),
            colors = ButtonDefaults.buttonColors(containerColor = PrimaryGold)
        ) {
            Text("Démarrer la navigation", color = PrimaryDark)
        }
    }
}

// FONCTION SUPPRIMÉE - La navigation reste dans l'application
// Plus de redirection vers Google Maps externe

private fun hasLocationPermission(context: Context): Boolean {
    val fineGranted = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
    val coarseGranted = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
    return fineGranted || coarseGranted
}