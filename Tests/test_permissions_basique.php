<?php
/**
 * Test ultra-basique des permissions et de l'exécution PHP
 */

// Forcer l'affichage des erreurs pour le debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Test Permissions</title></head><body>";
echo "<h1>=== TEST PERMISSIONS ET PHP BASIQUE ===</h1>";
echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Script:</strong> " . __FILE__ . "</p>";
echo "<p><strong>Répertoire:</strong> " . getcwd() . "</p>";
echo "<p><strong>Version PHP:</strong> " . PHP_VERSION . "</p>";

// Test 1: Vérifier si on peut lire les fichiers API
echo "<h2>=== TEST 1: LECTURE FICHIERS API ===</h2>";
$baseDir = dirname(__DIR__); // Répertoire parent (coursier_prod)
$apiFiles = [
    $baseDir . '/api/register_device_token_simple.php',
    $baseDir . '/api/timeline_sync.php', 
    $baseDir . '/api/submit_order.php'
];

echo "<ul>";
foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        echo "<li style='color:green'>✅ " . htmlspecialchars($file) . " existe</li>";
        if (is_readable($file)) {
            echo "<li style='color:green'>✅ " . htmlspecialchars($file) . " lisible</li>";
        } else {
            echo "<li style='color:red'>❌ " . htmlspecialchars($file) . " NON lisible</li>";
        }
    } else {
        echo "<li style='color:red'>❌ " . htmlspecialchars($file) . " n'existe pas</li>";
    }
}
echo "</ul>";

// Test 2: Test config.php
echo "<h2>=== TEST 2: CONFIG.PHP ===</h2>";

$configFile = $baseDir . '/config.php';
if (file_exists($configFile)) {
    echo "<p style='color:green'>✅ config.php trouvé</p>";
    try {
        require_once $configFile;
        echo "<p style='color:green'>✅ config.php chargé</p>";
        
        // Test fonction getDBConnection
        if (function_exists('getDBConnection')) {
            echo "<p style='color:green'>✅ Fonction getDBConnection disponible</p>";
            try {
                $pdo = getDBConnection();
                echo "<p style='color:green'>✅ Connexion DB réussie</p>";
                
                // Test requête simple
                $stmt = $pdo->query("SELECT COUNT(*) FROM coursiers");
                $count = $stmt->fetchColumn();
                echo "<p style='color:blue'>📊 Nombre de coursiers: $count</p>";
            } catch (Exception $e) {
                echo "<p style='color:red'>❌ Erreur DB: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p style='color:red'>❌ Fonction getDBConnection manquante</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Erreur config.php: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ config.php non trouvé à: " . htmlspecialchars($configFile) . "</p>";
}

echo "<hr><p><small>🕐 Test terminé le " . date('Y-m-d H:i:s') . "</small></p>";
echo "</body></html>";
    
    // Test table device_tokens
    $stmt = $pdo->query("SELECT COUNT(*) FROM device_tokens");
    $tokenCount = $stmt->fetchColumn();
    echo "✅ Nombre de tokens: $tokenCount\n";
    
} catch (Exception $e) {
    echo "❌ Erreur DB: " . $e->getMessage() . "\n";
}

echo "\n=== FIN TEST BASIQUE ===\n";
?>