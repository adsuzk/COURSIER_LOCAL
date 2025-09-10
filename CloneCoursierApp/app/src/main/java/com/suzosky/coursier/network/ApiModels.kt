package com.suzosky.coursier.network

import kotlinx.serialization.Serializable

@Serializable
data class CommandeApi(
    val id: String,
    val clientNom: String? = null,
    val clientTelephone: String? = null,
    val adresseEnlevement: String? = null,
    val adresseLivraison: String? = null,
    val distance: Double? = null,
    val tempsEstime: Int? = null,
    val prixTotal: Double? = null,
    val prixLivraison: Double? = null,
    val statut: String? = null,
    val dateCommande: String? = null,
    val heureCommande: String? = null,
    val description: String? = null,
    val typeCommande: String? = null
)
