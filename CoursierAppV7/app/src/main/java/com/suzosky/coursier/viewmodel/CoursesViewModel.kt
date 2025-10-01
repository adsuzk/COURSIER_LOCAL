package com.suzosky.coursier.viewmodel

import android.content.Context
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.google.android.gms.maps.model.LatLng
import com.suzosky.coursier.data.models.Commande
import com.suzosky.coursier.network.ApiService
import com.suzosky.coursier.ui.screens.CourseStep
import com.suzosky.coursier.utils.CourseLocationUtils
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class CoursesUiState(
    val pendingOrders: List<Commande> = emptyList(),
    val activeOrder: Commande? = null,
    val currentStep: CourseStep = CourseStep.WAITING_ACCEPTANCE,
    val courierLocation: LatLng? = null,
    val canValidateCurrentStep: Boolean = false,
    val distanceToDestination: Double? = null,
    val isLoading: Boolean = false,
    val error: String? = null,
    val navigationLaunched: Boolean = false
)

@HiltViewModel
class CoursesViewModel @Inject constructor(
    @ApplicationContext private val context: Context
) : ViewModel() {
    
    private val _uiState = MutableStateFlow(CoursesUiState())
    val uiState: StateFlow<CoursesUiState> = _uiState.asStateFlow()
    
    private var locationUpdateJob: Job? = null
    private var ordersPollingJob: Job? = null
    
    init {
        startOrdersPolling()
    }
    
    /**
     * Récupère l'ID du coursier connecté depuis SharedPreferences
     */
    private fun getUserId(): Int? {
        val prefs = context.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
        val coursierId = prefs.getInt("coursier_id", -1)
        return if (coursierId > 0) coursierId else null
    }
    
    /**
     * Démarre le polling des nouvelles commandes
     */
    private fun startOrdersPolling() {
        ordersPollingJob?.cancel()
        ordersPollingJob = viewModelScope.launch {
            while (true) {
                if (_uiState.value.activeOrder == null) {
                    loadPendingOrders()
                }
                delay(5000) // Vérifier toutes les 5 secondes
            }
        }
    }
    
    /**
     * Charge les commandes en attente
     */
    private suspend fun loadPendingOrders() {
        try {
            _uiState.value = _uiState.value.copy(isLoading = true, error = null)
            
            // Récupérer l'ID du coursier connecté
            val coursierId = getUserId() // Fonction à implémenter selon votre système d'auth
            
            if (coursierId != null) {
                ApiService.getCoursierOrders(
                    coursierId = coursierId,
                    status = "all", // Récupérer toutes les commandes pour filtrer localement
                    limit = 20
                ) { result, error ->
                    if (error != null) {
                        _uiState.value = _uiState.value.copy(
                            isLoading = false,
                            error = "Erreur API: $error"
                        )
                    } else if (result != null) {
                        val commandes = result["commandes"] as? List<Map<String, Any>> ?: emptyList()
                        
                        // Filtrer les commandes en attente (nouvelles ou assignées)
                        val pendingCommandes = commandes.filter { commande ->
                            val statut = commande["statut"] as? String ?: ""
                            val statutRaw = commande["statut_raw"] as? String ?: ""
                            
                            // Considérer comme "pending" les commandes assignées qui n'ont pas encore été acceptées
                            statutRaw in listOf("assignee", "attribuee", "nouvelle", "en_attente")
                        }.map { commande ->
                            Commande(
                                id = commande["id"] as? String ?: "",
                                clientNom = commande["clientNom"] as? String ?: "",
                                clientTelephone = commande["clientTelephone"] as? String ?: "",
                                adresseEnlevement = commande["adresseEnlevement"] as? String ?: "",
                                adresseLivraison = commande["adresseLivraison"] as? String ?: "",
                                distanceKm = (commande["distanceKm"] as? Double) ?: 0.0,
                                prix = (commande["prix"] as? Double) ?: 0.0,
                                statut = commande["statut"] as? String ?: "",
                                dateCommande = commande["dateCommande"] as? String ?: "",
                                heureCommande = commande["heureCommande"] as? String ?: ""
                            )
                        }
                        
                        _uiState.value = _uiState.value.copy(
                            pendingOrders = pendingCommandes,
                            isLoading = false
                        )
                    } else {
                        _uiState.value = _uiState.value.copy(
                            pendingOrders = emptyList(),
                            isLoading = false
                        )
                    }
                }
            } else {
                _uiState.value = _uiState.value.copy(
                    isLoading = false,
                    error = "Coursier non connecté"
                )
            }
            
        } catch (e: Exception) {
            _uiState.value = _uiState.value.copy(
                isLoading = false,
                error = "Erreur lors du chargement des commandes: ${e.message}"
            )
        }
    }
    
    /**
     * Accepte une commande
     */
    fun acceptOrder(orderId: String) {
        viewModelScope.launch {
            try {
                val orderToAccept = _uiState.value.pendingOrders.find { it.id == orderId }
                if (orderToAccept != null) {
                    
                    // Appel API pour accepter la commande
                    ApiService.updateOrderStatus(orderId, "acceptee") { success ->
                        if (success) {
                            val updatedPendingOrders = _uiState.value.pendingOrders.filter { it.id != orderId }
                            
                            _uiState.value = _uiState.value.copy(
                                activeOrder = orderToAccept,
                                currentStep = CourseStep.GOING_TO_PICKUP,
                                pendingOrders = updatedPendingOrders,
                                navigationLaunched = false
                            )
                            
                            startLocationMonitoring()
                        }
                    }
                }
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    error = "Erreur lors de l'acceptation: ${e.message}"
                )
            }
        }
    }
    
    /**
     * Refuse une commande
     */
    fun rejectOrder(orderId: String) {
        viewModelScope.launch {
            try {
                // Appel API pour refuser la commande si nécessaire
                val updatedPendingOrders = _uiState.value.pendingOrders.filter { it.id != orderId }
                _uiState.value = _uiState.value.copy(pendingOrders = updatedPendingOrders)
                
            } catch (e: Exception) {
                _uiState.value = _uiState.value.copy(
                    error = "Erreur lors du refus: ${e.message}"
                )
            }
        }
    }
    
    /**
     * Valide l'étape actuelle et passe à la suivante
     */
    fun validateCurrentStep() {
        viewModelScope.launch {
            val currentState = _uiState.value
            val activeOrder = currentState.activeOrder ?: return@launch
            
            when (currentState.currentStep) {
                CourseStep.ARRIVED_PICKUP -> {
                    // Valider récupération
                    ApiService.updateOrderStatus(activeOrder.id, "picked_up") { success ->
                        if (success) {
                            _uiState.value = _uiState.value.copy(
                                currentStep = CourseStep.GOING_TO_DELIVERY,
                                navigationLaunched = false
                            )
                        }
                    }
                }
                
                CourseStep.ARRIVED_DELIVERY -> {
                    // Valider livraison
                    val newStatus = if (activeOrder.methodePaiement.equals("especes", ignoreCase = true)) {
                        "livree_cash_pending"
                    } else {
                        "livree"
                    }
                    
                    ApiService.updateOrderStatus(activeOrder.id, newStatus) { success ->
                        if (success) {
                            completeCurrentOrder()
                        }
                    }
                }
                
                else -> {
                    // Pas de validation nécessaire pour les autres étapes
                }
            }
        }
    }
    
    /**
     * Termine la commande actuelle et active la suivante
     */
    private fun completeCurrentOrder() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                currentStep = CourseStep.COMPLETED
            )
            
            // Attendre un peu pour montrer le statut "Terminée"
            delay(2000)
            
            // Passer à la commande suivante ou revenir en attente
            val nextOrder = _uiState.value.pendingOrders.firstOrNull()
            
            if (nextOrder != null) {
                // Activer automatiquement la commande suivante
                acceptOrder(nextOrder.id)
            } else {
                // Revenir en mode attente
                _uiState.value = _uiState.value.copy(
                    activeOrder = null,
                    currentStep = CourseStep.WAITING_ACCEPTANCE,
                    canValidateCurrentStep = false,
                    distanceToDestination = null
                )
                stopLocationMonitoring()
            }
        }
    }
    
    /**
     * Met à jour la position du coursier
     */
    fun updateCourierLocation(location: LatLng) {
        _uiState.value = _uiState.value.copy(courierLocation = location)
        checkStepValidation()
    }
    
    /**
     * Vérifie si on peut valider l'étape actuelle selon la position
     */
    private fun checkStepValidation() {
        val currentState = _uiState.value
        val activeOrder = currentState.activeOrder
        val courierLocation = currentState.courierLocation
        
        if (activeOrder == null || courierLocation == null) {
            return
        }
        
        val targetLocation = when (currentState.currentStep) {
            CourseStep.GOING_TO_PICKUP -> {
                activeOrder.coordonneesEnlevement?.let { 
                    LatLng(it.latitude, it.longitude) 
                }
            }
            CourseStep.GOING_TO_DELIVERY -> {
                activeOrder.coordonneesLivraison?.let { 
                    LatLng(it.latitude, it.longitude) 
                }
            }
            else -> null
        }
        
        if (targetLocation != null) {
            val canValidate = CourseLocationUtils.canValidateStep(courierLocation, targetLocation)
            val distance = CourseLocationUtils.getDistanceToDestination(courierLocation, targetLocation)
            
            _uiState.value = _uiState.value.copy(
                canValidateCurrentStep = canValidate,
                distanceToDestination = distance
            )
            
            // Auto-transition quand on arrive à destination
            if (canValidate) {
                when (currentState.currentStep) {
                    CourseStep.GOING_TO_PICKUP -> {
                        _uiState.value = _uiState.value.copy(
                            currentStep = CourseStep.ARRIVED_PICKUP
                        )
                    }
                    CourseStep.GOING_TO_DELIVERY -> {
                        _uiState.value = _uiState.value.copy(
                            currentStep = CourseStep.ARRIVED_DELIVERY
                        )
                    }
                    else -> {}
                }
            }
        }
    }
    
    /**
     * Démarre le monitoring de localisation
     */
    private fun startLocationMonitoring() {
        locationUpdateJob?.cancel()
        locationUpdateJob = viewModelScope.launch {
            while (_uiState.value.activeOrder != null) {
                // Ici vous devriez obtenir la position actuelle
                // Pour l'instant, on simule
                delay(3000) // Vérifier toutes les 3 secondes
                checkStepValidation()
            }
        }
    }
    
    /**
     * Arrête le monitoring de localisation
     */
    private fun stopLocationMonitoring() {
        locationUpdateJob?.cancel()
        locationUpdateJob = null
    }
    
    /**
     * Marque la navigation comme lancée
     */
    fun markNavigationLaunched() {
        _uiState.value = _uiState.value.copy(navigationLaunched = true)
    }
    
    /**
     * Efface les erreurs
     */
    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
    
    override fun onCleared() {
        super.onCleared()
        ordersPollingJob?.cancel()
        locationUpdateJob?.cancel()
    }
}