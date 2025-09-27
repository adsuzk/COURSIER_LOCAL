package com.suzosky.coursier

import android.app.Application
import android.util.Log
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class SuzoskyCoursierApplication : Application() {
    override fun onCreate() {
        super.onCreate()

        // Trace de démarrage de l'application
        Log.i("AppStartup", "SuzoskyCoursierApplication.onCreate()")
        println("🚀 Application.onCreate - démarrage")

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
