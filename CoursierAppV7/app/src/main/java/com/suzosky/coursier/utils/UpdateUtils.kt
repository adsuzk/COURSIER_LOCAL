package com.suzosky.coursier.utils

import android.app.Activity
import android.app.DownloadManager
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Environment
import android.widget.Toast
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.io.File

object UpdateUtils {
    private fun resolveBaseUrl(): String {
        val useProd = try { com.suzosky.coursier.BuildConfig.USE_PROD_SERVER } catch (_: Throwable) { true }
        if (useProd) {
            val prod = try { com.suzosky.coursier.BuildConfig.PROD_BASE } catch (_: Throwable) {
                "https://coursier.conciergerie-privee-suzosky.com"
            }
            return prod.trimEnd('/')
        }

        val host = try { com.suzosky.coursier.BuildConfig.DEBUG_LOCAL_HOST } catch (_: Throwable) { "" }
        if (host.isNotBlank()) {
            val normalized = if (host.startsWith("http")) host else "http://$host"
            return if (normalized.endsWith("/coursier_prod")) normalized.trimEnd('/') else "$normalized/coursier_prod"
        }

        return "http://10.0.2.2/coursier_prod"
    }

    private fun updateUrl(): String = "${resolveBaseUrl()}/api/check_update.php"
    private const val APK_FILE_NAME = "coursier_update.apk"

    fun checkForUpdate(context: Context, currentVersion: String, onUpdateAvailable: (String, String, String) -> Unit) {
        Thread {
            try {
                val client = OkHttpClient()
                val request = Request.Builder().url(updateUrl()).build()
                val response = client.newCall(request).execute()
                val body = response.body?.string()
                if (response.isSuccessful && body != null) {
                    val json = JSONObject(body)
                    val latestVersion = json.getString("version")
                    val apkUrl = json.getString("apk_url")
                    val notes = json.optString("notes", "")
                    if (latestVersion != currentVersion) {
                        (context as Activity).runOnUiThread {
                            onUpdateAvailable(latestVersion, apkUrl, notes)
                        }
                    }
                }
            } catch (e: Exception) {
                e.printStackTrace()
            }
        }.start()
    }

    fun downloadAndInstallApk(context: Context, apkUrl: String) {
        val request = DownloadManager.Request(Uri.parse(apkUrl))
            .setTitle("Mise à jour Suzosky Coursier")
            .setDescription("Téléchargement de la nouvelle version...")
            .setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, APK_FILE_NAME)
            .setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
            .setAllowedOverMetered(true)
            .setAllowedOverRoaming(true)
        val dm = context.getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager
        dm.enqueue(request)
        Toast.makeText(context, "Téléchargement de la mise à jour...", Toast.LENGTH_LONG).show()
        // L'utilisateur devra cliquer sur la notification pour installer
    }

    fun promptInstallApk(context: Context) {
        val file = File(Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS), APK_FILE_NAME)
        val apkUri = Uri.fromFile(file)
        val intent = Intent(Intent.ACTION_VIEW)
        intent.setDataAndType(apkUri, "application/vnd.android.package-archive")
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK
        try {
            context.startActivity(intent)
        } catch (e: ActivityNotFoundException) {
            Toast.makeText(context, "Impossible d'ouvrir le fichier APK", Toast.LENGTH_LONG).show()
        }
    }
}
