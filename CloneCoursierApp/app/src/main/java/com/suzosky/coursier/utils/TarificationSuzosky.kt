package com.suzosky.coursier.utils

import kotlin.math.max
import kotlin.math.round

/**
 * Module de calcul de tarifs Suzosky Coursier
 * Règles de tarification :
 * - Base fixe : 300 FCFA
 * - Prix par kilomètre : 200 FCFA/km
 * - Minimum garanti : 800 FCFA
 * - Frais d'attente : 50 FCFA/minute
 */
object TarificationSuzosky {

    /**
     * Calcule le total des gains du coursier sur une liste de commandes
     * @param commandes Liste de commandes (doit avoir un champ montant ou tarifFinalFCFA)
     * @return Somme totale des gains (Int)
     */
    fun calculerGainsCoursier(commandes: List<Any>): Int {
        // On tente de lire un champ "tarifFinalFCFA" ou "montant" sur chaque commande
        return commandes.sumOf {
            when (it) {
                is Map<*, *> -> (it["tarifFinalFCFA"] as? Int) ?: (it["montant"] as? Int) ?: 0
                else -> {
                    try {
                        val prop = it!!::class.members.firstOrNull { m -> m.name == "tarifFinalFCFA" }?.call(it) as? Int
                        prop ?: 0
                    } catch (e: Exception) { 0 }
                }
            }
        }
    }
    
    private const val BASE_FIXE = 300.0
    private const val PRIX_PAR_KM = 200.0
    private const val MINIMUM_GARANTI = 800.0
    private const val FRAIS_ATTENTE_PAR_MINUTE = 50.0
    
    /**
     * Calcule le tarif d'une livraison
     * @param distanceKm Distance en kilomètres (arrondie à 1 décimale)
     * @param minutesAttente Minutes d'attente chez le client (défaut: 0)
     * @return TarificationResult contenant le détail du calcul
     */
    fun calculerTarif(distanceKm: Float, minutesAttente: Int = 0): TarificationResult {
        // Arrondir la distance à 1 décimale
        val distanceArrondie = round(distanceKm * 10) / 10
        
        // Calcul de base
        val tarifBase = BASE_FIXE + (distanceArrondie * PRIX_PAR_KM)
        
        // Frais d'attente
        val fraisAttente = minutesAttente * FRAIS_ATTENTE_PAR_MINUTE
        
        // Total avant minimum
        val totalAvantMinimum = tarifBase + fraisAttente
        
        // Application du minimum garanti
        val tarifFinal = max(totalAvantMinimum, MINIMUM_GARANTI)
        
        return TarificationResult(
            distanceKm = distanceArrondie,
            minutesAttente = minutesAttente,
            baseFCFA = BASE_FIXE.toInt(),
            distanceFCFA = (distanceArrondie * PRIX_PAR_KM).toInt(),
            attenteFCFA = fraisAttente.toInt(),
            sousTotal = totalAvantMinimum.toInt(),
            minimumGaranti = MINIMUM_GARANTI.toInt(),
            tarifFinalFCFA = tarifFinal.toInt(),
            minutesEstimees = calculerDureeEstimee(distanceArrondie)
        )
    }
    
    /**
     * Calcule la durée estimée basée sur la distance
     * Vitesse moyenne estimée : 25 km/h en ville
     */
    private fun calculerDureeEstimee(distanceKm: Float): Int {
        val vitesseMoyenne = 25.0 // km/h
        val heures = distanceKm / vitesseMoyenne
        return (heures * 60).toInt() // Conversion en minutes
    }
    
    /**
     * Formatage pour l'affichage
     */
    fun formaterTarif(tarif: Int): String = "${tarif} FCFA"
    
    fun formaterDistance(distance: Float): String = "${distance} km"
    
    fun formaterDuree(minutes: Int): String {
        return if (minutes < 60) {
            "$minutes min"
        } else {
            val heures = minutes / 60
            val minutesRestantes = minutes % 60
            "${heures}h${minutesRestantes.toString().padStart(2, '0')}"
        }
    }
}

/**
 * Résultat du calcul de tarification
 */
