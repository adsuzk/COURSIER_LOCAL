<?php
/**
 * INTÉGRATION EMAIL DANS ADMIN.PHP
 * 
 * Instructions d'intégration :
 * 
 * 1. Ajoutez cette ligne dans la section HEAD de votre admin.php :
 *    <link rel="stylesheet" href="email_system/admin_styles.css">
 * 
 * 2. Ajoutez cette ligne avant la fermeture </body> :
 *    <script src="email_system/admin_script.js"></script>
 * 
 * 3. Dans le menu de navigation gauche, ajoutez cet élément :
 *    <li class="nav-item">
 *        <a href="?section=emails" class="nav-link <?= ($currentSection === 'emails') ? 'active' : '' ?>">
 *            📧 Gestion d'Emails
 *        </a>
 *    </li>
 * 
 * 4. Dans le switch de gestion des sections, ajoutez ce case :
 * 
 */

// À ajouter dans votre switch des sections :
if ($currentSection === 'emails') {
    // Inclure les classes nécessaires
    require_once __DIR__ . '/email_system/EmailManager.php';
    require_once __DIR__ . '/email_system/admin_panel.php';
    
    // Configuration email (adaptez selon votre config existante)
    $emailConfig = [
        'smtp_host' => 'smtp.gmail.com', // Ou votre serveur SMTP
        'smtp_port' => 587,
        'smtp_username' => 'reply@conciergerie-privee-suzosky.com',
        'smtp_password' => 'votre_mot_de_passe_app', // Mot de passe d'application
        'from_email' => 'reply@conciergerie-privee-suzosky.com',
        'from_name' => 'Conciergerie Privée Suzosky',
        'reply_to' => 'reply@conciergerie-privee-suzosky.com'
    ];
    
    // Créer l'instance du panneau admin
    $emailPanel = new EmailAdminPanel($pdo, $emailConfig);
    
    // Gérer les actions POST (sauvegarde de paramètres, etc.)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'save_settings':
                // Logique de sauvegarde des paramètres
                $_SESSION['admin_message'] = 'Paramètres sauvegardés avec succès !';
                header('Location: ?section=emails&email_tab=settings');
                exit;
                break;
        }
    }
    
    // Afficher la section
    echo '<div class="email-management-section">';
    echo '<h1>📧 Gestion d\'Emails Robuste</h1>';
    
    // Afficher un message de succès si présent
    if (isset($_SESSION['admin_message'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['admin_message']) . '</div>';
        unset($_SESSION['admin_message']);
    }
    
    // Rendre le panneau email
    $emailPanel->renderEmailSection();
    
    echo '</div>';
}

/**
 * EXEMPLE D'INTÉGRATION COMPLÈTE DANS ADMIN.PHP
 * 
 * Voici un exemple de structure pour votre admin.php :
 */
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Conciergerie Suzosky</title>
    
    <!-- Vos styles existants -->
    <link rel="stylesheet" href="css/admin.css">
    
    <!-- NOUVEAU : Styles pour la gestion d'emails -->
    <link rel="stylesheet" href="email_system/admin_styles.css">
    
    <!-- Chart.js pour les graphiques -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Navigation latérale -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>📊 Administration</h3>
            </div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="?section=dashboard" class="nav-link <?= ($currentSection === 'dashboard') ? 'active' : '' ?>">
                        🏠 Tableau de bord
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?section=commandes" class="nav-link <?= ($currentSection === 'commandes') ? 'active' : '' ?>">
                        📦 Commandes
                    </a>
                </li>
                <li class="nav-item">
                    <a href="?section=clients" class="nav-link <?= ($currentSection === 'clients') ? 'active' : '' ?>">
                        👥 Clients
                    </a>
                </li>
                
                <!-- NOUVEAU : Menu Gestion d'Emails -->
                <li class="nav-item">
                    <a href="?section=emails" class="nav-link <?= ($currentSection === 'emails') ? 'active' : '' ?>">
                        📧 Gestion d'Emails
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="?section=agents" class="nav-link <?= ($currentSection === 'agents') ? 'active' : '' ?>">
                        🚴 Agents
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Contenu principal -->
        <main class="main-content">
            <?php
            $currentSection = $_GET['section'] ?? 'dashboard';
            
            switch ($currentSection) {
                case 'dashboard':
                    include 'sections/dashboard.php';
                    break;
                    
                case 'commandes':
                    include 'sections/commandes.php';
                    break;
                    
                case 'clients':
                    include 'sections/clients.php';
                    break;
                    
                // NOUVEAU : Section emails
                case 'emails':
                    // Code d'intégration emails (voir ci-dessus)
                    require_once __DIR__ . '/email_system/EmailManager.php';
                    require_once __DIR__ . '/email_system/admin_panel.php';
                    
                    $emailConfig = [
                        'smtp_host' => 'smtp.gmail.com',
                        'smtp_port' => 587,
                        'smtp_username' => 'reply@conciergerie-privee-suzosky.com',
                        'smtp_password' => 'votre_mot_de_passe_app',
                        'from_email' => 'reply@conciergerie-privee-suzosky.com',
                        'from_name' => 'Conciergerie Privée Suzosky',
                        'reply_to' => 'reply@conciergerie-privee-suzosky.com'
                    ];
                    
                    $emailPanel = new EmailAdminPanel($pdo, $emailConfig);
                    
                    echo '<div class="email-management-section">';
                    echo '<h1>📧 Gestion d\'Emails Robuste</h1>';
                    $emailPanel->renderEmailSection();
                    echo '</div>';
                    break;
                    
                case 'agents':
                    include 'sections/agents.php';
                    break;
                    
                default:
                    include 'sections/dashboard.php';
            }
            ?>
        </main>
    </div>

    <!-- Vos scripts existants -->
    <script src="js/admin.js"></script>
    
    <!-- NOUVEAU : Scripts pour la gestion d'emails -->
    <script src="email_system/admin_script.js"></script>
</body>
</html>

<?php
/**
 * CONFIGURATION REQUISE
 * 
 * Assurez-vous d'avoir dans votre config.php ou équivalent :
 * 
 * - Connexion PDO active ($pdo)
 * - Configuration SMTP
 * - Sessions démarrées
 * 
 * TABLES DE BASE DE DONNÉES
 * 
 * Les tables suivantes seront créées automatiquement par EmailManager :
 * - email_logs : logs de tous les emails
 * - email_campaigns : campagnes d'emailing
 * - email_templates : templates d'emails
 * 
 * SÉCURITÉ
 * 
 * - Vérifiez les permissions admin avant d'afficher cette section
 * - Validez toutes les entrées utilisateur
 * - Utilisez des mots de passe d'application pour SMTP
 * 
 * PERSONNALISATION
 * 
 * Vous pouvez personnaliser :
 * - Les couleurs dans admin_styles.css
 * - Les icônes et labels
 * - Les statistiques affichées
 * - Les filtres disponibles
 */
?>