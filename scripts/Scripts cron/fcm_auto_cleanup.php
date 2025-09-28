<?php
/**
 * SURVEILLANCE AUTOMATIQUE FCM TOKENS - TÂCHE CRON
 * Exécution automatique toutes les 5 minutes pour sécurité
 *
 * Exemple de cron: */5 * * * * /usr/bin/php /path/to/Scripts/Scripts cron/fcm_auto_cleanup.php
 */

require_once dirname(__DIR__, 2) . '/config.php';
require_once __DIR__ . '/fcm_token_security.php';

$logsDir = dirname(__DIR__, 2) . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0775, true);
}

$logFile = $logsDir . '/fcm_auto_cleanup.log';
$statsFile = $logsDir . '/fcm_stats_latest.json';

// Log avec timestamp
function logCleanup(string $message, string $level = 'INFO'): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] $message\n";

    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

    if ($level === 'CRITICAL') {
        error_log("[FCM_CLEANUP_CRITICAL] $message");
    }
}

try {
    logCleanup("=== DÉMARRAGE NETTOYAGE AUTOMATIQUE FCM ===");

    $security = new FCMTokenSecurity();

    $results = $security->enforceTokenSecurity();

    if (!empty($results['security_violations'])) {
        logCleanup('VIOLATIONS DÉTECTÉES: ' . count($results['security_violations']), 'CRITICAL');
        foreach ($results['security_violations'] as $violation) {
            $matricule = $violation['matricule'] ?? 'N/A';
            $nom = $violation['nom_complet'] ?? 'Inconnu';
            $statut = $violation['statut'] ?? 'N/A';
            $tokens = $violation['tokens_actifs'] ?? 0;
            $minutes = $violation['minutes_inactif'] ?? 0;
            logCleanup("  - $nom (M:$matricule): $statut, $tokens tokens, inactif $minutes min", 'CRITICAL');
        }
    }

    logCleanup('Tokens désactivés: ' . ($results['tokens_disabled'] ?? 0));
    logCleanup('Sessions nettoyées: ' . ($results['sessions_cleaned'] ?? 0));
    logCleanup('Statut sécurité: ' . ($results['security_status'] ?? 'INCONNU'));

    $orderCapacity = $security->canAcceptNewOrders();
    $canAccept = $orderCapacity['can_accept_orders'] ?? false;
    logCleanup('Acceptation commandes: ' . ($canAccept ? 'OUI' : 'NON'));
    logCleanup('Coursiers disponibles: ' . ($orderCapacity['coursiers_disponibles'] ?? 0));

    if (!$canAccept) {
        logCleanup('⚠️ ALERTE: Aucun coursier disponible - Service suspendu', 'CRITICAL');

        if (function_exists('sendAdminAlert')) {
            sendAdminAlert(
                'Service Suzosky Indisponible',
                'Aucun coursier connecté. Nouvelles commandes suspendues.',
                'high'
            );
        }
    }

    $stats = [
        'timestamp' => time(),
        'violations_count' => count($results['security_violations'] ?? []),
        'tokens_cleaned' => $results['tokens_disabled'] ?? 0,
        'sessions_cleaned' => $results['sessions_cleaned'] ?? 0,
        'system_operational' => $canAccept,
        'available_couriers' => $orderCapacity['coursiers_disponibles'] ?? 0,
    ];

    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));

    logCleanup('=== NETTOYAGE TERMINÉ AVEC SUCCÈS ===');

    if (($results['tokens_disabled'] ?? 0) > 0 || ($results['sessions_cleaned'] ?? 0) > 0) {
        echo 'Nettoyage effectué: ' . ($results['tokens_disabled'] ?? 0) . ' tokens, ' . ($results['sessions_cleaned'] ?? 0) . " sessions\n";
    }
} catch (Exception $e) {
    $errorMsg = 'ERREUR CRITIQUE NETTOYAGE: ' . $e->getMessage();
    logCleanup($errorMsg, 'CRITICAL');
    fwrite(STDERR, $errorMsg . '\n');
    exit(1);
} catch (Throwable $e) {
    $errorMsg = 'ERREUR FATALE NETTOYAGE: ' . $e->getMessage();
    logCleanup($errorMsg, 'CRITICAL');
    fwrite(STDERR, $errorMsg . '\n');
    exit(1);
}

function sendAdminAlert(string $subject, string $message, string $priority = 'medium'): void {
    error_log("[ADMIN_ALERT][$priority] $subject: $message");
}
