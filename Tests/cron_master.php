<?php
/**
 * CRON MASTER - Toutes les tâches automatiques en un seul script
 * À exécuter chaque minute : * * * * * /usr/bin/php /home/coursier/public_html/Scripts/Scripts cron/cron_master.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../diagnostic_logs/cron_master.log');

// Log de démarrage
$log_file = __DIR__ . '/../diagnostic_logs/cron_master.log';
$start_time = date('Y-m-d H:i:s');
file_put_contents($log_file, "\n[{$start_time}] 🚀 CRON MASTER - Démarrage\n", FILE_APPEND | LOCK_EX);

try {
    // Chemin de base
    $base_path = dirname(__DIR__);
    
    // Fonction pour exécuter un script
    function executeScript($script_path, $description) {
        global $log_file;
        
        if (!file_exists($script_path)) {
            $msg = "❌ Script manquant: {$description} ({$script_path})\n";
            file_put_contents($GLOBALS['log_file'], "[" . date('Y-m-d H:i:s') . "] {$msg}", FILE_APPEND | LOCK_EX);
            return false;
        }
        
        $start = microtime(true);
        
        // Capturer la sortie du script
        ob_start();
        $success = false;
        
        try {
            include_once $script_path;
            $success = true;
        } catch (Exception $e) {
            $error = $e->getMessage();
            file_put_contents($GLOBALS['log_file'], "[" . date('Y-m-d H:i:s') . "] ❌ Erreur {$description}: {$error}\n", FILE_APPEND | LOCK_EX);
        }
        
        $output = ob_get_clean();
        $duration = round((microtime(true) - $start) * 1000, 2);
        
        $status = $success ? "✅" : "❌";
        $msg = "{$status} {$description} ({$duration}ms)\n";
        file_put_contents($GLOBALS['log_file'], "[" . date('Y-m-d H:i:s') . "] {$msg}", FILE_APPEND | LOCK_EX);
        
        return $success;
    }
    
    // Obtenir le timestamp actuel
    $current_time = time();
    $current_minute = (int)date('i');
    $current_hour = (int)date('H');
    
    // === TÂCHES CHAQUE MINUTE ===
    file_put_contents($log_file, "[{$start_time}] 📋 Exécution tâches minute...\n", FILE_APPEND | LOCK_EX);
    
    // 1. Assignation automatique des commandes (priorité haute)
    executeScript($base_path . '/auto_assign_orders.php', 'Assignation automatique');
    
    // 2. Surveillance temps réel
    executeScript($base_path . '/surveillance_temps_reel.php', 'Surveillance temps réel');
    
    // 3. Assignation sécurisée (depuis Scripts cron)
    executeScript($base_path . '/secure_order_assignment.php', 'Assignation sécurisée');
    
    // === TÂCHES TOUTES LES 5 MINUTES ===
    if ($current_minute % 5 == 0) {
        file_put_contents($log_file, "[{$start_time}] 📋 Exécution tâches 5min...\n", FILE_APPEND | LOCK_EX);
        
        // 3. Mise à jour statuts coursiers
        executeScript($base_path . '/coursier_update_status.php', 'MAJ statuts coursiers');
    }
    
    // === TÂCHES TOUTES LES 15 MINUTES ===
    if ($current_minute % 15 == 0) {
        file_put_contents($log_file, "[{$start_time}] 📋 Exécution tâches 15min...\n", FILE_APPEND | LOCK_EX);
        
        // 4. Nettoyage statuts coursiers
        executeScript($base_path . '/coursier_status_cleanup.php', 'Nettoyage statuts');
    }
    
    // === TÂCHES TOUTES LES HEURES ===
    if ($current_minute == 0) {
        file_put_contents($log_file, "[{$start_time}] 📋 Exécution tâches horaires...\n", FILE_APPEND | LOCK_EX);
        
        // 5. FCM Token Security
        executeScript($base_path . '/fcm_token_security.php', 'Sécurité tokens FCM');
        
        // 6. Nettoyage automatique FCM
        executeScript($base_path . '/fcm_auto_cleanup.php', 'Nettoyage FCM');
        
        // 7. Vérification système
        executeScript($base_path . '/system_health.php', 'Vérification système');
    }
    
    // === TÂCHES QUOTIDIENNES ===
    if ($current_hour == 2 && $current_minute == 0) {
        file_put_contents($log_file, "[{$start_time}] 📋 Exécution tâches quotidiennes...\n", FILE_APPEND | LOCK_EX);
        
        // 8. Nettoyage approfondi base de données
        executeScript($base_path . '/database/cleanup_old_data.php', 'Nettoyage BDD quotidien');
        
        // 9. Sauvegarde logs
        executeScript($base_path . '/diagnostic_logs/rotate_logs.php', 'Rotation logs');
    }
    
    $end_time = date('Y-m-d H:i:s');
    $total_duration = round((microtime(true) - strtotime($start_time)) * 1000, 2);
    
    file_put_contents($log_file, "[{$end_time}] ✅ CRON MASTER terminé ({$total_duration}ms)\n", FILE_APPEND | LOCK_EX);
    
    // Nettoyage du log si trop volumineux (> 1MB)
    if (file_exists($log_file) && filesize($log_file) > 1048576) {
        $lines = file($log_file);
        $recent_lines = array_slice($lines, -1000); // Garder les 1000 dernières lignes
        file_put_contents($log_file, implode('', $recent_lines));
        file_put_contents($log_file, "[{$end_time}] 🧹 Log nettoyé (1000 dernières entrées conservées)\n", FILE_APPEND | LOCK_EX);
    }
    
} catch (Exception $e) {
    $error_time = date('Y-m-d H:i:s');
    $error_msg = "[{$error_time}] 💥 ERREUR CRITIQUE CRON MASTER: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $error_msg, FILE_APPEND | LOCK_EX);
    
    // En cas d'erreur critique, essayer d'envoyer une notification
    if (file_exists($base_path . '/fcm_manager.php')) {
        try {
            include_once $base_path . '/fcm_manager.php';
            // Notifier l'admin de l'erreur (si système de notification configuré)
        } catch (Exception $notification_error) {
            // Ignorer les erreurs de notification
        }
    }
}

// Sortie pour le CRON
echo "CRON MASTER executed at " . date('Y-m-d H:i:s') . "\n";
?>