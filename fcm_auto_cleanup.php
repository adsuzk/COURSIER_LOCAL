<?php
/**
 * SURVEILLANCE AUTOMATIQUE FCM TOKENS - TÂCHE CRON
 * Exécution automatique toutes les 5 minutes pour sécurité
 * 
 * Pour configurer :
 * */5 * * * * /usr/bin/php /path/to/fcm_auto_cleanup.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fcm_token_security.php';

// Log avec timestamp
function logCleanup($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] $message\n";
    
    // Log dans fichier dédié
    file_put_contents(__DIR__ . '/logs/fcm_auto_cleanup.log', $logLine, FILE_APPEND | LOCK_EX);
    
    // Si erreur critique, aussi dans error_log
    if ($level === 'CRITICAL') {
        error_log("[FCM_CLEANUP_CRITICAL] $message");
    }
}

try {
    logCleanup("=== DÉMARRAGE NETTOYAGE AUTOMATIQUE FCM ===");
    
    $security = new FCMTokenSecurity();
    
    // 1. Effectuer le nettoyage de sécurité
    $results = $security->enforceTokenSecurity();
    
    // 2. Logger les résultats
    if (!empty($results['security_violations'])) {
        logCleanup("VIOLATIONS DÉTECTÉES: " . count($results['security_violations']), 'CRITICAL');
        foreach ($results['security_violations'] as $violation) {
            logCleanup("  - {$violation['nom_complet']} (M:{$violation['matricule']}): {$violation['statut']}, {$violation['tokens_actifs']} tokens, inactif {$violation['minutes_inactif']}min", 'CRITICAL');
        }
    }
    
    logCleanup("Tokens désactivés: {$results['tokens_disabled']}");
    logCleanup("Sessions nettoyées: {$results['sessions_cleaned']}");
    logCleanup("Statut sécurité: " . ($results['security_status'] ?? 'INCONNU'));
    
    // 3. Vérifier capacité du système
    $orderCapacity = $security->canAcceptNewOrders();
    logCleanup("Acceptation commandes: " . ($orderCapacity['can_accept_orders'] ? 'OUI' : 'NON'));
    logCleanup("Coursiers disponibles: {$orderCapacity['coursiers_disponibles']}");
    
    // 4. Alertes critiques si système hors service
    if (!$orderCapacity['can_accept_orders']) {
        logCleanup("⚠️ ALERTE: Aucun coursier disponible - Service suspendu", 'CRITICAL');
        
        // Envoyer notification admin si configuré
        if (function_exists('sendAdminAlert')) {
            sendAdminAlert('Service Suzosky Indisponible', 
                          'Aucun coursier connecté. Nouvelles commandes suspendues.',
                          'high');
        }
    }
    
    // 5. Statistiques pour monitoring
    $stats = [
        'timestamp' => time(),
        'violations_count' => count($results['security_violations'] ?? []),
        'tokens_cleaned' => $results['tokens_disabled'] ?? 0,
        'sessions_cleaned' => $results['sessions_cleaned'] ?? 0,
        'system_operational' => $orderCapacity['can_accept_orders'],
        'available_couriers' => $orderCapacity['coursiers_disponibles']
    ];
    
    // Sauvegarder stats JSON pour dashboard
    file_put_contents(__DIR__ . '/logs/fcm_stats_latest.json', json_encode($stats, JSON_PRETTY_PRINT));
    
    logCleanup("=== NETTOYAGE TERMINÉ AVEC SUCCÈS ===");
    
    // Sortie pour cron (silencieuse si tout va bien)
    if ($results['tokens_disabled'] > 0 || $results['sessions_cleaned'] > 0) {
        echo "Nettoyage effectué: {$results['tokens_disabled']} tokens, {$results['sessions_cleaned']} sessions\n";
    }
    
} catch (Exception $e) {
    $errorMsg = "ERREUR CRITIQUE NETTOYAGE: " . $e->getMessage();
    logCleanup($errorMsg, 'CRITICAL');
    
    // Forcer la sortie d'erreur pour cron
    fwrite(STDERR, $errorMsg . "\n");
    exit(1);
} catch (Throwable $e) {
    $errorMsg = "ERREUR FATALE NETTOYAGE: " . $e->getMessage();
    logCleanup($errorMsg, 'CRITICAL');
    
    fwrite(STDERR, $errorMsg . "\n");
    exit(1);
}

/**
 * FONCTION OPTIONNELLE: Alerte admin
 * À personnaliser selon votre système de notifications
 */
function sendAdminAlert($subject, $message, $priority = 'medium') {
    // Exemple avec email (à adapter)
    /*
    $to = 'admin@suzosky.com';
    $headers = 'From: system@suzosky.com';
    mail($to, "[SUZOSKY ALERT] $subject", $message, $headers);
    */
    
    // Exemple avec Telegram (à adapter)
    /*
    $telegramBot = 'YOUR_BOT_TOKEN';
    $chatId = 'YOUR_ADMIN_CHAT_ID';
    $text = "🚨 *$subject*\n\n$message";
    
    file_get_contents("https://api.telegram.org/bot$telegramBot/sendMessage?" . http_build_query([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ]));
    */
    
    // Pour l'instant, juste logger
    error_log("[ADMIN_ALERT] $subject: $message");
}
?>