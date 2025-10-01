<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=coursier_local;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "=== COURSIERS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query("DESCRIBE coursiers");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} - {$row['Type']} ({$row['Null']} - {$row['Key']})\n";
    }

    echo "\n=== COURSIERS DISPONIBLES ===\n";
    $stmt = $pdo->query("SELECT * FROM coursiers LIMIT 5");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']} - " . json_encode($row) . "\n";
    }

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>