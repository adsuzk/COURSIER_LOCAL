package com.suzosky.coursier.utils

import android.location.Location
import com.google.android.gms.maps.model.LatLng
import kotlin.math.*

object CourseLocationUtils {
    
    // Distance minimale pour considérer qu'on est "arrivé" (en mètres)
    private const val ARRIVAL_THRESHOLD_METERS = 100.0
    
    /**
     * Calcule la distance entre deux points GPS en mètres
     */
    fun calculateDistance(from: LatLng, to: LatLng): Double {
        val earthRadius = 6371000.0 // Rayon de la Terre en mètres
        
        val lat1Rad = Math.toRadians(from.latitude)
        val lat2Rad = Math.toRadians(to.latitude)
        val deltaLatRad = Math.toRadians(to.latitude - from.latitude)
        val deltaLngRad = Math.toRadians(to.longitude - from.longitude)
        
        val a = sin(deltaLatRad / 2).pow(2) + 
                cos(lat1Rad) * cos(lat2Rad) * sin(deltaLngRad / 2).pow(2)
        val c = 2 * atan2(sqrt(a), sqrt(1 - a))
        
        return earthRadius * c
    }
    
    /**
     * Vérifie si le coursier est arrivé à destination
     */
    fun isArrivedAtDestination(
        courierLocation: LatLng?,
        destination: LatLng?,
        thresholdMeters: Double = ARRIVAL_THRESHOLD_METERS
    ): Boolean {
        if (courierLocation == null || destination == null) return false
        
        val distance = calculateDistance(courierLocation, destination)
        return distance <= thresholdMeters
    }
    
    /**
     * Formate la distance pour l'affichage
     */
    fun formatDistance(distanceMeters: Double): String {
        return when {
            distanceMeters < 1000 -> "${distanceMeters.toInt()}m"
            else -> "${"%.1f".format(distanceMeters / 1000)}km"
        }
    }
    
    /**
     * Détermine si on peut valider l'étape en cours selon la position
     */
    fun canValidateStep(
        courierLocation: LatLng?,
        targetLocation: LatLng?
    ): Boolean {
        return isArrivedAtDestination(courierLocation, targetLocation)
    }
    
    /**
     * Obtient la distance restante jusqu'à la destination
     */
    fun getDistanceToDestination(
        courierLocation: LatLng?,
        destination: LatLng?
    ): Double? {
        if (courierLocation == null || destination == null) return null
        return calculateDistance(courierLocation, destination)
    }
    
    /**
     * Vérifie si les coordonnées sont valides
     */
    fun areCoordinatesValid(latLng: LatLng?): Boolean {
        return latLng != null && 
               latLng.latitude != 0.0 && 
               latLng.longitude != 0.0 &&
               latLng.latitude >= -90 && latLng.latitude <= 90 &&
               latLng.longitude >= -180 && latLng.longitude <= 180
    }
}