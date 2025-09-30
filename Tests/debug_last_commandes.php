<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, code_commande, order_number, statut, coursier_id, created_at FROM commandes ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Dernières commandes :\n";
foreach ($rows as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
