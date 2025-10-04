package com.suzosky.coursierclient.net

import android.content.Context
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

object ClientStore {
    private const val PREF = "suzosky_client"
    private val clientPhoneFlow = MutableStateFlow<String?>(null)

    fun saveClientPhone(context: Context, phone: String?) {
        if (phone == null) return
        context.getSharedPreferences(PREF, Context.MODE_PRIVATE)
            .edit()
            .putString("client_phone", phone)
            .apply()
        clientPhoneFlow.value = phone
    }

    fun getClientPhone(context: Context): String? =
        context.getSharedPreferences(PREF, Context.MODE_PRIVATE)
            .getString("client_phone", null)

    fun observeClientPhone(context: Context): StateFlow<String?> {
        if (clientPhoneFlow.value == null) {
            clientPhoneFlow.value = getClientPhone(context)
        }
        return clientPhoneFlow.asStateFlow()
    }
}
