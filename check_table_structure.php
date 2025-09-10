<?php
// check_table_structure.php - Vérifier la structure de la table commandes
require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "✅ Connexion à la base de données réussie\n";
    
    // Vérifier la structure de la table commandes
    echo "📋 Structure actuelle de la table commandes:\n";
    $columns = $pdo->query("DESCRIBE commandes")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        echo sprintf("   %-20s %-25s %s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'] === 'NO' ? 'NOT NULL' : 'NULL',
            $column['Key'] ? "({$column['Key']})" : ''
        );
    }
    
    // Vérifier quelques enregistrements
    echo "\n📦 Échantillon de commandes existantes:\n";
    $samples = $pdo->query("SELECT * FROM commandes LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($samples)) {
        echo "   Aucune commande trouvée\n";
    } else {
        foreach ($samples as $sample) {
            echo "   ID: " . $sample['id'] . " - ";
            foreach ($sample as $key => $value) {
                if ($key !== 'id') {
                    echo "$key: $value | ";
                }
            }
            echo "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
