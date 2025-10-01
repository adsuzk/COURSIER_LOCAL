<?php
require_once 'config.php';

$db = getDbConnection();
$stmt = $db->query("SELECT id, code_commande, statut, methode_paiement, heure_livraison, cash_recupere FROM commandes WHERE coursier_id=5 ORDER BY id DESC LIMIT 3");

echo "=== État des commandes coursier #5 ===" . PHP_EOL;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Code: {$row['code_commande']} | Statut: {$row['statut']} | Paiement: {$row['methode_paiement']} | Livré: " . ($row['heure_livraison'] ?? 'NULL') . " | Cash récup: " . ($row['cash_recupere'] ?? 'NULL') . PHP_EOL;
}
