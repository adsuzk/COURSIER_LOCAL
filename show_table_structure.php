<?php
require_once 'config.php';

$pdo = getDBConnection();
$stmt = $pdo->query('DESCRIBE agents_suzosky');

echo "STRUCTURE TABLE agents_suzosky:\n";
while($row = $stmt->fetch()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
?>