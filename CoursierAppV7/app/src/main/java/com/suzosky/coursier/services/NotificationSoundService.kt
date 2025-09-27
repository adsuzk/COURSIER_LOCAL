package com.suzosky.coursier.services

import android.content.Context
import android.media.AudioAttributes
import android.media.AudioManager
import android.media.MediaPlayer
import android.media.RingtoneManager
import android.net.Uri
import android.os.Handler
import android.os.Looper
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.util.Log
import kotlinx.coroutines.*

/**
 * Service pour gérer les notifications sonores et vibrations
 * pour les nouvelles commandes de coursier
 */
class NotificationSoundService(private val context: Context) {
    
    private var mediaPlayer: MediaPlayer? = null
    private var vibrator: Vibrator? = null
    private var soundJob: Job? = null
    private var isPlaying = false
    
    init {
        // Initialiser le vibrateur selon la version Android
        vibrator = if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.S) {
            val vibratorManager = context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as VibratorManager
            vibratorManager.defaultVibrator
        } else {
            @Suppress("DEPRECATION")
            context.getSystemService(Context.VIBRATOR_SERVICE) as Vibrator
        }
    }
    
    /**
     * Jouer le son de notification en continu jusqu'à ce qu'on l'arrête
     */
    fun startNotificationSound() {
        if (isPlaying) {
            Log.d("NotificationSound", "Son déjà en cours de lecture")
            return
        }
        
        try {
            Log.d("NotificationSound", "🔊 Démarrage du son de notification")
            isPlaying = true
            
            // Utiliser le son de notification par défaut du système
            val notificationUri: Uri? = try {
                RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
                    ?: RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE)
            } catch (e: Exception) {
                Log.w("NotificationSound", "Aucun son par défaut disponible: ${e.message}")
                null
            }

            if (notificationUri == null) {
                Log.w("NotificationSound", "Son de notification introuvable, annulation du démarrage")
                isPlaying = false
                return
            }
            
            mediaPlayer = MediaPlayer().apply {
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .setUsage(AudioAttributes.USAGE_NOTIFICATION)
                        .build()
                )
                
                setDataSource(context, notificationUri)
                isLooping = true // Répéter le son en continu
                prepareAsync()
                
                setOnPreparedListener { mp ->
                    Log.d("NotificationSound", "MediaPlayer prêt, démarrage lecture")
                    mp.start()
                    startVibration()
                }
                
                setOnErrorListener { mp, what, extra ->
                    Log.e("NotificationSound", "Erreur MediaPlayer: what=$what, extra=$extra")
                    stopNotificationSound()
                    true
                }
            }
            
        } catch (e: Exception) {
            Log.e("NotificationSound", "Erreur lors du démarrage du son", e)
            isPlaying = false
        }
    }
    
    /**
     * Arrêter le son de notification
     */
    fun stopNotificationSound() {
        Log.d("NotificationSound", "🔇 Arrêt du son de notification")
        
        try {
            mediaPlayer?.let { player ->
                if (player.isPlaying) {
                    player.stop()
                }
                player.release()
            }
            mediaPlayer = null
            
            stopVibration()
            soundJob?.cancel()
            isPlaying = false
            
        } catch (e: Exception) {
            Log.e("NotificationSound", "Erreur lors de l'arrêt du son", e)
        }
    }
    
    /**
     * Démarrer la vibration continue
     */
    private fun startVibration() {
        try {
            vibrator?.let { vib ->
                if (android.os.Build.VERSION.SDK_INT >= android.os.Build.VERSION_CODES.O) {
                    // Pattern: vibrer 1s, pause 0.5s, répéter
                    val pattern = longArrayOf(0, 1000, 500)
                    val vibrationEffect = VibrationEffect.createWaveform(pattern, 0) // 0 = répéter infiniment
                    try {
                        vib.vibrate(vibrationEffect)
                    } catch (e: Exception) {
                        Log.w("NotificationSound", "Impossible de démarrer la vibration: ${e.message}")
                    }
                } else {
                    @Suppress("DEPRECATION")
                    val pattern = longArrayOf(0, 1000, 500)
                    try {
                        vib.vibrate(pattern, 0) // 0 = répéter infiniment
                    } catch (e: Exception) {
                        Log.w("NotificationSound", "Impossible de démarrer la vibration (legacy): ${e.message}")
                    }
                }
                Log.d("NotificationSound", "Vibration démarrée")
            }
        } catch (e: Exception) {
            Log.e("NotificationSound", "Erreur vibration", e)
        }
    }
    
    /**
     * Arrêter la vibration
     */
    private fun stopVibration() {
        try {
            vibrator?.cancel()
            Log.d("NotificationSound", "Vibration arrêtée")
        } catch (e: Exception) {
            Log.e("NotificationSound", "Erreur arrêt vibration", e)
        }
    }
    
    /**
     * Jouer un son unique (non répétitif) pour les actions
     */
    fun playActionSound() {
        try {
            val actionUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            MediaPlayer.create(context, actionUri)?.let { player ->
                player.setOnCompletionListener { mp ->
                    mp.release()
                }
                player.start()
            }
        } catch (e: Exception) {
            Log.e("NotificationSound", "Erreur son d'action", e)
        }
    }
    
    /**
     * Vérifier si le son est en cours de lecture
     */
    fun isPlaying(): Boolean = isPlaying
    
    /**
     * Nettoyer les ressources
     */
    fun release() {
        stopNotificationSound()
    }
}