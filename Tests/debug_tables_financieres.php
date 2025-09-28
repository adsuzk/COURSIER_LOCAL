<?php
require_once 'config.php';

$pdo = getPDO();

echo "=== STRUCTURE TABLES FINANCIÈRES ===\n\n";

// Vérifier si la table transactions_financieres existe
try {
    $stmt = $pdo->query('DESCRIBE transactions_financieres');
    echo "Structure de transactions_financieres :\n";
    while($row = $stmt->fetch()) {
        echo "- {$row['Field']} ({$row['Type']})\n";
    }
} catch (Exception $e) {
    echo "Table transactions_financieres : " . $e->getMessage() . "\n";
}

echo "\n";

// Chercher d'autres tables de transactions
$tables = ['transactions', 'wallet_transactions', 'financial_transactions', 'recharges'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "Structure de $table :\n";
        while($row = $stmt->fetch()) {
            echo "- {$row['Field']} ({$row['Type']})\n";
        }
        echo "\n";
    } catch (Exception $e) {
        echo "Table $table : inexistante\n";
    }
}