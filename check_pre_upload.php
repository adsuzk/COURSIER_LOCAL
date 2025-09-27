<?php
/**
 * VÉRIFICATION PRE-UPLOAD LWS
 * Script pour vérifier que tous les fichiers sont prêts pour le déploiement
 */

echo "🔍 VÉRIFICATION PRE-UPLOAD LWS\n";
echo "==============================\n\n";

$errors = 0;
$warnings = 0;

// Liste des fichiers critiques pour le déploiement
$criticalFiles = [
    // Système email core
    'email_system/EmailManager.php' => 'Gestionnaire email principal',
    'email_system/admin_panel.php' => 'Interface admin email',
    'email_system/api.php' => 'API indépendante email',
    'email_system/track.php' => 'Système de tracking',
    'email_system/admin_styles.css' => 'Styles CSS admin',
    'email_system/admin_script.js' => 'JavaScript admin',
    'email_system/templates/password_reset_default.html' => 'Template HTML reset',
    
    // Intégration admin
    'admin/emails.php' => 'Section admin emails',
    'admin/admin.php' => 'Admin principal (modifié)',
    'admin/functions.php' => 'Functions admin (modifiée)',
    
    // Scripts de déploiement
    'post_deploy_email.php' => 'Script post-déploiement',
    'GUIDE_DEPLOIEMENT_LWS.md' => 'Guide de déploiement',
    
    // Core application
    'config.php' => 'Configuration principale',
    'index.php' => 'Page d\'accueil',
    'assets/js/connexion_modal.js' => 'Modal connexion (modifié)'
];

echo "📁 Vérification fichiers critiques...\n";

foreach ($criticalFiles as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $size = filesize(__DIR__ . '/' . $file);
        if ($size > 0) {
            echo "  ✅ $description ($size bytes)\n";
        } else {
            echo "  ❌ $description - FICHIER VIDE\n";
            $errors++;
        }
    } else {
        echo "  ❌ $description - MANQUANT\n";
        $errors++;
    }
}

// Vérifier les modifications dans admin.php
echo "\n🔧 Vérification intégrations admin...\n";

$adminContent = file_get_contents(__DIR__ . '/admin/admin.php');
if (strpos($adminContent, "case 'emails'") !== false) {
    echo "  ✅ Case 'emails' présent dans admin.php\n";
} else {
    echo "  ❌ Case 'emails' manquant dans admin.php\n";
    $errors++;
}

$functionsContent = file_get_contents(__DIR__ . '/admin/functions.php');
if (strpos($functionsContent, "section=emails") !== false) {
    echo "  ✅ Menu emails présent dans functions.php\n";
} else {
    echo "  ❌ Menu emails manquant dans functions.php\n";
    $errors++;
}

// Vérifier connexion_modal.js
$modalContent = file_get_contents(__DIR__ . '/assets/js/connexion_modal.js');
if (strpos($modalContent, "/email_system/api.php") !== false) {
    echo "  ✅ Connexion modal utilise nouvelle API email\n";
} else {
    echo "  ⚠️ Connexion modal n'utilise pas la nouvelle API\n";
    $warnings++;
}

// Vérifier structure dossiers
echo "\n📂 Vérification structure dossiers...\n";

$requiredDirs = [
    'email_system',
    'email_system/templates',
    'admin',
    'assets/js'
];

foreach ($requiredDirs as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        echo "  ✅ Dossier $dir présent\n";
    } else {
        echo "  ❌ Dossier $dir manquant\n";
        $errors++;
    }
}

// Calculer la taille totale
echo "\n📊 Statistiques du projet...\n";

function getDirSize($dir) {
    $size = 0;
    foreach(glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : getDirSize($each);
    }
    return $size;
}

$totalSize = getDirSize(__DIR__);
$sizeFormatted = round($totalSize / 1024 / 1024, 2);

echo "  📏 Taille totale projet : {$sizeFormatted} MB\n";
echo "  📁 Fichiers PHP : " . count(glob(__DIR__ . '/*.php')) . "\n";
echo "  📧 Fichiers email system : " . count(glob(__DIR__ . '/email_system/*')) . "\n";

// Vérifier config.php pour les constantes
echo "\n⚙️ Vérification configuration...\n";

$configContent = file_get_contents(__DIR__ . '/config.php');
$requiredConstants = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USERNAME', 'SMTP_PASSWORD'];
$configuredConstants = 0;

foreach ($requiredConstants as $constant) {
    if (strpos($configContent, "define('$constant'") !== false) {
        $configuredConstants++;
        echo "  ✅ Constante $constant définie\n";
    } else {
        echo "  ⚠️ Constante $constant à configurer sur le serveur\n";
        $warnings++;
    }
}

// Résumé final
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 RÉSUMÉ PRE-UPLOAD\n";
echo str_repeat("=", 50) . "\n";

if ($errors === 0 && $warnings === 0) {
    echo "🎉 PARFAIT ! Projet prêt pour le déploiement !\n";
    echo "🚀 Tous les fichiers sont présents et configurés.\n";
} elseif ($errors === 0) {
    echo "✅ PROJET PRÊT avec quelques configurations à finaliser.\n";
    echo "⚠️  $warnings avertissements à vérifier.\n";
} else {
    echo "❌ ERREURS DÉTECTÉES - Corrigez avant upload.\n";
    echo "🛠️  $errors erreurs critiques à résoudre.\n";
}

echo "\n📋 ÉTAPES DE DÉPLOIEMENT :\n";
echo "1. 📤 Uploadez tout le projet sur LWS\n";
echo "2. ⚙️ Configurez config.php avec vos credentials SMTP\n";
echo "3. 🔧 Exécutez post_deploy_email.php sur le serveur\n";
echo "4. 🧪 Testez admin.php?section=emails\n";
echo "\n📖 Consultez GUIDE_DEPLOIEMENT_LWS.md pour les détails !\n";

if ($errors > 0) {
    exit(1);
}
?>