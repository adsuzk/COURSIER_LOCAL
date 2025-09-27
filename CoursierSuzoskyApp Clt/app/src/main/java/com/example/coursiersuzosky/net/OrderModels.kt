package com.example.coursiersuzosky.net

data class OrderRequest(
    val departure: String,
    val destination: String,
    val senderPhone: String,
    val receiverPhone: String,
    val packageDescription: String?,
    val priority: String, // normale | urgente | express
    val paymentMethod: String, // cash | orange_money | mtn_money | moov_money | card | wave
    val price: Double,
    val distance: String? = null,
    val duration: String? = null,
    val departure_lat: Double? = null,
    val departure_lng: Double? = null
)

data class SubmitOrderResponse(
    val success: Boolean,
    val message: String? = null,
    val data: OrderData? = null
)

data class OrderData(
    val order_id: Long?,
    val order_number: String?,
    val code_commande: String?,
    val price: Double?,
    val payment_method: String?,
    val payment_url: String? = null,
    val transaction_id: String? = null
)
