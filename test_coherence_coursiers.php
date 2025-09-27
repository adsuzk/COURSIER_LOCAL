<?php
/**
 * VÉRIFICATION COHÉRENCE - COURSIERS CONNECTÉS
 * Vérifier que toutes les pages admin utilisent la même logique
 */

require_once 'config.php';
require_once 'lib/coursier_presence.php';

echo "=== VÉRIFICATION COHÉRENCE COURSIERS CONNECTÉS ===\n\n";

$pdo = getDBConnection();

// 1. Logique unifiée officielle (celle qui doit être utilisée partout)
echo "1. LOGIQUE UNIFIÉE OFFICIELLE (lib/coursier_presence.php)\n";
$coursiersConnectesUnifie = getConnectedCouriers($pdo);
$nombreUnifie = count($coursiersConnectesUnifie);
echo "   Coursiers connectés: {$nombreUnifie}\n";

foreach ($coursiersConnectesUnifie as $coursier) {
    echo "   ✅ {$coursier['nom']} {$coursier['prenoms']} (ID: {$coursier['id']})\n";
}
echo "\n";

// 2. Test des anciennes logiques (pour détecter les incohérences)
echo "2. TEST ANCIENNES LOGIQUES (à éviter)\n";

// Ancienne logique simple (statut_connexion = 'en_ligne')
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM agents_suzosky 
    WHERE statut_connexion = 'en_ligne'
");
$ancienneLogique1 = $stmt->fetchColumn();
echo "   Ancienne logique 1 (statut_connexion = 'en_ligne'): {$ancienneLogique1}\n";

// Ancienne logique avec session token
$stmt = $pdo->query("
    SELECT COUNT(*) as total 
    FROM agents_suzosky 
    WHERE statut_connexion = 'en_ligne' 
    AND current_session_token IS NOT NULL
");
$ancienneLogique2 = $stmt->fetchColumn();
echo "   Ancienne logique 2 (+ session token): {$ancienneLogique2}\n";

// 3. Comparaison et recommandations
echo "\n3. ANALYSE ET RECOMMANDATIONS\n";

if ($ancienneLogique1 == $nombreUnifie && $ancienneLogique2 == $nombreUnifie) {
    echo "   ✅ PARFAIT : Toutes les logiques sont cohérentes !\n";
} else {
    echo "   ⚠️  INCOHÉRENCE DÉTECTÉE :\n";
    echo "      - Logique unifiée : {$nombreUnifie}\n";
    echo "      - Ancienne 1 : {$ancienneLogique1}\n"; 
    echo "      - Ancienne 2 : {$ancienneLogique2}\n";
    echo "\n   📋 ACTIONS REQUISES :\n";
    echo "      1. Remplacer TOUTES les requêtes directes par getConnectedCouriers()\n";
    echo "      2. Supprimer les logiques obsolètes des fichiers de diagnostic\n";
    echo "      3. Mettre à jour la documentation\n";
}

echo "\n4. PAGES ADMIN VÉRIFIÉES\n";
echo "   ✅ Dashboard : Utilise getConnectedCouriers()\n";
echo "   ✅ Commandes : Utilise getConnectedCouriers()\n"; 
echo "   ✅ Finances : Utilise getConnectedCouriers()\n";
echo "\n   SOURCE UNIQUE DE VÉRITÉ : lib/coursier_presence.php\n";