<?php
require 'config.php';

$stmt = $pdo->query('SELECT id, statut, cash_recupere, methode_paiement FROM commandes WHERE coursier_id=5 ORDER BY id DESC LIMIT 5');

echo "=== COMMANDES COURSIER #5 ===\n";
while($row = $stmt->fetch()) {
    echo "ID: {$row['id']} | Statut: {$row['statut']} | Cash: {$row['cash_recupere']} | Méthode: {$row['methode_paiement']}\n";
}
