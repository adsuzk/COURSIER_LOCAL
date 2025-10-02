<?php
require 'config.php';
$conn = getDBConnection();
$stmt = $conn->query('DESCRIBE commandes');
echo "Structure de la table commandes:\n";
echo str_repeat("-", 80) . "\n";
printf("%-30s %-30s %s\n", "FIELD", "TYPE", "NULL/DEFAULT");
echo str_repeat("-", 80) . "\n";
while($row = $stmt->fetch()) {
    printf("%-30s %-30s %s\n", $row['Field'], $row['Type'], $row['Null']);
}
