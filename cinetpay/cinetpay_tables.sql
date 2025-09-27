-- Ajout de la table order_payments pour le suivi des paiements CinetPay
CREATE TABLE IF NOT EXISTS order_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(120) UNIQUE NOT NULL,
    order_number VARCHAR(60) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_number (order_number),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ajout de la colonne paiement_confirme à la table commandes si elle n'existe pas
ALTER TABLE commandes ADD COLUMN IF NOT EXISTS paiement_confirme TINYINT(1) DEFAULT 0;
ALTER TABLE commandes ADD INDEX IF NOT EXISTS idx_paiement (paiement_confirme);
