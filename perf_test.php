<?php
// Test simple de performance et connexion DB
$start = microtime(true);

echo "=== TEST PERFORMANCE ===\n";
echo "Début: " . date('H:i:s.u') . "\n";

// Test 1: Chargement config
$config_start = microtime(true);
require_once 'config.php';
$config_time = (microtime(true) - $config_start) * 1000;
echo "Config chargé en: {$config_time}ms\n";

// Test 2: Détection environnement
$env_start = microtime(true);
$isProd = isProductionEnvironment();
$env_time = (microtime(true) - $env_start) * 1000;
echo "Environnement détecté: " . ($isProd ? 'PRODUCTION' : 'DEVELOPMENT') . " en {$env_time}ms\n";

// Test 3: Variables d'environnement 
echo "FORCE_LOCAL: " . (getenv('FORCE_LOCAL') ? 'OUI' : 'NON') . "\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'non défini') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'non défini') . "\n";

// Test 4: Connexion DB
$db_start = microtime(true);
try {
    $pdo = getDBConnection();
    $db_time = (microtime(true) - $db_start) * 1000;
    echo "DB connectée en: {$db_time}ms\n";
    
    // Test simple SQL
    $sql_start = microtime(true);
    $result = $pdo->query("SELECT 1 as test")->fetch();
    $sql_time = (microtime(true) - $sql_start) * 1000;
    echo "Requête SELECT 1 en: {$sql_time}ms\n";
    
} catch (Exception $e) {
    $db_time = (microtime(true) - $db_start) * 1000;
    echo "ERREUR DB après {$db_time}ms: " . $e->getMessage() . "\n";
}

$total_time = (microtime(true) - $start) * 1000;
echo "=== TOTAL: {$total_time}ms ===\n";
?>