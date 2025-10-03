<?php
/**
 * GESTIONNAIRE D'EMAILS ROBUSTE
 * Système complet de gestion, suivi et délivrance d'emails
 */

class EmailManager {
    private $pdo;
    private $config;
    private $logFile;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->logFile = __DIR__ . '/logs/email_' . date('Y-m-d') . '.log';
        $this->createTables();
        $this->ensureLogDirectory();
    }
    
    /**
     * Créer les tables nécessaires
     */
    private function createTables() {
        // Table des emails envoyés
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255) NULL,
            subject VARCHAR(500) NOT NULL,
            email_type ENUM('password_reset', 'campaign', 'notification', 'welcome') NOT NULL,
            template_used VARCHAR(100) NULL,
            status ENUM('pending', 'sent', 'failed', 'bounced', 'opened', 'clicked') DEFAULT 'pending',
            error_message TEXT NULL,
            headers_used TEXT NULL,
            content_preview TEXT NULL,
            sent_at TIMESTAMP NULL,
            opened_at TIMESTAMP NULL,
            clicked_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            user_agent TEXT NULL,
            ip_address VARCHAR(45) NULL,
            campaign_id INT NULL,
            tracking_id VARCHAR(255) UNIQUE NULL
        )");
        
        // Table des campagnes email
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            template_path VARCHAR(255) NOT NULL,
            recipient_criteria JSON NULL,
            status ENUM('draft', 'scheduled', 'sending', 'sent', 'paused') DEFAULT 'draft',
            scheduled_at TIMESTAMP NULL,
            sent_at TIMESTAMP NULL,
            total_recipients INT DEFAULT 0,
            emails_sent INT DEFAULT 0,
            emails_opened INT DEFAULT 0,
            emails_clicked INT DEFAULT 0,
            created_by VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Table des templates
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            type ENUM('password_reset', 'campaign', 'notification', 'welcome') NOT NULL,
            subject_template VARCHAR(500) NOT NULL,
            html_content TEXT NOT NULL,
            text_content TEXT NULL,
            variables JSON NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }
    
    /**
     * Créer le dossier de logs
     */
    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    /**
     * Logger les événements
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Vérifier si un compte client existe
     */
    public function verifyAccountExists($emailOrPhone) {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenoms, email, telephone 
             FROM clients_particuliers 
             WHERE email = ? OR telephone = ? 
             LIMIT 1"
        );
        $stmt->execute([$emailOrPhone, $emailOrPhone]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Envoyer un email de réinitialisation de mot de passe
     */
    public function sendPasswordReset($emailOrPhone) {
        // 1. Vérifier que le compte existe
        $client = $this->verifyAccountExists($emailOrPhone);
        if (!$client) {
            $this->log("Tentative reset pour compte inexistant: $emailOrPhone", 'WARNING');
            return [
                'success' => false,
                'message' => 'Aucun compte trouvé avec cet email ou téléphone',
                'code' => 'ACCOUNT_NOT_FOUND'
            ];
        }
        
        // 2. Générer token sécurisé
        $token = bin2hex(random_bytes(32)); // Token plus long
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 heure
        $trackingId = 'pwd_' . uniqid() . '_' . time();
        
        // 3. Sauvegarder token
        $stmt = $this->pdo->prepare(
            "UPDATE clients_particuliers 
             SET reset_token = ?, reset_expires_at = ? 
             WHERE id = ?"
        );
        $stmt->execute([$token, $expires, $client['id']]);
        
        // 4. Préparer l'email
        $resetLink = $this->generateResetLink($token, $trackingId);
        $emailData = [
            'recipient' => $client['email'],
            'name' => $client['prenoms'] . ' ' . $client['nom'],
            'subject' => 'Réinitialisation de votre mot de passe - Suzosky Coursier',
            'reset_link' => $resetLink,
            'expires_minutes' => 60,
            'tracking_id' => $trackingId
        ];
        
        // 5. Envoyer avec suivi complet
        return $this->sendTrackedEmail(
            $emailData,
            'password_reset',
            $client['id']
        );
    }
    
    /**
     * Générer un lien de reset sécurisé
     */
    private function generateResetLink($token, $trackingId) {
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "$protocol://$host/sections_index/reset_password.php?token=$token&t=$trackingId";
    }
    
    /**
     * Envoyer un email avec suivi complet
     */
    public function sendTrackedEmail($emailData, $type, $clientId = null, $campaignId = null) {
        $trackingId = $emailData['tracking_id'] ?? uniqid('email_' . time() . '_');
        
        // 1. Logger l'intention d'envoi
        $logId = $this->logEmailAttempt($emailData, $type, $clientId, $campaignId, $trackingId);
        
        try {
            // 2. Charger le template
            $template = $this->loadTemplate($type);
            if (!$template) {
                throw new Exception("Template non trouvé pour le type: $type");
            }
            
            // 3. Générer le contenu
            $content = $this->renderTemplate($template, $emailData, $trackingId);
            
            // 4. Préparer les headers anti-spam
            $headers = $this->buildAntiSpamHeaders($emailData['recipient']);
            
            // 5. Envoyer l'email
            $success = mail(
                $emailData['recipient'],
                $emailData['subject'],
                $content['html'],
                $headers
            );
            
            if ($success) {
                // 6. Marquer comme envoyé
                $this->updateEmailStatus($logId, 'sent', null, $headers);
                $this->log("Email envoyé avec succès à {$emailData['recipient']} (ID: $logId)");
                
                return [
                    'success' => true,
                    'message' => 'Email envoyé avec succès',
                    'tracking_id' => $trackingId,
                    'log_id' => $logId
                ];
            } else {
                throw new Exception('Échec de la fonction mail()');
            }
            
        } catch (Exception $e) {
            // 7. Logger l'erreur
            $this->updateEmailStatus($logId, 'failed', $e->getMessage());
            $this->log("Échec envoi email à {$emailData['recipient']}: " . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'envoi: ' . $e->getMessage(),
                'tracking_id' => $trackingId,
                'log_id' => $logId
            ];
        }
    }
    
    /**
     * Logger une tentative d'email
     */
    private function logEmailAttempt($emailData, $type, $clientId, $campaignId, $trackingId) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO email_logs 
            (recipient_email, recipient_name, subject, email_type, status, 
             tracking_id, campaign_id, content_preview, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $emailData['recipient'],
            $emailData['name'] ?? null,
            $emailData['subject'],
            $type,
            $trackingId,
            $campaignId,
            substr($emailData['subject'], 0, 200),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Mettre à jour le statut d'un email
     */
    private function updateEmailStatus($logId, $status, $errorMessage = null, $headers = null) {
        $fields = "status = ?";
        $params = [$status, $logId];
        
        if ($status === 'sent') {
            $fields .= ", sent_at = NOW()";
        }
        
        if ($errorMessage) {
            $fields .= ", error_message = ?";
            array_unshift($params, $errorMessage);
        }
        
        if ($headers) {
            $fields .= ", headers_used = ?";
            array_unshift($params, $headers);
        }
        
        $stmt = $this->pdo->prepare("UPDATE email_logs SET $fields WHERE id = ?");
        $stmt->execute($params);
    }
    
    /**
     * Construire des headers anti-spam
     */
    private function buildAntiSpamHeaders($recipient) {
        $domain = 'conciergerie-privee-suzosky.com';
        $fromEmail = "reply@$domain";
        
        $headers = [
            "From: Suzosky Coursier <$fromEmail>",
            "Reply-To: $fromEmail",
            "Return-Path: $fromEmail",
            "X-Mailer: Suzosky Email System v1.0",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "X-Priority: 3",
            "X-MSMail-Priority: Normal",
            "Message-ID: <" . uniqid() . "@$domain>",
            "Date: " . date('r'),
            "List-Unsubscribe: <mailto:unsubscribe@$domain>",
            "X-Sender-IP: " . ($_SERVER['SERVER_ADDR'] ?? 'unknown'),
        ];
        
        return implode("\r\n", $headers);
    }
    
    /**
     * Charger un template
     */
    private function loadTemplate($type) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM email_templates 
             WHERE type = ? AND is_active = TRUE 
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->execute([$type]);
        
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Template par défaut si aucun trouvé
        if (!$template) {
            return $this->getDefaultTemplate($type);
        }
        
        return $template;
    }
    
    /**
     * Template par défaut pour reset password
     */
    private function getDefaultTemplate($type) {
        if ($type === 'password_reset') {
            return [
                'html_content' => file_get_contents(__DIR__ . '/templates/password_reset_default.html'),
                'subject_template' => 'Réinitialisation de votre mot de passe - Suzosky Coursier',
                'variables' => json_encode(['name', 'reset_link', 'expires_minutes'])
            ];
        }
        
        return null;
    }
    
    /**
     * Rendre un template avec les variables
     */
    private function renderTemplate($template, $data, $trackingId) {
        $html = $template['html_content'];
        $subject = $template['subject_template'];
        
        // Remplacer les variables
        foreach ($data as $key => $value) {
            $html = str_replace("{{$key}}", htmlspecialchars($value), $html);
            $subject = str_replace("{{$key}}", $value, $subject);
        }
        
        // Ajouter le pixel de tracking
        $trackingPixel = $this->generateTrackingPixel($trackingId);
        $html = str_replace('</body>', $trackingPixel . '</body>', $html);
        
        return [
            'html' => $html,
            'subject' => $subject
        ];
    }
    
    /**
     * Générer un pixel de tracking
     */
    private function generateTrackingPixel($trackingId) {
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "<img src='$protocol://$host/email_system/track.php?t=$trackingId&e=open' width='1' height='1' style='display:none;' />";
    }
    
    /**
     * Obtenir les statistiques d'emails
     */
    public function getEmailStats($days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT 
                email_type,
                status,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM email_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY email_type, status, DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtenir les emails récents
     */
    public function getRecentEmails($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT 
                id, recipient_email, subject, email_type, status, 
                sent_at, opened_at, error_message, created_at
            FROM email_logs 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>