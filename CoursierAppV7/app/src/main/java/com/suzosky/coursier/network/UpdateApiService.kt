package com.suzosky.coursier.network

import android.util.Log
import com.google.gson.Gson
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.ResponseBody
import java.io.IOException

/**
 * Service API pour les mises à jour automatiques avec OkHttp
 */
class UpdateApiService {
    
    private val client = OkHttpClient()
    private val gson = Gson()
    private val baseUrl: String = run {
        val useProd = try { com.suzosky.coursier.BuildConfig.USE_PROD_SERVER } catch (_: Throwable) { false }
        if (useProd) {
            val b = try { com.suzosky.coursier.BuildConfig.PROD_BASE } catch (_: Throwable) { "https://coursier.conciergerie-privee-suzosky.com/COURSIER_LOCAL" }
            val norm = if (b.endsWith('/')) b else "$b/"
            norm
        } else {
            val host = try { com.suzosky.coursier.BuildConfig.DEBUG_LOCAL_HOST } catch (_: Throwable) { "" }
            val h = if (host.isNotBlank()) {
                if (host.startsWith("http")) host.replace("http://", "https://") else "https://$host"
            } else {
                "https://10.0.2.2"
            }
            val base = if (h.endsWith("/COURSIER_LOCAL")) "$h/" else "$h/COURSIER_LOCAL/"
            base
        }
    }
    
    companion object {
        fun create(): UpdateApiService = UpdateApiService()
    }
    
    suspend fun checkForUpdates(deviceId: String, versionCode: Int): UpdateResponse = withContext(Dispatchers.IO) {
        try {
            val url = "${baseUrl}api/app_updates.php?device_id=$deviceId&version_code=$versionCode"
            val request = Request.Builder()
                .url(url)
                .get()
                .build()
                
            val response = client.newCall(request).execute()
            
            if (response.isSuccessful) {
                val json = response.body?.string() ?: "{}"
                gson.fromJson(json, UpdateResponse::class.java)
            } else {
                Log.e("UpdateApiService", "Erreur HTTP: ${response.code}")
                UpdateResponse(false, false, VersionInfo(0, "", "", 0, "", emptyList()), false, 3600)
            }
        } catch (e: Exception) {
            Log.e("UpdateApiService", "Erreur lors de la vérification des mises à jour", e)
            UpdateResponse(false, false, VersionInfo(0, "", "", 0, "", emptyList()), false, 3600)
        }
    }
    
    suspend fun registerDevice(deviceInfo: Map<String, Any>): ApiResult<ApiResponse> = withContext(Dispatchers.IO) {
        try {
            // Assurer le champ 'action' attendu par l'API
            val payload = if (!deviceInfo.containsKey("action")) deviceInfo + mapOf("action" to "register_device") else deviceInfo
            val json = gson.toJson(payload)
            val requestBody = json.toRequestBody("application/json".toMediaType())
            
            val request = Request.Builder()
                .url("${baseUrl}api/app_updates.php")
                .post(requestBody)
                .build()
                
            val response = client.newCall(request).execute()
            
            if (response.isSuccessful) {
                val responseJson = response.body?.string() ?: "{\"success\":true}"
                val apiResponse = gson.fromJson(responseJson, ApiResponse::class.java)
                ApiResult.Success(apiResponse)
            } else {
                ApiResult.Error(response.code, "HTTP Error: ${response.code}")
            }
        } catch (e: Exception) {
            Log.e("UpdateApiService", "Erreur lors de l'enregistrement", e)
            ApiResult.Error(500, e.message ?: "Unknown error")
        }
    }
    
    suspend fun reportStatus(statusData: Map<String, String>): ApiResult<ApiResponse> = withContext(Dispatchers.IO) {
        try {
            val payload = if (!statusData.containsKey("action")) statusData + mapOf("action" to "update_status") else statusData
            val json = gson.toJson(payload)
            val requestBody = json.toRequestBody("application/json".toMediaType())
            
            val request = Request.Builder()
                .url("${baseUrl}api/app_updates.php")
                .post(requestBody)
                .build()
                
            val response = client.newCall(request).execute()
            
            if (response.isSuccessful) {
                val responseJson = response.body?.string() ?: "{\"success\":true}"
                val apiResponse = gson.fromJson(responseJson, ApiResponse::class.java)
                ApiResult.Success(apiResponse)
            } else {
                ApiResult.Error(response.code, "HTTP Error: ${response.code}")
            }
        } catch (e: Exception) {
            Log.e("UpdateApiService", "Erreur lors du rapport de statut", e)
            ApiResult.Error(500, e.message ?: "Unknown error")
        }
    }
    
    suspend fun downloadApk(url: String): ApiResult<ResponseBody> = withContext(Dispatchers.IO) {
        try {
            val request = Request.Builder()
                .url(url)
                .get()
                .build()
                
            val response = client.newCall(request).execute()
            
            if (response.isSuccessful && response.body != null) {
                ApiResult.Success(response.body!!)
            } else {
                ApiResult.Error(response.code, "Download failed: ${response.code}")
            }
        } catch (e: Exception) {
            Log.e("UpdateApiService", "Erreur lors du téléchargement", e)
            ApiResult.Error(500, e.message ?: "Download error")
        }
    }
}

/**
 * Classe de résultat pour les appels API
 */
sealed class ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>()
    data class Error(val code: Int, val message: String) : ApiResult<Nothing>()
    
    fun isSuccessful(): Boolean = this is Success
}

/**
 * Modèles de données pour les réponses API
 */
data class UpdateResponse(
    val updateAvailable: Boolean,
    val forceUpdate: Boolean,
    val latestVersion: VersionInfo,
    val autoInstall: Boolean,
    val checkInterval: Int,
    val downloadUrl: String? = null
)

data class VersionInfo(
    val versionCode: Int,
    val versionName: String,
    val apkUrl: String,
    val apkSize: Long,
    val releaseDate: String,
    val changelog: List<String>
)

data class ApiResponse(
    val success: Boolean,
    val message: String? = null,
    val error: String? = null
)