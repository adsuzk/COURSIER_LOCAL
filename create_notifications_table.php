<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Création table notifications_log_fcm...\n";

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

if ($pdo->exec($sql)) {
    echo "✅ Table notifications_log_fcm créée avec succès!\n";
} else {
    echo "❌ Erreur création table\n";
}
?>