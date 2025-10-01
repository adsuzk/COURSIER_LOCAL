<?php
require_once 'config.php';
$db = getDbConnection();
$stmt = $db->query("SELECT id, code_commande, statut, mode_paiement, heure_debut, heure_retrait, heure_livraison, cash_recupere FROM commandes WHERE coursier_id=5 ORDER BY id DESC LIMIT 3");
echo "=== État actuel des commandes coursier #5 ===" . PHP_EOL;
echo str_pad('ID', 4) . ' | ' . str_pad('Code', 18) . ' | ' . str_pad('Statut', 10) . ' | ' . str_pad('Paiement', 10) . ' | Cash' . PHP_EOL;
echo str_repeat('-', 60) . PHP_EOL;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo str_pad($row['id'], 4) . ' | ' . str_pad($row['code_commande'], 18) . ' | ' . str_pad($row['statut'], 10) . ' | ' . str_pad($row['mode_paiement'] ?: '-', 10) . ' | ' . ($row['cash_recupere'] ? 'OUI' : 'NON') . PHP_EOL;
    echo "     Début: " . ($row['heure_debut'] ?: 'NULL') . " | Récup: " . ($row['heure_retrait'] ?: 'NULL') . " | Livré: " . ($row['heure_livraison'] ?: 'NULL') . PHP_EOL;
    echo PHP_EOL;
}
