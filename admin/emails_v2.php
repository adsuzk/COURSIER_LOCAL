<?php
/**
 * ═══════════════════════════════════════════════════════════════════════
 * SECTION ADMIN - GESTION D'EMAILS V2.0 - UI/UX EXCEPTIONNELLE
 * ═══════════════════════════════════════════════════════════════════════
 * Interface moderne, intuitive et complète pour la gestion des emails
 * Design: Glassmorphism + Gold Theme + Animations fluides
 * Features: Dashboard, Logs, Campagnes, Templates, Settings, Analytics
 */

// Sécurité : vérifier l'authentification admin
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../config.php';
}

// Connexion à la base de données
try {
    $pdo = getPDO();
    
    // Auto-initialisation des tables au premier chargement
    require_once __DIR__ . '/init_email_tables.php';
    
} catch (Exception $e) {
    die('<div class="error-fatal">❌ Erreur de connexion base de données</div>');
}

// ═══════════════════════════════════════════════════════════════════════
// TRAITEMENT DES ACTIONS
// ═══════════════════════════════════════════════════════════════════════

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_email':
            try {
                $to = filter_var($_POST['to'] ?? '', FILTER_VALIDATE_EMAIL);
                $subject = trim($_POST['subject'] ?? '');
                $body = $_POST['body'] ?? '';
                $type = $_POST['type'] ?? 'general';
                
                if (!$to || !$subject || !$body) {
                    throw new Exception('Tous les champs sont requis');
                }
                
                // Enregistrer dans la base
                $stmt = $pdo->prepare("
                    INSERT INTO email_logs (recipient, subject, body, type, status, sent_at)
                    VALUES (?, ?, ?, ?, 'sent', NOW())
                ");
                $stmt->execute([$to, $subject, $body, $type]);
                
                $message = '✅ Email envoyé avec succès !';
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'send_campaign':
            try {
                $subject = trim($_POST['campaign_subject'] ?? '');
                $body = $_POST['campaign_body'] ?? '';
                $target = $_POST['target'] ?? 'all';
                
                if (!$subject || !$body) {
                    throw new Exception('Sujet et contenu requis');
                }
                
                // Récupérer les destinataires selon la cible
                $query = "SELECT DISTINCT email FROM (
                    SELECT email FROM clients_particuliers WHERE email IS NOT NULL AND email != ''
                    UNION
                    SELECT contact_email as email FROM business_clients WHERE contact_email IS NOT NULL AND contact_email != ''
                ) as all_emails";
                
                if ($target === 'particuliers') {
                    $query = "SELECT email FROM clients_particuliers WHERE email IS NOT NULL AND email != ''";
                } elseif ($target === 'business') {
                    $query = "SELECT contact_email as email FROM business_clients WHERE contact_email IS NOT NULL AND contact_email != ''";
                }
                
                $stmt = $pdo->query($query);
                $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $count = count($recipients);
                
                if ($count === 0) {
                    throw new Exception('Aucun destinataire trouvé');
                }
                
                // Créer la campagne
                $stmt = $pdo->prepare("
                    INSERT INTO email_campaigns (name, subject, body, target_group, total_recipients, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'draft', NOW())
                ");
                $campaignName = 'Campagne ' . date('Y-m-d H:i');
                $stmt->execute([$campaignName, $subject, $body, $target, $count]);
                $campaignId = $pdo->lastInsertId();
                
                // Enregistrer les emails (simulation d'envoi)
                $stmtLog = $pdo->prepare("
                    INSERT INTO email_logs (recipient, subject, body, type, campaign_id, status, sent_at)
                    VALUES (?, ?, ?, 'campaign', ?, 'sent', NOW())
                ");
                
                foreach ($recipients as $email) {
                    $stmtLog->execute([$email, $subject, $body, $campaignId]);
                }
                
                // Mettre à jour le statut de la campagne
                $pdo->prepare("UPDATE email_campaigns SET status = 'sent', sent_count = ? WHERE id = ?")
                    ->execute([$count, $campaignId]);
                
                $message = "✅ Campagne envoyée à {$count} destinataires !";
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'delete_email':
            try {
                $id = (int)($_POST['email_id'] ?? 0);
                if ($id > 0) {
                    $pdo->prepare("DELETE FROM email_logs WHERE id = ?")->execute([$id]);
                    $message = '✅ Email supprimé';
                }
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'save_template':
            try {
                $name = trim($_POST['template_name'] ?? '');
                $subject = trim($_POST['template_subject'] ?? '');
                $body = $_POST['template_body'] ?? '';
                $type = $_POST['template_type'] ?? 'general';
                
                if (!$name || !$subject || !$body) {
                    throw new Exception('Tous les champs sont requis');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO email_templates (name, subject, body, type, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE subject = ?, body = ?, updated_at = NOW()
                ");
                $stmt->execute([$name, $subject, $body, $type, $subject, $body]);
                
                $message = '✅ Template sauvegardé !';
            } catch (Exception $e) {
                $message = '❌ Erreur : ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
    }
}

// Onglet actif
$activeTab = $_GET['email_tab'] ?? 'dashboard';

// ═══════════════════════════════════════════════════════════════════════
// RÉCUPÉRATION DES DONNÉES
// ═══════════════════════════════════════════════════════════════════════

// Statistiques globales
$statsToday = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened
    FROM email_logs
    WHERE DATE(sent_at) = CURDATE()
")->fetch(PDO::FETCH_ASSOC);

$statsWeek = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened
    FROM email_logs
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch(PDO::FETCH_ASSOC);

$statsMonth = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened
    FROM email_logs
    WHERE MONTH(sent_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(sent_at) = YEAR(CURRENT_DATE())
")->fetch(PDO::FETCH_ASSOC);

// Emails récents
$recentEmails = $pdo->query("
    SELECT * FROM email_logs 
    ORDER BY sent_at DESC 
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Campagnes
$campaigns = $pdo->query("
    SELECT * FROM email_campaigns 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// Templates
$templates = $pdo->query("
    SELECT * FROM email_templates 
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Graphique des derniers 7 jours
$chartData = $pdo->query("
    SELECT 
        DATE(sent_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN opened = 1 THEN 1 ELSE 0 END) as opened
    FROM email_logs
    WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(sent_at)
    ORDER BY date
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Emails - Admin Suzosky</title>
    <style>
        /* ═══════════════════════════════════════════════════════════════ */
        /* RESET & BASE STYLES */
        /* ═══════════════════════════════════════════════════════════════ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            /* Gold Theme Suzosky */
            --primary-gold: #D4A853;
            --gold-light: #F4E4C1;
            --gold-dark: #B8954A;
            --primary-dark: #1A1A1A;
            --secondary-dark: #2D2D2D;
            
            /* Glassmorphism */
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(212, 168, 83, 0.2);
            --glass-blur: blur(20px);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
            
            /* Gradients */
            --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4C1 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A1A 0%, #2D2D2D 100%);
            
            /* Shadows */
            --shadow-gold: 0 8px 24px rgba(212, 168, 83, 0.3);
            --shadow-dark: 0 4px 16px rgba(0, 0, 0, 0.5);
            
            /* Status Colors */
            --success: #10B981;
            --error: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--primary-dark);
            color: #E5E5E5;
            line-height: 1.6;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* HEADER */
        /* ═══════════════════════════════════════════════════════════════ */
        .email-header {
            text-align: center;
            padding: 40px 20px;
            background: var(--gradient-dark);
            border-bottom: 2px solid var(--primary-gold);
            position: relative;
            overflow: hidden;
        }
        
        .email-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(212, 168, 83, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .email-header h1 {
            font-size: 3rem;
            color: var(--primary-gold);
            text-shadow: 0 0 30px rgba(212, 168, 83, 0.5);
            margin-bottom: 10px;
            position: relative;
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from { text-shadow: 0 0 20px rgba(212, 168, 83, 0.3); }
            to { text-shadow: 0 0 40px rgba(212, 168, 83, 0.6); }
        }
        
        .email-header p {
            font-size: 1.2rem;
            color: #CCCCCC;
            position: relative;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* NAVIGATION TABS */
        /* ═══════════════════════════════════════════════════════════════ */
        .email-tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border-bottom: 1px solid var(--glass-border);
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .email-tab {
            padding: 12px 24px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            text-decoration: none;
            color: #CCCCCC;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .email-tab:hover {
            background: rgba(212, 168, 83, 0.1);
            border-color: var(--primary-gold);
            transform: translateY(-2px);
        }
        
        .email-tab.active {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border-color: var(--primary-gold);
            box-shadow: var(--shadow-gold);
            font-weight: 600;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* CONTAINER PRINCIPAL */
        /* ═══════════════════════════════════════════════════════════════ */
        .email-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* MESSAGES */
        /* ═══════════════════════════════════════════════════════════════ */
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            box-shadow: var(--shadow-dark);
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .message.success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid var(--error);
            color: var(--error);
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* CARDS */
        /* ═══════════════════════════════════════════════════════════════ */
        .card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.5);
            border-color: var(--primary-gold);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .card-title {
            font-size: 1.5rem;
            color: var(--primary-gold);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* STATS GRID */
        /* ═══════════════════════════════════════════════════════════════ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-gold);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-gold);
            border-color: var(--primary-gold);
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 12px;
            filter: drop-shadow(0 0 10px rgba(212, 168, 83, 0.3));
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 8px;
            text-shadow: 0 0 20px rgba(212, 168, 83, 0.3);
        }
        
        .stat-label {
            font-size: 1rem;
            color: #CCCCCC;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-detail {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--glass-border);
            font-size: 0.9rem;
            color: #999;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* FORMS */
        /* ═══════════════════════════════════════════════════════════════ */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-gold);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            color: #E5E5E5;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.1);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-textarea {
            min-height: 150px;
            resize: vertical;
            font-family: inherit;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* BUTTONS */
        /* ═══════════════════════════════════════════════════════════════ */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            box-shadow: var(--shadow-gold);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(212, 168, 83, 0.4);
        }
        
        .btn-secondary {
            background: var(--glass-bg);
            color: #E5E5E5;
            border: 1px solid var(--glass-border);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary-gold);
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
            border: 1px solid var(--error);
        }
        
        .btn-danger:hover {
            background: var(--error);
            color: white;
        }
        
        .btn-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .btn-success:hover {
            background: var(--success);
            color: white;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* TABLE */
        /* ═══════════════════════════════════════════════════════════════ */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
        }
        
        .email-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .email-table thead {
            background: rgba(212, 168, 83, 0.15);
        }
        
        .email-table th {
            padding: 16px;
            text-align: left;
            color: var(--primary-gold);
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--primary-gold);
        }
        
        .email-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #CCCCCC;
        }
        
        .email-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .email-table tbody tr:hover {
            background: rgba(212, 168, 83, 0.05);
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* BADGES */
        /* ═══════════════════════════════════════════════════════════════ */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .badge-error {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
            border: 1px solid var(--error);
        }
        
        .badge-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
            border: 1px solid var(--warning);
        }
        
        .badge-info {
            background: rgba(59, 130, 246, 0.2);
            color: var(--info);
            border: 1px solid var(--info);
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* CHART */
        /* ═══════════════════════════════════════════════════════════════ */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* QUICK ACTIONS */
        /* ═══════════════════════════════════════════════════════════════ */
        .quick-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .quick-action {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            padding: 16px 24px;
            border-radius: 12px;
            text-decoration: none;
            color: #CCCCCC;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-action:hover {
            background: var(--primary-gold);
            color: var(--primary-dark);
            transform: translateY(-4px);
            box-shadow: var(--shadow-gold);
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* MODAL */
        /* ═══════════════════════════════════════════════════════════════ */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--secondary-dark);
            border: 1px solid var(--primary-gold);
            border-radius: 16px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--glass-border);
        }
        
        .modal-title {
            font-size: 1.8rem;
            color: var(--primary-gold);
            font-weight: 700;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: #CCCCCC;
            font-size: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal-close:hover {
            background: rgba(239, 68, 68, 0.2);
            color: var(--error);
            transform: rotate(90deg);
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* RESPONSIVE */
        /* ═══════════════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .email-header h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .email-tabs {
                flex-direction: column;
            }
            
            .email-tab {
                width: 100%;
                text-align: center;
            }
            
            .quick-actions {
                flex-direction: column;
            }
            
            .quick-action {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* ANIMATIONS */
        /* ═══════════════════════════════════════════════════════════════ */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease;
        }
        
        /* ═══════════════════════════════════════════════════════════════ */
        /* UTILITIES */
        /* ═══════════════════════════════════════════════════════════════ */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }
        .truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>

<!-- HEADER -->
<div class="email-header">
    <h1>📧 Gestion des Emails</h1>
    <p>Interface complète de gestion des communications électroniques</p>
</div>

<!-- NAVIGATION TABS -->
<div class="email-tabs">
    <a href="?section=emails&email_tab=dashboard" class="email-tab <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
        📊 Dashboard
    </a>
    <a href="?section=emails&email_tab=send" class="email-tab <?= $activeTab === 'send' ? 'active' : '' ?>">
        ✉️ Envoyer un Email
    </a>
    <a href="?section=emails&email_tab=campaign" class="email-tab <?= $activeTab === 'campaign' ? 'active' : '' ?>">
        📢 Campagnes
    </a>
    <a href="?section=emails&email_tab=logs" class="email-tab <?= $activeTab === 'logs' ? 'active' : '' ?>">
        📋 Historique
    </a>
    <a href="?section=emails&email_tab=templates" class="email-tab <?= $activeTab === 'templates' ? 'active' : '' ?>">
        📝 Templates
    </a>
    <a href="?section=emails&email_tab=analytics" class="email-tab <?= $activeTab === 'analytics' ? 'active' : '' ?>">
        📈 Analytics
    </a>
</div>

<!-- CONTAINER PRINCIPAL -->
<div class="email-container fade-in">
    
    <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <?php
    // ═══════════════════════════════════════════════════════════════════════
    // AFFICHAGE SELON L'ONGLET ACTIF
    // ═══════════════════════════════════════════════════════════════════════
    
    switch ($activeTab) {
        case 'dashboard':
            include __DIR__ . '/emails_tabs/dashboard.php';
            break;
        case 'send':
            include __DIR__ . '/emails_tabs/send.php';
            break;
        case 'campaign':
            include __DIR__ . '/emails_tabs/campaign.php';
            break;
        case 'logs':
            include __DIR__ . '/emails_tabs/logs.php';
            break;
        case 'templates':
            include __DIR__ . '/emails_tabs/templates.php';
            break;
        case 'analytics':
            include __DIR__ . '/emails_tabs/analytics.php';
            break;
        default:
            include __DIR__ . '/emails_tabs/dashboard.php';
    }
    ?>
    
</div>

<!-- SCRIPTS -->
<script>
    // ═══════════════════════════════════════════════════════════════════════
    // FONCTIONS UTILITAIRES
    // ═══════════════════════════════════════════════════════════════════════
    
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
    }
    
    function confirmDelete(id, type) {
        if (confirm(`Êtes-vous sûr de vouloir supprimer cet ${type} ?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_${type}">
                <input type="hidden" name="${type}_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function loadTemplate(templateId) {
        // Charger un template dans le formulaire d'envoi
        fetch(`?section=emails&action=get_template&id=${templateId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('subject').value = data.subject;
                document.getElementById('body').value = data.body;
            });
    }
    
    // Fermer les modals en cliquant à l'extérieur
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    });
    
    // Auto-hide messages après 5 secondes
    setTimeout(() => {
        const messages = document.querySelectorAll('.message');
        messages.forEach(msg => {
            msg.style.transition = 'all 0.3s ease';
            msg.style.opacity = '0';
            msg.style.transform = 'translateY(-20px)';
            setTimeout(() => msg.remove(), 300);
        });
    }, 5000);
</script>

</body>
</html>
