<?php
// system_health.php - Vérification de la santé du système
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== VÉRIFICATION SANTÉ SYSTÈME SUZOSKY ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Environment: " . (defined('FORCE_PRODUCTION_DB') && FORCE_PRODUCTION_DB ? 'PRODUCTION' : 'LOCAL') . "\n\n";

try {
    // 1. Vérifier connexion DB
    echo "1. ✅ BASE DE DONNÉES:\n";
    $stmt = $pdo->query("SELECT DATABASE() as db_name, NOW() as current_time");
    $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Connectée: {$dbInfo['db_name']}\n";
    echo "   Heure serveur: {$dbInfo['current_time']}\n\n";

    // 2. Vérifier agents
    echo "2. 👥 AGENTS COURSIERS:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM agents_suzosky");
    $totalAgents = $stmt->fetchColumn();
    echo "   Total agents: $totalAgents\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
    $stmt->execute();
    $connectes = $stmt->fetchColumn();
    echo "   En ligne: $connectes\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne' AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30");
    $stmt->execute();
    $actifs = $stmt->fetchColumn();
    echo "   Actifs récents: $actifs\n\n";

    // 3. Vérifier fichiers système
    echo "3. 📁 FICHIERS SYSTÈME:\n";
    
    $criticalFiles = [
        'FCM Security' => __DIR__ . '/Scripts/Scripts cron/fcm_token_security.php',
        'Auto Migration' => __DIR__ . '/Scripts/Scripts cron/automated_db_migration.php',
        'FCM Cleanup' => __DIR__ . '/Scripts/Scripts cron/fcm_auto_cleanup.php',
        'Web Trigger' => __DIR__ . '/web_cron_trigger.php'
    ];
    
    foreach ($criticalFiles as $name => $path) {
        $exists = file_exists($path);
        echo "   " . ($exists ? '✅' : '❌') . " $name: " . ($exists ? 'OK' : 'MANQUANT') . "\n";
    }
    echo "\n";

    // 4. Vérifier répertoire logs
    echo "4. 📊 RÉPERTOIRES:\n";
    $logDir = __DIR__ . '/diagnostic_logs';
    $logExists = is_dir($logDir);
    $logWritable = $logExists && is_writable($logDir);
    echo "   " . ($logExists ? '✅' : '❌') . " diagnostic_logs: " . ($logExists ? 'EXISTS' : 'MISSING');
    if ($logExists) {
        echo " (writable: " . ($logWritable ? 'YES' : 'NO') . ")";
    }
    echo "\n";

    $scriptsDir = __DIR__ . '/Scripts';
    $scriptsExists = is_dir($scriptsDir);
    echo "   " . ($scriptsExists ? '✅' : '❌') . " Scripts: " . ($scriptsExists ? 'EXISTS' : 'MISSING') . "\n\n";

    // 5. Tester une requête FCM
    echo "5. 🔐 TEST FCM SECURITY:\n";
    $fcmPath = __DIR__ . '/Scripts/Scripts cron/fcm_token_security.php';
    if (file_exists($fcmPath)) {
        try {
            include_once $fcmPath;
            if (class_exists('FCMTokenSecurity')) {
                echo "   ✅ Classe FCMTokenSecurity chargée\n";
                $fcmSec = new FCMTokenSecurity();
                $canAccept = $fcmSec->canAcceptNewOrders();
                echo "   📊 Peut accepter commandes: " . ($canAccept['can_accept_orders'] ? 'OUI' : 'NON') . "\n";
                if (isset($canAccept['coursiers_disponibles'])) {
                    echo "   👥 Coursiers disponibles: " . $canAccept['coursiers_disponibles'] . "\n";
                }
            } else {
                echo "   ⚠️ Classe FCMTokenSecurity non trouvée\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Erreur FCM: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   ❌ Fichier FCM manquant\n";
    }
    echo "\n";

    // 6. Vérifier tokens FCM
    echo "6. 📱 TOKENS FCM:\n";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'device_tokens'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM device_tokens WHERE is_active = 1");
            $activeTokens = $stmt->fetchColumn();
            echo "   ✅ Tokens actifs: $activeTokens\n";
        } else {
            echo "   ⚠️ Table device_tokens manquante\n";
        }
    } catch (Exception $e) {
        echo "   ⚠️ Erreur tokens: " . $e->getMessage() . "\n";
    }

    echo "\n7. 🎯 STATUT GLOBAL:\n";
    if ($totalAgents > 0 && $logExists && file_exists($fcmPath)) {
        echo "   🟢 SYSTÈME OPÉRATIONNEL\n";
        echo "   ✅ Prêt pour activation automatique\n";
    } else {
        echo "   🟡 SYSTÈME PARTIELLEMENT CONFIGURÉ\n";
        echo "   ⚠️ Vérifiez les éléments manquants ci-dessus\n";
    }

} catch (Exception $e) {
    echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "\nTrace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN VÉRIFICATION ===\n";
?>