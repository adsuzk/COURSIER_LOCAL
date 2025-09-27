package com.suzosky.coursier.ui.components

data class CommandeData(
    val id: String,
    val statut: String,
    val typeCommande: String,
    val nomClient: String,
    val telephone: String,
    val adresseRecuperation: String,
    val adresseLivraison: String,
    val instructions: String,
    val distanceKm: Float,
    val minutesAttente: Int,
    val heureCreation: String
)
