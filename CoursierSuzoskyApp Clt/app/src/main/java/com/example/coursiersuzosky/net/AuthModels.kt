package com.example.coursiersuzosky.net

data class LoginResponse(
    val success: Boolean,
    val message: String? = null,
    val error: String? = null,
    val client: ClientInfo? = null
)

data class ClientInfo(
    val id: Int,
    val nom: String,
    val prenoms: String,
    val email: String?,
    val telephone: String?
)

data class UpdateInfo(
    val update_available: Boolean,
    val latest_version_code: Int?,
    val latest_version_name: String?,
    val download_url: String?,
    val force_update: Boolean?
)
