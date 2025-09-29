<?php
// Test direct simulation index.php avec timing précis
echo "=== SIMULATION INDEX.PHP COMPLÈTE ===\n";
$total_start = microtime(true);

// 1. Detector
$step_start = microtime(true);
$deploymentDetectorPath = __DIR__ . '/diagnostic_logs/deployment_error_detector.php';
if (file_exists($deploymentDetectorPath)) {
    require_once $deploymentDetectorPath;
}
echo "Étape 1 - Detector: " . ((microtime(true) - $step_start) * 1000) . "ms\n";

// 2. Config
$step_start = microtime(true);
require_once __DIR__ . '/config.php';
echo "Étape 2 - Config: " . ((microtime(true) - $step_start) * 1000) . "ms\n";

// 3. Session
$step_start = microtime(true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Étape 3 - Session: " . ((microtime(true) - $step_start) * 1000) . "ms\n";

// 4. Logging hooks
$step_start = microtime(true);
if (file_exists(__DIR__ . '/diagnostic_logs/logging_hooks.php')) {
    require_once __DIR__ . '/diagnostic_logs/logging_hooks.php';
    $interface_start_time = initLogging('INDEX');
    logInfo("Test chargement", ['ip_address' => '127.0.0.1'], 'INDEX');
}
echo "Étape 4 - Logging: " . ((microtime(true) - $step_start) * 1000) . "ms\n";

// 5. Web CRON (le suspect principal)
$step_start = microtime(true);
if (file_exists(__DIR__ . '/web_cron_trigger.php')) {
    include_once __DIR__ . '/web_cron_trigger.php';
}
echo "Étape 5 - Web CRON: " . ((microtime(true) - $step_start) * 1000) . "ms\n";

// 6. FCM Security check
$step_start = microtime(true);
$coursiersDisponibles = false;
try {
    $fcmSecurityPath = __DIR__ . '/fcm_token_security.php';
    if (file_exists($fcmSecurityPath)) {
        require_once $fcmSecurityPath;
        $tokenSecurity = new FCMTokenSecurity();
        $tokenSecurity->enforceTokenSecurity();
        $disponibilite = $tokenSecurity->canAcceptNewOrders();
        $coursiersDisponibles = $disponibilite['can_accept_orders'];
    } else {
        // Fallback DB
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne' AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30");
        $stmt->execute();
        $coursiersConnectes = $stmt->fetchColumn();
        $coursiersDisponibles = $coursiersConnectes > 0;
    }
} catch (Exception $e) {
    $coursiersDisponibles = true;
}
echo "Étape 6 - FCM Security: " . ((microtime(true) - $step_start) * 1000) . "ms\n";

// 7. SystemSync
$step_start = microtime(true);
try {
    require_once __DIR__ . '/lib/SystemSync.php';
    $metrics = [
        'request_uri' => '/',
        'host' => 'localhost',
        'session_active' => 1,
        'coursiers_disponibles' => $coursiersDisponibles ? 1 : 0,
    ];
    SystemSync::record('frontend_index', 'ok', $metrics);
} catch (Throwable $e) {
    // Ignorer les erreurs
}
echo "Étape 7 - SystemSync: " . ((microtime(true) - $step_start) * 1000) . "ms\n";

$total_time = (microtime(true) - $total_start) * 1000;
echo "=== TOTAL SIMULATION: {$total_time}ms ===\n";

if ($total_time > 1000) {
    echo "⚠️ LENTEUR DÉTECTÉE! Plus de 1 seconde\n";
}
?>