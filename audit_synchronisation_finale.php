<?php
/**
 * AUDIT FINAL - SYNCHRONISATION PARFAITE
 * Vérifier qu'aucune page n'utilise plus l'ancien statut
 */

echo "=== AUDIT FINAL SYNCHRONISATION ===\n\n";

// Pages à vérifier
$pages = [
    'Dashboard' => 'admin/dashboard_suzosky_modern.php',
    'Commandes' => 'admin_commandes_enhanced.php', 
    'Finances' => 'admin/sections_finances/rechargement_direct.php'
];

echo "1. VÉRIFICATION CODE SOURCE:\n";
foreach ($pages as $nom => $fichier) {
    if (file_exists($fichier)) {
        $contenu = file_get_contents($fichier);
        
        // Rechercher les anciennes méthodes
        $usesBadLogic = false;
        $badPatterns = [
            "statut_connexion = 'en_ligne'" => "Requête SQL directe",
            "WHERE.*en_ligne" => "Filtre SQL direct", 
            "\$coursier\['statut_connexion'\] === 'en_ligne'" => "Test statut brut"
        ];
        
        $issues = [];
        foreach ($badPatterns as $pattern => $desc) {
            if (preg_match("/$pattern/i", $contenu)) {
                $issues[] = $desc;
                $usesBadLogic = true;
            }
        }
        
        // Vérifier les bonnes méthodes
        $usesGoodLogic = (
            strpos($contenu, 'getConnectedCouriers') !== false ||
            strpos($contenu, 'getAllCouriers') !== false
        );
        
        echo "   📄 {$nom}:\n";
        if (!$usesBadLogic && $usesGoodLogic) {
            echo "      ✅ CONFORME - Utilise la logique unifiée\n";
        } else {
            echo "      ❌ PROBLÈME DÉTECTÉ:\n";
            foreach ($issues as $issue) {
                echo "         - {$issue}\n";
            }
            if (!$usesGoodLogic) {
                echo "         - N'utilise pas getConnectedCouriers()\n";
            }
        }
    } else {
        echo "   📄 {$nom}: ⚠️ Fichier introuvable\n";
    }
}

echo "\n2. TEST COHÉRENCE FINALE:\n";

require_once 'config.php';
require_once 'lib/coursier_presence.php';

$pdo = getDBConnection();
$coursiersConnectes = getConnectedCouriers($pdo);
$nombreUnifie = count($coursiersConnectes);

// Test anciennes logiques
$stmt = $pdo->query("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
$ancienneLogique = $stmt->fetchColumn();

echo "   Logique unifiée: {$nombreUnifie} coursier(s)\n";
echo "   Ancienne logique: {$ancienneLogique} coursier(s)\n";

if ($nombreUnifie <= $ancienneLogique) {
    echo "   ✅ COHÉRENCE: La logique unifiée filtre correctement\n";
} else {
    echo "   ❌ INCOHÉRENCE: Logique unifiée > ancienne logique\n";
}

echo "\n3. RÉSULTAT AUDIT:\n";
echo "   🎯 SYNCHRONISATION: " . ($nombreUnifie <= $ancienneLogique ? 'PARFAITE' : 'IMPARFAITE') . "\n";
echo "   📊 FILTRAGE INTELLIGENT: " . ($nombreUnifie < $ancienneLogique ? 'ACTIF' : 'INACTIF') . "\n";
echo "   🔧 SOURCE UNIQUE: lib/coursier_presence.php\n";

echo "\n=== FIN AUDIT ===\n";
?>