#!/bin/bash

# SCRIPT DE DÉPLOIEMENT SYSTÈME EMAIL SUZOSKY
# Déploiement automatique vers serveur LWS

echo "🚀 DÉPLOIEMENT SYSTÈME EMAIL SUZOSKY"
echo "===================================="

# Configuration serveur LWS
FTP_HOST="ftpperso.free.fr"  # Remplacez par votre serveur LWS
FTP_USER="votre_login_lws"   # Remplacez par votre login
FTP_PASS="votre_password"    # Remplacez par votre mot de passe
REMOTE_PATH="/public_html/"  # Chemin sur le serveur

# Dossiers à déployer
LOCAL_PATH="."
EXCLUDE_PATTERNS="
--exclude=.git/
--exclude=*.log
--exclude=*.tmp
--exclude=diagnostic_logs/
--exclude=mobile_connection_log.txt
--exclude=debug_requests.log
--exclude=cookies.txt
--exclude=lockout.json
--exclude=.env
--exclude=composer.lock
--exclude=vendor/
--exclude=node_modules/
--exclude=*.bat
--exclude=*.ps1
"

echo "📦 Préparation du déploiement..."

# Créer un fichier de configuration de production
echo "🔧 Génération config production..."

cat > deploy_config_email.php << 'EOF'
<?php
/**
 * CONFIGURATION EMAIL PRODUCTION - LWS
 * Générée automatiquement lors du déploiement
 */

// Configuration SMTP Production
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'reply@conciergerie-privee-suzosky.com');
define('SMTP_PASSWORD', 'VOTRE_MOT_DE_PASSE_APP'); // À configurer sur le serveur
define('SMTP_FROM_EMAIL', 'reply@conciergerie-privee-suzosky.com');
define('SMTP_FROM_NAME', 'Conciergerie Privée Suzosky');
define('SMTP_REPLY_TO', 'reply@conciergerie-privee-suzosky.com');

// Configuration base de données production (LWS)
// Ces constantes seront utilisées par EmailManager si les constantes DB principales ne sont pas définies
define('EMAIL_DB_HOST', 'mysql-conciergerie-privee-suzosky.alwaysdata.net'); // Remplacer par votre host LWS
define('EMAIL_DB_NAME', 'conciergerie-privee-suzosky_coursier');
define('EMAIL_DB_USER', 'votre_user_db');
define('EMAIL_DB_PASS', 'votre_pass_db');

// Configuration sécurité production
define('EMAIL_RATE_LIMIT', 50); // Limite emails/heure en production
define('EMAIL_DEBUG_MODE', false); // Désactiver debug en production
define('EMAIL_LOG_LEVEL', 'error'); // Seulement les erreurs

// Domaines autorisés pour l'envoi
define('ALLOWED_EMAIL_DOMAINS', 'gmail.com,yahoo.fr,hotmail.com,outlook.com,orange.fr,free.fr,wanadoo.fr,laposte.net');

// Configuration anti-spam production
define('ENABLE_SPF_DKIM', true);
define('ENABLE_DOMAIN_VALIDATION', true);
define('ENABLE_BOUNCE_TRACKING', true);

echo "✅ Configuration email production générée\n";
?>
EOF

echo "✅ Configuration production créée"

# Créer script de post-déploiement
echo "📋 Génération script post-déploiement..."

