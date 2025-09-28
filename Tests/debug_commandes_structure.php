<?php
require_once 'config.php';

$pdo = getPDO();

echo "=== STRUCTURE TABLE COMMANDES ===\n";
try {
    $stmt = $pdo->query('DESCRIBE commandes');
    while($row = $stmt->fetch()) {
        echo "{$row['Field']} - {$row['Type']}\n";
    }
} catch (Exception $e) {
    echo "Erreur commandes: " . $e->getMessage() . "\n";
}