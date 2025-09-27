<?php
/**
 * RECONSTRUCTEUR AUTOMATIQUE SYSTÈME EMAIL
 * Recrée TOUS les fichiers manquants directement sur le serveur
 */

echo "🔧 RECONSTRUCTION SYSTÈME EMAIL\n";
echo "===============================\n\n";

// Création du répertoire EMAIL_SYSTEM s'il n'existe pas
if (!is_dir('EMAIL_SYSTEM')) {
    mkdir('EMAIL_SYSTEM', 0755, true);
    echo "📁 Répertoire EMAIL_SYSTEM créé\n";
}

if (!is_dir('EMAIL_SYSTEM/templates')) {
    mkdir('EMAIL_SYSTEM/templates', 0755, true);
    echo "📁 Répertoire EMAIL_SYSTEM/templates créé\n";
}

// 1. EmailManager.php
echo "📝 Création EmailManager.php...\n";
$emailManagerContent = '<?php
/**
 * GESTIONNAIRE EMAIL ROBUSTE SUZOSKY
 * Système complet de gestion d\'emails avec tracking et anti-spam
 */

class EmailManager {
    private $pdo;
    private $config;
    private $tablesCreated = false;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->createTables();
    }
    
    /**
     * Créer les tables nécessaires
     */
    private function createTables() {
        if ($this->tablesCreated) return;
        
        try {
            // Table des logs d\'emails
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_email VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                email_type VARCHAR(50) NOT NULL,
                status ENUM(\'pending\', \'sent\', \'failed\', \'opened\', \'clicked\') DEFAULT \'pending\',
                error_message TEXT NULL,
                tracking_id VARCHAR(32) NOT NULL UNIQUE,
                opened_at DATETIME NULL,
                clicked_at DATETIME NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_recipient (recipient_email),
                INDEX idx_status (status),
                INDEX idx_type (email_type),
                INDEX idx_tracking (tracking_id),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Table des campagnes
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                template_id INT NULL,
                status ENUM(\'draft\', \'scheduled\', \'sending\', \'sent\', \'paused\') DEFAULT \'draft\',
                total_recipients INT DEFAULT 0,
                emails_sent INT DEFAULT 0,
                emails_opened INT DEFAULT 0,
                emails_clicked INT DEFAULT 0,
                scheduled_at DATETIME NULL,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Table des templates
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                html_content LONGTEXT NOT NULL,
                variables TEXT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $this->tablesCreated = true;
            
        } catch (PDOException $e) {
            error_log("Erreur création tables email: " . $e->getMessage());
        }
    }
    
    /**
     * Vérifier si un compte existe
     */
    public function verifyAccountExists($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, nom, prenom FROM clients_particuliers WHERE email = ? OR telephone = ? LIMIT 1");
            $stmt->execute([$email, $email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur vérification compte: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envoyer email de reset password
     */
    public function sendPasswordReset($email) {
        $account = $this->verifyAccountExists($email);
        if (!$account) {
            return [\'success\' => false, \'error\' => \'Compte non trouvé\'];
        }
        
        // Générer token
        $token = bin2hex(random_bytes(32));
        $expires = date(\'Y-m-d H:i:s\', strtotime(\'+1 hour\'));
        
        try {
            // Sauvegarder token
            $stmt = $this->pdo->prepare("UPDATE clients_particuliers SET reset_token = ?, reset_expires_at = ? WHERE email = ?");
            $stmt->execute([$token, $expires, $email]);
            
            // Template HTML
            $resetUrl = \'https://\' . $_SERVER[\'HTTP_HOST\'] . \'/sections_index/reset_password.php?token=\' . $token;
            
            $htmlContent = $this->getResetPasswordTemplate([
                \'nom\' => $account[\'nom\'] ?: \'Client\',
                \'reset_url\' => $resetUrl,
                \'expires\' => \'1 heure\'
            ]);
            
            return $this->sendTrackedEmail(
                $email,
                \'Réinitialisation de votre mot de passe - Conciergerie Suzosky\',
                $htmlContent,
                \'password_reset\'
            );
            
        } catch (PDOException $e) {
            error_log("Erreur reset password: " . $e->getMessage());
            return [\'success\' => false, \'error\' => \'Erreur base de données\'];
        }
    }
    
    /**
     * Envoyer email avec tracking
     */
    public function sendTrackedEmail($to, $subject, $htmlContent, $type = \'general\') {
        $trackingId = bin2hex(random_bytes(16));
        
        try {
            // Log initial
            $stmt = $this->pdo->prepare("INSERT INTO email_logs (recipient_email, subject, email_type, status, tracking_id) VALUES (?, ?, ?, \'pending\', ?)");
            $stmt->execute([$to, $subject, $type, $trackingId]);
            $logId = $this->pdo->lastInsertId();
            
            // Ajouter pixel de tracking
            $trackingPixel = \'<img src="https://\' . $_SERVER[\'HTTP_HOST\'] . \'/email_system/track.php?t=\' . $trackingId . \'" width="1" height="1" style="display:none;">\';
            $htmlContent .= $trackingPixel;
            
            // Headers anti-spam
            $headers = $this->buildAntiSpamHeaders();
            
            // Envoi simple avec mail() pour compatibilité
            $sent = mail($to, $subject, $htmlContent, $headers);
            
            if ($sent) {
                $this->pdo->prepare("UPDATE email_logs SET status = \'sent\' WHERE id = ?")->execute([$logId]);
                return [\'success\' => true, \'tracking_id\' => $trackingId];
            } else {
                $this->pdo->prepare("UPDATE email_logs SET status = \'failed\', error_message = \'Envoi échoué\' WHERE id = ?")->execute([$logId]);
                return [\'success\' => false, \'error\' => \'Envoi échoué\'];
            }
            
        } catch (Exception $e) {
            error_log("Erreur envoi email: " . $e->getMessage());
            return [\'success\' => false, \'error\' => $e->getMessage()];
        }
    }
    
    /**
     * Construire headers anti-spam
     */
    private function buildAntiSpamHeaders() {
        $headers = [];
        $headers[] = \'MIME-Version: 1.0\';
        $headers[] = \'Content-Type: text/html; charset=UTF-8\';
        $headers[] = \'From: \' . ($this->config[\'from_name\'] ?? \'Suzosky\') . \' <\' . ($this->config[\'from_email\'] ?? \'noreply@suzosky.com\') . \'>\';
        $headers[] = \'Reply-To: \' . ($this->config[\'reply_to\'] ?? $this->config[\'from_email\'] ?? \'noreply@suzosky.com\');
        $headers[] = \'Return-Path: \' . ($this->config[\'from_email\'] ?? \'noreply@suzosky.com\');
        $headers[] = \'X-Mailer: Suzosky Email System 1.0\';
        $headers[] = \'X-Priority: 3\';
        $headers[] = \'X-MSMail-Priority: Normal\';
        
        return implode("\\r\\n", $headers);
    }
    
    /**
     * Template reset password
     */
    private function getResetPasswordTemplate($vars) {
        return \'<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation mot de passe</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #D4A853 0%, #E8C468 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: #1A1A2E; margin: 0; font-size: 28px;">🔐 Réinitialisation</h1>
        <p style="color: #1A1A2E; margin: 10px 0 0 0; opacity: 0.9;">Conciergerie Privée Suzosky</p>
    </div>
    
    <div style="background: #ffffff; padding: 40px; border: 1px solid #ddd; border-radius: 0 0 10px 10px;">
        <h2 style="color: #1A1A2E; margin-bottom: 20px;">Bonjour \' . htmlspecialchars($vars[\'nom\']) . \',</h2>
        
        <p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte Conciergerie Privée Suzosky.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="\' . $vars[\'reset_url\'] . \'" style="background: linear-gradient(135deg, #D4A853 0%, #E8C468 100%); color: #1A1A2E; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                🔄 Réinitialiser mon mot de passe
            </a>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="color: #dc3545; margin-top: 0;">⚠️ Important :</h3>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>Ce lien expire dans <strong>\' . $vars[\'expires\'] . \'</strong></li>
                <li>Si vous n\\\'avez pas demandé cette réinitialisation, ignorez cet email</li>
                <li>Utilisez un mot de passe sécurisé (8+ caractères, majuscules, chiffres)</li>
            </ul>
        </div>
        
        <p style="font-size: 14px; color: #666; text-align: center; margin-top: 30px;">
            Cet email a été envoyé automatiquement, merci de ne pas y répondre.<br>
            Pour toute question, contactez notre support client.
        </p>
    </div>
    
    <div style="text-align: center; padding: 20px; font-size: 12px; color: #888;">
        © 2025 Conciergerie Privée Suzosky - Tous droits réservés
    </div>
</body>
</html>\';
    }
    
    /**
     * Obtenir statistiques
     */
    public function getEmailStats($days = 7) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    status,
                    COUNT(*) as count
                FROM email_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at), status
                ORDER BY date DESC
            ");
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Obtenir emails récents
     */
    public function getRecentEmails($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
?>';

file_put_contents('email_system/EmailManager.php', $emailManagerContent);
echo "✅ EmailManager.php créé\n";

// 2. API
echo "📝 Création api.php...\n";
$apiContent = '<?php
/**
 * API INDÉPENDANTE SYSTÈME EMAIL
 */

header(\'Content-Type: application/json\');
header(\'X-Powered-By: Suzosky Email System\');

require_once __DIR__ . \'/../config.php\';
require_once __DIR__ . \'/EmailManager.php\';

try {
    $pdo = getPDO();
    $emailConfig = [
        \'smtp_host\' => defined(\'SMTP_HOST\') ? SMTP_HOST : \'smtp.gmail.com\',
        \'smtp_port\' => defined(\'SMTP_PORT\') ? SMTP_PORT : 587,
        \'smtp_username\' => defined(\'SMTP_USERNAME\') ? SMTP_USERNAME : \'\',
        \'smtp_password\' => defined(\'SMTP_PASSWORD\') ? SMTP_PASSWORD : \'\',
        \'from_email\' => defined(\'SMTP_FROM_EMAIL\') ? SMTP_FROM_EMAIL : \'noreply@suzosky.com\',
        \'from_name\' => defined(\'SMTP_FROM_NAME\') ? SMTP_FROM_NAME : \'Conciergerie Suzosky\',
    ];
    
    $emailManager = new EmailManager($pdo, $emailConfig);
    
    $action = $_POST[\'action\'] ?? $_GET[\'action\'] ?? \'\';
    
    switch ($action) {
        case \'reset_password_request\':
            $email = $_POST[\'email_or_phone\'] ?? \'\';
            
            if (empty($email)) {
                echo json_encode([\'success\' => false, \'error\' => \'Email requis\']);
                break;
            }
            
            $result = $emailManager->sendPasswordReset($email);
            
            if ($result[\'success\']) {
                echo json_encode([
                    \'success\' => true,
                    \'message\' => \'Si ce compte existe, un email de réinitialisation a été envoyé.\'
                ]);
            } else {
                echo json_encode([
                    \'success\' => true, // Toujours true pour ne pas révéler l\'existence du compte
                    \'message\' => \'Si ce compte existe, un email de réinitialisation a été envoyé.\'
                ]);
            }
            break;
            
        case \'get_stats\':
            $stats = $emailManager->getEmailStats();
            echo json_encode([\'success\' => true, \'stats\' => $stats]);
            break;
            
        default:
            echo json_encode([\'success\' => false, \'error\' => \'Action non reconnue\']);
    }
    
} catch (Exception $e) {
    error_log("Erreur API email: " . $e->getMessage());
    echo json_encode([\'success\' => false, \'error\' => \'Erreur système\']);
}
?>';

file_put_contents('email_system/api.php', $apiContent);
echo "✅ api.php créé\n";

// 3. Tracking
echo "📝 Création track.php...\n";
$trackContent = '<?php
/**
 * SYSTÈME DE TRACKING EMAIL
 */

require_once __DIR__ . \'/../config.php\';

if (isset($_GET[\'t\'])) {
    $trackingId = $_GET[\'t\'];
    
    try {
        $pdo = getPDO();
        
        // Marquer comme ouvert
        $stmt = $pdo->prepare("UPDATE email_logs SET status = \'opened\', opened_at = NOW(), ip_address = ?, user_agent = ? WHERE tracking_id = ? AND opened_at IS NULL");
        $stmt->execute([
            $_SERVER[\'REMOTE_ADDR\'] ?? \'\',
            $_SERVER[\'HTTP_USER_AGENT\'] ?? \'\',
            $trackingId
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur tracking: " . $e->getMessage());
    }
}

// Retourner pixel transparent
header(\'Content-Type: image/png\');
header(\'Cache-Control: no-cache, no-store, must-revalidate\');
header(\'Pragma: no-cache\');
header(\'Expires: 0\');

// Pixel 1x1 transparent
echo base64_decode(\'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==\');
?>';

file_put_contents('email_system/track.php', $trackContent);
echo "✅ track.php créé\n";

echo "\n🎊 TOUS LES FICHIERS CRITIQUES CRÉÉS !\n";
echo "🔄 Maintenant relancez post_deploy_email.php\n";
?>