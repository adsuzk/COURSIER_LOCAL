<?php
/**
 * DIAGNOSTIC RAPIDE SERVEUR
 * Vérification de ce qui est présent/manquant sur le serveur
 */

echo "🔍 DIAGNOSTIC SERVEUR LWS\n";
echo "========================\n\n";

echo "📂 Vérification dossiers:\n";
$dirs = ['email_system', 'email_system/templates', 'admin', 'assets/js'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '/*');
        echo "✅ $dir (" . count($files) . " fichiers)\n";
    } else {
        echo "❌ $dir MANQUANT\n";
    }
}

echo "\n📄 Vérification fichiers email_system:\n";
$emailFiles = [
    'email_system/EmailManager.php',
    'email_system/admin_panel.php', 
    'email_system/api.php',
    'email_system/track.php',
    'email_system/admin_styles.css',
    'email_system/admin_script.js',
    'email_system/templates/password_reset_default.html'
];

foreach ($emailFiles as $file) {
    if (file_exists($file)) {
        echo "✅ " . basename($file) . " (" . filesize($file) . " bytes)\n";
    } else {
        echo "❌ " . basename($file) . " MANQUANT\n";
    }
}

echo "\n📊 Résumé:\n";
echo "Répertoire actuel: " . __DIR__ . "\n";
echo "Fichiers PHP trouvés: " . count(glob('*.php')) . "\n";

if (!is_dir('email_system')) {
    echo "\n🚨 PROBLÈME: Le dossier email_system n'a pas été uploadé!\n";
    echo "📤 ACTION: Uploadez le dossier email_system/ complet via FTP\n";
}
?>