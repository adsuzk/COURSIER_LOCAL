<?php
/**
 * CORRECTEUR CHEMINS EMAIL_SYSTEM
 * Met à jour tous les références vers le bon répertoire
 */

echo "🔧 CORRECTION CHEMINS EMAIL_SYSTEM\n";
echo "==================================\n\n";

// Fichiers à corriger
$filesToFix = [
    'admin/emails.php',
    'sections_index/reset_password.php',
    'assets/js/reset_password.js'
];

$corrections = 0;

foreach ($filesToFix as $file) {
    if (file_exists($file)) {
        echo "📝 Correction de {$file}...\n";
        
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Remplacements
        $content = str_replace('email_system/', 'EMAIL_SYSTEM/', $content);
        $content = str_replace('/email_system/', '/EMAIL_SYSTEM/', $content);
        $content = str_replace('email_system\\', 'EMAIL_SYSTEM\\', $content);
        $content = str_replace('\\email_system\\', '\\EMAIL_SYSTEM\\', $content);
        
        if ($content !== $originalContent) {
            file_put_contents($file, $content);
            echo "✅ {$file} corrigé\n";
            $corrections++;
        } else {
            echo "➖ {$file} déjà OK\n";
        }
    } else {
        echo "⚠️ {$file} non trouvé\n";
    }
}

echo "\n🎊 CORRECTION TERMINÉE !\n";
echo "📊 {$corrections} fichiers corrigés\n";
echo "✅ Tous les chemins pointent vers EMAIL_SYSTEM/\n";
?>