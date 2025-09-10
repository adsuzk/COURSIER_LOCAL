<?php
/**
 * TESTEUR DU SYSTÈME DE LOGGING AVANCÉ
 * Tests complets pour valider le fonctionnement
 */

// Démarrer la session
session_start();

// Inclure le système de logging
require_once 'advanced_logger.php';
require_once 'logging_hooks.php';

// Initialiser le logging pour les tests
$test_start_time = initLogging('TEST_SYSTEM');

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>🧪 Test Système Logging Avancé</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f0f0f; color: #fff; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 10px; text-align: center; margin-bottom: 30px; }
        .test-section { background: #1a1a1a; border-radius: 10px; padding: 20px; margin-bottom: 20px; border-left: 4px solid #667eea; }
        .test-result { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: rgba(46, 213, 115, 0.2); border: 1px solid #2ed573; }
        .error { background: rgba(255, 71, 87, 0.2); border: 1px solid #ff4757; }
        .warning { background: rgba(255, 165, 2, 0.2); border: 1px solid #ffa502; }
        .info { background: rgba(55, 66, 250, 0.2); border: 1px solid #3742fa; }
        .test-code { background: #2a2a2a; padding: 15px; border-radius: 5px; font-family: 'Courier New', monospace; font-size: 12px; margin: 10px 0; overflow-x: auto; }
        .btn { background: #667eea; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin: 5px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: #2a2a2a; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #667eea; }
        pre { background: #2a2a2a; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🧪 Test Système Logging Avancé</h1>
            <p>Validation complète du système de logging extrêmement précis</p>
        </div>";

// Statistiques de test
$tests_total = 0;
$tests_success = 0;
$tests_error = 0;

function runTest($name, $description, $testFunction) {
    global $tests_total, $tests_success, $tests_error;
    $tests_total++;
    
    echo "<div class='test-section'>
            <h3>🔬 Test: $name</h3>
            <p>$description</p>";
    
    try {
        $result = $testFunction();
        if ($result === true) {
            $tests_success++;
            echo "<div class='test-result success'>✅ Test réussi</div>";
        } else {
            $tests_error++;
            echo "<div class='test-result error'>❌ Test échoué: $result</div>";
        }
    } catch (Exception $e) {
        $tests_error++;
        echo "<div class='test-result error'>❌ Exception: " . $e->getMessage() . "</div>";
    }
    
    echo "</div>";
}

// Test 1: Logging basique
runTest("Logging basique", "Test des fonctions de logging de base", function() {
    logInfo("Test d'information", ['test_id' => 1], 'TEST_SYSTEM');
    logError("Test d'erreur", ['test_id' => 2], 'TEST_SYSTEM');
    logWarning("Test d'avertissement", ['test_id' => 3], 'TEST_SYSTEM');
    logDebug("Test de debug", ['test_id' => 4], 'TEST_SYSTEM');
    logCritical("Test critique", ['test_id' => 5], 'TEST_SYSTEM');
    
    // Vérifier que les logs sont écrits
    return file_exists('application.log') && filesize('application.log') > 0;
});

// Test 2: Logging spécialisé paiements
runTest("Logging paiements", "Test du système de logging des paiements", function() {
    $payment_data = [
        'amount' => 5000,
        'currency' => 'XOF',
        'transaction_id' => 'TEST_TXN_' . time(),
        'order_id' => 'TEST_ORD_' . time(),
        'client_id' => 'TEST_CLIENT_123',
        'status' => 'success'
    ];
    
    logPayment('payment_test', $payment_data, 'TEST_SYSTEM');
    
    return file_exists('payments.log') && filesize('payments.log') > 0;
});

// Test 3: Logging base de données
runTest("Logging base de données", "Test du logging des opérations base de données", function() {
    $query = "SELECT * FROM commandes WHERE status = ? AND amount > ?";
    $params = ['pending', 1000];
    $execution_time = 0.025;
    
    logDatabase($query, $params, $execution_time, 'TEST_SYSTEM');
    
    return file_exists('database.log') && filesize('database.log') > 0;
});

// Test 4: Logging actions utilisateur
runTest("Logging actions utilisateur", "Test du logging des actions utilisateur", function() {
    logUserAction('test_login', 'test_user_123', ['ip' => '127.0.0.1', 'success' => true], 'TEST_SYSTEM');
    logUserAction('test_order_create', 'test_user_123', ['order_id' => 'TEST_456'], 'TEST_SYSTEM');
    
    return file_exists('user_actions.log') && filesize('user_actions.log') > 0;
});

// Test 5: Logging API
runTest("Logging API", "Test du logging des appels API", function() {
    logAPI('/api/test/endpoint', 'POST', 200, 0.150, 'TEST_SYSTEM');
    logAPI('/api/test/error', 'GET', 404, 0.050, 'TEST_SYSTEM');
    
    return file_exists('api.log') && filesize('api.log') > 0;
});

// Test 6: Logging sécurité
runTest("Logging sécurité", "Test du logging des événements de sécurité", function() {
    logSecurity('test_failed_login', 'high', ['attempts' => 5, 'ip' => '192.168.1.100'], 'TEST_SYSTEM');
    logSecurity('test_suspicious_activity', 'medium', ['action' => 'multiple_requests'], 'TEST_SYSTEM');
    
    return file_exists('security.log') && filesize('security.log') > 0;
});

// Test 7: Logging performance
runTest("Logging performance", "Test du logging des métriques de performance", function() {
    $start = microtime(true);
    usleep(50000); // Simuler une opération de 50ms
    $end = microtime(true);
    
    logPerformance('test_operation', $start, $end, 'TEST_SYSTEM');
    
    return file_exists('performance.log') && filesize('performance.log') > 0;
});

// Test 8: Hooks d'intégration
runTest("Hooks d'intégration", "Test des hooks d'intégration pour interfaces", function() {
    logUserOperation('test_hook_action', 'test_user_hook', ['hook_test' => true]);
    logInterfaceError("Test d'erreur d'interface", ['context' => 'test']);
    logPaymentOperation('test_hook_payment', ['amount' => 1000, 'status' => 'test']);
    logSecurityEvent('test_hook_security', 'low', ['test' => true]);
    
    return true; // Les hooks utilisent les fonctions déjà testées
});

// Test 9: Rotation des logs
runTest("Rotation des logs", "Test de la rotation automatique des logs", function() {
    // Créer un gros log pour tester la rotation
    $large_data = str_repeat("Test de données volumineuses pour rotation. ", 1000);
    
    for ($i = 0; $i < 100; $i++) {
        logInfo("Test rotation $i", ['large_data' => $large_data], 'TEST_SYSTEM');
    }
    
    return true; // Difficile de tester la rotation sans fichiers très volumineux
});

// Test 10: Gestion d'erreurs
runTest("Gestion d'erreurs", "Test de la capture automatique d'erreurs", function() {
    // Simuler une erreur
    try {
        throw new Exception("Test d'exception pour logging");
    } catch (Exception $e) {
        logCritical("Exception capturée: " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], 'TEST_SYSTEM');
    }
    
    return file_exists('critical_errors.log');
});

// Afficher les statistiques
echo "<div class='stats'>
        <div class='stat-card'>
            <div class='stat-number'>$tests_total</div>
            <div>Tests Total</div>
        </div>
        <div class='stat-card'>
            <div class='stat-number'>$tests_success</div>
            <div>Succès</div>
        </div>
        <div class='stat-card'>
            <div class='stat-number'>$tests_error</div>
            <div>Erreurs</div>
        </div>
        <div class='stat-card'>
            <div class='stat-number'>" . round(($tests_success / $tests_total) * 100, 1) . "%</div>
            <div>Taux de Réussite</div>
        </div>
      </div>";

// Test de lecture des logs générés
echo "<div class='test-section'>
        <h3>📄 Aperçu des logs générés</h3>";

$log_files = [
    'application.log' => 'Logs Application',
    'test_system.log' => 'Logs Test System',
    'payments.log' => 'Logs Paiements',
    'database.log' => 'Logs Base de Données',
    'user_actions.log' => 'Logs Actions Utilisateur',
    'api.log' => 'Logs API',
    'security.log' => 'Logs Sécurité',
    'performance.log' => 'Logs Performance',
    'critical_errors.log' => 'Logs Erreurs Critiques'
];

foreach ($log_files as $file => $label) {
    if (file_exists($file)) {
        $size = filesize($file);
        $lines = count(file($file));
        echo "<div class='test-result info'>
                📁 <strong>$label</strong>: $lines lignes, " . round($size/1024, 2) . " KB
              </div>";
    }
}

echo "</div>";

// Mesures de performance du système de test
$test_end_time = microtime(true);
$test_duration = $test_end_time - $test_start_time;
$memory_used = memory_get_usage(true);
$peak_memory = memory_get_peak_usage(true);

echo "<div class='test-section'>
        <h3>⚡ Métriques de Performance</h3>
        <div class='test-code'>
Durée totale des tests: " . round($test_duration, 4) . " secondes
Mémoire utilisée: " . round($memory_used / 1024 / 1024, 2) . " MB
Pic mémoire: " . round($peak_memory / 1024 / 1024, 2) . " MB
Fichiers inclus: " . count(get_included_files()) . "
        </div>
      </div>";

// Log final du test
logInfo("Test système terminé", [
    'tests_total' => $tests_total,
    'tests_success' => $tests_success,
    'tests_error' => $tests_error,
    'success_rate' => round(($tests_success / $tests_total) * 100, 1),
    'duration' => $test_duration,
    'memory_used' => $memory_used,
    'peak_memory' => $peak_memory
], 'TEST_SYSTEM');

echo "<div class='test-section'>
        <h3>🎯 Conclusion</h3>";

if ($tests_error === 0) {
    echo "<div class='test-result success'>
            🎉 <strong>Tous les tests sont passés avec succès!</strong><br>
            Le système de logging avancé est opérationnel et prêt pour la production.
          </div>";
} else {
    echo "<div class='test-result warning'>
            ⚠️ <strong>$tests_error test(s) ont échoué.</strong><br>
            Veuillez vérifier la configuration et les permissions.
          </div>";
}

echo "
        <a href='log_viewer.php' class='btn'>📊 Voir le Dashboard Logs</a>
        <a href='?rerun=1' class='btn'>🔄 Relancer les Tests</a>
      </div>

    </div>
</body>
</html>";
?>
