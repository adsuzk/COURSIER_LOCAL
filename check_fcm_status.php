<?php
/**
 * Vérifier les logs et l'état FCM
 */

echo "=== VÉRIFICATION SYSTÈME FCM ===\n\n";

// 1. Logs récents
echo "📋 DERNIERS LOGS (diagnostic_logs/diagnostics_errors.log):\n";
$logFile = 'diagnostic_logs/diagnostics_errors.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -20);
    foreach ($recentLines as $line) {
        echo $line;
    }
} else {
    echo "❌ Fichier log non trouvé\n";
}

echo "\n\n";

// 2. Token FCM du coursier 5
require_once 'config.php';
$pdo = getDBConnection();

echo "🔑 TOKENS FCM DU COURSIER #5:\n";
$stmt = $pdo->query("
    SELECT id, token, is_active, device_type, created_at, updated_at 
    FROM device_tokens 
    WHERE coursier_id = 5
    ORDER BY updated_at DESC
");
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tokens)) {
    echo "❌ AUCUN TOKEN ENREGISTRÉ\n";
} else {
    foreach ($tokens as $t) {
        $active = $t['is_active'] ? '✅ ACTIF' : '❌ INACTIF';
        echo "$active - Token: " . substr($t['token'], 0, 50) . "...\n";
        echo "   Device: {$t['device_type']}\n";
        echo "   Créé: {$t['created_at']}\n";
        echo "   MAJ: {$t['updated_at']}\n\n";
    }
}

echo "\n";

// 3. Notifications FCM envoyées récemment
echo "📲 HISTORIQUE NOTIFICATIONS FCM:\n";
$stmt = $pdo->query("
    SELECT id, coursier_id, message, status, created_at 
    FROM notifications_log_fcm 
    ORDER BY id DESC 
    LIMIT 10
");
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($notifs)) {
    echo "❌ Aucune notification dans l'historique\n";
} else {
    foreach ($notifs as $n) {
        $statusIcon = $n['status'] === 'sent' ? '✅' : '❌';
        echo "$statusIcon [{$n['created_at']}] Coursier #{$n['coursier_id']}\n";
        echo "   " . substr($n['message'], 0, 100) . "\n\n";
    }
}

echo "\n";

// 4. Configuration FCM
echo "⚙️ CONFIGURATION FCM:\n";
require_once 'fcm_manager.php';
$fcm = new FCMManager();
$reflection = new ReflectionClass($fcm);
$serverKeyProp = $reflection->getProperty('serverKey');
$serverKeyProp->setAccessible(true);
$serverKey = $serverKeyProp->getValue($fcm);

if ($serverKey === 'LEGACY_KEY_NOT_CONFIGURED') {
    echo "❌ CLÉ FCM NON CONFIGURÉE (mode fallback activé)\n";
    echo "   Les notifications sont simulées mais pas envoyées réellement\n";
} else {
    echo "✅ Clé FCM configurée: " . substr($serverKey, 0, 20) . "...\n";
}

echo "\n=== FIN VÉRIFICATION ===\n";
?>
