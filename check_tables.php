<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "=== STRUCTURE TABLE CLIENTS ===\n";
$stmt = $pdo->query('DESCRIBE clients');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']}\n";
}

echo "\n=== STRUCTURE TABLE COMMANDES ===\n";
$stmt = $pdo->query('DESCRIBE commandes');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']}\n";
}
?>