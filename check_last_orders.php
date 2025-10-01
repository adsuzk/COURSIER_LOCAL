<?php
require_once 'config.php';
$db = getDBConnection();
$stmt = $db->query("SELECT id, code_commande, statut, coursier_id, created_at FROM commandes ORDER BY id DESC LIMIT 5");
echo "=== DERNIÈRES COMMANDES CRÉÉES ===" . PHP_EOL;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Code: {$row['code_commande']} | Statut: {$row['statut']} | Coursier: " . ($row['coursier_id'] ?: 'NULL') . " | Créée: {$row['created_at']}" . PHP_EOL;
}
