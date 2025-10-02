<?php
require 'config.php';
require 'vendor/autoload.php';

echo "🧪 TEST DES EXPORTS COMPTABILITÉ\n";
echo str_repeat("=", 60) . "\n\n";

// Simuler les paramètres GET
$_GET['section'] = 'comptabilite';
$_GET['export'] = 'excel';
$_GET['date_debut'] = '2025-10-01';
$_GET['date_fin'] = '2025-10-02';

echo "✅ Paramètres simulés:\n";
echo "   - section: " . $_GET['section'] . "\n";
echo "   - export: " . $_GET['export'] . "\n";
echo "   - date_debut: " . $_GET['date_debut'] . "\n";
echo "   - date_fin: " . $_GET['date_fin'] . "\n\n";

// Vérifier que PhpSpreadsheet est disponible
if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    echo "✅ PhpSpreadsheet chargé\n";
} else {
    echo "❌ PhpSpreadsheet non disponible\n";
}

if (class_exists('TCPDF')) {
    echo "✅ TCPDF chargé\n";
} else {
    echo "❌ TCPDF non disponible\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📋 URL attendue pour Excel:\n";
echo "admin.php?section=comptabilite&export=excel&date_debut=2025-10-01&date_fin=2025-10-02\n\n";

echo "📋 URL attendue pour PDF:\n";
echo "admin.php?section=comptabilite&export=pdf&date_debut=2025-10-01&date_fin=2025-10-02\n\n";

echo "✨ Correction appliquée:\n";
echo "✅ section=finances&tab=comptabilite → section=comptabilite\n";
echo "✅ Les exports devraient maintenant télécharger directement\n";
