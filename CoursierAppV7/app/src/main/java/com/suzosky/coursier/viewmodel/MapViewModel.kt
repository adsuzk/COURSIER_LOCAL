package com.suzosky.coursier.viewmodel

import android.location.Location
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.android.gms.maps.model.LatLng
import com.google.android.libraries.places.api.model.AutocompletePrediction
import com.suzosky.coursier.services.LocationService
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * ViewModel pour la gestion des cartes et de la géolocalisation
 */
@HiltViewModel
class MapViewModel @Inject constructor(
    private val locationService: LocationService
) : ViewModel() {

    data class MapUiState(
        val currentLocation: LatLng? = null,
        val isLocationLoading: Boolean = false,
        val searchResults: List<AutocompletePrediction> = emptyList(),
        val isSearching: Boolean = false,
        val errorMessage: String? = null
    )

    private val _uiState = MutableStateFlow(MapUiState())
    val uiState: StateFlow<MapUiState> = _uiState.asStateFlow()

    init {
        getCurrentLocation()
    }

    /**
     * Obtient la position actuelle du coursier
     */
    fun getCurrentLocation() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLocationLoading = true)
            
            try {
                val location = locationService.getCurrentLocation()
                location?.let {
                    _uiState.value = _uiState.value.copy(
                        currentLocation = LatLng(it.latitude, it.longitude),
                        isLocationLoading = false,
                        errorMessage = null
                    )
                } ?: run {
                    _uiState.value = _uiState.value.copy(
                        isLocationLoading = false,
                        errorMessage = "Impossible d'obtenir la position"
                    )
                }
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    isLocationLoading = false,
                    errorMessage = "Erreur de géolocalisation: ${e.message}"
                )
            }
        }
    }

    /**
     * Recherche d'adresses avec autocomplétion
     */
    fun searchPlaces(query: String) {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isSearching = true)
            
            try {
                val results = locationService.searchPlaces(query)
                _uiState.value = _uiState.value.copy(
                    searchResults = results,
                    isSearching = false,
                    errorMessage = null
                )
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    searchResults = emptyList(),
                    isSearching = false,
                    errorMessage = "Erreur de recherche: ${e.message}"
                )
            }
        }
    }

    /**
     * Démarre le suivi de position en temps réel
     */
    fun startLocationTracking() {
        viewModelScope.launch {
            locationService.startLocationUpdates { location ->
                _uiState.value = _uiState.value.copy(
                    currentLocation = LatLng(location.latitude, location.longitude)
                )
            }
        }
    }

    /**
     * Calcule la distance entre deux points
     */
    fun calculateDistance(start: LatLng, end: LatLng): Float {
        return locationService.calculateDistance(start, end)
    }

    /**
     * Efface les messages d'erreur
     */
    fun clearError() {
        _uiState.value = _uiState.value.copy(errorMessage = null)
    }
}