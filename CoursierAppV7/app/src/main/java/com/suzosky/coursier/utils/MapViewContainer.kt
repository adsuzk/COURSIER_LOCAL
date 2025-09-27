package com.suzosky.coursier.utils

import android.content.Context
import android.os.Bundle
import com.google.android.gms.maps.MapView

/**
 * Wrapper for MapView to handle lifecycle events in Compose
 */
class MapViewContainer(context: Context) {
    val mapView: MapView = MapView(context)

    init {
        // Initialize MapView
        mapView.onCreate(null)
    }

    fun onStart() {
        mapView.onStart()
    }

    fun onResume() {
        mapView.onResume()
    }

    fun onPause() {
        mapView.onPause()
    }

    fun onStop() {
        mapView.onStop()
    }

    fun onDestroy() {
        mapView.onDestroy()
    }

    fun onLowMemory() {
        mapView.onLowMemory()
    }

    fun onSaveInstanceState(outState: Bundle) {
        mapView.onSaveInstanceState(outState)
    }
    
    // Ensure the class has a closing brace
}
