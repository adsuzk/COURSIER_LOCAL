package com.example.coursiersuzosky.net

import android.content.Context
import okhttp3.HttpUrl
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.logging.HttpLoggingInterceptor
import java.util.concurrent.TimeUnit
import okhttp3.HttpUrl.Companion.toHttpUrl

object ApiClient {
    private val logging = HttpLoggingInterceptor().apply {
        level = HttpLoggingInterceptor.Level.BODY
    }

    @Volatile
    private var _http: OkHttpClient? = null
    val http: OkHttpClient
        get() = _http ?: throw IllegalStateException("ApiClient not initialized. Call ApiClient.init(context) first.")

    fun init(context: Context) {
        if (_http != null) return
        val cookieJar = PersistentCookieJar(context.applicationContext)
        _http = OkHttpClient.Builder()
            .cookieJar(cookieJar)
            .addInterceptor(logging)
            .connectTimeout(20, TimeUnit.SECONDS)
            .readTimeout(20, TimeUnit.SECONDS)
            .build()
    }

    fun buildUrl(endpoint: String): HttpUrl = (ApiConfig.BASE_URL + endpoint).toHttpUrl()

    fun requestBuilder(url: HttpUrl): Request.Builder = Request.Builder().url(url)
}
