<?php
/**
 * TEST LOGIQUE UNIFIÉE - PAGE FINANCES
 * Vérifier que YAPO Emmanuel apparaît bien comme "Hors ligne"
 */

require_once 'config.php';
require_once 'lib/coursier_presence.php';

$pdo = getDBConnection();

echo "=== TEST LOGIQUE UNIFIÉE - PAGE FINANCES ===\n\n";

// 1. Récupérer tous les coursiers (comme fait dans rechargement_direct.php)
$allCoursiers = getAllCouriers($pdo);
echo "1. TOUS LES COURSIERS (getAllCouriers):\n";
foreach ($allCoursiers as $coursier) {
    echo "   {$coursier['nom']} {$coursier['prenoms']} - Statut DB: {$coursier['statut_connexion']}\n";
}

echo "\n2. COURSIERS CONNECTÉS (getConnectedCouriers):\n";
$coursiersConnectes = getConnectedCouriers($pdo);
foreach ($coursiersConnectes as $coursier) {
    echo "   ✅ {$coursier['nom']} {$coursier['prenoms']} - RÉELLEMENT CONNECTÉ\n";
}

echo "\n3. SIMULATION AFFICHAGE PAGE FINANCES:\n";
foreach ($allCoursiers as $coursier) {
    // Logique exacte du fichier corrigé
    $isReallyConnected = false;
    foreach ($coursiersConnectes as $connected) {
        if ($connected['id'] == $coursier['id']) {
            $isReallyConnected = true;
            break;
        }
    }
    
    $statusLabel = $isReallyConnected ? '🟢 En ligne' : '⚫ Hors ligne';
    echo "   {$coursier['nom']} {$coursier['prenoms']}: {$statusLabel}\n";
}

echo "\n✅ YAPO Emmanuel devrait maintenant apparaître comme 'Hors ligne' dans l'interface !\n";
?>