package com.suzosky.coursier.network

import okhttp3.*
import okhttp3.FormBody
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import okhttp3.ResponseBody.Companion.toResponseBody
import android.content.Context
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import androidx.core.content.edit
import com.google.android.gms.maps.model.LatLng
import com.suzosky.coursier.BuildConfig
import org.json.JSONArray
import org.json.JSONObject
import kotlinx.serialization.decodeFromString
import kotlinx.serialization.json.Json
import java.io.IOException
import java.io.File
import javax.net.ssl.*
import java.security.cert.X509Certificate
import java.security.SecureRandom
import kotlinx.serialization.Serializable
import java.util.Locale
import java.util.UUID

object ApiService {
    private const val LOCAL_SEGMENT = "/COURSIER_LOCAL"
    // Local dev: emulator loopback. For physical device, we will prefer LAN host from BuildConfig.DEBUG_LOCAL_HOST if provided.
    private const val EMULATOR_LOCAL_BASE = "http://10.0.2.2$LOCAL_SEGMENT"
    private const val DEFAULT_PROD_BASE = "https://coursier.conciergerie-privee-suzosky.com$LOCAL_SEGMENT"

    private fun isDebug(): Boolean = BuildConfig.DEBUG
    private fun useProd(): Boolean = try { BuildConfig.USE_PROD_SERVER } catch (_: Throwable) { true }
    private fun forceLocalOnly(): Boolean = try { BuildConfig.FORCE_LOCAL_ONLY } catch (_: Throwable) { false }

    private fun deviceLocalBase(): String? {
        // Prefer developer-provided LAN IP (e.g., http://192.168.1.20/COURSIER_LOCAL) for physical devices
        val host = try { BuildConfig.DEBUG_LOCAL_HOST } catch (_: Throwable) { "" }
        return host.takeIf { it.isNotBlank() }?.let { h ->
            val base = if (h.startsWith("http")) h else "http://$h"
            normalizeLocalBase(base)
        }
    }

    private fun normalizeLocalBase(base: String): String {
        val trimmed = base.trimEnd('/')
        return if (trimmed.endsWith(LOCAL_SEGMENT, ignoreCase = true)) trimmed else "$trimmed$LOCAL_SEGMENT"
    }

    private fun isEmulator(): Boolean {
        // Basic heuristic without importing Build.* constants across files
        // Many emulators report 10.0.2.2 reachability, physical devices usually can't.
        // We'll rely primarily on DEBUG_LOCAL_HOST presence. If absent, assume emulator for debug builds.
        return isDebug() && (deviceLocalBase() == null)
    }

    private fun debugLocalBase(): String {
        return if (isEmulator()) EMULATOR_LOCAL_BASE else (deviceLocalBase() ?: EMULATOR_LOCAL_BASE)
    }

    // Resolve primary/secondary bases and execute with automatic fallback (local <-> prod)
    private fun prodBase(): String {
        val configured = try { BuildConfig.PROD_BASE } catch (_: Throwable) { null }
        return (configured?.takeIf { it.isNotBlank() } ?: DEFAULT_PROD_BASE).trimEnd('/')
    }

    private fun basePair(): Pair<String, String> =
        if (useProd()) Pair(prodBase(), debugLocalBase()) else Pair(debugLocalBase(), prodBase())

    private fun enqueueInternal(
        urlBase: String,
        allowRetry: Boolean,
        secondary: String,
        allowSecondary: Boolean,
        buildRequest: (String) -> Request,
        onResponseMain: (Response) -> Unit,
        onFailureMain: (String) -> Unit
    ) {
        val req = buildRequest(urlBase)
        android.util.Log.d("ApiService", "Making request to: ${req.url}")
        client.newCall(req).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                android.util.Log.e("ApiService", "Request failed to ${call.request().url}: ${e.message}", e)
                if (allowRetry && allowSecondary) {
                    android.util.Log.d("ApiService", "Retrying with secondary: $secondary")
                    enqueueInternal(secondary, false, secondary, allowSecondary, buildRequest, onResponseMain, onFailureMain)
                } else {
                    Handler(Looper.getMainLooper()).post { onFailureMain(e.message ?: "Erreur réseau") }
                }
            }

