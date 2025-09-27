<?php
/**
 * VÉRIFICATION SYSTÈME EMAIL - DIAGNOSTIC COMPLET
 * 
 * Script de diagnostic pour vérifier que le système email
 * est correctement installé et configuré.
 */

require_once __DIR__ . '/config.php';

class EmailSystemChecker {
    private $results = [];
    private $errors = 0;
    private $warnings = 0;
    
    public function runFullDiagnostic() {
        echo "🔍 DIAGNOSTIC SYSTÈME EMAIL SUZOSKY\n";
        echo "===================================\n\n";
        
        $this->checkFileStructure();
        $this->checkDatabaseTables();
        $this->checkConfiguration();
        $this->checkAdminIntegration();
        $this->checkPermissions();
        $this->checkEmailConnectivity();
        
        $this->displayResults();
    }
    
    /**
     * Vérifier la structure des fichiers
     */
    private function checkFileStructure() {
        echo "📁 Vérification structure des fichiers...\n";
        
        $requiredFiles = [
            'email_system/EmailManager.php' => 'Gestionnaire email principal',
            'email_system/admin_panel.php' => 'Interface admin',
            'email_system/admin_styles.css' => 'Styles CSS',
            'email_system/admin_script.js' => 'Scripts JavaScript',
            'email_system/api.php' => 'API indépendante',
            'email_system/track.php' => 'Système de tracking',
            'email_system/templates/password_reset_default.html' => 'Template HTML',
            'admin/emails.php' => 'Section admin emails',
        ];
        
        foreach ($requiredFiles as $file => $description) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $this->addResult('✅', "Fichier $description", "Présent");
            } else {
                $this->addResult('❌', "Fichier $description", "MANQUANT : $file");
                $this->errors++;
            }
        }
    }
    
    /**
     * Vérifier les tables de base de données
     */
    private function checkDatabaseTables() {
        echo "\n🗄️ Vérification base de données...\n";
        
        try {
            $pdo = getPDO();
            
            // Vérifier les tables email
            $requiredTables = [
                'email_logs' => 'Logs des emails',
                'email_campaigns' => 'Campagnes email',
                'email_templates' => 'Templates email'
            ];
            
            foreach ($requiredTables as $table => $description) {
                $stmt = $pdo->prepare("SHOW TABLES LIKE '$table'");
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    // Compter les enregistrements
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM $table");
                    $countStmt->execute();
                    $count = $countStmt->fetchColumn();
                    
                    $this->addResult('✅', "Table $description", "Présente ($count enregistrements)");
                } else {
                    $this->addResult('⚠️', "Table $description", "Sera créée automatiquement");
                    $this->warnings++;
                }
            }
            
            // Vérifier la table clients pour reset password
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'clients_particuliers'");
            $stmt->execute();
            
            if ($stmt->fetch()) {
                // Vérifier les colonnes reset_token
                $columns = $pdo->prepare("SHOW COLUMNS FROM clients_particuliers LIKE 'reset_%'");
                $columns->execute();
                $resetColumns = $columns->fetchAll();
                
                if (count($resetColumns) >= 2) {
                    $this->addResult('✅', "Colonnes reset password", "Configurées");
                } else {
                    $this->addResult('⚠️', "Colonnes reset password", "Seront ajoutées automatiquement");
                    $this->warnings++;
                }
            }
            
        } catch (Exception $e) {
            $this->addResult('❌', "Connexion base de données", "ERREUR : " . $e->getMessage());
            $this->errors++;
        }
    }
    
    /**
     * Vérifier la configuration
     */
    private function checkConfiguration() {
        echo "\n⚙️ Vérification configuration...\n";
        
        // Vérifier les constantes SMTP
        $smtpConstants = [
            'SMTP_HOST' => 'Serveur SMTP',
            'SMTP_PORT' => 'Port SMTP', 
            'SMTP_USERNAME' => 'Nom utilisateur SMTP',
            'SMTP_PASSWORD' => 'Mot de passe SMTP',
            'SMTP_FROM_EMAIL' => 'Email expéditeur',
            'SMTP_FROM_NAME' => 'Nom expéditeur'
        ];
        
        foreach ($smtpConstants as $constant => $description) {
            if (defined($constant) && constant($constant)) {
                $value = $constant === 'SMTP_PASSWORD' ? '***masqué***' : constant($constant);
                $this->addResult('✅', $description, $value);
            } else {
                $this->addResult('⚠️', $description, "NON CONFIGURÉ dans config.php");
                $this->warnings++;
            }
        }
        
        // Vérifier les extensions PHP
        $extensions = ['pdo', 'pdo_mysql', 'curl', 'mbstring', 'openssl'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                $this->addResult('✅', "Extension PHP $ext", "Chargée");
            } else {
                $this->addResult('❌', "Extension PHP $ext", "MANQUANTE");
                $this->errors++;
            }
        }
    }
    
    /**
     * Vérifier l'intégration admin
     */
    private function checkAdminIntegration() {
        echo "\n🎛️ Vérification intégration admin...\n";
        
        // Vérifier le switch dans admin.php
        $adminFile = __DIR__ . '/admin/admin.php';
        if (file_exists($adminFile)) {
            $content = file_get_contents($adminFile);
            if (strpos($content, "case 'emails'") !== false) {
                $this->addResult('✅', "Routage admin", "Section emails intégrée");
            } else {
                $this->addResult('❌', "Routage admin", "Case 'emails' manquant dans admin.php");
                $this->errors++;
            }
        }
        
        // Vérifier le menu dans functions.php  
        $functionsFile = __DIR__ . '/admin/functions.php';
        if (file_exists($functionsFile)) {
            $content = file_get_contents($functionsFile);
            if (strpos($content, "section=emails") !== false) {
                $this->addResult('✅', "Menu admin", "Lien emails présent");
            } else {
                $this->addResult('❌', "Menu admin", "Lien emails manquant dans functions.php");
                $this->errors++;
            }
        }
        
        // Vérifier le fichier de section
        if (file_exists(__DIR__ . '/admin/emails.php')) {
            $this->addResult('✅', "Section emails", "Fichier admin/emails.php présent");
        } else {
            $this->addResult('❌', "Section emails", "Fichier admin/emails.php manquant");
            $this->errors++;
        }
    }
    
    /**
     * Vérifier les permissions
     */
    private function checkPermissions() {
        echo "\n🔒 Vérification permissions...\n";
        
        $directories = [
            'email_system/',
            'email_system/templates/',
            'email_system/logs/'
        ];
        
        foreach ($directories as $dir) {
            $fullPath = __DIR__ . '/' . $dir;
            if (is_dir($fullPath)) {
                if (is_writable($fullPath)) {
                    $this->addResult('✅', "Permissions $dir", "Lecture/Écriture OK");
                } else {
                    $this->addResult('⚠️', "Permissions $dir", "Écriture limitée");
                    $this->warnings++;
                }
            } else {
                // Créer le dossier s'il n'existe pas
                if (@mkdir($fullPath, 0755, true)) {
                    $this->addResult('✅', "Dossier $dir", "Créé automatiquement");
                } else {
                    $this->addResult('❌', "Dossier $dir", "Impossible à créer");
                    $this->errors++;
                }
            }
        }
    }
    
    /**
     * Vérifier la connectivité email
     */
    private function checkEmailConnectivity() {
        echo "\n📧 Vérification connectivité email...\n";
        
        if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME')) {
            $this->addResult('⚠️', "Test SMTP", "Configuration manquante - sautée");
            return;
        }
        
        try {
            // Test de connexion SMTP basique
            $host = constant('SMTP_HOST');
            $port = defined('SMTP_PORT') ? constant('SMTP_PORT') : 587;
            
            $socket = @fsockopen($host, $port, $errno, $errstr, 10);
            if ($socket) {
                fclose($socket);
                $this->addResult('✅', "Connexion SMTP", "Serveur $host:$port accessible");
            } else {
                $this->addResult('❌', "Connexion SMTP", "Impossible de joindre $host:$port ($errstr)");
                $this->errors++;
            }
            
        } catch (Exception $e) {
            $this->addResult('❌', "Test connectivité", "Erreur : " . $e->getMessage());
            $this->errors++;
        }
    }
    
    /**
     * Ajouter un résultat
     */
    private function addResult($status, $test, $result) {
        $this->results[] = compact('status', 'test', 'result');
        echo sprintf("  %s %s: %s\n", $status, $test, $result);
    }
    
    /**
     * Afficher le résumé final
     */
    private function displayResults() {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 RÉSUMÉ DU DIAGNOSTIC\n";
        echo str_repeat("=", 50) . "\n";
        
        $total = count($this->results);
        $success = $total - $this->errors - $this->warnings;
        
        echo "✅ Tests réussis : $success\n";
        echo "⚠️  Avertissements : $this->warnings\n";
        echo "❌ Erreurs : $this->errors\n";
        echo "📈 Total : $total tests\n\n";
        
        if ($this->errors === 0 && $this->warnings === 0) {
            echo "🎉 PARFAIT ! Système email 100% opérationnel !\n";
            echo "🚀 Accédez à admin.php?section=emails pour commencer.\n";
        } elseif ($this->errors === 0) {
            echo "✅ SYSTÈME FONCTIONNEL avec quelques améliorations possibles.\n";
            echo "🔧 Consultez les avertissements ci-dessus.\n";
        } else {
            echo "⚠️  SYSTÈME PARTIELLEMENT OPÉRATIONNEL\n";
            echo "🛠️  Corrigez les erreurs listées ci-dessus.\n";
        }
        
        echo "\n📚 Consultez GUIDE_EMAIL_ADMIN.md pour plus d'informations.\n";
    }
}

// Exécuter le diagnostic si appelé directement
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'] ?? '')) {
    $checker = new EmailSystemChecker();
    $checker->runFullDiagnostic();
}
?>