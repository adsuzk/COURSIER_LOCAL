<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

echo "=== STRUCTURE TABLE COMMANDES ===\n";
$stmt = $pdo->query('DESCRIBE commandes');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
