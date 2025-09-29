<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
$pdo->exec('CREATE TABLE IF NOT EXISTS notifications_log_fcm (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coursier_id INT NULL,
    commande_id INT NULL,
    title VARCHAR(255) NULL,
    message TEXT NULL,
    status VARCHAR(64) NULL,
    fcm_response_code INT NULL,
    fcm_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');
echo "Table notifications_log_fcm créée ou déjà existante.\n";