            override fun onResponse(call: Call, response: Response) {
                android.util.Log.d("ApiService", "Response from ${call.request().url}: ${response.code} ${response.message}")
                if (!response.isSuccessful && allowRetry && allowSecondary) {
                    // Fallback to secondary base on HTTP errors (e.g., 404 when endpoint not deployed on primary)
                    try { response.close() } catch (_: Throwable) {}
                    android.util.Log.d("ApiService", "HTTP ${response.code} -> retrying with secondary: $secondary")
                    enqueueInternal(secondary, false, secondary, allowSecondary, buildRequest, onResponseMain, onFailureMain)
                } else {
                    val originalBody = response.body
                    val mediaType = originalBody?.contentType()
                    val cachedBody = try {
                        originalBody?.string()
                    } catch (e: Exception) {
                        null
                    }

                    val safeResponse = if (cachedBody != null) {
                        response.newBuilder()
                            .body(cachedBody.toResponseBody(mediaType))
                            .build()
                    } else {
                        response
                    }

                    if (safeResponse !== response) {
                        try { response.close() } catch (_: Throwable) {}
                    }

                    Handler(Looper.getMainLooper()).post {
                        onResponseMain(safeResponse)
                    }
                }
            }
        })
    }

    private fun executeWithFallback(
        buildRequest: (String) -> Request,
        onResponseMain: (Response) -> Unit,
        onFailureMain: (String) -> Unit
    ) {
        val (primary, secondary) = basePair()
        try {
            android.util.Log.d("ApiService", "=== NETWORK DEBUG ===")
            android.util.Log.d("ApiService", "DEBUG_LOCAL_HOST = '${BuildConfig.DEBUG_LOCAL_HOST}'")
            android.util.Log.d("ApiService", "isDebug() = ${isDebug()}")
            android.util.Log.d("ApiService", "useProd() = ${useProd()}")
            android.util.Log.d("ApiService", "isEmulator() = ${isEmulator()}")
            android.util.Log.d("ApiService", "deviceLocalBase() = ${deviceLocalBase()}")
            android.util.Log.d("ApiService", "debugLocalBase() = ${debugLocalBase()}")
            android.util.Log.d("ApiService", "Base primary=$primary secondary=$secondary")
            android.util.Log.d("ApiService", "=====================")
        } catch (_: Throwable) {}
        // In debug builds with USE_PROD_SERVER=false, stick to local only (no prod retry)
    val allowSecondary = !forceLocalOnly()
        enqueueInternal(primary, true, secondary, allowSecondary, buildRequest, onResponseMain, onFailureMain)
    }

    // Helpers to build endpoint URLs from a chosen base
    private fun buildCoursierPhp(base: String) = "$base/coursier.php"
    private fun buildApi(base: String, path: String) = "$base/api/$path"

    /**
     * Récupérer l'historique de commandes et stats pour alimenter Portefeuille/Stats
     */
    fun getCoursierOrders(
        coursierId: Int,
        status: String = "all",
        limit: Int = 50,
        offset: Int = 0,
        callback: (Map<String, Any>?, String?) -> Unit
    ) {
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "get_coursier_orders.php")
                val finalUrl = "$url?coursier_id=$coursierId&status=$status&limit=$limit&offset=$offset"
                logIfUrlInvalid(finalUrl)
                Request.Builder().url(finalUrl).get().build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                if (!response.isSuccessful || body == null) {
                    callback(null, body ?: "Erreur serveur")
                } else {
                    try {
                        val json = JSONObject(body)
                        val success = json.optBoolean("success", false)
                        if (!success) {
                            callback(null, json.optString("message", json.optString("error", "Erreur inconnue")))
                        } else {
                            val data = json.optJSONObject("data")
                            val result = mutableMapOf<String, Any>()
                            if (data != null) {
                                val commandesArray = data.optJSONArray("commandes")
                                val commandes = mutableListOf<Map<String, Any>>()
                                if (commandesArray != null) {
                                    for (i in 0 until commandesArray.length()) {
                                        val cmd = commandesArray.optJSONObject(i) ?: continue
                                        val rawStatut = cmd.optString("statut")
                                        val mappedStatut = when (rawStatut.lowercase(Locale.getDefault())) {
                                            "termine" -> "livree"
                                            "annule" -> "annulee"
                                            else -> rawStatut
                                        }
                                        val dateCreation = cmd.optString("date_creation", "")
                                        val (dateOnly, timeOnly) = try {
                                            val parts = dateCreation.split(" ")
                                            Pair(parts.getOrNull(0) ?: "", parts.getOrNull(1) ?: "")
                                        } catch (_: Exception) { Pair("", "") }
                                        val prix = if (cmd.has("gain_commission")) cmd.optDouble("gain_commission", 0.0) else cmd.optDouble("montant_total", 0.0)
                                        commandes.add(
                                            mapOf(
                                                "id" to cmd.optInt("id", 0).toString(),
                                                "clientNom" to cmd.optString("client_nom", ""),
                                                "clientTelephone" to cmd.optString("client_telephone", ""),
                                                "adresseEnlevement" to cmd.optString("adresse_depart", ""),
                                                "adresseLivraison" to cmd.optString("adresse_arrivee", ""),
                                                "distanceKm" to cmd.optDouble("distance_km", 0.0),
                                                "prix" to prix,
                                                "statut" to mappedStatut,
                                                "dateCommande" to dateOnly,
                                                "heureCommande" to timeOnly
                                            )
                                        )
                                    }
                                }
                                result["commandes"] = commandes
                                data.optJSONObject("pagination")?.let { p ->
                                    result["pagination"] = mapOf(
                                        "total" to p.optInt("total", 0),
                                        "limit" to p.optInt("limit", limit),
                                        "offset" to p.optInt("offset", offset),
                                        "pages" to p.optInt("pages", 1),
                                        "current_page" to p.optInt("current_page", 1)
                                    )
                                }
                                data.optJSONObject("statistiques")?.let { s ->
                                    result["statistiques"] = mapOf(
                                        "total_commandes" to s.optInt("total_commandes", 0),
                                        "commandes_terminees" to s.optInt("commandes_terminees", 0),
                                        "commandes_actives" to s.optInt("commandes_actives", 0),
                                        "commandes_annulees" to s.optInt("commandes_annulees", 0),
                                        "revenus_total" to s.optDouble("revenus_total", 0.0),
                                        "taux_reussite" to s.optDouble("taux_reussite", 0.0)
                                    )
                                }
                                data.optJSONObject("gains")?.let { g ->
                                    result["gains"] = mapOf(
                                        "commandes_payantes" to g.optInt("commandes_payantes", 0),
                                        "total_commissions" to g.optDouble("total_commissions", 0.0),
                                        "commission_moyenne" to g.optDouble("commission_moyenne", 0.0)
                                    )
                                }
                            }
                            callback(result, null)
                        }
                    } catch (e: Exception) {
                        callback(null, "Erreur parsing: ${e.message}")
                    }
                }
            },
            onFailureMain = { err -> callback(null, err) }
        )
    }

    fun registerDeviceToken(context: Context, coursierId: Int, token: String) {
        val deviceId = getOrCreateDeviceId(context)
        
        // NOUVEAU: Essayer d'abord l'API simple avec plus de debugging
        android.util.Log.d("ApiService", "🔥 registerDeviceToken START - Coursier: $coursierId, Token: ${token.substring(0, 20)}...")
        
        val form = FormBody.Builder()
            .add("coursier_id", coursierId.toString())
            .add("agent_id", coursierId.toString())
            .add("token", token)
            .add("app_version", "1.1.0")
            .build()
            
        executeWithFallback(
            buildRequest = { base ->
                // Essayer d'abord l'API simple
                val regUrl = buildApi(base, "register_device_token_simple.php")
                android.util.Log.d("ApiService", "🔥 URL complète: $regUrl")
                logIfUrlInvalid(regUrl)
                Request.Builder().url(regUrl).post(form).build()
            },
            onResponseMain = { response ->
                try {
                    val body = response.body?.string() ?: ""
                    android.util.Log.d("ApiService", "🔥 registerDeviceToken -> ${response.code}")
                    android.util.Log.d("ApiService", "🔥 Response body: $body")
                    
                    if (response.code == 403) {
                        android.util.Log.e("ApiService", "🚨 403 FORBIDDEN - Problème de permissions serveur!")
                    }
                } catch (e: Exception) {
                    android.util.Log.e("ApiService", "🚨 Erreur lecture réponse: ${e.message}")
                } finally {
                    response.close()
                }
            },
            onFailureMain = { err ->
                android.util.Log.w("ApiService", "🚨 registerDeviceToken failed: $err")
            }
        )

        syncAgentToken(coursierId, deviceId, token)
    }

    private fun getOrCreateDeviceId(context: Context): String {
        val prefs = context.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
        prefs.getString("device_id", null)?.takeIf { it.isNotBlank() }?.let { return it }

        val candidate = try {
            Settings.Secure.getString(context.contentResolver, Settings.Secure.ANDROID_ID)
        } catch (_: Exception) {
            null
        }

        val finalId = candidate?.takeUnless {
            it.isBlank() || it.equals("unknown", ignoreCase = true)
        } ?: UUID.randomUUID().toString()

    try { prefs.edit { putString("device_id", finalId) } } catch (_: Exception) {}
        return finalId
    }

    private fun syncAgentToken(agentId: Int, deviceId: String, token: String) {
        if (agentId <= 0 || token.isBlank()) return

        val payload = JSONObject().apply {
            put("agent_id", agentId)
            put("device_id", deviceId)
            put("fcm_token", token)
            put("token", token)
        }

        val body = payload.toString().toRequestBody("application/json; charset=utf-8".toMediaType())

        executeWithFallback(
            buildRequest = { base ->
                val syncUrl = buildApi(base, "sync_tokens.php")
                logIfUrlInvalid(syncUrl)
                Request.Builder().url(syncUrl).post(body).build()
            },
            onResponseMain = { response ->
                try {
                    android.util.Log.d("ApiService", "syncAgentToken -> ${response.code}")
                } finally {
                    response.close()
                }
            },
            onFailureMain = { err ->
                android.util.Log.w("ApiService", "syncAgentToken failed: ${err}")
            }
        )
    }
    /**
     * Désactiver un token FCM côté serveur. Si l'appel échoue et que
     * reEnqueueOnFailure=true, le token sera enregistré localement pour
     * réessayer plus tard via processPendingDeactivations(context).
     */
    fun deactivateDeviceToken(
        context: Context,
        token: String,
        reEnqueueOnFailure: Boolean = true,
        onSuccess: () -> Unit = {},
        onFailure: (String) -> Unit = {}
    ) {
        if (token.isBlank()) {
            onFailure("Token vide")
            return
        }

        val form = FormBody.Builder()
            .add("token", token)
            .build()

        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "deactivate_device_token.php")
                logIfUrlInvalid(url)
                Request.Builder().url(url).post(form).build()
            },
            onResponseMain = { response ->
                try {
                    val body = response.body?.string()
                    android.util.Log.d("ApiService", "deactivateDeviceToken -> ${response.code} body=${body}")
                    if (response.isSuccessful) {
                        onSuccess()
                    } else {
                        val msg = "HTTP ${response.code}"
                        if (reEnqueueOnFailure) enqueuePendingDeactivation(context, token)
                        onFailure(msg)
                    }
                } catch (e: Exception) {
                    val msg = e.message ?: "Erreur parsing"
                    if (reEnqueueOnFailure) enqueuePendingDeactivation(context, token)
                    onFailure(msg)
                } finally {
                    try { response.close() } catch (_: Throwable) {}
                }
            },
            onFailureMain = { err ->
                if (reEnqueueOnFailure) enqueuePendingDeactivation(context, token)
                onFailure(err)
            }
        )
    }

    private fun enqueuePendingDeactivation(context: Context, token: String) {
        try {
            val prefs = context.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
            val set = prefs.getStringSet("pending_deactivations", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
            if (!set.contains(token)) {
                set.add(token)
                prefs.edit().putStringSet("pending_deactivations", set).apply()
                android.util.Log.d("ApiService", "Token ajouté à la file locale de désactivation")
            }
        } catch (e: Exception) {
            android.util.Log.w("ApiService", "Impossible d'enregistrer la désactivation locale: ${e.message}")
        }
    }

    fun processPendingDeactivations(context: Context) {
        try {
            val prefs = context.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
            val set = prefs.getStringSet("pending_deactivations", emptySet())?.toMutableSet() ?: mutableSetOf()
            if (set.isEmpty()) return
            android.util.Log.d("ApiService", "processPendingDeactivations - tokens=${set.size}")
            val iterator = set.iterator()
            while (iterator.hasNext()) {
                val token = iterator.next()
                deactivateDeviceToken(context, token, reEnqueueOnFailure = false, onSuccess = {
                    // remove from set
                    try {
                        val current = prefs.getStringSet("pending_deactivations", mutableSetOf())?.toMutableSet() ?: mutableSetOf()
                        current.remove(token)
                        prefs.edit().putStringSet("pending_deactivations", current).apply()
                        android.util.Log.d("ApiService", "Token deactivation réussie (queued): removed")
                    } catch (_: Exception) {}
                }, onFailure = { err ->
                    android.util.Log.w("ApiService", "Echec de la désactivation queued token: $err")
                })
            }
        } catch (e: Exception) {
            android.util.Log.w("ApiService", "Erreur processPendingDeactivations: ${e.message}")
        }
    }
    private fun getInitRechargeUrl(): String =
        if (useProd()) "${prodBase()}/api/init_recharge.php" else "${debugLocalBase()}/api/init_recharge.php"

    private fun getCoursierDataUrl(): String =
        if (useProd()) "${prodBase()}/api/get_coursier_data.php" else "${debugLocalBase()}/api/get_coursier_data.php"

    // Deprecated: prefer buildApi(base, "update_order_status.php") with executeWithFallback

    private fun getProfileUrl(): String =
        if (useProd()) "${prodBase()}/api/profile.php" else "${debugLocalBase()}/api/profile.php"

    private fun getAgentAuthUrl(): String =
        if (useProd()) "${prodBase()}/api/agent_auth.php" else "${debugLocalBase()}/api/agent_auth.php"

    /**
     * Récupérer le profil du coursier (nom, prenoms, telephone, stats)
     */
    fun getCoursierProfile(
        coursierId: Int,
        callback: (Map<String, Any>?, String?) -> Unit
    ) {
        executeWithFallback(
            buildRequest = { base ->
                val url = "${buildApi(base, "profile.php")}?coursier_id=$coursierId"
                Request.Builder().url(url).get().build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                if (!response.isSuccessful || body == null) {
                    callback(null, body ?: "Erreur serveur")
                } else {
                    try {
                        val json = JSONObject(body)
                        if (!json.optBoolean("success", false)) {
                            callback(null, json.optString("message", json.optString("error", "Erreur inconnue")))
                        } else {
                            val data = json.optJSONObject("data") ?: JSONObject()
                            val result = mutableMapOf<String, Any>()
                            result["nom_complet"] = listOf(
                                data.optString("nom", ""),
                                data.optString("prenoms", "")
                            ).filter { it.isNotBlank() }.joinToString(" ")
                            result["telephone"] = data.optString("telephone", "")
                            result["email"] = data.optString("email", "")
                            result["date_inscription"] = data.optString("date_inscription", "")
                            result["total_commandes"] = data.optInt("total_commandes", 0)
                            result["note_globale"] = data.optDouble("note_globale", 0.0)
                            callback(result, null)
                        }
                    } catch (e: Exception) {
                        callback(null, "Erreur parsing: ${e.message}")
                    }
                }
            },
            onFailureMain = { err -> callback(null, err) }
        )
    }

    /**
     * Vérifie la session côté serveur et retourne l'ID agent (coursier) si connecté
     */
    fun checkCoursierSession(callback: (Int?, String?) -> Unit) {
        executeWithFallback(
            buildRequest = { base ->
                val url = "${buildApi(base, "agent_auth.php")}?action=check_session"
                Request.Builder().url(url).get().build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                if (!response.isSuccessful || body == null) {
                    callback(null, body ?: "Erreur serveur")
                } else {
                    try {
                        val json = JSONObject(body)
                        if (!json.optBoolean("success", false)) {
                            callback(null, json.optString("error", "NO_SESSION"))
                        } else {
                            val agent = json.optJSONObject("agent")
                            val id = agent?.optInt("id", 0) ?: 0
                            if (id > 0) callback(id, null) else callback(null, "ID invalide")
                        }
                    } catch (e: Exception) {
                        callback(null, "Erreur parsing: ${e.message}")
                    }
                }
            },
            onFailureMain = { err -> callback(null, err) }
        )
    }
    /**
     * Initiate a recharge via CinetPay or local test URL
     * @param coursierId ID of the coursier
     * @param montant Amount to recharge
     * @param callback Receives payment URL or error message
     */
    fun initRecharge(coursierId: Int, montant: Double, callback: (String?, String?) -> Unit) {
        println("🔄 ApiService.initRecharge - Debut avec coursierId: $coursierId, montant: $montant")
        
        // Server expects coursier_id and montant (see api/init_recharge.php)
        val formBody = FormBody.Builder()
            .add("coursier_id", coursierId.toString())
            .add("montant", montant.toString())
            .add("force_prod", "1")
            .build()
        
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "init_recharge.php")
                logIfUrlInvalid(url)
                println("📡 Envoi requête vers: $url")
                Request.Builder().url(url).post(formBody).build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                println("📥 Réponse reçue - Code: ${response.code}, Body: $body")
                if (body != null) {
                    try {
                        val json = JSONObject(body)
                        val success = json.optBoolean("success")
                        println("🔍 JSON parsé - Success: $success")
                        if (success) {
                            val paymentUrl = json.optString("payment_url")
                            println("✅ URL de paiement extraite: $paymentUrl")
                            callback(paymentUrl, null)
                        } else {
                            val errorMsg = json.optString("message", json.optString("error", "Unknown error"))
                            println("❌ Erreur serveur: $errorMsg")
                            callback(null, errorMsg)
                        }
                    } catch (e: Exception) {
                        println("❌ Erreur parsing JSON: ${e.message}")
                        callback(null, "Erreur parsing: ${e.message}")
                    }
                } else {
                    println("❌ Réponse vide")
                    callback(null, "Réponse vide")
                }
            },
            onFailureMain = { err -> callback(null, "Erreur réseau: $err") }
        )
    }

    /**
     * Récupérer les vraies données du coursier : solde, commandes, gains
     */
    fun getCoursierData(coursierId: Int, callback: (Map<String, Any>?, String?) -> Unit) {
        println("🔄 ApiService.getCoursierData - coursier: $coursierId")
        
        executeWithFallback(
            buildRequest = { base ->
                val url = "${buildApi(base, "get_coursier_data.php")}?coursier_id=$coursierId"
                logIfUrlInvalid(url)
                println("📡 Requête données vers: $url")
                Request.Builder().url(url).build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                println("📥 Données reçues - Code: ${response.code}, Body: $body")

                if (body.isNullOrBlank()) {
                    callback(null, "Réponse vide")
                    return@executeWithFallback
                }

                val trimmed = body.trim()
                // If response doesn't start with JSON, try to recover by finding the first
                // JSON token ('{' or '[') and parsing from there. This handles cases where
                // server emits HTML wrappers, debug prints, or BOMs before the JSON payload.
                val effectiveJson = if (trimmed.startsWith("{") || trimmed.startsWith("[")) {
                    trimmed
                } else {
                    val idxObj = trimmed.indexOf('{')
                    val idxArr = trimmed.indexOf('[')
                    val idx = listOf(idxObj, idxArr).filter { it >= 0 }.minOrNull() ?: -1
                    if (idx >= 0) {
                        val candidate = trimmed.substring(idx)
                        println("⚠️ getCoursierData: JSON trouvé au milieu de la réponse, extracting substring starting at $idx")
                        candidate
                    } else {
                        println("❌ Réponse non JSON détectée pour getCoursierData (no '{' or '['): ${trimmed.take(200)}")
                        callback(null, "Réponse serveur inattendue")
                        return@executeWithFallback
                    }
                }

                try {
                    // Fallback: certaines versions renvoient directement un tableau de commandes
                    if (trimmed.startsWith("[")) {
                        val commandesArray = JSONArray(trimmed)
                        val commandes = mutableListOf<Map<String, Any>>()
                        for (i in 0 until commandesArray.length()) {
                            val cmd = commandesArray.optJSONObject(i) ?: continue
                            commandes.add(
                                mapOf(
                                    "id" to cmd.optString("id"),
                                    "clientNom" to cmd.optString("clientNom"),
                                    "clientTelephone" to cmd.optString("clientTelephone"),
                                    "telephoneDestinataire" to cmd.optString("telephoneDestinataire"),
                                    "adresseEnlevement" to cmd.optString("adresseEnlevement"),
                                    "adresseLivraison" to cmd.optString("adresseLivraison"),
                                    "latitudeEnlevement" to cmd.optDouble("latitudeEnlevement"),
                                    "longitudeEnlevement" to cmd.optDouble("longitudeEnlevement"),
                                    "latitudeLivraison" to cmd.optDouble("latitudeLivraison"),
                                    "longitudeLivraison" to cmd.optDouble("longitudeLivraison"),
                                    "distance" to cmd.optDouble("distance"),
                                    "prixLivraison" to cmd.optDouble("prixLivraison"),
                                    "statut" to cmd.optString("statut"),
                                    "description" to cmd.optString("description")
                                )
                            )
                        }
                        val result = mutableMapOf<String, Any>(
                            "balance" to 0.0,
                            "commandes_attente" to commandes.size,
                            "gains_du_jour" to 0.0,
                            "commandes" to commandes
                        )
                        println("✅ Données coursier parsées (fallback tableau): ${result.size} éléments")
                        callback(result, null)
                        return@executeWithFallback
                    }

                    val json = JSONObject(trimmed)
                    val success = json.optBoolean("success", false)
                    if (!success) {
                        val errorMsg = json.optString("error", json.optString("message", "Erreur inconnue"))
                        println("❌ Erreur serveur données: $errorMsg")
                        callback(null, errorMsg)
                        return@executeWithFallback
                    }

                    val dataNode = json.opt("data")
                    val data = when (dataNode) {
                        is JSONObject -> dataNode
                        is String -> {
                            val inner = dataNode.trim()
                            try {
                                when {
                                    inner.startsWith("{") -> JSONObject(inner)
                                    inner.startsWith("[") -> JSONObject().apply { put("commandes", JSONArray(inner)) }
                                    else -> null
                                }
                            } catch (_: Exception) { null }
                        }
                        is JSONArray -> JSONObject().apply { put("commandes", dataNode) }
                        JSONObject.NULL -> null
                        else -> null
                    } ?: run {
                        println("❌ Champ data manquant ou invalide dans getCoursierData: $dataNode")
                        callback(null, "Structure de données inattendue")
                        return@executeWithFallback
                    }

                    val result = mutableMapOf<String, Any>()
                    result["balance"] = data.optDouble("balance", json.optDouble("balance", 0.0))
                    result["commandes_attente"] = data.optInt("commandes_attente", json.optInt("commandes_attente", 0))
                    result["gains_du_jour"] = data.optDouble("gains_du_jour", json.optDouble("gains_du_jour", 0.0))

                    val commandesArray = data.optJSONArray("commandes") ?: json.optJSONArray("commandes") ?: JSONArray()
                    val commandes = mutableListOf<Map<String, Any>>()
                    for (i in 0 until commandesArray.length()) {
                        val cmd = commandesArray.optJSONObject(i) ?: continue
                        commandes.add(
                            mapOf(
                                "id" to cmd.optString("id"),
                                "clientNom" to cmd.optString("clientNom"),
                                "clientTelephone" to cmd.optString("clientTelephone"),
                                "telephoneDestinataire" to cmd.optString("telephoneDestinataire"),
                                "adresseEnlevement" to cmd.optString("adresseEnlevement"),
                                "adresseLivraison" to cmd.optString("adresseLivraison"),
                                "latitudeEnlevement" to cmd.optDouble("latitudeEnlevement"),
                                "longitudeEnlevement" to cmd.optDouble("longitudeEnlevement"),
                                "latitudeLivraison" to cmd.optDouble("latitudeLivraison"),
                                "longitudeLivraison" to cmd.optDouble("longitudeLivraison"),
                                "distance" to cmd.optDouble("distance"),
                                "prixLivraison" to cmd.optDouble("prixLivraison"),
                                "statut" to cmd.optString("statut"),
                                "description" to cmd.optString("description")
                            )
                        )
                    }
                    result["commandes"] = commandes
                    println("✅ Données coursier parsées: ${result.size} éléments")
                    callback(result, null)
                } catch (e: Exception) {
                    println("❌ Erreur parsing données: ${e.message}")
                    callback(null, "Erreur parsing: ${e.message}")
                }
            },
            onFailureMain = { err -> callback(null, "Erreur réseau: $err") }
        )
    }

    fun pollBalanceUntilChange(
        coursierId: Int,
        initialBalance: Double,
        timeoutMs: Long = 90_000,
        pollIntervalMs: Long = 4_000,
        onResult: (Double?, Boolean) -> Unit
    ) {
        val handler = Handler(Looper.getMainLooper())
        val deadline = System.currentTimeMillis() + timeoutMs
        var completed = false

        fun parseBalance(value: Any?): Double? = when (value) {
            null -> null
            is Number -> value.toDouble()
            is String -> value.replace(" ", "").replace(" ", "").toDoubleOrNull()
            else -> null
        }

        fun poll() {
            if (completed) return
            getCoursierData(coursierId) { data, error ->
                if (completed) return@getCoursierData

                val now = System.currentTimeMillis()
                val balance = data?.let { parseBalance(it["balance"]) }

                val hasChanged = balance?.let { kotlin.math.abs(it - initialBalance) > 0.5 } ?: false

                when {
                    hasChanged -> {
                        completed = true
                        onResult(balance, true)
                    }
                    now >= deadline -> {
                        completed = true
                        onResult(balance, false)
                    }
                    error != null -> {
                        // Erreur réseau momentanée : réessayer en allongeant légèrement le délai
                        if (!completed) {
                            handler.postDelayed({ poll() }, pollIntervalMs + 2_000)
                        }
                    }
                    else -> {
                        if (!completed) {
                            handler.postDelayed({ poll() }, pollIntervalMs)
                        }
                    }
                }
            }
        }

        poll()
    }

    fun registerCoursier(
        nom: String,
        prenoms: String,
        dateNaissance: String?,
        lieuNaissance: String?,
        lieuResidence: String?,
        telephone: String,
        typePoste: String,
        pieceRecto: java.io.File?,
        pieceVerso: java.io.File?,
        permisRecto: java.io.File?,
        permisVerso: java.io.File?,
        callback: (Boolean, String?) -> Unit
    ) {
        val builder = MultipartBody.Builder().setType(MultipartBody.FORM)
            .addFormDataPart("action", "register")
            .addFormDataPart("nom", nom)
            .addFormDataPart("prenoms", prenoms)
            .addFormDataPart("date_naissance", dateNaissance ?: "")
            .addFormDataPart("lieu_naissance", lieuNaissance ?: "")
            .addFormDataPart("lieu_residence", lieuResidence ?: "")
            .addFormDataPart("telephone", telephone)
            .addFormDataPart("type_poste", typePoste)
        if (pieceRecto != null) builder.addFormDataPart("piece_recto", pieceRecto.name, pieceRecto.asRequestBody("image/*".toMediaTypeOrNull()))
        if (pieceVerso != null) builder.addFormDataPart("piece_verso", pieceVerso.name, pieceVerso.asRequestBody("image/*".toMediaTypeOrNull()))
        if (permisRecto != null) builder.addFormDataPart("permis_recto", permisRecto.name, permisRecto.asRequestBody("image/*".toMediaTypeOrNull()))
        if (permisVerso != null) builder.addFormDataPart("permis_verso", permisVerso.name, permisVerso.asRequestBody("image/*".toMediaTypeOrNull()))

        executeWithFallback(
            buildRequest = { base ->
                val url = buildCoursierPhp(base)
                Request.Builder().url(url).post(builder.build()).build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                if (response.isSuccessful && body?.contains("succès") == true) {
                    callback(true, null)
                } else {
                    callback(false, body ?: "Erreur inconnue")
                }
            },
            onFailureMain = { err -> callback(false, err) }
        )
    }
    
    // Configuration réseau permissive pour développement
    // Simple in-memory cookie store to persist PHP session across requests
    private val cookieStore: MutableMap<String, MutableList<Cookie>> = java.util.Collections.synchronizedMap(mutableMapOf())
    private val inMemoryCookieJar = object : CookieJar {
        override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
            // Merge with existing cookies for this host
            val host = url.host
            val existing = cookieStore[host] ?: mutableListOf()
            val updated = existing.filterNot { ex -> cookies.any { it.name == ex.name } }.toMutableList()
            updated.addAll(cookies)
            cookieStore[host] = updated
            println("🍪 Saved cookies for $host: ${cookies.map { it.name }}")
        }
        override fun loadForRequest(url: HttpUrl): List<Cookie> {
            val host = url.host
            val cookies = cookieStore[host] ?: emptyList()
            if (cookies.isNotEmpty()) println("🍪 Loading cookies for $host: ${cookies.map { it.name }}")
            return cookies
        }
    }

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, java.util.concurrent.TimeUnit.SECONDS)
        .readTimeout(30, java.util.concurrent.TimeUnit.SECONDS)
        .writeTimeout(30, java.util.concurrent.TimeUnit.SECONDS)
        .cookieJar(inMemoryCookieJar)
        .apply {
            // Configuration SSL permissive pour développement uniquement
            try {
                val trustManager = object : X509TrustManager {
                    override fun checkClientTrusted(chain: Array<X509Certificate>, authType: String) {}
                    override fun checkServerTrusted(chain: Array<X509Certificate>, authType: String) {}
                    override fun getAcceptedIssuers(): Array<X509Certificate> = arrayOf()
                }
                
                val sslContext = SSLContext.getInstance("SSL")
                sslContext.init(null, arrayOf<TrustManager>(trustManager), SecureRandom())
                
                sslSocketFactory(sslContext.socketFactory, trustManager)
                hostnameVerifier { _, _ -> true }
                
                println("✅ Configuration SSL permissive activée pour développement")
            } catch (e: Exception) {
                println("⚠️ Impossible de configurer SSL permissif: ${e.message}")
            }
        }
        .build()

    // Petite aide pour diagnostiquer les URLs mal formées
    private fun logIfUrlInvalid(url: String) {
        if (!(url.startsWith("http://") || url.startsWith("https://"))) {
            println("❌ URL invalide (schéma manquant): $url")
        }
    }

    fun login(identifier: String, password: String, callback: (Boolean, String?) -> Unit) {
        // Validation des inputs
        if (identifier.isBlank() || password.isBlank()) {
            Handler(Looper.getMainLooper()).post {
                callback(false, "Veuillez remplir tous les champs")
            }
            return
        }

        // Local test credentials fallback
        if (identifier == "test" && password == "test") {
            Handler(Looper.getMainLooper()).post {
                callback(true, null)
            }
            return
        }

        try {
            val requestBody = JSONObject().apply {
                put("action", "login")
                put("identifier", identifier)
                put("password", password)
            }.toString().toRequestBody("application/json; charset=utf-8".toMediaType())

            executeWithFallback(
                buildRequest = { base ->
                    val baseUrl = buildApi(base, "agent_auth.php")
                    logIfUrlInvalid(baseUrl)
                    Request.Builder().url(baseUrl).post(requestBody).build()
                },
                onResponseMain = { response ->
                    try {
                        val rawBody = response.body?.string()
                        val body = rawBody?.trim() ?: ""
                        val contentType = (response.header("Content-Type") ?: "").lowercase()
                        val isLikelyJson = contentType.contains("application/json") || body.startsWith("{")

                        if (isLikelyJson) {
                            try {
                                val json = JSONObject(body)
                                val success = json.optBoolean("success", false)
                                if (success) {
                                    callback(true, null)
                                } else {
                                    val err = json.optString("error", json.optString("message", "Identifiants incorrects"))
                                    callback(false, err.ifBlank { "Identifiants incorrects" })
                                }
                            } catch (pe: Exception) {
                                android.util.Log.e("ApiService", "[login] JSON parse error", pe)
                                callback(false, "Réponse serveur invalide")
                            }
                        } else {
                            // Si la réponse ressemble à du HTML (doctype, html, head, body), ne pas l'afficher à l'utilisateur
                            val looksHtml = body.contains("<html", true) || body.contains("<head", true) || body.contains("<body", true) || body.contains("<!doctype", true)
                            if (response.isSuccessful && body.contains("coursier_logged_in")) {
                                callback(true, null)
                            } else if (looksHtml) {
                                android.util.Log.w("ApiService", "[login] Réponse HTML inattendue, masquer pour l'UI")
                                callback(false, "Identifiants incorrects")
                            } else {
                                android.util.Log.w("ApiService", "[login] Réponse inattendue: $body")
                                // Ne jamais remonter du HTML brut
                                callback(false, if (body.length in 1..200) body else "Identifiants incorrects")
                            }
                        }
                    } catch (e: Exception) {
                        android.util.Log.e("ApiService", "[login] Erreur de parsing de la réponse", e)
                        callback(false, "Erreur de traitement: ${e.message ?: "inconnue"}")
                    }
                },
                onFailureMain = { err ->
                    android.util.Log.e("ApiService", "[login] Erreur de connexion: $err")
                    callback(false, "Erreur de connexion: $err")
                }
            )
        } catch (e: Exception) {
            android.util.Log.e("ApiService", "[login] Exception inattendue", e)
            Handler(Looper.getMainLooper()).post {
                callback(false, "Erreur inattendue: ${e.message ?: "inconnue"}")
            }
        }
    }

    fun getCommandes(callback: (List<CommandeApi>?, String?) -> Unit) {
        try {
            val formBody = FormBody.Builder()
                .add("ajax", "true")
                .add("action", "get_commandes")
                .build()
            executeWithFallback(
                buildRequest = { base ->
                    val baseUrl = buildCoursierPhp(base)
                    logIfUrlInvalid(baseUrl)
                    Request.Builder().url(baseUrl).post(formBody).build()
                },
                onResponseMain = { response ->
                    try {
                        val body = response.body?.string()
                        if (response.isSuccessful && body != null) {
                            try {
                                val commandes = Json.decodeFromString<List<CommandeApi>>(body)
                                callback(commandes, null)
                            } catch (e: Exception) {
                                callback(null, "Erreur parsing: ${e.message}")
                            }
                        } else {
                            callback(null, body ?: "Erreur serveur")
                        }
                    } catch (e: Exception) {
                        callback(null, "Erreur traitement: ${e.message}")
                    }
                },
                onFailureMain = { err -> callback(null, "Erreur réseau: $err") }
            )
        } catch (e: Exception) {
            Handler(Looper.getMainLooper()).post {
                callback(null, "Erreur inattendue: ${e.message}")
            }
        }
    }

    fun updateCoursierPosition(coursierId: Int, lat: Double, lng: Double, callback: (Boolean, String?) -> Unit) {
        val payload = JSONObject().apply {
            put("coursier_id", coursierId)
            put("lat", lat)
            put("lng", lng)
        }.toString()
        val body = payload.toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "update_coursier_position.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val ok = response.isSuccessful
                callback(ok, if (ok) null else response.body?.string() ?: "Erreur server")
            },
            onFailureMain = { err -> callback(false, err) }
        )
    }

    /**
     * Posts raw JSON to an arbitrary API path (full URL), using the fallback/executeWithFallback machinery.
     * callback: (ok, errMessage)
     */
    fun executeRawJson(fullUrl: String, json: JSONObject, callback: (Boolean, String?) -> Unit) {
        val body = json.toString().toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        executeWithFallback(
            buildRequest = { _ ->
                Request.Builder().url(fullUrl).post(body).build()
            },
            onResponseMain = { response ->
                val ok = response.isSuccessful
                callback(ok, if (ok) null else response.body?.string() ?: "Erreur server")
            },
            onFailureMain = { err -> callback(false, err) }
        )
    }

    fun pollCoursierOrders(coursierId: Int, callback: (String?, String?) -> Unit) {
        executeWithFallback(
            buildRequest = { base ->
                val url = "${buildApi(base, "poll_coursier_orders.php")}?coursier_id=$coursierId"
                Request.Builder().url(url).get().build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                if (response.isSuccessful) callback(body, null) else callback(null, body ?: "Erreur server")
            },
            onFailureMain = { err -> callback(null, err) }
        )
    }

    fun setActiveOrder(coursierId: Int, commandeId: String, active: Boolean = true, callback: (Boolean) -> Unit) {
        val payload = JSONObject().apply {
            put("coursier_id", coursierId)
            put("commande_id", commandeId.toIntOrNull() ?: 0)
            put("active", active)
        }.toString()
    val body = payload.toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "set_active_order.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val ok = response.isSuccessful && try {
                    val s = response.body?.string()
                    s != null && JSONObject(s).optBoolean("success", false)
                } catch (_: Exception) { false }
                callback(ok)
            },
            onFailureMain = { _ -> callback(false) }
        )
    }

    /**
     * Accepte ou refuse une commande via l'API order_response.php
     */
    fun respondToOrder(
        orderId: String,
        coursierId: String,
        action: String, // "accept" ou "refuse"
        callback: (Boolean, String?) -> Unit
    ) {
        val payload = JSONObject().apply {
            put("order_id", orderId)
            put("coursier_id", coursierId)
            put("action", action)
        }.toString()
        val body = payload.toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "order_response.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val bodyStr = response.body?.string()
                android.util.Log.d("ApiService", "respondToOrder response: $bodyStr (code=${response.code})")

                fun extractMessage(jsonText: String?): Pair<Boolean, String?> {
                    if (jsonText == null) return Pair(false, null)
                    return try {
                        val json = JSONObject(jsonText)
                        val success = json.optBoolean("success", false)
                        val message = json.optString("message", json.optString("error", ""))
                        Pair(success, message.takeIf { it.isNotBlank() })
                    } catch (_: Exception) { Pair(false, null) }
                }

                if (!response.isSuccessful) {
                    val (_, parsedMsg) = extractMessage(bodyStr)
                    callback(false, parsedMsg ?: "Erreur serveur HTTP ${response.code}")
                } else {
                    val (success, parsedMsg) = extractMessage(bodyStr)
                    callback(success, parsedMsg)
                }
            },
            onFailureMain = { error ->
                android.util.Log.e("ApiService", "respondToOrder failed: $error")
                callback(false, error)
            }
        )
    }
    
    fun updateOrderStatus(commandeId: String, statut: String, callback: (Boolean) -> Unit) {
        updateOrderStatusWithCash(commandeId, statut, false, null, callback)
    }
    
    /**
     * Démarre la livraison (acceptee → en_cours)
     */
    fun startDelivery(commandeId: Int, coursierId: Int, callback: (Boolean, String?) -> Unit) {
        executeWithFallback(
            buildRequest = { base ->
                val url = "$base/mobile_sync_api.php" +
                        "?action=start_delivery&coursier_id=$coursierId&commande_id=$commandeId"
                Request.Builder().url(url).post("".toRequestBody()).build()
            },
            onResponseMain = { response ->
                val bodyStr = response.body?.string()
                if (!response.isSuccessful || bodyStr == null) {
                    callback(false, "Erreur réseau")
                    return@executeWithFallback
                }
                try {
                    val json = JSONObject(bodyStr)
                    val success = json.optBoolean("success", false)
                    val message = json.optString("message", "")
                    callback(success, message)
                } catch (e: Exception) {
                    callback(false, "Erreur de parsing: ${e.message}")
                }
            },
            onFailureMain = { error ->
                callback(false, error)
            }
        )
    }
    
    /**
     * Marque le colis comme récupéré (en_cours → recuperee)
     */
    fun pickupPackage(commandeId: Int, coursierId: Int, callback: (Boolean, String?) -> Unit) {
        executeWithFallback(
            buildRequest = { base ->
                val url = "$base/mobile_sync_api.php" +
                        "?action=pickup_package&coursier_id=$coursierId&commande_id=$commandeId"
                Request.Builder().url(url).post("".toRequestBody()).build()
            },
            onResponseMain = { response ->
                val bodyStr = response.body?.string()
                if (!response.isSuccessful || bodyStr == null) {
                    callback(false, "Erreur réseau")
                    return@executeWithFallback
                }
                try {
                    val json = JSONObject(bodyStr)
                    val success = json.optBoolean("success", false)
                    val message = json.optString("message", "")
                    callback(success, message)
                } catch (e: Exception) {
                    callback(false, "Erreur de parsing: ${e.message}")
                }
            },
            onFailureMain = { error ->
                callback(false, error)
            }
        )
    }
    
    /**
     * Marque la commande comme livrée (recuperee → livree)
     */
    fun markDelivered(commandeId: Int, coursierId: Int, callback: (Boolean, String?) -> Unit) {
        executeWithFallback(
            buildRequest = { base ->
                val url = "$base/mobile_sync_api.php" +
                        "?action=mark_delivered&coursier_id=$coursierId&commande_id=$commandeId"
                Request.Builder().url(url).post("".toRequestBody()).build()
            },
            onResponseMain = { response ->
                val bodyStr = response.body?.string()
                if (!response.isSuccessful || bodyStr == null) {
                    callback(false, "Erreur réseau")
                    return@executeWithFallback
                }
                try {
                    val json = JSONObject(bodyStr)
                    val success = json.optBoolean("success", false)
                    val message = json.optString("message", "")
                    callback(success, message)
                } catch (e: Exception) {
                    callback(false, "Erreur de parsing: ${e.message}")
                }
            },
            onFailureMain = { error ->
                callback(false, error)
            }
        )
    }
    
    /**
     * Confirme que le cash a été récupéré pour une commande en espèces (après livraison)
     */
    fun confirmCashReceived(commandeId: Int, coursierId: Int, callback: (Boolean, String?) -> Unit) {
        executeWithFallback(
            buildRequest = { base ->
                val url = "$base/mobile_sync_api.php" +
                        "?action=confirm_cash_received&coursier_id=$coursierId&commande_id=$commandeId"
                Request.Builder().url(url).post("".toRequestBody()).build()
            },
            onResponseMain = { response ->
                val bodyStr = response.body?.string()
                if (!response.isSuccessful || bodyStr == null) {
                    callback(false, "Erreur réseau")
                    return@executeWithFallback
                }
                try {
                    val json = JSONObject(bodyStr)
                    val success = json.optBoolean("success", false)
                    val message = json.optString("message", "")
                    callback(success, message)
                } catch (e: Exception) {
                    callback(false, "Erreur de parsing: ${e.message}")
                }
            },
            onFailureMain = { error ->
                callback(false, error)
            }
        )
    }
    
    fun updateOrderStatusWithCash(
        commandeId: String,
        statut: String,
        cashCollected: Boolean = false,
        cashAmount: Double? = null,
        callback: (Boolean) -> Unit
    ) {
        val parameters = mutableMapOf<String, Any>()
        parameters["commande_id"] = commandeId.toIntOrNull() ?: 0
        parameters["statut"] = statut
        
        if (cashCollected) {
            parameters["cash_collected"] = true
            cashAmount?.let {
                parameters["cash_amount"] = it
            }
        }
        
        val json = com.google.gson.Gson().toJson(parameters)
    val body = json.toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "update_order_status.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val bodyStr = response.body?.string()
                val ok = if (response.isSuccessful && bodyStr != null) {
                    try { JSONObject(bodyStr).optBoolean("success", false) } catch (_: Exception) { false }
                } else false
                callback(ok)
            },
            onFailureMain = { _ -> callback(false) }
        )
    }

    /**
     * Empêche l'acceptation concurrente d'une commande via un verrouillage temporaire côté serveur
     */
    fun assignWithLock(
        commandeId: Int,
        coursierId: Int,
        action: String = "accept", // "accept" ou "release"
        ttlSeconds: Int = 60,
        callback: (Boolean, String?) -> Unit
    ) {
        val payload = JSONObject().apply {
            put("commande_id", commandeId)
            put("coursier_id", coursierId)
            put("action", action)
            put("ttl_seconds", ttlSeconds)
        }.toString()
    val body = payload.toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "assign_with_lock.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val bodyStr = response.body?.string()
                if (!response.isSuccessful || bodyStr == null) {
                    callback(false, bodyStr ?: "Erreur serveur")
                } else {
                    try {
                        val json = JSONObject(bodyStr)
                        val ok = json.optBoolean("success", false)
                        if (ok) callback(true, null) else callback(false, json.optString("error", "Echec du verrouillage"))
                    } catch (e: Exception) {
                        callback(false, "Erreur parsing: ${e.message}")
                    }
                }
            },
            onFailureMain = { err -> callback(false, err) }
        )
    }

    /**
     * Génère/renouvelle un OTP de livraison (affiché côté coursier pour vérification avec le client)
     */
    fun generateDeliveryOtp(
        commandeId: Int,
        length: Int = 4,
        ttlSeconds: Int = 900,
        callback: (String?, String?) -> Unit
    ) {
        val payload = JSONObject().apply {
            put("commande_id", commandeId)
            put("length", length)
            put("ttl_seconds", ttlSeconds)
        }.toString()
    val body = payload.toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "generate_delivery_otp.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val s = response.body?.string()
                if (!response.isSuccessful || s == null) {
                    callback(null, s ?: "Erreur serveur OTP")
                } else {
                    try {
                        val json = JSONObject(s)
                        if (json.optBoolean("success", false)) {
                            callback(json.optString("otp", ""), null)
                        } else {
                            callback(null, json.optString("error", "Echec génération OTP"))
                        }
                    } catch (e: Exception) { callback(null, "Erreur parsing: ${e.message}") }
                }
            },
            onFailureMain = { err -> callback(null, err) }
        )
    }

    /**
     * Confirme la livraison via OTP; peut inclure l'information de cash collecté
     */
    fun confirmDelivery(
        commandeId: Int,
        otpCode: String,
        coursierId: Int? = null,
        cashCollected: Boolean = false,
        cashAmount: Double? = null,
        callback: (Boolean, String?) -> Unit
    ) {
        val payload = JSONObject().apply {
            put("commande_id", commandeId)
            put("otp_code", otpCode)
            if (coursierId != null) put("coursier_id", coursierId)
            if (cashCollected) put("cash_collected", true)
            if (cashAmount != null) put("cash_amount", cashAmount)
        }.toString()
    val body = payload.toRequestBody("application/json; charset=utf-8".toMediaTypeOrNull())
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "confirm_delivery.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val s = response.body?.string()
                if (!response.isSuccessful || s == null) {
                    callback(false, s ?: "Erreur serveur confirmation")
                } else {
                    try {
                        val json = JSONObject(s)
                        val ok = json.optBoolean("success", false)
                        if (ok) callback(true, null) else callback(false, json.optString("error", "Echec confirmation"))
                    } catch (e: Exception) { callback(false, "Erreur parsing: ${e.message}") }
                }
            },
            onFailureMain = { err -> callback(false, err) }
        )
    }

    /**
     * Envoie une preuve de livraison (photo ou signature)
     */
    fun uploadProof(
        commandeId: Int,
        type: String, // "photo" ou "signature"
        file: File,
        coursierId: Int? = null,
        callback: (Boolean, String?) -> Unit
    ) {
        val builder = MultipartBody.Builder().setType(MultipartBody.FORM)
            .addFormDataPart("commande_id", commandeId.toString())
            .addFormDataPart("type", type)
        if (coursierId != null) builder.addFormDataPart("coursier_id", coursierId.toString())
        builder.addFormDataPart("proof", file.name, file.asRequestBody("image/*".toMediaTypeOrNull()))

        val body = builder.build()
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "upload_proof.php")
                Request.Builder().url(url).post(body).build()
            },
            onResponseMain = { response ->
                val s = response.body?.string()
                if (!response.isSuccessful || s == null) {
                    callback(false, s ?: "Erreur serveur upload")
                } else {
                    try {
                        val json = JSONObject(s)
                        val ok = json.optBoolean("success", false)
                        if (ok) callback(true, null) else callback(false, json.optString("error", "Echec upload"))
                    } catch (e: Exception) { callback(false, "Erreur parsing: ${e.message}") }
                }
            },
            onFailureMain = { err -> callback(false, err) }
        )
    }

    /**
     * Récupère un itinéraire (overview polyline) entre origin et destination via notre proxy backend
     */
    fun getDirections(
        origin: LatLng,
        destination: LatLng,
        mode: String = "driving",
        language: String = "fr",
        region: String = "ci",
        callback: (List<LatLng>?, String?) -> Unit
    ) {
        executeWithFallback(
            buildRequest = { base ->
                val url = buildApi(base, "directions_proxy.php") +
                        "?origin=${origin.latitude},${origin.longitude}" +
                        "&destination=${destination.latitude},${destination.longitude}" +
                        "&mode=${mode}&language=${language}&region=${region}"
                logIfUrlInvalid(url)
                Request.Builder().url(url).get().build()
            },
            onResponseMain = { response ->
                val body = response.body?.string()
                if (!response.isSuccessful || body == null) {
                    callback(null, body ?: "Erreur serveur Directions")
                } else {
                    try {
                        val json = JSONObject(body)
                        val ok = json.optBoolean("ok", false)
                        if (!ok) {
                            callback(null, json.optString("error", "Directions non disponibles"))
                            return@executeWithFallback
                        }
                        val directions = json.optJSONObject("directions")
                        val routes = directions?.optJSONArray("routes")
                        val route0 = routes?.optJSONObject(0)
                        val poly = route0?.optJSONObject("overview_polyline")
                        val points = poly?.optString("points", null)
                        if (!points.isNullOrBlank()) {
                            callback(decodePolyline(points), null)
                        } else {
                            callback(emptyList<LatLng>(), null)
                        }
                    } catch (e: Exception) {
                        callback(null, "Erreur parsing Directions: ${e.message}")
                    }
                }
            },
            onFailureMain = { err -> callback(null, err) }
        )
    }

    // Décodage de polyline Google
    private fun decodePolyline(poly: String): List<LatLng> {
        val len = poly.length
        var index = 0
        val path = mutableListOf<LatLng>()
        var lat = 0
        var lng = 0
        while (index < len) {
            var b: Int
            var shift = 0
            var result = 0
            do {
                b = poly[index++].code - 63
                result = result or ((b and 0x1f) shl shift)
                shift += 5
            } while (b >= 0x20)
            val dlat = if ((result and 1) != 0) (result shr 1).inv() else (result shr 1)
            lat += dlat

            shift = 0
            result = 0
            do {
                b = poly[index++].code - 63
                result = result or ((b and 0x1f) shl shift)
                shift += 5
            } while (b >= 0x20)
            val dlng = if ((result and 1) != 0) (result shr 1).inv() else (result shr 1)
            lng += dlng

            path += LatLng(lat / 1E5, lng / 1E5)
        }
        return path
    }

    /**
     * Ping instantané pour signaler que le coursier est en ligne
     * Met à jour last_ping pour déclencher l'ouverture immédiate du formulaire
     */
    fun pingDeviceToken(context: Context, token: String? = null, attempt: Int = 0) {
        val prefs = context.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
        val finalToken = token ?: prefs.getString("fcm_token", null)

        if (finalToken.isNullOrBlank()) {
            android.util.Log.w("ApiService", "🚨 pingDeviceToken: Aucun token disponible")
            return
        }

        val coursierId = prefs.getInt("coursier_id", -1)
        if (coursierId <= 0) {
            android.util.Log.w(
                "ApiService",
                "🚨 pingDeviceToken: Aucun coursier_id stocké (attempt=$attempt), récupération session..."
            )

            if (attempt == 0) {
                checkCoursierSession { id, err ->
                    if (id != null && id > 0) {
                        try {
                            prefs.edit { putInt("coursier_id", id) }
                        } catch (_: Exception) {
                        }
                        android.util.Log.d(
                            "ApiService",
                            "✅ pingDeviceToken: coursier_id=$id récupéré via session"
                        )
                        val refreshedToken = prefs.getString("fcm_token", null) ?: finalToken
                        if (!refreshedToken.isNullOrBlank()) {
                            pingDeviceToken(context, refreshedToken, attempt + 1)
                        } else {
                            android.util.Log.w(
                                "ApiService",
                                "🚨 pingDeviceToken: Token introuvable après récupération session"
                            )
                        }
                    } else {
                        android.util.Log.e(
                            "ApiService",
                            "❌ pingDeviceToken: session invalide, coursier_id indisponible (${err ?: "inconnu"})"
                        )
                    }
                }
            } else {
                android.util.Log.e(
                    "ApiService",
                    "❌ pingDeviceToken: coursier_id toujours manquant après récupération session"
                )
            }
            return
        }

        android.util.Log.d(
            "ApiService",
            "🚀 pingDeviceToken START - coursier_id=$coursierId, token=${finalToken.take(20)}..."
        )

        val form = FormBody.Builder()
            .add("coursier_id", coursierId.toString())
            .add("token", finalToken)
            .add("platform", "android")
            .add("app_version", BuildConfig.VERSION_NAME)
            .build()

        executeWithFallback(
            buildRequest = { base ->
                val pingUrl = buildApi(base, "ping_device_token.php")
                android.util.Log.d("ApiService", "🚀 Ping URL: $pingUrl")
                logIfUrlInvalid(pingUrl)
                Request.Builder().url(pingUrl).post(form).build()
            },
            onResponseMain = { response ->
                try {
                    val body = response.body?.string() ?: ""
                    android.util.Log.d("ApiService", "✅ pingDeviceToken -> ${response.code}")
                    android.util.Log.d("ApiService", "✅ Ping response: $body")
                } catch (e: Exception) {
                    android.util.Log.e("ApiService", "🚨 Erreur lecture ping response: ${e.message}")
                } finally {
                    response.close()
                }
            },
            onFailureMain = { err ->
                android.util.Log.w("ApiService", "🚨 pingDeviceToken failed: $err")
            }
        )
    }
}
