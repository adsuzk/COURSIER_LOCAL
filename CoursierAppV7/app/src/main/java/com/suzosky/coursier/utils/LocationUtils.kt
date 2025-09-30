package com.suzosky.coursier.utils

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.os.Looper
import androidx.core.app.ActivityCompat
import com.google.android.gms.location.*
import com.suzosky.coursier.network.ApiService
import android.util.Log

object LocationUtils {
    private var fusedLocationClient: FusedLocationProviderClient? = null
    private var locationCallback: LocationCallback? = null

    fun startLocationUpdates(context: Context, onLocation: (Location) -> Unit) {
        if (fusedLocationClient == null) {
            fusedLocationClient = LocationServices.getFusedLocationProviderClient(context)
        }
        if (ActivityCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED &&
            ActivityCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
            // Permission non accordée
            return
        }
        val locationRequest = LocationRequest.Builder(Priority.PRIORITY_HIGH_ACCURACY, 10000L)
            .setMinUpdateIntervalMillis(5000L)
            .build()

        locationCallback = object : LocationCallback() {
            override fun onLocationResult(result: LocationResult) {
                result.lastLocation?.let { loc ->
                    try {
                        onLocation(loc)

                        // Envoi automatique de la position au serveur si coursier_id présent
                        try {
                            val prefs = context.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
                            val coursierId = prefs.getInt("coursier_id", -1)
                            if (coursierId > 0) {
                                // ApiService gère l'envoi asynchrone et les fallback bases
                                val _posCb: (Boolean, String?) -> Unit = { ok, err ->
                                    if (!ok) {
                                        Log.w("LocationUtils", "updateCoursierPosition failed: $err")
                                    }
                                }
                                ApiService.updateCoursierPosition(coursierId, loc.latitude, loc.longitude, _posCb)
                            }
                        } catch (e: Exception) {
                            Log.w("LocationUtils", "Impossible d'envoyer position: ${e.message}")
                        }
                    } catch (e: Exception) {
                        // Garder le callback principal robuste
                        Log.w("LocationUtils", "Erreur handling location: ${e.message}")
                    }
                }
            }
        }

        fusedLocationClient?.requestLocationUpdates(locationRequest, locationCallback!!, Looper.getMainLooper())
    }

    fun stopLocationUpdates(context: Context) {
        fusedLocationClient?.removeLocationUpdates(locationCallback!!)
    }
}
