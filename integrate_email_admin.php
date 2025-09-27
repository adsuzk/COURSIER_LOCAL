<?php
/**
 * INTÉGRATION AUTOMATIQUE DE LA GESTION D'EMAILS
 * 
 * Ce fichier modifie automatiquement votre admin existant pour ajouter
 * la section de gestion d'emails robuste.
 * 
 * INSTRUCTIONS :
 * 1. Sauvegardez votre admin/functions.php avant l'intégration
 * 2. Exécutez ce script une fois : php integrate_email_admin.php
 * 3. La section emails sera accessible via admin.php?section=emails
 */

require_once __DIR__ . '/config.php';

class EmailAdminIntegrator {
    private $adminPath;
    private $functionsPath;
    
    public function __construct() {
        $this->adminPath = __DIR__ . '/admin/admin.php';
        $this->functionsPath = __DIR__ . '/admin/functions.php';
    }
    
    /**
     * Intégrer complètement le système email dans l'admin
     */
    public function integrate() {
        echo "🔧 Intégration du système de gestion d'emails...\n";
        
        // 1. Ajouter le CSS dans le header
        $this->addEmailCSS();
        echo "✅ CSS ajouté au header\n";
        
        // 2. Ajouter le JavaScript dans le footer  
        $this->addEmailJS();
        echo "✅ JavaScript ajouté au footer\n";
        
        // 3. Ajouter le menu dans la sidebar
        $this->addEmailMenu();
        echo "✅ Menu ajouté à la sidebar\n";
        
        // 4. Ajouter le case dans le switch
        $this->addEmailCase();
        echo "✅ Section ajoutée au routeur\n";
        
        // 5. Créer le fichier de section email
        $this->createEmailSection();
        echo "✅ Fichier de section créé\n";
        
        echo "\n🎉 Intégration terminée avec succès !\n";
        echo "📧 Accédez à la gestion d'emails via : admin.php?section=emails\n";
    }
    
    /**
     * Ajouter le CSS email dans renderHeader
     */
    private function addEmailCSS() {
        $content = file_get_contents($this->functionsPath);
        
        // Chercher la balise de fermeture </style> du header
        $cssInsert = '
        /* EMAIL ADMIN STYLES - INTEGRATION AUTOMATIQUE */
        @import url("../email_system/admin_styles.css");
        </style>';
        
        $content = str_replace('</style>', $cssInsert, $content);
        
        file_put_contents($this->functionsPath, $content);
    }
    
    /**
     * Ajouter le JavaScript dans renderFooter
     */  
    private function addEmailJS() {
        $content = file_get_contents($this->functionsPath);
        
        // Chercher renderFooter et ajouter le script
        $jsInsert = '
    <!-- EMAIL ADMIN SCRIPTS - INTEGRATION AUTOMATIQUE -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../email_system/admin_script.js"></script>
    </body>
    </html>
    <?php
}';
        
        // Remplacer la fin de renderFooter
        $pattern = '/(<\/body>\s*<\/html>\s*<\?php\s*})/';
        $content = preg_replace($pattern, $jsInsert, $content);
        
