<?php
/**
 * API ADMINISTRATEUR POUR LE SYSTEME EMAIL
 * Fournit des endpoints JSON pour le tableau de bord admin (stats, campagnes, templates…).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/EmailManager.php';

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $config = $config ?? [];
    $emailManager = new EmailManager($pdo, $config);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Impossible de se connecter à la base de données',
        'error' => $e->getMessage()
    ], 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if (!$action) {
    respond([
        'success' => false,
        'message' => 'Action manquante'
    ], 400);
}

switch ($action) {
    case 'get_stats':
        handleGetStats($pdo);
        break;

    case 'get_email':
        handleGetEmail($pdo);
        break;

    case 'retry_email':
        handleRetryEmail($pdo, $emailManager, $method);
        break;

    case 'get_campaign':
        handleGetCampaign($pdo);
        break;

    case 'create_campaign':
    case 'update_campaign':
        handleSaveCampaign($pdo, $action === 'update_campaign');
        break;

    case 'send_campaign':
        handleSendCampaign($pdo, $method);
        break;

    case 'get_templates':
        handleGetTemplates($pdo);
        break;

    case 'create_template':
    case 'update_template':
        handleSaveTemplate($pdo, $action === 'update_template');
        break;

    default:
        respond([
            'success' => false,
            'message' => 'Action non supportée'
        ], 400);
}

function handleGetStats(PDO $pdo): void
{
    try {
        $statsQuery = $pdo->query(
            "SELECT 
                SUM(status = 'sent') AS sent,
                SUM(status = 'failed') AS failed,
                SUM(status = 'opened') AS opened,
                SUM(status = 'clicked') AS clicked,
                COUNT(*) AS total
             FROM email_logs"
        );
        $totals = $statsQuery->fetch(PDO::FETCH_ASSOC) ?: [];

        $sent = (int)($totals['sent'] ?? 0);
        $failed = (int)($totals['failed'] ?? 0);
        $opened = (int)($totals['opened'] ?? 0);
        $clicked = (int)($totals['clicked'] ?? 0);

        $openRate = $sent > 0 ? round(($opened / $sent) * 100, 1) : 0.0;
        $clickRate = $opened > 0 ? round(($clicked / $opened) * 100, 1) : 0.0;

        $chartStmt = $pdo->prepare(
            "SELECT 
                DATE(created_at) AS date,
                SUM(status = 'sent') AS sent,
                SUM(status = 'failed') AS failed,
                SUM(status = 'opened') AS opened
             FROM email_logs
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY DATE(created_at)
             ORDER BY DATE(created_at) ASC"
        );
        $chartStmt->execute();
        $chartRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

        $chart = [];
        foreach ($chartRows as $row) {
            $chart[$row['date']] = [
                'sent' => (int)$row['sent'],
                'failed' => (int)$row['failed'],
                'opened' => (int)$row['opened'],
            ];
        }

        $recentStmt = $pdo->prepare(
            "SELECT id, recipient_email, subject, email_type, status, created_at
             FROM email_logs
             ORDER BY created_at DESC
             LIMIT 6"
        );
        $recentStmt->execute();
        $recentEmails = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        respond([
            'success' => true,
            'stats' => [
                'sent' => $sent,
                'failed' => $failed,
                'openRate' => $openRate,
                'clickRate' => $clickRate,
                'total' => (int)($totals['total'] ?? 0)
            ],
            'chart' => $chart,
            'recentEmails' => $recentEmails,
        ]);
    } catch (Throwable $e) {
        respond([
            'success' => false,
            'message' => 'Erreur lors de la récupération des statistiques',
            'error' => $e->getMessage()
        ], 500);
    }
}

function handleGetEmail(PDO $pdo): void
{
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        respond([
            'success' => false,
            'message' => 'Identifiant email invalide'
        ], 400);
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM email_logs WHERE id = :id LIMIT 1"
    );
    $stmt->execute(['id' => $id]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) {
        respond([
            'success' => false,
            'message' => 'Email introuvable'
        ], 404);
    }

    respond([
        'success' => true,
        'email' => $email
    ]);
}

function handleRetryEmail(PDO $pdo, EmailManager $emailManager, string $method): void
{
    if (strtoupper($method) !== 'POST') {
        respond([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $emailId = isset($payload['email_id']) ? (int)$payload['email_id'] : 0;
    if ($emailId <= 0) {
        respond([
            'success' => false,
            'message' => 'Identifiant email manquant'
        ], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $emailId]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) {
        respond([
            'success' => false,
            'message' => 'Email introuvable'
        ], 404);
    }

    try {
        if ($email['email_type'] === 'password_reset' && !empty($email['recipient_email'])) {
            $result = $emailManager->sendPasswordReset($email['recipient_email']);
            if ($result['success']) {
                respond([
                    'success' => true,
                    'message' => 'Nouvelle tentative d\'envoi effectuée'
                ]);
            }

            respond([
                'success' => false,
                'message' => $result['message'] ?? 'Échec lors de la nouvelle tentative'
            ], 500);
        }

        // Pour d'autres types, on remet l'email en file d'attente
        $update = $pdo->prepare(
            "UPDATE email_logs 
             SET status = 'pending', error_message = NULL, sent_at = NULL 
             WHERE id = :id"
        );
        $update->execute(['id' => $emailId]);

        respond([
            'success' => true,
            'message' => 'Email remis en file d\'attente (action manuelle requise)'
        ]);
    } catch (Throwable $e) {
        respond([
            'success' => false,
            'message' => 'Erreur lors de la remise en file d\'attente',
            'error' => $e->getMessage()
        ], 500);
    }
}

function handleGetCampaign(PDO $pdo): void
{
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        respond([
            'success' => false,
            'message' => 'Identifiant campagne invalide'
        ], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM email_campaigns WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        respond([
            'success' => false,
            'message' => 'Campagne introuvable'
        ], 404);
    }

    $campaign['recipient_criteria'] = $campaign['recipient_criteria'] ? json_decode($campaign['recipient_criteria'], true) : null;

    respond([
        'success' => true,
        'campaign' => $campaign
    ]);
}

function handleSaveCampaign(PDO $pdo, bool $isUpdate): void
{
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $recipientType = $_POST['recipient_type'] ?? 'all';

    if ($name === '' || $subject === '' || $templateId <= 0) {
        respond([
            'success' => false,
            'message' => 'Nom, sujet et template sont requis'
        ], 400);
    }

    $recipientCriteria = json_encode([
        'type' => $recipientType
    ]);

    try {
        if ($isUpdate) {
            $campaignId = isset($_POST['campaign_id']) ? (int)$_POST['campaign_id'] : 0;
            if ($campaignId <= 0) {
                respond([
                    'success' => false,
                    'message' => 'Identifiant campagne manquant'
                ], 400);
            }

            $stmt = $pdo->prepare(
                "UPDATE email_campaigns
                 SET name = :name,
                     subject = :subject,
                     template_path = :template,
                     recipient_criteria = :criteria
                 WHERE id = :id"
            );
            $stmt->execute([
                'name' => $name,
                'subject' => $subject,
                'template' => 'template:' . $templateId,
                'criteria' => $recipientCriteria,
                'id' => $campaignId
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO email_campaigns 
                    (name, subject, template_path, recipient_criteria)
                 VALUES (:name, :subject, :template, :criteria)"
            );
            $stmt->execute([
                'name' => $name,
                'subject' => $subject,
                'template' => 'template:' . $templateId,
                'criteria' => $recipientCriteria
            ]);
        }

        respond([
            'success' => true
        ]);
    } catch (Throwable $e) {
        respond([
            'success' => false,
            'message' => 'Erreur lors de la sauvegarde de la campagne',
            'error' => $e->getMessage()
        ], 500);
    }
}

function handleSendCampaign(PDO $pdo, string $method): void
{
    if (strtoupper($method) !== 'POST') {
        respond([
            'success' => false,
            'message' => 'Méthode non autorisée'
        ], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $campaignId = isset($payload['campaign_id']) ? (int)$payload['campaign_id'] : 0;
    if ($campaignId <= 0) {
        respond([
            'success' => false,
            'message' => 'Identifiant campagne manquant'
        ], 400);
    }

    $stmt = $pdo->prepare("UPDATE email_campaigns SET status = 'scheduled', scheduled_at = NOW() WHERE id = :id");
    $stmt->execute(['id' => $campaignId]);

    respond([
        'success' => true,
        'message' => 'Campagne programmée pour envoi (simulation locale)'
    ]);
}

function handleGetTemplates(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        "SELECT id, name, type FROM email_templates WHERE is_active = TRUE ORDER BY updated_at DESC"
    );
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respond([
        'success' => true,
        'templates' => $templates
    ]);
}

function handleSaveTemplate(PDO $pdo, bool $isUpdate): void
{
    $name = trim($_POST['name'] ?? '');
    $defaultSubject = trim($_POST['default_subject'] ?? '');
    $htmlContent = trim($_POST['html_content'] ?? '');

    if ($name === '' || $defaultSubject === '' || $htmlContent === '') {
        respond([
            'success' => false,
            'message' => 'Nom, sujet et contenu HTML sont requis'
        ], 400);
    }

    $templateData = [
        'name' => $name,
        'type' => 'campaign',
        'subject' => $defaultSubject,
        'html' => $htmlContent
    ];

    try {
        if ($isUpdate) {
            $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
            if ($templateId <= 0) {
                respond([
                    'success' => false,
                    'message' => 'Identifiant template manquant'
                ], 400);
            }

            $stmt = $pdo->prepare(
                "UPDATE email_templates
                 SET name = :name,
                     subject_template = :subject,
                     html_content = :html
                 WHERE id = :id"
            );
            $stmt->execute([
                'name' => $templateData['name'],
                'subject' => $templateData['subject'],
                'html' => $templateData['html'],
                'id' => $templateId
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO email_templates 
                    (name, type, subject_template, html_content) 
                 VALUES (:name, :type, :subject, :html)"
            );
            $stmt->execute([
                'name' => $templateData['name'],
                'type' => $templateData['type'],
                'subject' => $templateData['subject'],
                'html' => $templateData['html']
            ]);
        }

        respond([
            'success' => true
        ]);
    } catch (Throwable $e) {
        respond([
            'success' => false,
            'message' => 'Erreur lors de la sauvegarde du template',
            'error' => $e->getMessage()
        ], 500);
    }
}
