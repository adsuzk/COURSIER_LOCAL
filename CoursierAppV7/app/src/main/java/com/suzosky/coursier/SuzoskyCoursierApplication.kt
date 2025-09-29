package com.suzosky.coursier

import android.app.Application
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.messaging.FirebaseMessaging
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class SuzoskyCoursierApplication : Application() {
    override fun onCreate() {
        super.onCreate()

        // Trace de démarrage de l'application
        Log.i("AppStartup", "SuzoskyCoursierApplication.onCreate()")
        println("🚀 Application.onCreate - démarrage")

        // INITIALISER FIREBASE - CRITIQUE !
        try {
            FirebaseApp.initializeApp(this)
            Log.i("Firebase", "✅ Firebase initialisé avec succès")
            println("🔥 Firebase initialisé avec succès")
            
            // Forcer l'initialisation du service de messagerie
            FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                if (task.isSuccessful) {
                    val token = task.result
                    Log.i("Firebase", "✅ Token FCM obtenu au démarrage: ${token?.take(20)}...")
                    println("🔑 Token FCM obtenu: ${token?.take(20)}...")
                } else {
                    Log.e("Firebase", "❌ Erreur obtention token FCM: ${task.exception}")
                    println("❌ Erreur FCM: ${task.exception?.message}")
                }
            }
        } catch (e: Exception) {
            Log.e("Firebase", "❌ ERREUR CRITIQUE initialisation Firebase", e)
            println("💥 ERREUR Firebase: ${e.message}")
        }

        // Journaliser toute exception non interceptée pour diagnostiquer les "app died"
        val previous = Thread.getDefaultUncaughtExceptionHandler()
        Thread.setDefaultUncaughtExceptionHandler { thread, throwable ->
            try {
                Log.e("AppCrash", "Uncaught exception in thread=${thread.name}", throwable)
                println("❌ AppCrash: ${throwable.javaClass.simpleName}: ${throwable.message}")
                println(throwable.stackTraceToString())
            } catch (_: Exception) {
                // ignore logging failures
            } finally {
                // Propager au handler précédent (système) pour conserver le comportement par défaut
                previous?.uncaughtException(thread, throwable)
            }
        }
    }
}
