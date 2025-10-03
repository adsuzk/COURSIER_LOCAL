<?php
// PHPMailer wrapper for unified email sending

require_once __DIR__ . '/../config.php';

// Ensure Composer autoload is available
$__vendor = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($__vendor)) {
    throw new RuntimeException('Vendor autoload not found. Please run composer install or deploy vendor/.');
}
require_once $__vendor;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class Mailer
{
    private PHPMailer $mailer;
    private array $smtp;

    public function __construct()
    {
        global $config;
        $this->smtp = $config['smtp'] ?? [];
        $this->mailer = new PHPMailer(true);
        $this->configure();
    }

    private function configure(): void
    {
        $host = trim((string)($this->smtp['host'] ?? ''));
        $username = (string)($this->smtp['username'] ?? '');
        $password = (string)($this->smtp['password'] ?? '');
        $port = (int)($this->smtp['port'] ?? 587);
        $encryption = (string)($this->smtp['encryption'] ?? 'tls');
        $fromEmail = (string)($this->smtp['from_email'] ?? 'no-reply@localhost');
        $fromName = (string)($this->smtp['from_name'] ?? 'Suzosky');
        $smtpDebug = (int)(getenv('SMTP_DEBUG') ?: 0); // 0=off, 2=basic debug

        // Transport: SMTP if host provided, otherwise use mail()
        if ($host !== '') {
            $this->mailer->isSMTP();
            $this->mailer->Host = $host;
            $this->mailer->SMTPAuth = !empty($username);
            if (!empty($username)) {
                $this->mailer->Username = $username;
                $this->mailer->Password = $password;
            }
            $this->mailer->Port = $port > 0 ? $port : 587;
            if (in_array(strtolower($encryption), ['ssl', 'tls'], true)) {
                $this->mailer->SMTPSecure = strtolower($encryption) === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            }
            if ($smtpDebug > 0) {
                $this->mailer->SMTPDebug = $smtpDebug;
                $this->mailer->Debugoutput = 'error_log'; // log to PHP error log
            }
        } else {
            $this->mailer->isMail();
        }

        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->isHTML(true);
        $this->mailer->setFrom($fromEmail, $fromName);
    }

    public function sendHtml(string $toEmail, string $toName, string $subject, string $html, string $altText = ''): array
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($toEmail, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $html;
            $this->mailer->AltBody = $altText !== '' ? $altText : strip_tags($html);
            $this->mailer->send();
            return ['success' => true];
        } catch (PHPMailerException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function renderTemplate(string $templateName, array $vars = []): string
    {
        $base = __DIR__ . '/../EMAIL_SYSTEM/templates/';
        $file = $base . $templateName . '.html';
        if (!file_exists($file)) {
            // fallback default for password reset
            if ($templateName === 'password_reset') {
                $file = $base . 'password_reset_default.html';
            }
        }
        if (!file_exists($file)) {
            // minimal fallback
            $content = '<html><body><h3>' . htmlspecialchars($vars['subject'] ?? 'Message') . '</h3><div>' . htmlspecialchars($vars['content'] ?? '') . '</div></body></html>';
        } else {
            $content = file_get_contents($file) ?: '';
        }

        foreach ($vars as $key => $val) {
            $content = str_replace('{{' . $key . '}}', (string)$val, $content);
        }
        return $content;
    }
}

?>
