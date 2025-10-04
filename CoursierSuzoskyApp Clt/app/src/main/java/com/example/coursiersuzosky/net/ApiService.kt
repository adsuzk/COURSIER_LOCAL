package com.suzosky.coursierclient.net

import android.os.NetworkOnMainThreadException
import okhttp3.FormBody
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.Request
import okhttp3.RequestBody
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.io.IOException
import java.net.SocketTimeoutException
import java.net.UnknownHostException
import javax.net.ssl.SSLHandshakeException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

object ApiService {
    private val JSON = "application/json; charset=utf-8".toMediaType()

    private fun normalizeText(value: String?): String? {
        val t = value?.trim()
        return if (t.isNullOrEmpty() || t.equals("null", ignoreCase = true)) null else t
    }

    data class AgentInfo(
        val id: Int,
        val matricule: String,
        val nom: String?,
        val prenoms: String?,
        val telephone: String?,
        val type_poste: String?,
        val nationalite: String?
    )

    data class AgentLoginResponse(
        val success: Boolean,
        val message: String?,
        val error: String?,
        val agent: AgentInfo?
    )

    suspend fun agentLogin(identifier: String, password: String): AgentLoginResponse = withContext(Dispatchers.IO) {
        val url = ApiClient.buildUrl(ApiConfig.AGENT_AUTH)
        val json = JSONObject().apply {
            put("action", "login")
            put("identifier", identifier)
            put("password", password)
        }
        val body = json.toString().toRequestBody(JSON)
        val req = ApiClient.requestBuilder(url).post(body).build()
        ApiClient.http.newCall(req).execute().use { resp ->
            if (!resp.isSuccessful) throw IOException("HTTP ${'$'}{resp.code} ${'$'}{resp.message}")
            val bodyStr = resp.body?.string().orEmpty()
            val jsonResp = JSONObject(bodyStr)
            val success = jsonResp.optBoolean("success")
            val agentObj = jsonResp.optJSONObject("agent")
            val agent = agentObj?.let { a ->
                AgentInfo(
                    id = a.optInt("id"),
                    matricule = a.optString("matricule"),
                    nom = a.optString("nom").takeIf { a.has("nom") },
                    prenoms = a.optString("prenoms").takeIf { a.has("prenoms") },
                    telephone = a.optString("telephone").takeIf { a.has("telephone") },
                    type_poste = a.optString("type_poste").takeIf { a.has("type_poste") },
                    nationalite = a.optString("nationalite").takeIf { a.has("nationalite") }
                )
            }
            AgentLoginResponse(
                success = success,
                message = normalizeText(jsonResp.optString("message").takeIf { jsonResp.has("message") }),
                error = normalizeText(jsonResp.optString("error").takeIf { jsonResp.has("error") }),
                agent = agent
            )
        }
    }

    fun friendlyError(e: Throwable): String = when (e) {
        is UnknownHostException -> "Serveur introuvable. Vérifiez votre connexion et l'URL."
        is SocketTimeoutException -> "Délai dépassé. Réseau lent ou serveur indisponible."
        is SSLHandshakeException -> "Erreur de sécurité SSL/TLS avec le serveur."
        is NetworkOnMainThreadException -> "Appel réseau sur le thread principal. Correction appliquée: les requêtes se font désormais en arrière-plan (IO). Relancez et réessayez."
        is java.net.UnknownServiceException -> {
            // Typical when cleartext HTTP is blocked by network security policy
            "Trafic HTTP non autorisé. Activez usesCleartextTraffic pour debug et autorisez ${ApiConfig.BASE_URL}."
        }
        is IOException -> e.message?.takeIf { it.isNotBlank() } ?: "Erreur réseau. Vérifiez votre connexion."
        else -> e.message?.takeIf { it.isNotBlank() } ?: "Erreur inconnue"
    }

    suspend fun login(emailOrPhone: String, password: String): LoginResponse = withContext(Dispatchers.IO) {
        val url = ApiClient.buildUrl(ApiConfig.AUTH)
        val json = JSONObject().apply {
            put("action", "login")
            if (emailOrPhone.contains("@")) put("email", emailOrPhone) else put("phone", emailOrPhone)
            put("password", password)
        }
        val body = json.toString().toRequestBody(JSON)
        val req = ApiClient.requestBuilder(url).post(body).build()
        ApiClient.http.newCall(req).execute().use { resp ->
            if (!resp.isSuccessful) throw IOException("HTTP ${'$'}{resp.code} ${'$'}{resp.message}")
            val bodyStr = resp.body?.string().orEmpty()
            val jsonResp = JSONObject(bodyStr)
            val success = jsonResp.optBoolean("success")
            val client = jsonResp.optJSONObject("client")?.let { c ->
                ClientInfo(
                    id = c.optInt("id"),
                    nom = c.optString("nom"),
                    prenoms = c.optString("prenoms"),
                    email = c.optString("email"),
                    telephone = c.optString("telephone")
                )
            }
            LoginResponse(
                success = success,
                message = normalizeText(jsonResp.optString("message").takeIf { jsonResp.has("message") }),
                error = normalizeText(jsonResp.optString("error").takeIf { jsonResp.has("error") }),
                client = client
            )
        }
    }

