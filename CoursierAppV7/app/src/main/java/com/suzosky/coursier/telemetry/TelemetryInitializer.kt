// CoursierAppV7/app/src/main/java/com/suzosky/coursier/telemetry/TelemetryInitializer.kt
package com.suzosky.coursier.telemetry

import android.app.Application
import android.content.Context
import androidx.startup.Initializer

/**
 * Initializer pour configurer automatiquement la télémétrie au démarrage de l'app
 * Utilise androidx.startup pour l'initialisation automatique
 */
class TelemetryInitializer : Initializer<TelemetrySDK> {
    
    override fun create(context: Context): TelemetrySDK {
        // Basculer automatiquement vers la prod en release
        val useProd = try { com.suzosky.coursier.BuildConfig.USE_PROD_SERVER } catch (_: Throwable) { false }
        val baseUrl = if (useProd) {
            val b = try { com.suzosky.coursier.BuildConfig.PROD_BASE } catch (_: Throwable) { "https://coursier.conciergerie-privee-suzosky.com/COURSIER_LOCAL" }
            b
        } else {
            val host = try { com.suzosky.coursier.BuildConfig.DEBUG_LOCAL_HOST } catch (_: Throwable) { "" }
            if (host.isNotBlank()) {
                val h = if (host.startsWith("http")) host else "http://$host"
                if (h.endsWith("/COURSIER_LOCAL")) h else "$h/COURSIER_LOCAL"
            } else {
                "http://10.0.2.2/COURSIER_LOCAL"
            }
        }
        val apiKey = "local_telemetry_key"
        return TelemetrySDK.initialize(context, baseUrl, apiKey)
    }
    
    override fun dependencies(): List<Class<out Initializer<*>>> {
        return emptyList()
    }
}

/**
 * Extension pour Application class pour faciliter l'usage
 */
fun Application.initTelemetry() {
    val useProd = try { com.suzosky.coursier.BuildConfig.USE_PROD_SERVER } catch (_: Throwable) { false }
    val baseUrl = if (useProd) {
        val b = try { com.suzosky.coursier.BuildConfig.PROD_BASE } catch (_: Throwable) { "https://coursier.conciergerie-privee-suzosky.com/COURSIER_LOCAL" }
        b
    } else {
        val host = try { com.suzosky.coursier.BuildConfig.DEBUG_LOCAL_HOST } catch (_: Throwable) { "" }
        if (host.isNotBlank()) {
            val h = if (host.startsWith("http")) host else "http://$host"
            if (h.endsWith("/COURSIER_LOCAL")) h else "$h/COURSIER_LOCAL"
        } else {
            "http://10.0.2.2/COURSIER_LOCAL"
        }
    }
    val apiKey = "local_telemetry_key"
    TelemetrySDK.initialize(this, baseUrl, apiKey)
}