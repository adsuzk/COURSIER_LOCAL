<?php
$file = 'admin/comptabilite.php';
$content = file_get_contents($file);

// Lire le nouveau style
$newStyle = file_get_contents('compta_style_dark.css');

// Trouver et remplacer la section <style>...</style>
$pattern = '/<style>.*?<\/style>/s';
$replacement = "<style>\n" . $newStyle . "\n</style>";

$content = preg_replace($pattern, $replacement, $content);

file_put_contents($file, $content);

echo "✅ Style Suzosky appliqué avec succès !\n";
echo "📊 Couleurs Dark officielles:\n";
echo "   - Gold: #D4A853\n";
echo "   - Dark: #1A1A2E\n";
echo "   - Blue: #16213E\n";
echo "   - Accent: #0F3460\n";
echo "   - Green: #27AE60\n";
echo "   - Red: #E94560\n";
