package com.suzosky.coursierclient.net

import android.content.Context

object ClientStore {
    private const val PREF = "suzosky_client"

    fun saveClientPhone(context: Context, phone: String?) {
        if (phone == null) return
        context.getSharedPreferences(PREF, Context.MODE_PRIVATE)
            .edit()
            .putString("client_phone", phone)
            .apply()
    }

    fun getClientPhone(context: Context): String? =
        context.getSharedPreferences(PREF, Context.MODE_PRIVATE)
            .getString("client_phone", null)
}