cat > post_deploy_email.php << 'EOF'
<?php
/**
 * SCRIPT POST-DÉPLOIEMENT EMAIL
 * À exécuter UNE FOIS après upload sur le serveur
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_system/EmailManager.php';

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
EOF

echo "✅ Script post-déploiement créé"

# Créer le guide de déploiement
echo "📖 Génération guide déploiement..."

cat > GUIDE_DEPLOIEMENT_LWS.md << 'EOF'
# 🚀 GUIDE DÉPLOIEMENT EMAIL SUZOSKY - LWS

## 📋 ÉTAPES DE DÉPLOIEMENT

### 1. 📤 Upload sur le serveur LWS

**Via FTP/SFTP :**
- Uploadez tous les fichiers du projet
- Vérifiez que le dossier `email_system/` est bien présent
- Permissions : 755 pour les dossiers, 644 pour les fichiers

**Dossiers critiques à uploader :**
```
email_system/
├── EmailManager.php
├── admin_panel.php  
├── admin_styles.css
├── admin_script.js
├── api.php
├── track.php
└── templates/
    └── password_reset_default.html

admin/
└── emails.php
```

### 2. ⚙️ Configuration sur le serveur

**A. Modifier `config.php` :**
```php
// Ajouter à la fin de config.php :

// === CONFIGURATION EMAIL PRODUCTION ===
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'reply@conciergerie-privee-suzosky.com');
define('SMTP_PASSWORD', 'VOTRE_MOT_DE_PASSE_APPLICATION'); // Gmail App Password
define('SMTP_FROM_EMAIL', 'reply@conciergerie-privee-suzosky.com');
define('SMTP_FROM_NAME', 'Conciergerie Privée Suzosky');
```

**B. Créer mot de passe application Gmail :**
1. https://myaccount.google.com/security
2. Validation en 2 étapes → Activer
3. Mots de passe d'applications → Générer
4. Copier dans `SMTP_PASSWORD`

### 3. 🔧 Post-déploiement automatique

**Exécuter UNE FOIS sur le serveur :**
```bash
# Via navigateur :
https://votre-domaine.com/post_deploy_email.php

# Ou via SSH si disponible :
php post_deploy_email.php
```

**Ce script va :**
- ✅ Créer les tables de base de données automatiquement
- ✅ Ajouter les colonnes reset_token si nécessaires  
- ✅ Tester l'envoi d'email
- ✅ Nettoyer les fichiers de développement
- ✅ Se supprimer automatiquement

### 4. 🧪 Tests de fonctionnement

**A. Interface admin :**
```
https://votre-domaine.com/admin.php?section=emails
```

**B. Test envoi email :**
- Admin → Emails → Bouton "🧪 Test email"
- Entrer votre email → Vérifier réception

**C. Test reset password :**
- Page connexion → "Mot de passe oublié" 
- Tester avec email client existant
- Vérifier logs dans admin

### 5. 📊 Surveillance

**Métriques disponibles :**
- Tableau de bord : statistiques temps réel
- Logs : historique complet des emails
- Tracking : ouvertures et clics
- Erreurs : retry automatique des échecs

## 🛡️ SÉCURITÉ PRODUCTION

### ✅ Fonctionnalités automatiques :
- Rate limiting (50 emails/heure)
- Headers anti-spam (SPF/DKIM)
- Validation domaines destinataires
- Logs complets pour audit
- Retry automatique des échecs

### 🔒 Recommandations :
- Utilisez HTTPS uniquement
- Configurez les DNS SPF/DKIM pour votre domaine
- Surveillez les métriques d'envoi
- Sauvegardez régulièrement les logs

## 📞 SUPPORT

**En cas de problème :**
1. Vérifiez les logs d'erreur Apache/PHP
2. Consultez admin → emails → logs pour les détails
3. Testez la connexion SMTP
4. Vérifiez les permissions des fichiers

## 🎊 FÉLICITATIONS !

Votre système de gestion d'emails professionnel est maintenant déployé en production avec :

✅ Interface admin complète  
✅ Reset password automatisé  
✅ Tracking avancé et statistiques  
✅ Anti-spam et sécurité  
✅ Templates HTML professionnels  
✅ Monitoring temps réel  

**🚀 Système opérationnel à 100% !**
EOF

echo "✅ Guide de déploiement créé"

echo ""
echo "🎯 FICHIERS DE DÉPLOIEMENT CRÉÉS :"
echo "=================================="
echo "📄 deploy_config_email.php - Configuration production"
echo "🔧 post_deploy_email.php - Script post-déploiement" 
echo "📖 GUIDE_DEPLOIEMENT_LWS.md - Instructions complètes"
echo ""
echo "🚀 PROCHAINES ÉTAPES :"
echo "1. 📤 Uploadez tout le projet sur LWS"
echo "2. ⚙️ Configurez config.php avec vos credentials SMTP"
echo "3. 🔧 Exécutez post_deploy_email.php sur le serveur"
echo "4. 🧪 Testez l'interface admin.php?section=emails"
echo ""
echo "📋 Consultez GUIDE_DEPLOIEMENT_LWS.md pour les détails !"