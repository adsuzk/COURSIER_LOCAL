<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=coursier_local;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "=== DEVICE_TOKENS STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE device_tokens");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
    
    echo "\n=== DEVICE_TOKENS DATA ===\n";
    $stmt = $pdo->query("SELECT * FROM device_tokens LIMIT 5");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode($row)."\n";
    }

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>