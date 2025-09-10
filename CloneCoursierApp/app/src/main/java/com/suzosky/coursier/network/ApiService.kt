package com.suzosky.coursier.network

import okhttp3.*
import okhttp3.RequestBody.Companion.asRequestBody
import okhttp3.MediaType.Companion.toMediaTypeOrNull
import java.io.IOException

object ApiService {

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

        val request = Request.Builder()
            .url(BASE_URL)
            .post(builder.build())
            .build()
        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                callback(false, e.message)
            }
            override fun onResponse(call: Call, response: Response) {
                val body = response.body?.string()
                if (response.isSuccessful && body?.contains("succès") == true) {
                    callback(true, null)
                } else {
                    callback(false, body)
                }
            }
        })
    }
    private const val BASE_URL = "http://10.0.2.2/coursier_prod/coursier.php" // 10.0.2.2 pour l'émulateur Android
    private val client = OkHttpClient()

    fun login(identifier: String, password: String, callback: (Boolean, String?) -> Unit) {
        val formBody = FormBody.Builder()
            .add("action", "login")
            .add("identifier", identifier)
            .add("password", password)
            .build()
        val request = Request.Builder()
            .url(BASE_URL)
            .post(formBody)
            .build()
        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                callback(false, e.message)
            }
            override fun onResponse(call: Call, response: Response) {
                val body = response.body?.string()
                if (response.isSuccessful && body?.contains("coursier_logged_in") == true) {
                    callback(true, null)
                } else {
                    callback(false, body)
                }
            }
        })
    }

    fun getCommandes(callback: (List<CommandeApi>?, String?) -> Unit) {
        val formBody = FormBody.Builder()
            .add("ajax", "true")
            .add("action", "get_commandes")
            .build()
        val request = Request.Builder()
            .url(BASE_URL)
            .post(formBody)
            .build()
        client.newCall(request).enqueue(object : Callback {
            override fun onFailure(call: Call, e: IOException) {
                callback(null, e.message)
            }
            override fun onResponse(call: Call, response: Response) {
                val body = response.body?.string()
                if (response.isSuccessful && body != null) {
                    try {
                        val commandes = kotlinx.serialization.json.Json.decodeFromString<List<CommandeApi>>(body)
                        callback(commandes, null)
                    } catch (e: Exception) {
                        callback(null, "Erreur parsing: ${e.message}")
                    }
                } else {
                    callback(null, body)
                }
            }
        })
    }
}
