<?php
/**
 * CORRECTION STRUCTURE TABLE DEVICE_TOKENS
 * Mise à jour de la structure pour la synchronisation mobile
 */

require_once 'config.php';

echo "🔧 CORRECTION STRUCTURE DEVICE_TOKENS\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier structure actuelle
    echo "\n📋 1. STRUCTURE ACTUELLE\n";
    
    $stmt = $pdo->query("DESCRIBE device_tokens");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Colonnes existantes:\n";
    foreach ($columns as $col) {
        echo "      • {$col['Field']} ({$col['Type']})\n";
    }
    
    // 2. Ajouter colonnes manquantes
    echo "\n🔨 2. AJOUT COLONNES MANQUANTES\n";
    
    $columnsToAdd = [
        'device_type' => "VARCHAR(50) DEFAULT 'mobile' AFTER token",
        'is_active' => "TINYINT(1) DEFAULT 1 AFTER device_type",
        'device_info' => "TEXT NULL AFTER is_active",
        'last_ping' => "TIMESTAMP NULL AFTER device_info"
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        try {
            $sql = "ALTER TABLE device_tokens ADD COLUMN $column $definition";
            $pdo->exec($sql);
            echo "   ✅ Colonne '$column' ajoutée\n";
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "   ℹ️ Colonne '$column' existe déjà\n";
            } else {
                echo "   ⚠️ Erreur colonne '$column': " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 3. Mettre à jour les enregistrements existants
    echo "\n🔄 3. MISE À JOUR DONNÉES EXISTANTES\n";
    
    // Mettre tous les tokens existants comme actifs s'ils n'ont pas de valeur
    $stmt = $pdo->exec("
        UPDATE device_tokens 
        SET is_active = 1, device_type = 'mobile', last_ping = NOW() 
        WHERE is_active IS NULL OR device_type IS NULL
    ");
    echo "   ✅ $stmt enregistrements mis à jour\n";
    
    // 4. Vérifier la structure finale
    echo "\n📋 4. STRUCTURE FINALE\n";
    
    $stmt = $pdo->query("DESCRIBE device_tokens");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Colonnes finales:\n";
    foreach ($columns as $col) {
        echo "      • {$col['Field']} ({$col['Type']}) {$col['Null']} {$col['Default']}\n";
    }
    
    // 5. Compter les tokens actuels
    echo "\n📊 5. STATISTIQUES TOKENS\n";
    
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as actifs,
            COUNT(DISTINCT coursier_id) as coursiers_uniques
        FROM device_tokens
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   • Total tokens: {$stats['total']}\n";
    echo "   • Tokens actifs: {$stats['actifs']}\n";
    echo "   • Coursiers avec tokens: {$stats['coursiers_uniques']}\n";
    
    echo "\n✅ STRUCTURE CORRIGÉE AVEC SUCCÈS\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
}
?>