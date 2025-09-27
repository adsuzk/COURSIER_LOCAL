<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Vérification tables transactions...\n";

try {
    $stmt = $pdo->query('SHOW TABLES LIKE "%transaction%"');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables trouvées:\n";
    foreach ($tables as $table) {
        echo "  • $table\n";
    }
    
    if (in_array('transactions_financieres', $tables)) {
        echo "\nStructure transactions_financieres:\n";
        $stmt = $pdo->query('DESCRIBE transactions_financieres');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $col) {
            echo "  • {$col['Field']} ({$col['Type']})\n";
        }
    } else {
        echo "\nTable transactions_financieres introuvable!\n";
        
        echo "Création de la table...\n";
        $sql = "CREATE TABLE IF NOT EXISTS transactions_financieres (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('credit', 'debit') NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            compte_type ENUM('coursier', 'client', 'business') NOT NULL,
            compte_id INT NOT NULL,
            reference VARCHAR(100) NOT NULL,
            description TEXT,
            statut ENUM('en_attente', 'reussi', 'echec') DEFAULT 'reussi',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_compte (compte_type, compte_id),
            INDEX idx_reference (reference),
            INDEX idx_date (created_at)
        )";
        
        if ($pdo->exec($sql)) {
            echo "✅ Table créée avec succès!\n";
        } else {
            echo "❌ Erreur création table\n";
        }
    }
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>