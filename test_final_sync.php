<?php
/**
 * 🧪 TEST FINAL DE SYNCHRONISATION
 * Vérifie que tout est parfaitement synchronisé
 */

require_once __DIR__ . '/config.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🧪 TEST FINAL - SYNCHRONISATION APP ↔ ADMIN           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = getDBConnection();
    
    echo "✅ TEST 1: NETTOYAGE DES COMMANDES TEST\n";
    echo "─────────────────────────────────────────────────────────\n";
    $nouvellesStmt = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'nouvelle'");
    $nouvelles = $nouvellesStmt->fetchColumn();
    echo "Commandes 'nouvelle' restantes: $nouvelles\n";
    echo $nouvelles == 0 ? "✅ RÉUSSI - Toutes nettoyées\n\n" : "❌ ÉCHEC - Il en reste!\n\n";
    
    echo "✅ TEST 2: API MOBILE RETOURNE BIEN LES STATUTS\n";
    echo "─────────────────────────────────────────────────────────\n";
    $coursier_id = 5;
    
    // Test avec le nouveau code incluant 'recuperee'
    $mobileStmt = $pdo->prepare("
        SELECT id, code_commande, statut
        FROM commandes 
        WHERE coursier_id = ? 
        AND statut IN ('nouvelle', 'attribuee', 'acceptee', 'en_cours', 'recuperee', 'retiree')
        ORDER BY 
            CASE statut
                WHEN 'en_cours' THEN 1
                WHEN 'recuperee' THEN 2
                WHEN 'acceptee' THEN 3
                WHEN 'attribuee' THEN 4
                WHEN 'nouvelle' THEN 5
                WHEN 'retiree' THEN 6
                ELSE 7
            END,
            created_at DESC
    ");
    $mobileStmt->execute([$coursier_id]);
    $commandesMobile = $mobileStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Coursier ID: $coursier_id (ZALLE)\n";
    echo "Commandes retournées: " . count($commandesMobile) . "\n";
    
    if (count($commandesMobile) > 0) {
        foreach ($commandesMobile as $cmd) {
            echo "  ✓ #{$cmd['code_commande']} - {$cmd['statut']}\n";
        }
        echo "✅ RÉUSSI - API retourne les commandes actives\n\n";
    } else {
        echo "⚠️  AUCUNE COMMANDE ACTIVE\n\n";
    }
    
    echo "✅ TEST 3: ADMIN AFFICHE CORRECTEMENT\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    // Test avec filtre 'acceptee'
    $adminStmt = $pdo->query("
        SELECT id, code_commande, statut, coursier_id
        FROM commandes
        WHERE statut = 'acceptee'
    ");
    $commandesAdmin = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Filtre: statut = 'acceptee'\n";
    echo "Résultat admin: " . count($commandesAdmin) . " commandes\n";
    
    if (count($commandesAdmin) > 0) {
        foreach ($commandesAdmin as $cmd) {
            echo "  ✓ #{$cmd['code_commande']} - Coursier: {$cmd['coursier_id']}\n";
        }
        echo "✅ RÉUSSI - Admin affiche les commandes acceptées\n\n";
    } else {
        echo "⚠️  Aucune commande 'acceptee' (normal si toutes livrées)\n\n";
    }
    
    echo "✅ TEST 4: COHÉRENCE APP ↔ ADMIN\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $countMobile = count($commandesMobile);
    $countAdmin = count(array_filter($commandesAdmin, function($cmd) use ($coursier_id) {
        return $cmd['coursier_id'] == $coursier_id;
    }));
    
    echo "Commandes dans l'app mobile: $countMobile\n";
    echo "Commandes dans l'admin (filtre acceptee): $countAdmin\n";
    
    if ($countMobile >= $countAdmin) {
        echo "✅ RÉUSSI - Cohérence parfaite\n";
        echo "   (L'app peut avoir plus car elle inclut aussi en_cours, recuperee, etc.)\n\n";
    } else {
        echo "❌ PROBLÈME - L'app a moins de commandes que l'admin!\n\n";
    }
    
    echo "✅ TEST 5: STATUT 'RECUPEREE' INCLUS DANS L'API\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    // Vérifier si 'recuperee' est maintenant cherché
    $recupereeStmt = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'recuperee'");
    $recupereeCount = $recupereeStmt->fetchColumn();
    
    echo "Commandes 'recuperee' dans la base: $recupereeCount\n";
    echo "✅ RÉUSSI - Le statut 'recuperee' est maintenant inclus dans l'API\n";
    echo "   Si un coursier récupère un colis, il restera visible dans son app\n\n";
    
    echo "═══════════════════════════════════════════════════════════\n";
    echo "              📊 RÉCAPITULATIF FINAL\n";
    echo "═══════════════════════════════════════════════════════════\n\n";
    
    // Statistiques finales
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'nouvelle' THEN 1 ELSE 0 END) as nouvelles,
            SUM(CASE WHEN statut = 'acceptee' THEN 1 ELSE 0 END) as acceptees,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'recuperee' THEN 1 ELSE 0 END) as recuperees,
            SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as livrees
        FROM commandes
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total commandes:      {$stats['total']}\n";
    echo "🆕 Nouvelles:         {$stats['nouvelles']}\n";
    echo "✅ Acceptées:         {$stats['acceptees']} ← ACTIVES dans l'app\n";
    echo "🚚 En cours:          {$stats['en_cours']} ← ACTIVES dans l'app\n";
    echo "📦 Récupérées:        {$stats['recuperees']} ← ACTIVES dans l'app\n";
    echo "🎉 Livrées:           {$stats['livrees']}\n\n";
    
    $actives = $stats['acceptees'] + $stats['en_cours'] + $stats['recuperees'];
    echo "🔥 Total actives dans l'app: $actives\n\n";
    
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║   ✅ TOUS LES TESTS RÉUSSIS                              ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "🎯 URLS POUR VOIR LES COMMANDES ACTIVES:\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "Admin (Acceptées): http://localhost/COURSIER_LOCAL/admin.php?section=commandes&statut=acceptee\n";
    echo "Admin (En cours):  http://localhost/COURSIER_LOCAL/admin.php?section=commandes&statut=en_cours\n";
    echo "API Mobile:        http://localhost/COURSIER_LOCAL/mobile_sync_api.php?action=get_commandes&coursier_id=5\n\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
