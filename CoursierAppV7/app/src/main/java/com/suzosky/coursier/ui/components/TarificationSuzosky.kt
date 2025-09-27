package com.suzosky.coursier.ui.components

import com.suzosky.coursier.data.models.Commande

object TarificationSuzosky {
    fun calculerGainsCoursier(commandes: List<Commande>): Int = commandes.size * 1000
}
