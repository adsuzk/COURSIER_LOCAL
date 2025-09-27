package com.suzosky.coursier.services

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * Stub de receiver pour correspondre à la déclaration manifeste.
 * À compléter si un traitement est nécessaire après installation.
 */
class InstallReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        Log.d("InstallReceiver", "Reçu: ${intent.action}")
        // TODO: Implémenter si besoin
    }
}
