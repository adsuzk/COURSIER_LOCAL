package com.suzosky.coursier.services

import android.content.Context
import android.speech.tts.TextToSpeech
import android.util.Log
import java.util.Locale

class VoiceGuidanceService(context: Context) : TextToSpeech.OnInitListener {
    
    private var tts: TextToSpeech? = null
    private var isReady = false
    
    init {
        tts = TextToSpeech(context, this)
    }
    
    override fun onInit(status: Int) {
        if (status == TextToSpeech.SUCCESS) {
            val result = tts?.setLanguage(Locale.FRENCH)
            
            if (result == TextToSpeech.LANG_MISSING_DATA || result == TextToSpeech.LANG_NOT_SUPPORTED) {
                Log.e("VoiceGuidance", "❌ Français non supporté, utilisation anglais")
                tts?.setLanguage(Locale.US)
            } else {
                Log.d("VoiceGuidance", "✅ TTS initialisé en français")
            }
            
            isReady = true
        } else {
            Log.e("VoiceGuidance", "❌ Erreur initialisation TTS")
        }
    }
    
    fun announceNewOrder(clientName: String, destination: String) {
        speak("Nouvelle commande de $clientName vers $destination")
    }
    
    fun announceOrderAccepted() {
        speak("Commande acceptée. Direction le point de départ")
    }
    
    fun announceDeliveryStarted(destination: String) {
        speak("Livraison démarrée. Direction $destination")
    }
    
    fun announcePackagePickedUp(destination: String) {
        speak("Colis récupéré. Direction $destination")
    }
    
    fun announceDeliveryCompleted() {
        speak("Livraison terminée. Félicitations")
    }
    
    fun announceCashReceived(amount: Double) {
        speak("Paiement cash de ${amount.toInt()} francs reçu")
    }
    
    fun announceWaitingForOrders() {
        speak("En attente de nouvelles commandes")
    }
    
    private fun speak(text: String) {
        if (!isReady) {
            Log.w("VoiceGuidance", "⚠️ TTS pas prêt, message ignoré: $text")
            return
        }
        
        Log.d("VoiceGuidance", "🔊 Annonce: $text")
        tts?.speak(text, TextToSpeech.QUEUE_FLUSH, null, "voiceGuidance${System.currentTimeMillis()}")
    }
    
    fun shutdown() {
        tts?.stop()
        tts?.shutdown()
        Log.d("VoiceGuidance", "🛑 TTS arrêté")
    }
}
