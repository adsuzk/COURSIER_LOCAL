<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Structure de la table commandes:\n";
$stmt = $pdo->query('DESCRIBE commandes');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "  • {$col['Field']} ({$col['Type']})\n";
}
?>