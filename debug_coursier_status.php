<?php
require_once 'config.php';

echo "=== ÉTAT DES COURSIERS ===\n\n";

$pdo = getDBConnection();

// Vérifier les coursiers actifs et leur statut de connexion
$stmt = $pdo->query("
    SELECT id, nom, prenoms, matricule, statut_connexion, last_login_at, 
           (SELECT COUNT(*) FROM device_tokens WHERE coursier_id = agents_suzosky.id AND is_active = 1) as fcm_tokens
    FROM agents_suzosky 
    WHERE status = 'actif' 
    ORDER BY last_login_at DESC 
    LIMIT 10
");

echo "COURSIERS ACTIFS:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | {$row['nom']} {$row['prenoms']} | Matricule: {$row['matricule']}\n";
    echo "  Statut connexion: {$row['statut_connexion']}\n";
    echo "  Dernier login: {$row['last_login_at']}\n";
    echo "  Tokens FCM: {$row['fcm_tokens']}\n\n";
}

// Vérifier les dernières commandes
echo "\n=== DERNIÈRES COMMANDES ===\n\n";
$stmt = $pdo->query("
    SELECT id, code_commande, statut, coursier_id, 
           adresse_depart, adresse_arrivee, prix_total, created_at
    FROM commandes 
    ORDER BY created_at DESC 
    LIMIT 5
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Commande #{$row['id']} - {$row['code_commande']}\n";
    echo "  Statut: {$row['statut']}\n";
    echo "  Coursier ID: " . ($row['coursier_id'] ?: 'NON ATTRIBUÉ') . "\n";
    echo "  De: {$row['adresse_depart']}\n";
    echo "  Vers: {$row['adresse_arrivee']}\n";
    echo "  Prix: {$row['prix_total']} FCFA\n";
    echo "  Créée: {$row['created_at']}\n\n";
}
?>
