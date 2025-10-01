<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=coursier_local;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== TOUTES LES COMMANDES DU COURSIER 5 ===\n\n";

$activeStatuses = ['assignee', 'nouvelle', 'acceptee', 'en_cours', 'picked_up', 'recuperee'];
$placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));

$stmt = $pdo->prepare("
    SELECT id, code_commande, statut, created_at
    FROM commandes 
    WHERE coursier_id = 5 AND statut IN ($placeholders)
    ORDER BY created_at DESC
");
$stmt->execute($activeStatuses);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Commandes actives (statuts: " . implode(', ', $activeStatuses) . ") :\n\n";
foreach ($commandes as $cmd) {
    echo "ID: {$cmd['id']} | Code: {$cmd['code_commande']} | Statut: {$cmd['statut']} | Créée: {$cmd['created_at']}\n";
}

echo "\nTotal : " . count($commandes) . " commandes\n";
