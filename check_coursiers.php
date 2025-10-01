<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=coursier_local;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "=== COURSIERS DISPONIBLES ===\n";
    $stmt = $pdo->query("SELECT id, nom, prenom, telephone, statut FROM coursiers LIMIT 10");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']} - {$row['nom']} {$row['prenom']} ({$row['statut']})\n";
    }

    echo "\n=== FOREIGN KEY INFO ===\n";
    $stmt = $pdo->query("SHOW CREATE TABLE commandes");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $result['Create Table']."\n";

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>