package com.example.coursiersuzosky.net

import android.content.Context
import androidx.core.content.edit
import android.util.Base64
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import org.json.JSONArray
import org.json.JSONObject

class PersistentCookieJar(private val context: Context) : CookieJar {
    private val prefs = context.getSharedPreferences("cookies", Context.MODE_PRIVATE)

    override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
        val host = url.host
        val arr = JSONArray()
        cookies.forEach { c ->
            val obj = JSONObject().apply {
                put("name", c.name)
                put("value", c.value)
                put("expiresAt", c.expiresAt)
                put("domain", c.domain)
                put("path", c.path)
                put("secure", c.secure)
                put("httpOnly", c.httpOnly)
            }
            arr.put(obj)
        }
        prefs.edit { putString(host, arr.toString()) }
    }

    override fun loadForRequest(url: HttpUrl): List<Cookie> {
        val host = url.host
        val str = prefs.getString(host, null) ?: return emptyList()
        return try {
            val arr = JSONArray(str)
            (0 until arr.length()).mapNotNull { i ->
                val o = arr.getJSONObject(i)
                Cookie.Builder()
                    .name(o.getString("name"))
                    .value(o.getString("value"))
                    .domain(o.getString("domain"))
                    .path(o.getString("path"))
                    .apply { if (o.optBoolean("secure")) secure() }
                    .apply { /* httpOnly is not directly settable via builder */ }
                    .build()
            }
        } catch (_: Exception) { emptyList() }
    }
}
