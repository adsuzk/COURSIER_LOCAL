<?php
require_once 'config.php';
$db = getDbConnection();
$stmt = $db->query('DESCRIBE commandes');
echo "=== Structure table commandes ===" . PHP_EOL;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['Field']} ({$row['Type']})" . PHP_EOL;
}
