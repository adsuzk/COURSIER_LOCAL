package com.suzosky.coursierclient.net

object ApiConfig {
    // Base URL injectée par BuildConfig selon le type de build
    val BASE_URL: String = com.suzosky.coursierclient.BuildConfig.BASE_URL
    const val AUTH = "auth.php"
    const val AGENT_AUTH = "agent_auth.php"
    const val SUBMIT_ORDER = "submit_order.php"
    const val DISTANCE_TEST = "estimate_price.php" // estimation alignée admin finances
    const val APP_UPDATES = "app_updates.php" // endpoint de vérification mise à jour
    const val INITIATE_PAYMENT_ONLY = "initiate_payment_only.php"
    const val CREATE_ORDER_AFTER_PAYMENT = "create_order_after_payment.php"
    const val COURIER_AVAILABILITY = "api/get_coursier_availability.php"
    // Client/Profile endpoints
    const val GET_CLIENT = "get_client.php"
    const val GET_CLIENT_ORDERS = "get_client_orders.php"
    const val SAVED_ADDRESSES = "saved_addresses.php"
}
