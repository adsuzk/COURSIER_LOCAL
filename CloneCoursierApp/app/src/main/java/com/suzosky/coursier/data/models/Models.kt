package com.suzosky.coursier.data.models

/**
 * Modèles de données identiques à la base de données web
 * Structure 100% conforme aux tables PHP
 */

/**
 * Modèle Commande - identique à la table MySQL
 */
data class Commande(
    val id: String,
    val clientNom: String,
    val clientTelephone: String,
    val adresseEnlevement: String,
    val adresseLivraison: String,
    val distance: Double, // en kilomètres
    val tempsEstime: Int, // en minutes
    val prixTotal: Double,
    val prixLivraison: Double,
    val statut: String, // nouvelle, attente, acceptee, en_cours, livree, annulee
    val dateCommande: String,
    val heureCommande: String,
    val description: String = "",
    val instructions: String = "",
    val typeCommande: String = "standard", // standard, express, programmee
    val methodePaiement: String = "especes", // especes, carte, mobile
    val pourboire: Double = 0.0,
    val coordonneesEnlevement: Coordonnees? = null,
    val coordonneesLivraison: Coordonnees? = null,
    val coursierId: String? = null,
    val coursierNom: String? = null,
    val heureAcceptation: String? = null,
    val heureDebutLivraison: String? = null,
    val heureLivraison: String? = null,
    val tempsAttente: Int = 0, // en minutes
    val fraisAttente: Double = 0.0,
    val commentaireClient: String = "",
    val noteClient: Int = 0, // 1-5 étoiles
    val photosLivraison: List<String> = emptyList()
)

/**
 * Coordonnées GPS
 */
data class Coordonnees(
    val latitude: Double,
    val longitude: Double
)

/**
 * Modèle Coursier
 */
data class Coursier(
    val id: String,
    val nom: String,
    val prenom: String,
    val telephone: String,
    val email: String,
    val statut: String, // EN_LIGNE, HORS_LIGNE, OCCUPE, PAUSE
    val position: Coordonnees? = null,
    val moyenTransport: String = "velo", // velo, moto, voiture, pied
    val dateInscription: String,
    val noteGlobale: Double = 0.0,
    val nombreLivraisons: Int = 0,
    val gainsTotal: Double = 0.0,
    val gainsMois: Double = 0.0,
    val gainsJour: Double = 0.0,
    val photoProfile: String? = null,
    val numeroCni: String? = null,
    val adresse: String = "",
    val dateNaissance: String = "",
    val sexe: String = "",
    val situationMatrimoniale: String = "",
    val niveauEtude: String = "",
    val experience: String = "",
    val disponibilite: List<String> = emptyList(), // jours de la semaine
    val horaireDebut: String = "08:00",
    val horaireFin: String = "20:00",
    val zoneCouverture: String = "",
    val languesParles: List<String> = listOf("Français"),
    val competencesSpeciales: List<String> = emptyList(),
    val certifie: Boolean = false,
    val actif: Boolean = true
)

/**
 * Modèle Transaction/Paiement
 */
data class Transaction(
    val id: String,
    val commandeId: String,
    val coursierId: String,
    val montant: Double,
    val commission: Double,
    val montantCoursier: Double,
    val type: String, // livraison, pourboire, bonus, penalite
    val statut: String, // en_attente, valide, verse, annule
    val dateTransaction: String,
    val methodePaiement: String,
    val referenceTransaction: String? = null,
    val commentaire: String = ""
)

/**
 * Modèle Statistiques Coursier
 */
data class StatistiquesCoursier(
    val coursierId: String,
    val periode: String, // jour, semaine, mois, annee
    val nombreCommandes: Int,
    val commandesAcceptees: Int,
    val commandesRefusees: Int,
    val commandesAnnulees: Int,
    val tempsConnexion: Int, // en minutes
    val distanceParcourue: Double, // en km
    val gainsTotal: Double,
    val noteAverage: Double,
    val tempsMoyenLivraison: Int, // en minutes
    val tauxAcceptation: Double, // pourcentage
    val tauxAnnulation: Double, // pourcentage
    val clientsSatisfaits: Int,
    val réclamations: Int
)

/**
 * Modèle Notification
 */
data class NotificationApp(
    val id: String,
    val coursierId: String,
    val titre: String,
    val message: String,
    val type: String, // commande, paiement, system, promo
    val priorite: String, // haute, normale, basse
    val lu: Boolean = false,
    val dateCreation: String,
    val dateExpiration: String? = null,
    val actionRequise: Boolean = false,
    val lienAction: String? = null,
    val donnees: Map<String, Any> = emptyMap()
)

/**
 * Modèle Zone de Livraison
 */
data class ZoneLivraison(
    val id: String,
    val nom: String,
    val description: String,
    val coordonnees: List<Coordonnees>, // polygone de la zone
    val tarifBase: Double,
    val tarifParKm: Double,
    val tempsMoyenLivraison: Int,
    val active: Boolean = true,
    val couleurCarte: String = "#D4A853"
)

/**
 * Modèle Évaluation
 */
data class Evaluation(
    val id: String,
    val commandeId: String,
    val coursierId: String,
    val clientId: String,
    val noteCoursier: Int, // 1-5
    val noteClient: Int, // 1-5
    val commentaireCoursier: String = "",
    val commentaireClient: String = "",
    val criteresCoursier: Map<String, Int> = emptyMap(), // ponctualite, politesse, soin
    val criteresClient: Map<String, Int> = emptyMap(), // paiement, respect, clarteInstructions
    val dateEvaluation: String,
    val recommande: Boolean = false
)

/**
 * Modèle Configuration App
 */
data class ConfigurationApp(
    val tarifBase: Double = 300.0,
    val tarifParKm: Double = 200.0,
    val tarifMinimum: Double = 800.0,
    val tarifAttente: Double = 50.0, // par minute
    val commissionSuzosky: Double = 0.10, // 10%
    val fraisRecharge: Double = 0.02, // 2%
    val distanceMaximale: Double = 50.0, // km
    val tempsAttenteMax: Int = 10, // minutes
    val rayonRecherche: Double = 5.0, // km
    val versionApp: String = "1.0.0",
    val forceUpdate: Boolean = false,
    val maintenance: Boolean = false,
    val messageAccueil: String = "",
    val horairesService: Map<String, String> = mapOf(
        "ouverture" to "06:00",
        "fermeture" to "23:00"
    )
)

/**
 * Extensions utiles pour les modèles
 */

/**
 * Extensions pour Commande
 */
val Commande.estNouvelle: Boolean
    get() = statut == "nouvelle"

val Commande.estEnCours: Boolean
    get() = statut == "en_cours"

val Commande.estTerminee: Boolean
    get() = statut == "livree"

val Commande.peutEtreAcceptee: Boolean
    get() = statut == "nouvelle" || statut == "attente"

fun Commande.calculerTempsEcoule(): Int {
    // Logique de calcul du temps écoulé depuis la création
    return 0 // TODO: implémenter le calcul réel
}

/**
 * Extensions pour Coursier
 */
val Coursier.estDisponible: Boolean
    get() = statut == "EN_LIGNE" && actif

val Coursier.peutAccepterCommande: Boolean
    get() = statut == "EN_LIGNE" && actif

fun Coursier.calculerTauxSucces(): Double {
    return if (nombreLivraisons > 0) {
        (noteGlobale / 5.0) * 100
    } else 0.0
}
