<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    echo "=== STRUCTURE DE transactions_financieres ===\n\n";
    $stmt = $pdo->query('DESCRIBE transactions_financieres');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "Colonne: {$col['Field']} | Type: {$col['Type']} | Null: {$col['Null']} | Défaut: {$col['Default']}\n";
    }
    
    echo "\n=== EXEMPLE DE DONNÉES ===\n\n";
    $stmt = $pdo->query('SELECT * FROM transactions_financieres LIMIT 3');
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
