<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=coursier_local;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $count = $pdo->query("SELECT COUNT(*) FROM coursiers")->fetchColumn();
    echo "Total coursiers: $count\n";
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT id, nom, telephone, statut, disponible FROM coursiers");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dispo = $row['disponible'] ?? 'NULL';
            echo "ID: {$row['id']} - {$row['nom']} ({$row['statut']}, dispo: $dispo)\n";
        }
    } else {
        echo "❌ Aucun coursier dans la base!\n";
        
        // Regardons dans device_tokens pour voir les coursiers connectés
        echo "\n=== DEVICE TOKENS (Coursiers connectés) ===\n";
        $stmt = $pdo->query("SELECT coursier_id, device_token, last_ping FROM device_tokens WHERE last_ping > DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "Coursier ID: {$row['coursier_id']} - Token: " . substr($row['device_token'], 0, 20) . "... - Last ping: {$row['last_ping']}\n";
        }
    }

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>