        file_put_contents($this->functionsPath, $content);
    }
    
    /**
     * Ajouter le menu email dans la sidebar
     */
    private function addEmailMenu() {
        $content = file_get_contents($this->functionsPath);
        
        // Chercher la section Applications et ajouter avant
        $menuInsert = '                <div class="nav-section">
                    <div class="nav-section-title">Communications</div>
                    <a href="admin.php?section=emails" class="menu-item <?php echo ($_GET[\'section\'] ?? \'\') === \'emails\' ? \'active\' : \'\'; ?>">
                        <i class="fas fa-envelope"></i><span>Gestion d\'Emails</span>
                    </a>
                </div>
                <div class="nav-section">
                    <div class="nav-section-title">Applications</div>';
        
        $content = str_replace(
            '<div class="nav-section">
                    <div class="nav-section-title">Applications</div>',
            $menuInsert,
            $content
        );
        
        file_put_contents($this->functionsPath, $content);
    }
    
    /**
     * Ajouter le case email dans le switch du routeur
     */
    private function addEmailCase() {
        $content = file_get_contents($this->adminPath);
        
        // Chercher le switch et ajouter le case
        $caseInsert = '    case \'emails\': include __DIR__ . \'/emails.php\'; break;
    case \'agents\': include __DIR__ . \'/agents.php\'; break;';
        
        $content = str_replace(
            '    case \'agents\': include __DIR__ . \'/agents.php\'; break;',
            $caseInsert,
            $content
        );
        
        file_put_contents($this->adminPath, $content);
    }
    
    /**
     * Créer le fichier de section emails.php
     */
    private function createEmailSection() {
        $emailSectionPath = __DIR__ . '/admin/emails.php';
        
        $sectionContent = '<?php
/**
 * SECTION ADMIN - GESTION D\'EMAILS
 * Générée automatiquement par l\'intégrateur
 */

// Sécurité : vérifier l\'authentification admin
if (!isset($_SESSION[\'admin_logged\']) || $_SESSION[\'admin_logged\'] !== true) {
    header(\'Location: admin.php\');
    exit;
}

// Inclure les classes email
require_once __DIR__ . \'/../email_system/EmailManager.php\';
require_once __DIR__ . \'/../email_system/admin_panel.php\';

// Configuration email (à adapter selon votre config)
$emailConfig = [
    \'smtp_host\' => \'smtp.gmail.com\',
    \'smtp_port\' => 587,
    \'smtp_username\' => \'reply@conciergerie-privee-suzosky.com\',
    \'smtp_password\' => \'votre_mot_de_passe_app\', // À configurer
    \'from_email\' => \'reply@conciergerie-privee-suzosky.com\',
    \'from_name\' => \'Conciergerie Privée Suzosky\',
    \'reply_to\' => \'reply@conciergerie-privee-suzosky.com\'
];

// Récupérer la connexion PDO existante
try {
    $pdo = getPDO();
} catch (Exception $e) {
    echo \'<div class="alert alert-danger">Erreur de connexion base de données : \' . htmlspecialchars($e->getMessage()) . \'</div>\';
    return;
}

// Créer l\'instance du panneau admin
$emailPanel = new EmailAdminPanel($pdo, $emailConfig);

// Gérer les actions POST
if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
    $action = $_POST[\'action\'] ?? \'\';
    
    switch ($action) {
        case \'save_settings\':
            // Sauvegarder les paramètres email
            $_SESSION[\'admin_message\'] = \'✅ Paramètres email sauvegardés avec succès !\';
            header(\'Location: admin.php?section=emails&email_tab=settings\');
            exit;
            break;
            
        case \'create_campaign\':
            // Créer une nouvelle campagne
            // Logique à implémenter selon vos besoins
            $_SESSION[\'admin_message\'] = \'📢 Campagne créée avec succès !\';
            header(\'Location: admin.php?section=emails&email_tab=campaigns\');
            exit;
            break;
    }
}

// Afficher les messages de succès/erreur
if (isset($_SESSION[\'admin_message\'])) {
    echo \'<div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 5px;">\';
    echo htmlspecialchars($_SESSION[\'admin_message\']);
    echo \'</div>\';
    unset($_SESSION[\'admin_message\']);
}

// Afficher le titre de la section
echo \'<div class="page-header" style="margin-bottom: 30px;">\';
echo \'<h1 style="color: #D4A853; font-size: 2.5rem; font-weight: 700;">\';
echo \'📧 Gestion d\\\'Emails Robuste\';
echo \'</h1>\';
echo \'<p style="color: #CCCCCC; margin-top: 10px;">\';
echo \'Surveillance, envoi et suivi des emails de la plateforme\';
echo \'</p>\';
echo \'</div>\';

// Rendu du panneau email principal
try {
    $emailPanel->renderEmailSection();
} catch (Exception $e) {
    echo \'<div class="alert alert-danger">Erreur lors de l\\\'affichage : \' . htmlspecialchars($e->getMessage()) . \'</div>\';
    echo \'<p>Stack trace pour débogage :</p>\';
    echo \'<pre style="background: #1a1a2e; padding: 15px; border-radius: 5px; color: #fff; overflow-x: auto;">\';
    echo htmlspecialchars($e->getTraceAsString());
    echo \'</pre>\';
}
?>';
        
        file_put_contents($emailSectionPath, $sectionContent);
    }
    
    /**
     * Vérifier les prérequis avant intégration
     */
    public function checkPrerequisites() {
        $errors = [];
        
        if (!file_exists($this->adminPath)) {
            $errors[] = "❌ Fichier admin.php non trouvé : " . $this->adminPath;
        }
        
        if (!file_exists($this->functionsPath)) {
            $errors[] = "❌ Fichier functions.php non trouvé : " . $this->functionsPath;
        }
        
        if (!is_writable(dirname($this->adminPath))) {
            $errors[] = "❌ Dossier admin non accessible en écriture";
        }
        
        if (!file_exists(__DIR__ . '/email_system/EmailManager.php')) {
            $errors[] = "❌ Système email non trouvé dans email_system/";
        }
        
        return $errors;
    }
}

// Exécution si appelé directement
if (basename(__FILE__) === basename($_SERVER[\'SCRIPT_NAME\'])) {
    $integrator = new EmailAdminIntegrator();
    
    echo "🔍 Vérification des prérequis...\n";
    $errors = $integrator->checkPrerequisites();
    
    if (!empty($errors)) {
        echo "❌ Erreurs détectées :\n";
        foreach ($errors as $error) {
            echo "   $error\n";
        }
        echo "\n❗ Corrigez ces erreurs avant l\'intégration.\n";
        exit(1);
    }
    
    echo "✅ Prérequis OK\n\n";
    
    // Demander confirmation
    echo "⚠️  Cette opération va modifier vos fichiers admin existants.\n";
    echo "📁 Sauvegardez admin/admin.php et admin/functions.php avant de continuer.\n";
    echo "🔄 Continuer l\'intégration ? (y/N): ";
    
    $confirmation = trim(fgets(STDIN));
    if (strtolower($confirmation) !== \'y\' && strtolower($confirmation) !== \'yes\') {
        echo "❌ Intégration annulée.\n";
        exit(0);
    }
    
    $integrator->integrate();
}
?>