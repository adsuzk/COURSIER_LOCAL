package com.example.coursiersuzosky.net

object ApiConfig {
    // Base URL injectée par BuildConfig selon le type de build
    val BASE_URL: String = com.example.coursiersuzosky.BuildConfig.BASE_URL
    const val AUTH = "auth.php"
    const val AGENT_AUTH = "agent_auth.php"
    const val SUBMIT_ORDER = "submit_order.php"
    const val DISTANCE_TEST = "../Test/test_distance_api.php" // pour estimation prix rapide
    const val APP_UPDATES = "app_updates.php" // endpoint de vérification mise à jour
}
