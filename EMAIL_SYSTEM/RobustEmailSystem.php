<?php
/**
 * SYSTÈME ROBUSTE D'ENVOI D'EMAILS
 * 
 * Fonctionnalités:
 * - Vérification préalable des comptes
 * - Suivi technique complet 
 * - Prévention anti-spam
 * - Logs détaillés
 * - Retry automatique
 * - Validation DNS
 */

class RobustEmailSystem 
{
    private $logFile;
    private $config;
    private $maxRetries = 3;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/logs/email_' . date('Y-m-d') . '.log';
        $this->config = $this->loadConfig();
        
        // Créer le fichier de log si nécessaire
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    private function loadConfig() {
        return [
            'from_email' => 'reply@conciergerie-privee-suzosky.com',
            'from_name' => 'Suzosky Coursier',
            'domain' => 'conciergerie-privee-suzosky.com',
            'dkim_enabled' => false, // À activer si DKIM configuré
            'spf_check' => true,
        ];
    }
    
    /**
     * Log détaillé avec timestamp et contexte
     */
    private function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = "[$timestamp] [$level] [$ip] $message";
        if ($contextStr) {
            $logEntry .= " | Context: $contextStr";
        }
        $logEntry .= "\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Vérifier qu'un compte existe AVANT d'envoyer
     */
    public function verifyAccountExists($emailOrPhone, $pdo) {
        $this->log('INFO', "Vérification existence compte: $emailOrPhone");
        
        try {
            $stmt = $pdo->prepare("SELECT id, email, telephone, nom, prenoms FROM clients_particuliers WHERE email = ? OR telephone = ? LIMIT 1");
            $stmt->execute([$emailOrPhone, $emailOrPhone]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($client) {
                $this->log('SUCCESS', "Compte trouvé ID:{$client['id']}", [
                    'client_id' => $client['id'],
                    'email' => $client['email'],
                    'phone' => $client['telephone']
                ]);
                return $client;
            } else {
                $this->log('WARNING', "Aucun compte trouvé pour: $emailOrPhone");
                return false;
            }
            
        } catch (Exception $e) {
            $this->log('ERROR', "Erreur vérification compte: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validation DNS de l'email pour éviter les bounces
     */
    private function validateEmailDns($email) {
        $domain = substr(strrchr($email, "@"), 1);
        
        if (!$domain) {
            $this->log('ERROR', "Email invalide: $email");
            return false;
        }
        
        // Vérifier l'enregistrement MX
        if (!checkdnsrr($domain, 'MX')) {
            $this->log('WARNING', "Pas d'enregistrement MX pour: $domain");
            return false;
        }
        
        $this->log('SUCCESS', "Validation DNS OK pour: $domain");
        return true;
    }
    
    /**
     * Créer des headers anti-spam optimisés
     */
    private function createAntiSpamHeaders($to, $messageId) {
        $headers = [];
        
        // From avec nom propre
        $headers[] = "From: {$this->config['from_name']} <{$this->config['from_email']}>";
        $headers[] = "Reply-To: {$this->config['from_email']}";
        
        // Message-ID unique
        $headers[] = "Message-ID: <$messageId@{$this->config['domain']}>";
        
        // Headers anti-spam
        $headers[] = "X-Mailer: Suzosky-EmailSystem-v1.0";
        $headers[] = "X-Priority: 3";
        $headers[] = "X-MSMail-Priority: Normal";
        $headers[] = "Importance: Normal";
        
        // Content type proper
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        
        // Authentication results (si DKIM activé)
        if ($this->config['dkim_enabled']) {
            $headers[] = "Authentication-Results: {$this->config['domain']}; dkim=pass";
        }
        
        // Headers de liste pour éviter le spam
        $headers[] = "List-Unsubscribe: <mailto:reply@{$this->config['domain']}>";
        $headers[] = "X-Auto-Response-Suppress: All";
        
        return implode("\r\n", $headers);
    }
    
    /**
     * Charger template HTML anti-spam
     */
    private function loadTemplate($templateName, $variables = []) {
        $templateFile = __DIR__ . "/templates/$templateName.html";
        
        if (!file_exists($templateFile)) {
            $this->log('ERROR', "Template introuvable: $templateName");
            return false;
        }
        
        $content = file_get_contents($templateFile);
        
        // Remplacer les variables
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }
        
        return $content;
    }
    
    /**
     * ENVOI ROBUSTE avec retry automatique
     */
    public function sendPasswordResetEmail($client, $resetToken) {
        $emailId = uniqid('reset_', true);
        $this->log('INFO', "Début envoi email reset", [
            'email_id' => $emailId,
            'client_id' => $client['id'],
            'token' => substr($resetToken, 0, 8) . '...'
        ]);
        
        // 1. Validation DNS
        if (!$this->validateEmailDns($client['email'])) {
            $this->log('ERROR', "Échec validation DNS", ['email_id' => $emailId]);
            return [
                'success' => false,
                'error' => 'Adresse email invalide ou domaine non configuré'
            ];
        }
        
        // 2. Créer le lien sécurisé
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $this->config['domain'];
        $resetLink = "$protocol://$host/sections_index/reset_password.php?token=$resetToken";
        
        // 3. Préparer le message HTML
        $messageId = $emailId . '_' . time();
        
        $htmlMessage = $this->loadTemplate('password_reset', [
            'client_name' => $client['prenoms'] ?? 'Client',
            'reset_link' => $resetLink,
            'expire_time' => '1 heure',
            'company_name' => 'Suzosky Coursier',
            'support_email' => $this->config['from_email'],
            'unsubscribe_link' => "$protocol://$host/unsubscribe?email=" . urlencode($client['email'])
        ]);
        
        if (!$htmlMessage) {
            // Fallback texte simple
            $htmlMessage = $this->createFallbackMessage($client, $resetLink);
        }
        
        // 4. Headers optimisés
        $headers = $this->createAntiSpamHeaders($client['email'], $messageId);
        
        // 5. Sujet optimisé anti-spam
        $subject = 'Réinitialisation sécurisée de votre mot de passe';
        
        // 6. ENVOI avec retry
        $attempt = 0;
        $lastError = '';
        
        while ($attempt < $this->maxRetries) {
            $attempt++;
            $this->log('INFO', "Tentative d'envoi #$attempt", ['email_id' => $emailId]);
            
            $startTime = microtime(true);
            $success = mail($client['email'], $subject, $htmlMessage, $headers);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($success) {
                $this->log('SUCCESS', "Email envoyé avec succès", [
                    'email_id' => $emailId,
                    'attempt' => $attempt,
                    'duration_ms' => $duration,
                    'to' => $client['email']
                ]);
                
                return [
                    'success' => true,
                    'email_id' => $emailId,
                    'message' => 'Email de réinitialisation envoyé avec succès'
                ];
            } else {
                $error = error_get_last();
                $lastError = $error['message'] ?? 'Erreur inconnue';
                
                $this->log('ERROR', "Échec envoi tentative #$attempt", [
                    'email_id' => $emailId,
                    'error' => $lastError,
                    'duration_ms' => $duration
                ]);
                
                if ($attempt < $this->maxRetries) {
                    sleep(2); // Attendre 2 secondes avant retry
                }
            }
        }
        
        // Échec final
        $this->log('CRITICAL', "Échec définitif envoi email", [
            'email_id' => $emailId,
            'attempts' => $this->maxRetries,
            'last_error' => $lastError
        ]);
        
        return [
            'success' => false,
            'error' => "Impossible d'envoyer l'email après {$this->maxRetries} tentatives",
            'technical_error' => $lastError
        ];
    }
    
    /**
     * Message de fallback si template HTML indisponible
     */
    private function createFallbackMessage($client, $resetLink) {
        $name = $client['prenoms'] ?? 'Client';
        
        return "
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Réinitialisation mot de passe</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #D4A853;'>Réinitialisation de votre mot de passe</h2>
                
                <p>Bonjour $name,</p>
                
                <p>Vous avez demandé la réinitialisation de votre mot de passe sur Suzosky Coursier.</p>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' 
                       style='background-color: #D4A853; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                        Réinitialiser mon mot de passe
                    </a>
                </p>
                
                <p><strong>Important:</strong> Ce lien expire dans 1 heure pour votre sécurité.</p>
                
                <p>Si vous n'avez pas demandé cette réinitialisation, ignorez simplement cet email.</p>
                
                <hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>
                
                <p style='font-size: 12px; color: #666;'>
                    Cet email a été envoyé par Suzosky Coursier<br>
                    Si vous avez des questions, contactez-nous à reply@conciergerie-privee-suzosky.com
                </p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Obtenir les statistiques d'envoi
     */
    public function getEmailStats($date = null) {
        $date = $date ?? date('Y-m-d');
        $logFile = __DIR__ . "/logs/email_$date.log";
        
        if (!file_exists($logFile)) {
            return ['total' => 0, 'success' => 0, 'failed' => 0, 'retry' => 0];
        }
        
        $content = file_get_contents($logFile);
        
        return [
            'total' => substr_count($content, 'Début envoi email'),
            'success' => substr_count($content, 'Email envoyé avec succès'),
            'failed' => substr_count($content, 'Échec définitif'),
            'retry' => substr_count($content, 'Tentative d\'envoi #2')
        ];
    }
}
?>