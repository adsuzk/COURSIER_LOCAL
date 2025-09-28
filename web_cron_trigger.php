<?php
// web_cron_trigger.php - Déclencheur automatique des tâches CRON via web
// Ce fichier s'exécute automatiquement à chaque visite pour simuler les CRON

$webCronFlagFile = __DIR__ . '/diagnostic_logs/webcron_active.flag';
$lastRunFile = __DIR__ . '/diagnostic_logs/webcron_last_run.txt';

// Vérifier si le CRON automatique est activé
if (!file_exists($webCronFlagFile)) {
    return; // Pas activé, ne rien faire
}

// Lire la dernière exécution
$lastRun = file_exists($lastRunFile) ? (int)file_get_contents($lastRunFile) : 0;
$now = time();

// Exécuter seulement si plus de 1 heure s'est écoulée
if ($now - $lastRun < 3600) {
    return; // Trop récent, ne pas exécuter
}

try {
    // Mettre à jour l'horodatage
    file_put_contents($lastRunFile, $now);
    
    // Log de démarrage
    $logFile = __DIR__ . '/diagnostic_logs/webcron.log';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Démarrage CRON automatique\n", FILE_APPEND);
    
    // 1. Exécuter FCM Token Security (le plus important)
    $fcmSecurityPath = __DIR__ . '/Scripts/Scripts cron/fcm_token_security.php';
    if (file_exists($fcmSecurityPath)) {
        ob_start();
        include $fcmSecurityPath;
        $output = ob_get_clean();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - FCM Security exécuté\n", FILE_APPEND);
    }
    
    // 2. Exécuter Auto Migration (si c'est l'heure - 2h du matin)
    $hour = (int)date('H');
    if ($hour >= 2 && $hour <= 3) { // Entre 2h et 3h
        $migrationPath = __DIR__ . '/Scripts/Scripts cron/automated_db_migration.php';
        if (file_exists($migrationPath)) {
            ob_start();
            include $migrationPath;
            $output = ob_get_clean();
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Migration automatique exécutée\n", FILE_APPEND);
        }
    }
    
    // 3. Exécuter FCM Cleanup toutes les 6 heures
    if ($hour % 6 === 0) {
        $cleanupPath = __DIR__ . '/Scripts/Scripts cron/fcm_auto_cleanup.php';
        if (file_exists($cleanupPath)) {
            ob_start();
            include $cleanupPath;
            $output = ob_get_clean();
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - FCM Cleanup exécuté\n", FILE_APPEND);
        }
    }
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - CRON automatique terminé\n", FILE_APPEND);
    
} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERREUR: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>