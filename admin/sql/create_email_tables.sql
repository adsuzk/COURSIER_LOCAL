-- ═══════════════════════════════════════════════════════════════════════
-- TABLES POUR LE SYSTÈME DE GESTION D'EMAILS V2.0
-- ═══════════════════════════════════════════════════════════════════════

-- Table pour les logs d'emails
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT 'general',
  `campaign_id` INT NULL,
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `error_message` TEXT NULL,
  `opened` TINYINT(1) DEFAULT 0,
  `opened_at` DATETIME NULL,
  `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_recipient` (`recipient`),
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_campaign` (`campaign_id`),
  INDEX `idx_sent_at` (`sent_at`),
  INDEX `idx_opened` (`opened`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les campagnes d'emails
CREATE TABLE IF NOT EXISTS `email_campaigns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body` TEXT NOT NULL,
  `target_group` VARCHAR(50) NOT NULL COMMENT 'all, particuliers, business',
  `total_recipients` INT DEFAULT 0,
  `sent_count` INT DEFAULT 0,
  `status` ENUM('draft', 'sending', 'sent', 'failed') DEFAULT 'draft',
  `scheduled_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_target` (`target_group`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les templates d'emails
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL UNIQUE,
  `subject` VARCHAR(500) NOT NULL,
  `body` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT 'general' COMMENT 'general, welcome, order, notification, marketing, support, campaign',
  `variables` TEXT NULL COMMENT 'JSON array of available variables',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_type` (`type`),
  INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les paramètres SMTP (optionnel)
CREATE TABLE IF NOT EXISTS `email_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer des paramètres par défaut
INSERT IGNORE INTO `email_settings` (`setting_key`, `setting_value`) VALUES
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_username', 'reply@conciergerie-privee-suzosky.com'),
('smtp_password', ''),
('from_email', 'reply@conciergerie-privee-suzosky.com'),
('from_name', 'Conciergerie Privée Suzosky'),
('reply_to', 'reply@conciergerie-privee-suzosky.com');

-- Insérer des templates d'exemple
INSERT IGNORE INTO `email_templates` (`name`, `subject`, `body`, `type`) VALUES
('Bienvenue Client', 'Bienvenue chez Coursier Suzosky !', 
'<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<div style="background: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
    <h1 style="color: #1A1A1A; margin: 0;">Bienvenue {{prenom}} !</h1>
</div>
<div style="background: #fff; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 10px 10px;">
    <p style="font-size: 16px; color: #333;">Bonjour <strong>{{prenom}} {{nom}}</strong>,</p>
    <p style="font-size: 16px; color: #333;">Nous sommes ravis de vous accueillir chez <strong>Coursier Suzosky</strong>, votre service de livraison premium à Abidjan.</p>
    <p style="font-size: 16px; color: #333;">Votre compte est maintenant actif et vous pouvez commencer à commander dès maintenant !</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{site_url}}" style="display: inline-block; background: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%); color: #1A1A1A; padding: 15px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;">Commencer</a>
    </div>
    <p style="font-size: 14px; color: #666; margin-top: 30px;">Si vous avez des questions, n\'hésitez pas à nous contacter à <a href="mailto:{{support_email}}" style="color: #D4A853;">{{support_email}}</a></p>
    <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
    <p style="font-size: 12px; color: #999; text-align: center;">© {{annee}} Coursier Suzosky. Tous droits réservés.</p>
</div>
</body></html>', 
'welcome'),

('Confirmation Commande', 'Votre commande #{{commande_id}} a été confirmée', 
'<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<div style="background: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
    <h1 style="color: #1A1A1A; margin: 0;">Commande Confirmée ✅</h1>
</div>
<div style="background: #fff; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 10px 10px;">
    <p style="font-size: 16px; color: #333;">Bonjour <strong>{{prenom}}</strong>,</p>
    <p style="font-size: 16px; color: #333;">Votre commande <strong>#{{commande_id}}</strong> a été confirmée avec succès !</p>
    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px; color: #666;">Numéro de commande:</td>
                <td style="padding: 10px; font-weight: bold; color: #333;">#{{commande_id}}</td>
            </tr>
            <tr>
                <td style="padding: 10px; color: #666;">Montant:</td>
                <td style="padding: 10px; font-weight: bold; color: #D4A853;">{{montant}} FCFA</td>
            </tr>
            <tr>
                <td style="padding: 10px; color: #666;">Statut:</td>
                <td style="padding: 10px; font-weight: bold; color: #10B981;">{{statut}}</td>
            </tr>
            <tr>
                <td style="padding: 10px; color: #666;">Date:</td>
                <td style="padding: 10px; font-weight: bold; color: #333;">{{date}}</td>
            </tr>
        </table>
    </div>
    <p style="font-size: 16px; color: #333;">Nous vous tiendrons informé de l\'évolution de votre commande.</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{site_url}}" style="display: inline-block; background: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%); color: #1A1A1A; padding: 15px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;">Suivre ma commande</a>
    </div>
    <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
    <p style="font-size: 12px; color: #999; text-align: center;">© {{annee}} Coursier Suzosky. Tous droits réservés.</p>
</div>
</body></html>', 
'order'),

('Newsletter', 'Nouveautés Coursier Suzosky', 
'<html><body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
<div style="background: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
    <h1 style="color: #1A1A1A; margin: 0;">📢 Nouveautés & Promotions</h1>
</div>
<div style="background: #fff; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 10px 10px;">
    <p style="font-size: 16px; color: #333;">Bonjour <strong>{{prenom}}</strong>,</p>
    <p style="font-size: 16px; color: #333;">Découvrez nos dernières nouveautés et profitez de nos offres exclusives !</p>
    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
        <h2 style="color: #D4A853; margin-top: 0;">🎉 Offre Spéciale</h2>
        <p style="font-size: 18px; color: #333; margin: 15px 0;">Profitez de <strong style="color: #10B981; font-size: 24px;">-20%</strong> sur votre prochaine commande !</p>
        <p style="font-size: 14px; color: #666;">Valable jusqu\'au {{date}}</p>
    </div>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{site_url}}" style="display: inline-block; background: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%); color: #1A1A1A; padding: 15px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;">En profiter maintenant</a>
    </div>
    <p style="font-size: 14px; color: #666; text-align: center; margin-top: 30px;">Vous recevez cet email car vous êtes inscrit à notre newsletter.<br><a href="{{site_url}}/unsubscribe" style="color: #999; text-decoration: underline;">Se désabonner</a></p>
    <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
    <p style="font-size: 12px; color: #999; text-align: center;">© {{annee}} Coursier Suzosky. Tous droits réservés.</p>
</div>
</body></html>', 
'marketing');

-- Créer des index pour optimiser les performances
ALTER TABLE `email_logs` ADD INDEX IF NOT EXISTS `idx_composite_stats` (`status`, `type`, `sent_at`);
ALTER TABLE `email_logs` ADD INDEX IF NOT EXISTS `idx_opened_analytics` (`opened`, `sent_at`, `opened_at`);

-- Message de succès
SELECT 'Tables créées avec succès pour le système d\'emails V2.0' AS MESSAGE;
