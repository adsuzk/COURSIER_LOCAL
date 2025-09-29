<?php
// Test des modules de index.php pour identifier la lenteur
$start = microtime(true);

echo "=== DIAGNOSTIC MODULES INDEX.PHP ===\n";

// Test 1: deployment_error_detector.php
$module_start = microtime(true);
$deploymentDetectorPath = __DIR__ . '/diagnostic_logs/deployment_error_detector.php';
if (file_exists($deploymentDetectorPath)) {
    require_once $deploymentDetectorPath;
    $time = (microtime(true) - $module_start) * 1000;
    echo "✅ deployment_error_detector.php: {$time}ms\n";
} else {
    echo "❌ deployment_error_detector.php: ABSENT\n";
}

// Test 2: config.php
$module_start = microtime(true);
require_once __DIR__ . '/config.php';
$time = (microtime(true) - $module_start) * 1000;
echo "✅ config.php: {$time}ms\n";

// Test 3: logging_hooks.php
$module_start = microtime(true);
if (file_exists(__DIR__ . '/diagnostic_logs/logging_hooks.php')) {
    require_once __DIR__ . '/diagnostic_logs/logging_hooks.php';
    $time = (microtime(true) - $module_start) * 1000;
    echo "✅ logging_hooks.php: {$time}ms\n";
} else {
    echo "❌ logging_hooks.php: ABSENT\n";
}

// Test 4: web_cron_trigger.php
$module_start = microtime(true);
if (file_exists(__DIR__ . '/web_cron_trigger.php')) {
    // On simule juste l'inclusion sans exécution
    $size = filesize(__DIR__ . '/web_cron_trigger.php');
    $time = (microtime(true) - $module_start) * 1000;
    echo "⚠️ web_cron_trigger.php: {$time}ms (taille: {$size} bytes) - NON EXÉCUTÉ\n";
} else {
    echo "❌ web_cron_trigger.php: ABSENT\n";
}

// Test 5: FCM Security
$module_start = microtime(true);
$fcmSecurityPath = __DIR__ . '/fcm_token_security.php';
if (file_exists($fcmSecurityPath)) {
    // Simulation inclusion sans exécution
    $size = filesize($fcmSecurityPath);
    $time = (microtime(true) - $module_start) * 1000;
    echo "⚠️ fcm_token_security.php: {$time}ms (taille: {$size} bytes) - NON EXÉCUTÉ\n";
} else {
    echo "❌ fcm_token_security.php: ABSENT\n";
}

// Test 6: SystemSync
$module_start = microtime(true);
if (file_exists(__DIR__ . '/lib/SystemSync.php')) {
    require_once __DIR__ . '/lib/SystemSync.php';
    $time = (microtime(true) - $module_start) * 1000;
    echo "✅ SystemSync.php: {$time}ms\n";
} else {
    echo "❌ SystemSync.php: ABSENT\n";
}

// Test 7: Connexion DB pour vérifier coursiers
$module_start = microtime(true);
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne' AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30");
    $stmt->execute();
    $result = $stmt->fetchColumn();
    $time = (microtime(true) - $module_start) * 1000;
    echo "✅ Requête coursiers disponibles: {$time}ms (résultat: {$result})\n";
} catch (Exception $e) {
    $time = (microtime(true) - $module_start) * 1000;
    echo "❌ Requête coursiers: {$time}ms - ERREUR: " . substr($e->getMessage(), 0, 50) . "...\n";
}

$total_time = (microtime(true) - $start) * 1000;
echo "=== TOTAL DIAGNOSTIC: {$total_time}ms ===\n";
?>