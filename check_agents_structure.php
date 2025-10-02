<?php
require 'config.php';
$conn = getDBConnection();
echo "Structure de la table agents_suzosky:\n";
echo str_repeat("-", 80) . "\n";
$stmt = $conn->query('DESCRIBE agents_suzosky');
printf("%-30s %-30s %s\n", "FIELD", "TYPE", "NULL");
echo str_repeat("-", 80) . "\n";
while($row = $stmt->fetch()) {
    printf("%-30s %-30s %s\n", $row['Field'], $row['Type'], $row['Null']);
}
