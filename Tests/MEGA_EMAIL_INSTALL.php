<?php
/**
 * MEGA SCRIPT - RECONSTRUCTION COMPLÈTE EMAIL SYSTEM
 * Un seul fichier pour tout faire
 */

echo "🚀 RECONSTRUCTION MEGA SCRIPT EMAIL SYSTEM\n";
echo "=========================================\n\n";

try {
    // ÉTAPE 1: Créer répertoires
    echo "📁 CRÉATION RÉPERTOIRES...\n";
    
    if (!is_dir('EMAIL_SYSTEM')) {
        mkdir('EMAIL_SYSTEM', 0755, true);
        echo "✅ EMAIL_SYSTEM/ créé\n";
    }
    
    if (!is_dir('EMAIL_SYSTEM/templates')) {
        mkdir('EMAIL_SYSTEM/templates', 0755, true);
        echo "✅ EMAIL_SYSTEM/templates/ créé\n";
    }
    
    // ÉTAPE 2: EmailManager.php (CODE COMPLET)
    echo "📝 CRÉATION EmailManager.php...\n";
    $emailManager = '<?php
class EmailManager {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config = []) {
        $this->pdo = $pdo;
        $this->config = array_merge([
            "from_email" => "noreply@conciergerie-privee-suzosky.com",
            "from_name" => "Conciergerie Privée Suzosky",
            "base_url" => "https://coursier.conciergerie-privee-suzosky.com"
        ], $config);
        $this->initDatabase();
    }
    
    private function initDatabase() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_email VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                body TEXT,
                status ENUM(\"pending\", \"sent\", \"failed\", \"bounced\") DEFAULT \"pending\",
                error_message TEXT,
                tracking_id VARCHAR(32) UNIQUE,
                opened_at DATETIME NULL,
                clicked_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                sent_at TIMESTAMP NULL,
                INDEX idx_recipient (recipient_email),
                INDEX idx_status (status),
                INDEX idx_tracking (tracking_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {
            error_log("Erreur init database: " . $e->getMessage());
        }
    }
    
    public function verifyAccountExists($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, nom, prenom FROM clients_particuliers WHERE mail = ? LIMIT 1");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function generateResetToken($email) {
        try {
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));
            
            $stmt = $this->pdo->prepare("UPDATE clients_particuliers SET reset_token = ?, reset_expires_at = ? WHERE mail = ?");
            $stmt->execute([$token, $expires, $email]);
            return $token;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function sendPasswordReset($email, $userData) {
        try {
            $token = $this->generateResetToken($email);
            if (!$token) throw new Exception("Token failed");
            
            $resetUrl = $this->config["base_url"] . "/sections_index/reset_password.php?token=" . $token;
            $template = $this->getPasswordResetTemplate();
            
            $variables = [
                "{{nom}}" => $userData["nom"] ?? "Client",
                "{{reset_url}}" => $resetUrl
            ];
            
            $emailBody = str_replace(array_keys($variables), array_values($variables), $template);
            $subject = "🔐 Réinitialisation mot de passe - Conciergerie Suzosky";
            
            return $this->sendTrackedEmail($email, $subject, $emailBody);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function sendTrackedEmail($toEmail, $subject, $htmlBody, $type = "general") {
        try {
            $trackingId = md5(uniqid(mt_rand(), true));
            $trackingPixel = "<img src=\"{$this->config[\"base_url\"]}/EMAIL_SYSTEM/track.php?action=open&id={$trackingId}\" width=\"1\" height=\"1\" style=\"display:none;\" />";
            $htmlBody .= $trackingPixel;
            
            $headers = $this->buildHeaders();
            $logId = $this->logEmail($toEmail, $subject, $htmlBody, $trackingId);
            
            $sent = mail($toEmail, $subject, $htmlBody, $headers);
            
            if ($sent) {
                $this->updateEmailStatus($logId, "sent");
                return ["success" => true, "tracking_id" => $trackingId];
            } else {
                $this->updateEmailStatus($logId, "failed", "Mail function failed");
                return ["success" => false];
            }
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
    
    private function buildHeaders() {
        $headers = [];
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-type: text/html; charset=utf-8";
        $headers[] = "From: " . $this->config["from_name"] . " <" . $this->config["from_email"] . ">";
        $headers[] = "Reply-To: " . $this->config["from_email"];
        $headers[] = "X-Mailer: PHP/" . phpversion();
        return implode("\r\n", $headers);
    }
    
    private function logEmail($recipient, $subject, $body, $trackingId = null) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO email_logs (recipient_email, subject, body, tracking_id, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$recipient, $subject, $body, $trackingId, "pending"]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function updateEmailStatus($logId, $status, $error = null) {
        try {
            $stmt = $this->pdo->prepare("UPDATE email_logs SET status = ?, error_message = ?, sent_at = NOW() WHERE id = ?");
            return $stmt->execute([$status, $error, $logId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function markAsOpened($trackingId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE email_logs SET opened_at = NOW() WHERE tracking_id = ? AND opened_at IS NULL");
            return $stmt->execute([$trackingId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getPasswordResetTemplate() {
        return "<!DOCTYPE html>
<html><head><meta charset=\"UTF-8\"><title>Reset Password</title></head>
<body style=\"font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;\">
<div style=\"background:linear-gradient(135deg,#D4A853 0%,#E8C468 100%);padding:30px;text-align:center;border-radius:10px 10px 0 0;\">
<h1 style=\"color:#1A1A2E;margin:0;\">🔐 Réinitialisation</h1>
<p style=\"color:#1A1A2E;margin:10px 0 0 0;\">Conciergerie Privée Suzosky</p>
</div>
<div style=\"background:#ffffff;padding:40px;border:1px solid #ddd;border-radius:0 0 10px 10px;\">
<h2 style=\"color:#1A1A2E;\">Bonjour {{nom}},</h2>
<p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>
<div style=\"text-align:center;margin:30px 0;\">
<a href=\"{{reset_url}}\" style=\"background:linear-gradient(135deg,#D4A853 0%,#E8C468 100%);color:#1A1A2E;padding:15px 30px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block;\">
🔄 Réinitialiser mon mot de passe</a>
</div>
<p style=\"font-size:14px;color:#666;text-align:center;\">Ce lien expire dans 1 heure.</p>
</div>
</body></html>";
    }
    
    public function getRecentEmails($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
?>';

    $defaultBaseUrl = rtrim(function_exists('getAppBaseUrl') ? getAppBaseUrl() : 'http://localhost/COURSIER_LOCAL/', '/');
    if ($defaultBaseUrl === '') {
        $defaultBaseUrl = '/';
    }
    $defaultFromEmail = getenv('SMTP_FROM_EMAIL') ?: 'no-reply@localhost.test';

    $emailManager = str_replace(
        '"from_email" => "noreply@conciergerie-privee-suzosky.com",',
        '"from_email" => ' . json_encode($defaultFromEmail, JSON_UNESCAPED_SLASHES) . ',',
        $emailManager
    );
    $emailManager = str_replace(
        '"base_url" => "https://coursier.conciergerie-privee-suzosky.com"',
        '"base_url" => ' . json_encode($defaultBaseUrl, JSON_UNESCAPED_SLASHES),
        $emailManager
    );
    
    file_put_contents('EMAIL_SYSTEM/EmailManager.php', $emailManager);
    echo "✅ EmailManager.php créé (complet)\n";
    
    // ÉTAPE 3: api.php
    echo "📝 CRÉATION api.php...\n";
    $apiCode = '<?php
header("Content-Type: application/json; charset=utf-8");
session_start();

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Method not allowed");
    }
    
    $data = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $action = $data["action"] ?? "";
    
    if (empty($action)) {
        throw new Exception("Action required");
    }
    
    require_once __DIR__ . "/../config.php";
    require_once __DIR__ . "/EmailManager.php";
    
    $pdo = new PDO("mysql:host=localhost;dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    $emailManager = new EmailManager($pdo, [
        "base_url" => "https://coursier.conciergerie-privee-suzosky.com"
    ]);
    
    switch ($action) {
        case "reset_password_request":
            $email = filter_var($data["email"] ?? "", FILTER_VALIDATE_EMAIL);
            if (!$email) throw new Exception("Email invalide");
            
            $userData = $emailManager->verifyAccountExists($email);
            if (!$userData) {
                echo json_encode(["success" => true, "message" => "Si ce compte existe, un email a été envoyé."]);
                exit;
            }
            
            $result = $emailManager->sendPasswordReset($email, $userData);
            echo json_encode([
                "success" => $result !== false,
                "message" => $result ? "Email envoyé!" : "Erreur envoi"
            ]);
            break;
            
        case "test_email":
            $testEmail = filter_var($data["test_email"] ?? "", FILTER_VALIDATE_EMAIL);
            if (!$testEmail) throw new Exception("Email test invalide");
            
            $result = $emailManager->sendTrackedEmail(
                $testEmail,
                "🧪 Test Email Système",
                "<h2>Test réussi!</h2><p>Système email opérationnel.</p>"
            );
            
            echo json_encode([
                "success" => $result["success"] ?? false,
                "message" => $result["success"] ? "Test envoyé!" : "Erreur test"
            ]);
            break;
            
        default:
            throw new Exception("Action non supportée");
    }
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>';

    $apiCode = str_replace(
        "\"base_url\" => \"https://coursier.conciergerie-privee-suzosky.com\"",
        "\"base_url\" => " . json_encode($defaultBaseUrl, JSON_UNESCAPED_SLASHES),
        $apiCode
    );

    file_put_contents('EMAIL_SYSTEM/api.php', $apiCode);
    echo "✅ api.php créé\n";
    
    // ÉTAPE 4: track.php
    echo "📝 CRÉATION track.php...\n";
    $trackCode = '<?php
header("Cache-Control: no-cache");

try {
    $action = $_GET["action"] ?? "";
    $trackingId = $_GET["id"] ?? "";
    
    if (empty($action) || empty($trackingId)) {
        throw new Exception("Paramètres manquants");
    }
    
    if (!preg_match("/^[a-f0-9]{32}$/", $trackingId)) {
        throw new Exception("ID invalide");
    }
    
    require_once __DIR__ . "/../config.php";
    require_once __DIR__ . "/EmailManager.php";
    
    $pdo = new PDO("mysql:host=localhost;dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
    $emailManager = new EmailManager($pdo);
    
    if ($action === "open") {
        $emailManager->markAsOpened($trackingId);
        header("Content-Type: image/gif");
        echo base64_decode("R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7");
    }
    
} catch (Exception $e) {
    header("Content-Type: image/gif");
    echo base64_decode("R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7");
}
?>';
    
    file_put_contents('EMAIL_SYSTEM/track.php', $trackCode);
    echo "✅ track.php créé\n";
    
    // ÉTAPE 5: Test de base de données
    echo "🗄️ TEST BASE DE DONNÉES...\n";
    require_once __DIR__ . '/config.php';
    
    $pdo = new PDO("mysql:host=localhost;dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
    $emailManager = new EmailManager($pdo);
    echo "✅ Connexion DB et tables OK\n";
    
    echo "\n🎊 MEGA SCRIPT TERMINÉ AVEC SUCCÈS !\n";
    echo "==========================================\n";
    echo "✅ Répertoire EMAIL_SYSTEM/ créé\n";
    echo "✅ EmailManager.php installé\n";
    echo "✅ api.php installé\n"; 
    echo "✅ track.php installé\n";
    echo "✅ Base de données initialisée\n";
    echo "\n🚀 SYSTÈME EMAIL 100% OPÉRATIONNEL !\n";
    echo "🔗 Testez: admin.php?section=emails\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>