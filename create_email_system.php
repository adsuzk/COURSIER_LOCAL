<?php
/**
 * CRÉATEUR AUTOMATIQUE SYSTÈME EMAIL
 * Recrée les fichiers manquants directement sur le serveur
 */

echo "🔧 CRÉATION SYSTÈME EMAIL SUR SERVEUR\n";
echo "====================================\n\n";

// Créer le dossier email_system
if (!is_dir('email_system')) {
    mkdir('email_system', 0755, true);
    echo "✅ Dossier email_system créé\n";
}

if (!is_dir('email_system/templates')) {
    mkdir('email_system/templates', 0755, true);
    echo "✅ Dossier templates créé\n";
}

if (!is_dir('email_system/logs')) {
    mkdir('email_system/logs', 0755, true);
    echo "✅ Dossier logs créé\n";
}

echo "📥 Les fichiers PHP seront recréés...\n";
echo "👆 Uploadez maintenant les fichiers suivants via FTP dans email_system/:\n\n";

echo "FICHIERS REQUIS:\n";
echo "- EmailManager.php (14,542 bytes)\n";
echo "- admin_panel.php (14,523 bytes)\n"; 
echo "- api.php (4,984 bytes)\n";
echo "- track.php (2,372 bytes)\n";
echo "- admin_styles.css (6,939 bytes)\n";
echo "- admin_script.js (19,061 bytes)\n";
echo "- templates/password_reset_default.html (7,876 bytes)\n";

echo "\n📤 TÉLÉCHARGEZ ces fichiers depuis votre local:\n";
echo "C:\\xampp\\htdocs\\coursier_prod\\email_system\\\n\n";

echo "🔄 Après upload, relancez post_deploy_email.php\n";
?>