<?php
// Test de performance des connexions à la base
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== TEST DE PERFORMANCE BASE DE DONNEES ===\n";
$start_total = microtime(true);

// Test 1: Inclusion config
echo "1. Test inclusion config.php... ";
$start = microtime(true);
try {
    include_once 'config.php';
    echo "OK (" . round((microtime(true) - $start) * 1000, 2) . "ms)\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}

// Test 2: Connexion PDO
echo "2. Test connexion PDO... ";
$start = microtime(true);
try {
    $host = 'localhost';
    $dbname = 'suzosky_coursier';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "OK (" . round((microtime(true) - $start) * 1000, 2) . "ms)\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}

// Test 3: Requête simple
echo "3. Test requête simple (SELECT 1)... ";
$start = microtime(true);
try {
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "OK (" . round((microtime(true) - $start) * 1000, 2) . "ms)\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}

// Test 4: Requête sur table utilisateurs
echo "4. Test requête utilisateurs... ";
$start = microtime(true);
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM utilisateurs LIMIT 1");
    $result = $stmt->fetch();
    echo "OK - " . $result['count'] . " utilisateurs (" . round((microtime(true) - $start) * 1000, 2) . "ms)\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}

// Test 5: Session start
echo "5. Test session_start()... ";
$start = microtime(true);
try {
    session_start();
    echo "OK (" . round((microtime(true) - $start) * 1000, 2) . "ms)\n";
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}

$total_time = microtime(true) - $start_total;
echo "\nTEMPS TOTAL: " . round($total_time * 1000, 2) . "ms\n";
echo "=== FIN TEST ===\n";
?>