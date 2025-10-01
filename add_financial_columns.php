<?php
/**
 * Script pour ajouter les colonnes financières aux commandes
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();

echo "=== Ajout colonnes financières à la table commandes ===" . PHP_EOL;

try {
    // Vérifier si colonnes existent déjà
    $stmt = $db->query("DESCRIBE commandes");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $colonnesAAjouter = [
        'frais_service' => "ALTER TABLE commandes ADD COLUMN frais_service DECIMAL(8,2) DEFAULT 0 COMMENT 'Frais débités au coursier'",
        'commission_suzosky' => "ALTER TABLE commandes ADD COLUMN commission_suzosky DECIMAL(8,2) DEFAULT 0 COMMENT 'Commission Suzosky'",
        'gain_coursier' => "ALTER TABLE commandes ADD COLUMN gain_coursier DECIMAL(8,2) DEFAULT 0 COMMENT 'Gain net pour le coursier'"
    ];
    
    foreach ($colonnesAAjouter as $colonne => $sql) {
        if (!in_array($colonne, $columns)) {
            echo "➕ Ajout colonne $colonne..." . PHP_EOL;
            $db->exec($sql);
            echo "✅ Colonne $colonne ajoutée avec succès!" . PHP_EOL;
        } else {
            echo "ℹ️  Colonne $colonne existe déjà" . PHP_EOL;
        }
    }
    
    echo PHP_EOL . "=== Structure mise à jour avec succès ===" . PHP_EOL;
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
