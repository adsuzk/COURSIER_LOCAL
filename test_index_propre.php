<?php
/**
 * TEST PROPRE INDEX - VÉRIFICATION COMMANDE
 * Test simple pour vérifier que la commande apparaît sur l'index
 */

echo "=== TEST INDEX PROPRE ===\n\n";

$commandeCode = 'CMD20250927234101';
$indexUrl = 'https://localhost/COURSIER_LOCAL/index.php';

echo "1. VÉRIFICATION COMMANDE:\n";
echo "   Code recherché: {$commandeCode}\n";
echo "   URL à tester: {$indexUrl}\n\n";

echo "2. TEST CURL SIMPLE:\n";

// Utiliser cURL PHP au lieu de PowerShell
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $indexUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($error) {
    echo "   ❌ Erreur cURL: {$error}\n";
} else {
    echo "   ✅ Code HTTP: {$httpCode}\n";
    
    if ($httpCode == 200) {
        if (strpos($response, $commandeCode) !== false) {
            echo "   ✅ SUCCÈS: Commande {$commandeCode} trouvée sur l'index!\n";
        } else {
            echo "   ⚠️  ATTENTION: Commande {$commandeCode} non trouvée sur l'index\n";
            echo "   📝 Vérifiez manuellement sur: {$indexUrl}\n";
        }
        
        // Analyser le contenu pour plus d'infos
        if (strpos($response, 'commandes') !== false) {
            echo "   📊 Section commandes détectée\n";
        }
        if (strpos($response, 'timeline') !== false) {
            echo "   ⏰ Timeline détectée\n";
        }
    } else {
        echo "   ❌ Erreur HTTP: Code {$httpCode}\n";
    }
}

curl_close($ch);

echo "\n3. VÉRIFICATION MANUELLE RECOMMANDÉE:\n";
echo "   🌐 Ouvrir navigateur: {$indexUrl}\n";
echo "   🔍 Rechercher: {$commandeCode}\n";
echo "   📊 Vérifier statut: 'en_route_livraison'\n";
echo "   ⏰ Vérifier timeline complète\n\n";

echo "4. ÉTAT FINAL SYSTÈME:\n";

// Vérification rapide système unifié
require_once 'config.php';
require_once 'lib/coursier_presence.php';

$pdo = getDBConnection();
$coursiersConnectes = getConnectedCouriers($pdo);

echo "   🚚 Coursiers connectés: " . count($coursiersConnectes) . "\n";

// Dernière commande
$stmt = $pdo->query("
    SELECT code_commande, statut, updated_at 
    FROM commandes 
    ORDER BY id DESC 
    LIMIT 1
");
$derniereCommande = $stmt->fetch(PDO::FETCH_ASSOC);

if ($derniereCommande) {
    echo "   📦 Dernière commande: {$derniereCommande['code_commande']}\n";
    echo "   🔄 Statut: {$derniereCommande['statut']}\n";
    echo "   ⏰ MAJ: {$derniereCommande['updated_at']}\n";
}

echo "\n✅ TEST TERMINÉ - Vérification manuelle recommandée!\n";
?>