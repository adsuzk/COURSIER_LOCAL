<?php
require_once 'config.php';

$pdo = getPDO();

echo "=== STRUCTURE TABLE COURSIERS ===\n";
try {
    $stmt = $pdo->query('DESCRIBE coursiers');
    while($row = $stmt->fetch()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
} catch (Exception $e) {
    echo "Erreur coursiers: " . $e->getMessage() . "\n";
}

echo "\n=== STRUCTURE TABLE AGENTS_SUZOSKY ===\n";
try {
    $stmt = $pdo->query('DESCRIBE agents_suzosky');
    while($row = $stmt->fetch()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
} catch (Exception $e) {
    echo "Erreur agents_suzosky: " . $e->getMessage() . "\n";
}