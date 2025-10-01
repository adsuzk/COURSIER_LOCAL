<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    echo "=== TABLES LIÉES AUX TRANSACTIONS ===\n\n";
    $stmt = $pdo->query("SHOW TABLES LIKE '%transaction%'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
    }
    
    echo "\n=== TABLES LIÉES AUX PAIEMENTS ===\n\n";
    $stmt = $pdo->query("SHOW TABLES LIKE '%paiement%'");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
    }
    
    echo "\n=== STRUCTURE TABLE commandes ===\n\n";
    $stmt = $pdo->query('DESCRIBE commandes');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if (stripos($col['Field'], 'prix') !== false || 
            stripos($col['Field'], 'paiement') !== false ||
            stripos($col['Field'], 'transaction') !== false) {
            echo "Colonne: {$col['Field']} | Type: {$col['Type']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
