<?php
/**
 * SECTION ADMIN - GESTION D'EMAILS
 * À ajouter dans admin/emails.php
 */

// Sécurité : vérifier l'authentification admin
if (!function_exists('getPDO')) {
    require_once __DIR__ . '/../config.php';
}

// Inclure les classes email
require_once __DIR__ . '/../email_system/EmailManager.php';
require_once __DIR__ . '/../email_system/admin_panel.php';

// Configuration email (à adapter selon votre config)
$emailConfig = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'reply@conciergerie-privee-suzosky.com',
    'smtp_password' => 'votre_mot_de_passe_app', // À configurer dans config.php
    'from_email' => 'reply@conciergerie-privee-suzosky.com',
    'from_name' => 'Conciergerie Privée Suzosky',
    'reply_to' => 'reply@conciergerie-privee-suzosky.com'
];

// Récupérer la connexion PDO existante
try {
    $pdo = getPDO();
} catch (Exception $e) {
    echo '<div class="alert alert-danger" style="background: #dc3545; color: white; padding: 15px; border-radius: 5px; margin: 20px 0;">❌ Erreur de connexion base de données : ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

// Créer l'instance du panneau admin
$emailPanel = new EmailAdminPanel($pdo, $emailConfig);

// Gérer les actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_settings':
            // Sauvegarder les paramètres email
            $_SESSION['admin_message'] = '✅ Paramètres email sauvegardés avec succès !';
            header('Location: admin.php?section=emails&email_tab=settings');
            exit;
            break;
            
        case 'create_campaign':
            // Créer une nouvelle campagne
            $_SESSION['admin_message'] = '📢 Campagne créée avec succès !';
            header('Location: admin.php?section=emails&email_tab=campaigns');
            exit;
            break;
            
        case 'test_email':
            // Tester l'envoi d'email
            try {
                $testEmail = $_POST['test_email'] ?? '';
                if (filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                    $emailManager = new EmailManager($pdo, $emailConfig);
                    $result = $emailManager->sendTrackedEmail(
                        $testEmail,
                        'Test Email Suzosky',
                        '<h2>🧪 Email de Test</h2><p>Ce mail confirme que votre configuration email fonctionne parfaitement !</p>',
                        'test'
                    );
                    
                    if ($result['success']) {
                        $_SESSION['admin_message'] = '✅ Email de test envoyé avec succès !';
                    } else {
                        $_SESSION['admin_message'] = '❌ Erreur : ' . $result['error'];
                    }
                } else {
                    $_SESSION['admin_message'] = '❌ Adresse email invalide';
                }
            } catch (Exception $e) {
                $_SESSION['admin_message'] = '❌ Erreur : ' . $e->getMessage();
            }
            header('Location: admin.php?section=emails');
            exit;
            break;
    }
}

// Afficher les messages de succès/erreur
if (isset($_SESSION['admin_message'])) {
    $isError = strpos($_SESSION['admin_message'], '❌') !== false;
    $bgColor = $isError ? '#dc3545' : '#28a745';
    
    echo '<div class="admin-message" style="background: ' . $bgColor . '; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
    echo htmlspecialchars($_SESSION['admin_message']);
    echo '</div>';
    unset($_SESSION['admin_message']);
}

// CSS inline pour l'intégration avec le thème admin existant
echo '<style>
/* INTEGRATION EMAIL AVEC THEME ADMIN SUZOSKY */
.email-admin-container {
    background: transparent !important;
    padding: 0 !important;
    min-height: auto !important;
}

.email-tabs {
    background: var(--glass-bg) !important;
    backdrop-filter: var(--glass-blur) !important;
    border: 1px solid var(--glass-border) !important;
    box-shadow: var(--glass-shadow) !important;
}

.email-tab {
    color: #CCCCCC !important;
}

.email-tab.active {
    background: var(--gradient-gold) !important;
    color: var(--primary-dark) !important;
    font-weight: 600 !important;
}

.stat-card, .email-table-container, .campaign-card, .recent-emails, .charts-container {
    background: var(--glass-bg) !important;
    backdrop-filter: var(--glass-blur) !important;
    border: 1px solid var(--glass-border) !important;
    box-shadow: var(--glass-shadow) !important;
}

.email-table th {
    background: rgba(212, 168, 83, 0.2) !important;
    color: var(--primary-gold) !important;
}

.btn-primary {
    background: var(--gradient-gold) !important;
    color: var(--primary-dark) !important;
    font-weight: 600 !important;
    border: none !important;
}

.btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: var(--shadow-gold) !important;
}

.page-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 30px 0;
}

.page-title {
    color: var(--primary-gold);
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 0 0 20px rgba(212, 168, 83, 0.3);
    margin-bottom: 10px;
}

.page-subtitle {
    color: #CCCCCC;
    font-size: 1.1rem;
    opacity: 0.8;
}

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
    padding: 15px 25px;
    border-radius: 8px;
    text-decoration: none;
    color: #CCCCCC;
    transition: all 0.3s ease;
}

.quick-action:hover {
    background: var(--primary-gold);
    color: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: var(--shadow-gold);
}
</style>';

// Header de la section avec actions rapides
echo '<div class="page-header">';
echo '<h1 class="page-title">📧 Gestion d\'Emails Robuste</h1>';
echo '<p class="page-subtitle">Surveillance, envoi et suivi des communications électroniques</p>';

// Actions rapides
echo '<div class="quick-actions">';
echo '<a href="?section=emails&email_tab=dashboard" class="quick-action">📊 Tableau de bord</a>';
echo '<a href="?section=emails&email_tab=logs" class="quick-action">📧 Voir les logs</a>';
echo '<a href="javascript:void(0)" onclick="showTestEmailModal()" class="quick-action">🧪 Test email</a>';
echo '<a href="?section=emails&email_tab=settings" class="quick-action">⚙️ Configuration</a>';
echo '</div>';
echo '</div>';

// Modal de test email
echo '<script>
function showTestEmailModal() {
    const email = prompt("Adresse email pour le test :", "admin@conciergerie-privee-suzosky.com");
    if (email && email.includes("@")) {
        const form = document.createElement("form");
        form.method = "POST";
        form.innerHTML = `
            <input type="hidden" name="action" value="test_email">
            <input type="hidden" name="test_email" value="${email}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>';

// Rendu du panneau email principal
try {
    $emailPanel->renderEmailSection();
} catch (Exception $e) {
    echo '<div style="background: var(--glass-bg); backdrop-filter: var(--glass-blur); border: 1px solid rgba(220, 53, 69, 0.3); padding: 20px; border-radius: 8px; margin: 20px 0;">';
    echo '<h3 style="color: #dc3545; margin-bottom: 15px;">❌ Erreur lors de l\'affichage</h3>';
    echo '<p style="color: #CCCCCC;">Message : ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<details style="margin-top: 15px;">';
    echo '<summary style="color: var(--primary-gold); cursor: pointer;">🔍 Détails techniques</summary>';
    echo '<pre style="background: var(--primary-dark); padding: 15px; border-radius: 5px; color: #fff; overflow-x: auto; margin-top: 10px; font-size: 0.85rem;">';
    echo htmlspecialchars($e->getTraceAsString());
    echo '</pre>';
    echo '</details>';
    echo '</div>';
}

// JavaScript et CSS supplémentaires pour l'intégration
echo '<script src="../email_system/admin_script.js"></script>';
?>