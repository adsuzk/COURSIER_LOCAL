<?php
/**
 * CORRECTION STRUCTURE NOTIFICATIONS_LOG_FCM
 */

require_once 'config.php';

echo "🔧 CORRECTION NOTIFICATIONS_LOG_FCM\n";
echo "=" . str_repeat("=", 40) . "\n";

try {
    $pdo = getDBConnection();
    
    // Vérifier structure
    $stmt = $pdo->query("DESCRIBE notifications_log_fcm");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existingColumns = array_column($columns, 'Field');
    echo "Colonnes: " . implode(', ', $existingColumns) . "\n";
    
    // Ajouter colonnes manquantes
    $columnsToAdd = [
        'type' => "VARCHAR(50) DEFAULT 'general' AFTER message",
        'priority' => "VARCHAR(20) DEFAULT 'normal' AFTER type",
        'retry_count' => "INT DEFAULT 0 AFTER priority"
    ];
    
    foreach ($columnsToAdd as $column => $definition) {
        if (!in_array($column, $existingColumns)) {
            try {
                $sql = "ALTER TABLE notifications_log_fcm ADD COLUMN $column $definition";
                $pdo->exec($sql);
                echo "✅ '$column' ajoutée\n";
            } catch (Exception $e) {
                echo "❌ '$column': " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "✅ Structure corrigée\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>