<?php
// Test de création d'une nouvelle table pour démontrer l'auto-migration
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    // Créer une table test pour démontrer l'auto-détection
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS test_auto_migration (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            email VARCHAR(150) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    echo "✅ Table test_auto_migration créée avec succès\n";
    echo "🔄 Maintenant lancez BAT/SYNC_COURSIER_PROD.bat pour voir l'auto-détection !\n";
    
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>