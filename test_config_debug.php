<?php
include_once 'config.php';

echo "=== DEBUG CONFIGURATION DATABASE ===\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'non défini') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'non défini') . "\n";
echo "Environment détecté: " . (isProductionEnvironment() ? 'PRODUCTION' : 'DEVELOPMENT') . "\n";

// Variables d'environnement
echo "\nVariables d'environnement DB:\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'non défini') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'non défini') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: 'non défini') . "\n";

// Test de connexion
echo "\n=== TEST CONNEXION ===\n";
try {
    $start = microtime(true);
    $pdo = getDBConnection();
    $time = round((microtime(true) - $start) * 1000, 2);
    echo "Connexion réussie en {$time}ms\n";
} catch (Exception $e) {
    echo "ERREUR de connexion: " . $e->getMessage() . "\n";
}
?>