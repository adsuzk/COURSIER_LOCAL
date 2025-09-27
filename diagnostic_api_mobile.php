<?php
/**
 * DIAGNOSTIC API MOBILE - ERREUR 500
 * Identifier et corriger l'erreur dans get_coursier_data.php
 */

require_once 'config.php';

echo "=== DIAGNOSTIC API MOBILE ===\n\n";

// 1. Test direct du fichier API
echo "1. TEST DIRECT API:\n";
$apiFile = 'api/get_coursier_data.php';

if (file_exists($apiFile)) {
    echo "   ✅ Fichier API trouvé: {$apiFile}\n";
    
    // Test de syntaxe PHP
    $output = [];
    $return_var = 0;
    exec("C:\\xampp\\php\\php.exe -l {$apiFile} 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "   ✅ Syntaxe PHP: OK\n";
    } else {
        echo "   ❌ Erreur syntaxe PHP:\n";
        foreach ($output as $line) {
            echo "      {$line}\n";
        }
    }
} else {
    echo "   ❌ Fichier API introuvable: {$apiFile}\n";
}

// 2. Test avec données réelles
echo "\n2. TEST AVEC DONNÉES RÉELLES:\n";

// Récupérer un coursier connecté
require_once 'lib/coursier_presence.php';
$pdo = getDBConnection();
$coursiersConnectes = getConnectedCouriers($pdo);

if (!empty($coursiersConnectes)) {
    $testCoursier = $coursiersConnectes[0];
    echo "   📱 Test avec coursier: {$testCoursier['nom']} {$testCoursier['prenoms']}\n";
    echo "   🆔 ID: {$testCoursier['id']}\n";
    
    // Simuler la requête POST
    $_POST = [
        'action' => 'get_coursier_data',
        'coursier_id' => $testCoursier['id']
    ];
    
    echo "   🔄 Simulation requête POST...\n";
    
    // Capturer la sortie de l'API
    ob_start();
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    try {
        include 'api/get_coursier_data.php';
        $apiOutput = ob_get_contents();
        ob_end_clean();
        
        echo "   ✅ API exécutée sans erreur fatale\n";
        echo "   📤 Sortie API: " . substr($apiOutput, 0, 100) . "...\n";
        
        // Tenter de décoder le JSON
        $jsonData = json_decode($apiOutput, true);
        if ($jsonData) {
            echo "   ✅ JSON valide\n";
            echo "   🎯 Success: " . ($jsonData['success'] ? 'true' : 'false') . "\n";
        } else {
            echo "   ❌ JSON invalide\n";
        }
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        ob_end_clean();
        echo "   ❌ Erreur fatale: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "   ⚠️  Aucun coursier connecté pour test\n";
}

// 3. Vérification logs d'erreur
echo "\n3. VÉRIFICATION LOGS:\n";
$errorLog = 'C:\\xampp\\apache\\logs\\error.log';

if (file_exists($errorLog)) {
    // Lire les dernières lignes du log
    $lines = file($errorLog);
    $recentLines = array_slice($lines, -10);
    
    echo "   📋 Dernières erreurs Apache:\n";
    foreach ($recentLines as $line) {
        if (strpos($line, 'get_coursier_data') !== false || 
            strpos($line, 'PHP Fatal') !== false ||
            strpos($line, 'PHP Parse') !== false) {
            echo "      🔴 " . trim($line) . "\n";
        }
    }
} else {
    echo "   ⚠️  Log d'erreur Apache non trouvé\n";
}

echo "\n✅ DIAGNOSTIC TERMINÉ\n";
?>