data class TarificationResult(
    val distanceKm: Float,
    val minutesAttente: Int,
    val baseFCFA: Int,
    val distanceFCFA: Int,
    val attenteFCFA: Int,
    val sousTotal: Int,
    val minimumGaranti: Int,
    val tarifFinalFCFA: Int,
    val minutesEstimees: Int
) {
    
    /**
     * Génère un résumé textuel du calcul
     */
    fun genererResume(): String {
        return buildString {
            appendLine("💰 Prix estimé: ${TarificationSuzosky.formaterTarif(tarifFinalFCFA)}")
            appendLine("📏 Distance: ${TarificationSuzosky.formaterDistance(distanceKm)}")
            appendLine("⏱️ Durée estimée: ${TarificationSuzosky.formaterDuree(minutesEstimees)}")
            if (minutesAttente > 0) {
                appendLine("⏳ Attente: ${minutesAttente} min (+${TarificationSuzosky.formaterTarif(attenteFCFA)})")
            }
        }
    }
    
    /**
     * Détail complet pour l'affichage admin
     */
    fun genererDetailComplet(): String {
        return buildString {
            appendLine("📊 DÉTAIL DU CALCUL")
            appendLine("─────────────────────")
            appendLine("Base fixe: ${TarificationSuzosky.formaterTarif(baseFCFA)}")
            appendLine("Distance (${distanceKm}km): ${TarificationSuzosky.formaterTarif(distanceFCFA)}")
            if (minutesAttente > 0) {
                appendLine("Attente (${minutesAttente}min): ${TarificationSuzosky.formaterTarif(attenteFCFA)}")
            }
            appendLine("─────────────────────")
            appendLine("Sous-total: ${TarificationSuzosky.formaterTarif(sousTotal)}")
            if (sousTotal < minimumGaranti) {
                appendLine("Minimum garanti: ${TarificationSuzosky.formaterTarif(minimumGaranti)}")
            }
            appendLine("TOTAL: ${TarificationSuzosky.formaterTarif(tarifFinalFCFA)}")
        }
    }
}

/**
 * Extensions pour les calculs administratifs
 */
object TarificationAdmin {
    
    /**
     * Calcule la commission Suzosky (10% du montant final)
     */
    fun calculerCommissionSuzosky(tarifFinal: Int): Int {
        return (tarifFinal * 0.10).toInt()
    }
    
    /**
     * Calcule ce que reçoit le coursier (90% du montant final)
     */
    fun calculerRemunerationCoursier(tarifFinal: Int): Int {
        return tarifFinal - calculerCommissionSuzosky(tarifFinal)
    }
    
    /**
     * Calcule les revenus Suzosky pour un rechargement de wallet
     * Frais de service sur rechargement : 2%
     */
    fun calculerRevenusRechargement(montantRechargement: Int): Int {
        return (montantRechargement * 0.02).toInt()
    }
    
    /**
     * Génère un rapport financier pour une course
     */
    fun genererRapportFinancier(tarifFinal: Int, rechargementUtilise: Int = 0): RapportFinancier {
        val commissionCourse = calculerCommissionSuzosky(tarifFinal)
        val remunerationCoursier = calculerRemunerationCoursier(tarifFinal)
        val revenus = commissionCourse + calculerRevenusRechargement(rechargementUtilise)
        
        return RapportFinancier(
            tarifTotal = tarifFinal,
            commissionSuzosky = commissionCourse,
            remunerationCoursier = remunerationCoursier,
            rechargementUtilise = rechargementUtilise,
            revenusRechargement = calculerRevenusRechargement(rechargementUtilise),
            revenusTotauxSuzosky = revenus
        )
    }
}

/**
 * Rapport financier détaillé
 */
data class RapportFinancier(
    val tarifTotal: Int,
    val commissionSuzosky: Int,
    val remunerationCoursier: Int,
    val rechargementUtilise: Int,
    val revenusRechargement: Int,
    val revenusTotauxSuzosky: Int
) {
    fun genererResume(): String {
        return buildString {
            appendLine("💸 RAPPORT FINANCIER")
            appendLine("═══════════════════")
            appendLine("Tarif total: ${TarificationSuzosky.formaterTarif(tarifTotal)}")
            appendLine("Commission Suzosky (10%): ${TarificationSuzosky.formaterTarif(commissionSuzosky)}")
            appendLine("Rémunération coursier (90%): ${TarificationSuzosky.formaterTarif(remunerationCoursier)}")
            if (rechargementUtilise > 0) {
                appendLine("Rechargement utilisé: ${TarificationSuzosky.formaterTarif(rechargementUtilise)}")
                appendLine("Revenus rechargement (2%): ${TarificationSuzosky.formaterTarif(revenusRechargement)}")
            }
            appendLine("═══════════════════")
            appendLine("REVENUS TOTAUX SUZOSKY: ${TarificationSuzosky.formaterTarif(revenusTotauxSuzosky)}")
        }
    }
}
