<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    echo "=== STRUCTURE payment_transactions ===\n\n";
    $stmt = $pdo->query('DESCRIBE payment_transactions');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "Colonne: {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']}\n";
    }
    
    echo "\n=== STRUCTURE transactions ===\n\n";
    $stmt = $pdo->query('DESCRIBE transactions');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "Colonne: {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']}\n";
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
