package com.suzosky.coursier.services

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import androidx.core.app.ActivityCompat
import com.google.android.gms.location.*
import com.google.android.gms.maps.model.LatLng
import com.google.android.gms.tasks.Tasks
import com.google.android.libraries.places.api.Places
import com.google.android.libraries.places.api.model.AutocompletePrediction
import com.google.android.libraries.places.api.model.AutocompleteSessionToken
import com.google.android.libraries.places.api.net.FindAutocompletePredictionsRequest
import com.google.android.libraries.places.api.net.PlacesClient
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.suspendCancellableCoroutine
import javax.inject.Inject
import javax.inject.Singleton
import kotlin.coroutines.resume

/**
 * Service de géolocalisation et intégration Google Places API
 * Utilise les APIs Google Location Services et Places API
 */
@Singleton
class LocationService @Inject constructor(
    @ApplicationContext private val context: Context
) {
    private val fusedLocationClient: FusedLocationProviderClient by lazy {
        LocationServices.getFusedLocationProviderClient(context)
    }

    private val placesClient: PlacesClient by lazy {
        Places.createClient(context)
    }

    private val locationRequest = LocationRequest.Builder(
        Priority.PRIORITY_HIGH_ACCURACY,
        10000 // 10 seconds
    ).build()

    /**
     * Obtient la position actuelle du coursier
     */
    suspend fun getCurrentLocation(): Location? {
        if (ActivityCompat.checkSelfPermission(
                context,
                Manifest.permission.ACCESS_FINE_LOCATION
            ) != PackageManager.PERMISSION_GRANTED
        ) {
            return null
        }
        return try {
            // One-shot high-accuracy location request
            val cts = com.google.android.gms.tasks.CancellationTokenSource()
            Tasks.await(
                fusedLocationClient.getCurrentLocation(
                    Priority.PRIORITY_HIGH_ACCURACY,
                    cts.token
                )
            )
        } catch (e: Exception) {
            null
        }
    }

    /**
     * Recherche d'adresses avec autocomplétion Google Places
     */
    suspend fun searchPlaces(query: String): List<AutocompletePrediction> {
        if (query.isBlank()) return emptyList()

        return try {
            val token = AutocompleteSessionToken.newInstance()
            val request = FindAutocompletePredictionsRequest.builder()
                .setQuery(query)
                .setSessionToken(token)
                .build()

            val response = Tasks.await(placesClient.findAutocompletePredictions(request))
            response.autocompletePredictions
        } catch (e: Exception) {
            emptyList()
        }
    }

    /**
     * Calcule la distance entre deux points géographiques
     */
    fun calculateDistance(start: LatLng, end: LatLng): Float {
        val results = FloatArray(1)
        Location.distanceBetween(
            start.latitude, start.longitude,
            end.latitude, end.longitude,
            results
        )
        return results[0]
    }

    /**
     * Démarre le suivi de position en temps réel
     */
    suspend fun startLocationUpdates(
        callback: (Location) -> Unit
    ): Boolean = suspendCancellableCoroutine { continuation ->
        
        if (ActivityCompat.checkSelfPermission(
                context,
                Manifest.permission.ACCESS_FINE_LOCATION
            ) != PackageManager.PERMISSION_GRANTED
        ) {
            continuation.resume(false)
            return@suspendCancellableCoroutine
        }

        val locationCallback = object : LocationCallback() {
            override fun onLocationResult(locationResult: LocationResult) {
                locationResult.lastLocation?.let { callback(it) }
            }
        }

        try {
            fusedLocationClient.requestLocationUpdates(
                locationRequest,
                locationCallback,
                context.mainLooper
            )
            
            continuation.invokeOnCancellation {
                fusedLocationClient.removeLocationUpdates(locationCallback)
            }
            
            continuation.resume(true)
        } catch (e: Exception) {
            continuation.resume(false)
        }
    }
}