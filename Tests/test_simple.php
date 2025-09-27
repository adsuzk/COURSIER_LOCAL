<?php
// Test simple ultra-basique
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Test Simple</title></head><body>";
echo "<h1>🔍 TEST ULTRA SIMPLE</h1>";
echo "<p>Date: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Version PHP: " . PHP_VERSION . "</p>";

// Test config
$baseDir = dirname(__DIR__);
echo "<p>Répertoire: " . htmlspecialchars($baseDir) . "</p>";

$configPath = $baseDir . '/config.php';
if (file_exists($configPath)) {
    echo "<p style='color:green'>✅ config.php trouvé</p>";
    try {
        require_once $configPath;
        if (function_exists('getDBConnection')) {
            $pdo = getDBConnection();
            echo "<p style='color:green'>✅ Base connectée</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color:red'>❌ config.php manquant</p>";
}

echo "<p>✅ Test terminé</p>";
echo "</body></html>";
?>