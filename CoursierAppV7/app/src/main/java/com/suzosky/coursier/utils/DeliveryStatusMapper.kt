package com.suzosky.coursier.utils

import com.suzosky.coursier.ui.screens.DeliveryStep

/**
 * Utilitaire pour mapper les étapes de livraison aux statuts serveur
 * et gérer les paramètres supplémentaires pour les appels API
 */
object DeliveryStatusMapper {
    
    /**
     * Mappe une étape de livraison vers le statut attendu par le serveur
     */
    fun mapStepToServerStatus(step: DeliveryStep): String {
        return when (step) {
            DeliveryStep.PENDING -> "nouvelle"
            DeliveryStep.ACCEPTED -> "acceptee"
            DeliveryStep.EN_ROUTE_PICKUP -> "acceptee"  // Même statut que accepté
            DeliveryStep.PICKUP_ARRIVED -> "acceptee"   // Même statut que accepté
            DeliveryStep.PICKED_UP -> "picked_up"       // Statut spécifique pickup
            DeliveryStep.EN_ROUTE_DELIVERY -> "en_cours" // En route vers livraison
            DeliveryStep.DELIVERY_ARRIVED -> "en_cours"  // Même statut qu'en_cours
            DeliveryStep.DELIVERED -> "livree"          // Livré
            DeliveryStep.CASH_CONFIRMED -> "livree"     // Livré avec cash confirmé
        }
    }
    
    /**
     * Vérifie si une étape nécessite une confirmation cash
     */
    fun requiresCashConfirmation(step: DeliveryStep, paymentMethod: String): Boolean {
        return step == DeliveryStep.CASH_CONFIRMED && 
               paymentMethod.equals("especes", ignoreCase = true)
    }
    
    /**
     * Génère les paramètres JSON pour l'appel API selon l'étape
     */
    fun buildApiParameters(
        commandeId: String,
        step: DeliveryStep,
        paymentMethod: String = "",
        cashAmount: Double? = null
    ): String {
        val serverStatus = mapStepToServerStatus(step)
        val parameters = mutableMapOf<String, Any>()
        
        parameters["commande_id"] = commandeId.toIntOrNull() ?: 0
        parameters["statut"] = serverStatus
        
        // Ajouter les paramètres cash si nécessaire
        if (requiresCashConfirmation(step, paymentMethod)) {
            parameters["cash_collected"] = true
            cashAmount?.let {
                parameters["cash_amount"] = it
            }
        }
        
        return com.google.gson.Gson().toJson(parameters)
    }
    
    /**
     * Obtient le message utilisateur approprie pour chaque etape
     */
    fun getSuccessMessage(step: DeliveryStep, paymentMethod: String): String {
        return when (step) {
            DeliveryStep.ACCEPTED -> "Commande acceptee !"
            DeliveryStep.EN_ROUTE_PICKUP -> "En route vers recuperation"
            DeliveryStep.PICKUP_ARRIVED -> "Arrive au point de recuperation"
            DeliveryStep.PICKED_UP -> "Colis recupere ! Direction client"
            DeliveryStep.EN_ROUTE_DELIVERY -> "En route vers livraison"
            DeliveryStep.DELIVERY_ARRIVED -> "Arrive chez le client"
            DeliveryStep.DELIVERED -> {
                if (paymentMethod.equals("especes", ignoreCase = true)) {
                    "Colis livre ! Confirmez le cash"
                } else {
                    "Livraison terminee !"
                }
            }
            DeliveryStep.CASH_CONFIRMED -> "Paiement confirme ! Livraison terminee"
            else -> "Etape mise a jour"
        }
    }
    
    /**
     * Vérifie si une étape nécessite un appel API
     */
    fun requiresApiCall(step: DeliveryStep): Boolean {
        return when (step) {
            DeliveryStep.ACCEPTED,
            DeliveryStep.PICKED_UP,
            DeliveryStep.DELIVERED,
            DeliveryStep.CASH_CONFIRMED -> true
            else -> false
        }
    }
}