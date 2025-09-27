<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Mise à jour activité ZALLE Ismael...\n";
$pdo->exec('UPDATE agents_suzosky SET last_login_at = NOW() WHERE nom = "ZALLE" AND prenoms = "Ismael"');
echo "✅ Activité mise à jour!\n";

// Vérifier
$stmt = $pdo->query('
    SELECT nom, prenoms, last_login_at,
           TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_ago
    FROM agents_suzosky 
    WHERE nom = "ZALLE"
');
$result = $stmt->fetch();

echo "Dernière activité: {$result['last_login_at']} (il y a {$result['minutes_ago']} minutes)\n";
?>