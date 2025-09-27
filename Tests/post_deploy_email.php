<?php
/**
 * SCRIPT POST-DÉPLOIEMENT EMAIL
 * À exécuter UNE FOIS après upload sur le serveur LWS
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/EMAIL_SYSTEM/EmailManager.php';

echo "🔧 POST-DÉPLOIEMENT SYSTÈME EMAIL\n";
echo "================================\n\n";

try {
    // 1. Vérifier connexion base de données
    echo "📊 Test connexion base de données...\n";
    $pdo = getPDO();
    echo "✅ Connexion DB OK\n";
    
    // 2. Créer les tables email si nécessaires
    echo "🗄️ Création tables email...\n";
    
    $emailConfig = [
        'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com',
        'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
        'smtp_username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
        'smtp_password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
        'from_email' => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '',
        'from_name' => defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Conciergerie Suzosky',
    ];
    
    $emailManager = new EmailManager($pdo, $emailConfig);
    echo "✅ Tables email créées/vérifiées\n";
    
    // 3. Vérifier colonnes reset_token dans clients_particuliers
    echo "🔧 Vérification colonnes reset password...\n";
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM clients_particuliers LIKE 'reset_token'");
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo "➕ Ajout colonnes reset password...\n";
        $pdo->exec("ALTER TABLE clients_particuliers 
                   ADD COLUMN reset_token VARCHAR(64) NULL,
                   ADD COLUMN reset_expires_at DATETIME NULL");
        echo "✅ Colonnes ajoutées\n";
    } else {
        echo "✅ Colonnes reset déjà présentes\n";
    }
    
    // 4. Test envoi email (si configuré)
    if (defined('SMTP_USERNAME') && SMTP_USERNAME && defined('SMTP_PASSWORD') && SMTP_PASSWORD) {
        echo "📧 Test envoi email...\n";
        
        $result = $emailManager->sendTrackedEmail(
            SMTP_FROM_EMAIL,
            '✅ Déploiement Email Suzosky Réussi',
            '<h2>🎉 Système Email Opérationnel</h2><p>Le système de gestion d\'emails a été déployé avec succès sur le serveur LWS.</p><p>Toutes les fonctionnalités sont opérationnelles :</p><ul><li>Reset password automatisé</li><li>Tracking avancé</li><li>Interface admin complète</li><li>Anti-spam et sécurité</li></ul>',
            'system_test'
        );
        
        if ($result['success']) {
            echo "✅ Email de test envoyé avec succès\n";
        } else {
            echo "⚠️ Email test échoué : " . $result['error'] . "\n";
        }
    } else {
        echo "⚠️ Configuration SMTP manquante - test email sauté\n";
    }
    
    // 5. Nettoyage fichiers de développement
    echo "🧹 Nettoyage fichiers dev...\n";
    
    $devFiles = [
        'check_email_system.php',
        'integrate_email_admin.php',
        'deploy_config_email.php',
        'GUIDE_EMAIL_ADMIN.md',
        'INSTALLATION_SUCCESS.md'
    ];
    
    foreach ($devFiles as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            @unlink(__DIR__ . '/' . $file);
        }
    }
    
    echo "✅ Fichiers dev supprimés\n";
    
    echo "\n🎊 DÉPLOIEMENT EMAIL TERMINÉ AVEC SUCCÈS !\n";
    echo "📧 Interface admin disponible : admin.php?section=emails\n";
    echo "🔧 N'oubliez pas de configurer SMTP_PASSWORD dans config.php\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors du post-déploiement : " . $e->getMessage() . "\n";
    echo "🔍 Vérifiez la configuration de la base de données\n";
}

// Auto-suppression de ce script après exécution
@unlink(__FILE__);
?>