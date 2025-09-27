<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Vérification et création table notifications_log_fcm...\n";

try {
    $sql = "CREATE TABLE IF NOT EXISTS notifications_log_fcm (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coursier_id INT NOT NULL,
        commande_id INT NULL,
        token_used TEXT NOT NULL,
        message TEXT NOT NULL,
        status ENUM('sent', 'delivered', 'failed') DEFAULT 'sent',
        response_data TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_coursier (coursier_id),
        INDEX idx_commande (commande_id),
        INDEX idx_status (status)
    )";
    
    $pdo->exec($sql);
    echo "✅ Table notifications_log_fcm créée avec succès!\n";
    
    // Vérifier la création
    $stmt = $pdo->query("DESCRIBE notifications_log_fcm");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📋 Structure de la table:\n";
    foreach ($columns as $col) {
        echo "  • {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    
    // Essayer une version simplifiée
    echo "\n🔄 Tentative avec version simplifiée...\n";
    try {
        $sql2 = "CREATE TABLE IF NOT EXISTS notifications_log_fcm (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coursier_id INT NOT NULL,
            commande_id INT NULL,
            token_used TEXT NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'sent',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql2);
        echo "✅ Table simplifiée créée!\n";
        
    } catch (Exception $e2) {
        echo "❌ Erreur version simplifiée: " . $e2->getMessage() . "\n";
    }
}
?>