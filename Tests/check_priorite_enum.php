<?php
require_once 'config.php';
$pdo = getDBConnection();
$stmt = $pdo->query('SHOW COLUMNS FROM commandes WHERE Field = "priorite"');
$col = $stmt->fetch();
echo "Colonne priorité: ";
print_r($col);
?>