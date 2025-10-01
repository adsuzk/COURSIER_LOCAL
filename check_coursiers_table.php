<?php
require_once __DIR__ . '/config.php';
$pdo = getPDO();

echo "=== STRUCTURE TABLE COURSIERS ===\n";
$stmt = $pdo->query('DESCRIBE coursiers');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== LISTE DES COURSIERS ===\n";
$stmt2 = $pdo->query('SELECT * FROM coursiers');
while($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " | ";
    foreach($row as $key => $val) {
        if ($key != 'id' && $val) {
            echo "$key: $val | ";
        }
    }
    echo "\n";
}
