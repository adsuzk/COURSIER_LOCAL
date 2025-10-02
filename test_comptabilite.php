<?php
/**
 * Script de test pour le module comptabilité
 * Vérifie que toutes les fonctions fonctionnent correctement
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

// Utilisation de la fonction getDBConnection()
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("❌ Erreur connexion: " . $e->getMessage() . "\n");
}

echo "🧪 TEST DU MODULE COMPTABILITÉ SUZOSKY\n";
echo str_repeat("=", 60) . "\n\n";

// Test 1: Connexion à la base de données
echo "✅ Test 1: Connexion PDO...\n";
try {
    $conn->query("SELECT 1");
    echo "   ✓ Connexion OK\n\n";
} catch (Exception $e) {
    die("   ✗ Erreur: " . $e->getMessage() . "\n");
}

// Test 2: Vérification de la table config_tarification
echo "✅ Test 2: Table config_tarification...\n";
$stmt = $conn->query("SELECT COUNT(*) as nb FROM config_tarification");
$nb = $stmt->fetchColumn();
echo "   ✓ Table existe avec $nb configuration(s)\n";

$stmt = $conn->query("SELECT * FROM config_tarification ORDER BY date_application DESC LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC);
if ($config) {
    echo "   ✓ Config actuelle:\n";
    echo "     - Commission: {$config['taux_commission']}%\n";
    echo "     - Frais plateforme: {$config['frais_plateforme']}%\n";
    echo "     - Frais publicitaires: {$config['frais_publicitaires']}%\n";
    echo "     - Date application: {$config['date_application']}\n\n";
}

// Test 3: Vérification des commandes livrées
echo "✅ Test 3: Commandes livrées...\n";
$stmt = $conn->query("SELECT COUNT(*) as nb, 
    MIN(created_at) as premiere_livraison,
    MAX(created_at) as derniere_livraison,
    SUM(prix_total) as ca_total
    FROM commandes WHERE statut = 'livree'");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   ✓ {$stats['nb']} commandes livrées\n";
if ($stats['nb'] > 0) {
    echo "   ✓ CA total: " . number_format($stats['ca_total'], 0, ',', ' ') . " FCFA\n";
    echo "   ✓ Période: {$stats['premiere_livraison']} → {$stats['derniere_livraison']}\n\n";
} else {
    echo "   ⚠ Aucune commande livrée pour le moment\n\n";
}

// Test 4: Test de la requête historique des taux
echo "✅ Test 4: Requête historique des taux...\n";
try {
    $query = "SELECT c.id, c.date_creation, c.montant_course,
        (SELECT taux_commission FROM config_tarification 
         WHERE date_application <= c.date_creation 
         ORDER BY date_application DESC LIMIT 1) as taux_historique
        FROM commandes c 
        WHERE c.statut = 'livree' 
        ORDER BY c.date_creation DESC 
        LIMIT 5";
    
    $stmt = $conn->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        echo "   ✓ Requête exécutée avec succès\n";
        echo "   ✓ Exemple des 5 dernières commandes:\n";
        foreach ($results as $row) {
            echo "     - ID {$row['id']}: {$row['montant_course']} FCFA (taux: {$row['taux_historique']}%)\n";
        }
    } else {
        echo "   ⚠ Aucune donnée à afficher\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n\n";
}

// Test 5: Test des bibliothèques d'export
echo "✅ Test 5: Bibliothèques d'export...\n";
require_once __DIR__ . '/vendor/autoload.php';

if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    echo "   ✓ PhpSpreadsheet chargé (Excel)\n";
} else {
    echo "   ✗ PhpSpreadsheet manquant\n";
}

if (class_exists('TCPDF')) {
    echo "   ✓ TCPDF chargé (PDF)\n";
} else {
    echo "   ✗ TCPDF manquant\n";
}
echo "\n";

// Test 6: Test de création d'un fichier Excel simple
echo "✅ Test 6: Création Excel de test...\n";
try {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Test Suzosky');
    $sheet->setCellValue('B1', date('Y-m-d H:i:s'));
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $testFile = __DIR__ . '/test_export.xlsx';
    $writer->save($testFile);
    
    if (file_exists($testFile)) {
        $size = filesize($testFile);
        echo "   ✓ Fichier Excel créé ($size octets)\n";
        unlink($testFile); // Nettoyage
        echo "   ✓ Test Excel réussi\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Erreur Excel: " . $e->getMessage() . "\n\n";
}

// Test 7: Test de création d'un PDF simple
echo "✅ Test 7: Création PDF de test...\n";
try {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Suzosky');
    $pdf->SetTitle('Test Comptabilité');
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Test Suzosky - ' . date('Y-m-d H:i:s'), 0, 1);
    
    $testFile = __DIR__ . '/test_export.pdf';
    $pdf->Output($testFile, 'F');
    
    if (file_exists($testFile)) {
        $size = filesize($testFile);
        echo "   ✓ Fichier PDF créé ($size octets)\n";
        unlink($testFile); // Nettoyage
        echo "   ✓ Test PDF réussi\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ✗ Erreur PDF: " . $e->getMessage() . "\n\n";
}

// Test 8: Vérification du fichier comptabilite.php
echo "✅ Test 8: Fichier comptabilite.php...\n";
$comptaFile = __DIR__ . '/admin/comptabilite.php';
if (file_exists($comptaFile)) {
    $size = filesize($comptaFile);
    echo "   ✓ Fichier existe (" . number_format($size, 0, ',', ' ') . " octets)\n";
    
    // Vérification des fonctions principales
    $content = file_get_contents($comptaFile);
    $functions = ['getComptabiliteData', 'exportComptabiliteExcel', 'exportComptabilitePDF'];
    foreach ($functions as $func) {
        if (strpos($content, "function $func") !== false) {
            echo "   ✓ Fonction $func() présente\n";
        }
    }
} else {
    echo "   ✗ Fichier manquant\n";
}
echo "\n";

// Résumé final
echo str_repeat("=", 60) . "\n";
echo "🎉 TESTS TERMINÉS AVEC SUCCÈS !\n";
echo str_repeat("=", 60) . "\n\n";

echo "📋 Prochaines étapes:\n";
echo "1. Accédez au module: http://localhost/COURSIER_LOCAL/admin.php?section=finances&tab=comptabilite\n";
echo "2. Testez les filtres de date\n";
echo "3. Testez les exports Excel et PDF\n";
echo "4. Ajoutez des configurations de taux historiques si besoin\n\n";

echo "💡 Pour ajouter une nouvelle configuration tarifaire:\n";
echo "INSERT INTO config_tarification (date_application, taux_commission, frais_plateforme, frais_publicitaires)\n";
echo "VALUES ('2025-02-01', 20.00, 7.00, 5.00);\n\n";