    suspend fun getAppUpdate(): UpdateInfo = withContext(Dispatchers.IO) {
        val url = ApiClient.buildUrl(ApiConfig.APP_UPDATES)
        val req = ApiClient.requestBuilder(url).get().build()
        ApiClient.http.newCall(req).execute().use { resp ->
            if (!resp.isSuccessful) throw IOException("HTTP ${'$'}{resp.code} ${'$'}{resp.message}")
            val body = resp.body?.string().orEmpty()
            val json = JSONObject(body)
            val info = json.optJSONObject("update_info") ?: json
            UpdateInfo(
                update_available = info.optBoolean("update_available"),
                latest_version_code = info.optInt("latest_version_code").let { if (it == 0 && !info.has("latest_version_code")) null else it },
                latest_version_name = info.optString("latest_version_name").takeIf { info.has("latest_version_name") },
                download_url = info.optString("download_url").takeIf { info.has("download_url") },
                force_update = if (info.has("force_update")) info.optBoolean("force_update") else null
            )
        }
    }

    suspend fun estimatePrice(
        departure: String,
        destination: String,
        depLat: Double? = null,
        depLng: Double? = null,
        dstLat: Double? = null,
        dstLng: Double? = null
    ): DistanceApiResponse = withContext(Dispatchers.IO) {
        val base = ApiClient.buildUrl(ApiConfig.DISTANCE_TEST)
        val b = base.newBuilder()
        if (departure.isNotBlank() && destination.isNotBlank()) {
            b.addQueryParameter("origin", departure)
            b.addQueryParameter("destination", destination)
        }
        if (depLat != null && depLng != null && dstLat != null && dstLng != null) {
            b.addQueryParameter("origin_lat", depLat.toString())
            b.addQueryParameter("origin_lng", depLng.toString())
            b.addQueryParameter("destination_lat", dstLat.toString())
            b.addQueryParameter("destination_lng", dstLng.toString())
        }
        val url = b.build()
        val req = ApiClient.requestBuilder(url).get().build()
        ApiClient.http.newCall(req).execute().use { resp ->
            if (!resp.isSuccessful) throw IOException("HTTP ${'$'}{resp.code} ${'$'}{resp.message}")
            val body = resp.body?.string().orEmpty()
            val json = JSONObject(body)
            val success = json.optBoolean("success")
            val distance = json.optJSONObject("distance")?.let { d ->
                DistanceValue(d.optString("text"), d.optLong("value"))
            }
            val duration = json.optJSONObject("duration")?.let { d ->
                DistanceValue(d.optString("text"), d.optLong("value"))
            }
            val calcsObj = json.optJSONObject("calculations")
            val calcs = mutableMapOf<String, PriceCalc>()
            if (calcsObj != null) {
                val keys = calcsObj.keys()
                while (keys.hasNext()) {
                    val k = keys.next()
                    val v = calcsObj.getJSONObject(k)
                    calcs[k] = PriceCalc(
                        name = v.optString("name"),
                        baseFare = v.optInt("baseFare"),
                        perKmRate = v.optInt("perKmRate"),
                        distanceKm = v.optDouble("distanceKm"),
                        distanceCost = v.optInt("distanceCost"),
                        totalPrice = v.optInt("totalPrice"),
                    )
                }
            }
            DistanceApiResponse(success, distance, duration, if (calcs.isEmpty()) null else calcs)
        }
    }

    data class PaymentInitResponse(
        val success: Boolean,
        val message: String?,
        val payment_url: String?,
        val transaction_id: String?
    )

    suspend fun initiatePaymentOnly(orderNumber: String, amount: Int, clientName: String?, clientPhone: String?, clientEmail: String?): PaymentInitResponse = withContext(Dispatchers.IO) {
        val url = ApiClient.buildUrl(ApiConfig.INITIATE_PAYMENT_ONLY)
        val payload = JSONObject().apply {
            put("order_number", orderNumber)
            put("amount", amount)
            clientName?.let { put("client_name", it) }
            clientPhone?.let { put("client_phone", it) }
            clientEmail?.let { put("client_email", it) }
        }
        val req = ApiClient.requestBuilder(url)
            .post(payload.toString().toRequestBody(JSON))
            .build()
        ApiClient.http.newCall(req).execute().use { resp ->
            val body = resp.body?.string().orEmpty()
            val json = JSONObject(body)
            PaymentInitResponse(
                success = json.optBoolean("success"),
                message = json.optString("message").takeIf { json.has("message") },
                payment_url = json.optString("payment_url").takeIf { json.has("payment_url") },
                transaction_id = json.optString("transaction_id").takeIf { json.has("transaction_id") }
            )
        }
    }

