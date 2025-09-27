package com.example.coursiersuzosky.net

data class DistanceApiResponse(
    val success: Boolean,
    val distance: DistanceValue?,
    val duration: DistanceValue?,
    val calculations: Map<String, PriceCalc>?
)

data class DistanceValue(val text: String, val value: Long)

data class PriceCalc(
    val name: String,
    val baseFare: Int,
    val perKmRate: Int,
    val distanceKm: Double,
    val distanceCost: Int,
    val totalPrice: Int
)
