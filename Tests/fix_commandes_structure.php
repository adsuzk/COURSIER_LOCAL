<?php
/**
 * CORRECTION STRUCTURE TABLE COMMANDES
 * Ajout colonnes manquantes pour sync mobile
 */

require_once 'config.php';

echo "🔧 CORRECTION STRUCTURE COMMANDES\n";
echo "=" . str_repeat("=", 40) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier structure actuelle
    echo "\n📋 STRUCTURE ACTUELLE\n";
    
    $stmt = $pdo->query("DESCRIBE commandes");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existingColumns = array_column($columns, 'Field');
    echo "   Colonnes: " . implode(', ', $existingColumns) . "\n";
    
    // 2. Ajouter colonnes manquantes
    echo "\n🔨 AJOUT COLONNES MANQUANTES\n";
    
    $columnsToAdd = [
        'description' => "TEXT NULL AFTER adresse_arrivee",
        'note_client' => "TEXT NULL AFTER description",
        'temps_estime' => "INT NULL COMMENT 'Minutes estimées' AFTER prix_total",
        'distance_km' => "DECIMAL(5,2) NULL AFTER temps_estime"
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        if (!in_array($column, $existingColumns)) {
            try {
                $sql = "ALTER TABLE commandes ADD COLUMN $column $definition";
                $pdo->exec($sql);
                echo "   ✅ Colonne '$column' ajoutée\n";
            } catch (Exception $e) {
                echo "   ❌ Erreur '$column': " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ℹ️ Colonne '$column' existe déjà\n";
        }
    }
    
    echo "\n✅ STRUCTURE CORRIGÉE\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
}
?>