<?php
require_once 'config.php';

$pdo = getPDO();
$stmt = $pdo->query('
    SELECT id, nom, prenoms, telephone, statut_connexion, solde_wallet, last_login_at 
    FROM agents_suzosky 
    WHERE last_login_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) 
    ORDER BY last_login_at DESC 
    LIMIT 5
');

echo "=== COURSIERS RÉCENTS ===\n";
while($row = $stmt->fetch()) {
    echo "ID: {$row['id']}, Nom: {$row['nom']} {$row['prenoms']}\n";
    echo "  Solde: {$row['solde_wallet']} FCFA, Statut: {$row['statut_connexion']}\n";
    echo "  Login: {$row['last_login_at']}\n---\n";
}