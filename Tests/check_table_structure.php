<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
echo "=== Structure table coursiers ===\n";
$stmt = $pdo->query('SHOW COLUMNS FROM coursiers');
foreach($stmt as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== Structure table agents ===\n";
try {
    $stmt = $pdo->query('SHOW COLUMNS FROM agents');
    foreach($stmt as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Table agents n'existe pas: " . $e->getMessage() . "\n";
}
?>