    data class CreateAfterPaymentRequest(
        val departure: String,
        val destination: String,
        val latitude_retrait: Double?,
        val longitude_retrait: Double?,
        val latitude_livraison: Double?,
        val longitude_livraison: Double?,
        val distance_km: Double?,
        val prix_livraison: Int,
        val telephone_destinataire: String?,
        val nom_destinataire: String?,
        val notes_speciales: String?,
        val client_name: String? = null,
        val client_phone: String? = null,
        val client_email: String? = null,
    )

    data class CreateAfterPaymentResponse(
        val success: Boolean,
        val message: String?,
        val order_id: Long?,
        val order_number: String?,
        val redirect_url: String?
    )

    suspend fun createOrderAfterPayment(reqData: CreateAfterPaymentRequest): CreateAfterPaymentResponse = withContext(Dispatchers.IO) {
        val url = ApiClient.buildUrl(ApiConfig.CREATE_ORDER_AFTER_PAYMENT)
        val fb = FormBody.Builder()
            .add("departure", reqData.departure)
            .add("destination", reqData.destination)
            .apply {
                reqData.latitude_retrait?.let { add("latitude_retrait", it.toString()) }
                reqData.longitude_retrait?.let { add("longitude_retrait", it.toString()) }
                reqData.latitude_livraison?.let { add("latitude_livraison", it.toString()) }
                reqData.longitude_livraison?.let { add("longitude_livraison", it.toString()) }
                reqData.distance_km?.let { add("distance", it.toString()) }
                add("prix_livraison", reqData.prix_livraison.toString())
                reqData.telephone_destinataire?.let { add("telephone_destinataire", it) }
                reqData.nom_destinataire?.let { add("nom_destinataire", it) }
                reqData.notes_speciales?.let { add("notes_speciales", it) }
                reqData.client_name?.let { add("client_name", it) }
                reqData.client_phone?.let { add("client_phone", it) }
                reqData.client_email?.let { add("client_email", it) }
            }
            .build()
        val req = ApiClient.requestBuilder(url).post(fb).build()
        ApiClient.http.newCall(req).execute().use { resp ->
            val body = resp.body?.string().orEmpty()
            val json = JSONObject(body)
            CreateAfterPaymentResponse(
                success = json.optBoolean("success"),
                message = json.optString("message").takeIf { json.has("message") },
                order_id = json.optLong("order_id").let { if (json.has("order_id")) it else null },
                order_number = json.optString("order_number").takeIf { json.has("order_number") },
                redirect_url = json.optString("redirect_url").takeIf { json.has("redirect_url") }
            )
        }
    }


    suspend fun submitOrder(reqData: OrderRequest): SubmitOrderResponse = withContext(Dispatchers.IO) {
        val url = ApiClient.buildUrl(ApiConfig.SUBMIT_ORDER)
        val json = JSONObject().apply {
            put("departure", reqData.departure)
            put("destination", reqData.destination)
            put("senderPhone", reqData.senderPhone)
            put("receiverPhone", reqData.receiverPhone)
            put("packageDescription", reqData.packageDescription)
            put("priority", reqData.priority)
            put("paymentMethod", reqData.paymentMethod)
            put("price", reqData.price)
            reqData.distance?.let { put("distance", it) }
            reqData.duration?.let { put("duration", it) }
            reqData.departure_lat?.let { put("departure_lat", it) }
            reqData.departure_lng?.let { put("departure_lng", it) }
            reqData.arrival_lat?.let { put("arrival_lat", it) }
            reqData.arrival_lng?.let { put("arrival_lng", it) }
        }
        val body: RequestBody = json.toString().toRequestBody(JSON)
        val req: Request = ApiClient.requestBuilder(url).post(body).build()
        ApiClient.http.newCall(req).execute().use { resp ->
            if (!resp.isSuccessful) throw IOException("HTTP ${'$'}{resp.code} ${'$'}{resp.message}")
            val bodyStr = resp.body?.string().orEmpty()
            val jsonResp = JSONObject(bodyStr)
            val success = jsonResp.optBoolean("success")
            val dataObj = jsonResp.optJSONObject("data")
            val data = dataObj?.let { d ->
                OrderData(
                    order_id = d.optLong("order_id"),
                    order_number = d.optString("order_number"),
                    code_commande = d.optString("code_commande").takeIf { d.has("code_commande") },
                    price = d.optDouble("price"),
                    payment_method = d.optString("payment_method"),
                    payment_url = d.optString("payment_url").takeIf { d.has("payment_url") },
                    transaction_id = d.optString("transaction_id").takeIf { d.has("transaction_id") }
                )
            }
            SubmitOrderResponse(success, jsonResp.optString("message").takeIf { jsonResp.has("message") }, data)
        }
    }
}
