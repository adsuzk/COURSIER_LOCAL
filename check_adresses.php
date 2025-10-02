<?php
require 'config.php';
$conn = getDBConnection();
$stmt = $conn->query("SHOW COLUMNS FROM commandes WHERE Field LIKE '%adresse%'");
echo "Colonnes d'adresses dans la table commandes:\n";
echo str_repeat("-", 50) . "\n";
while($row = $stmt->fetch()) {
    echo $row['Field'] . "\n";
}
