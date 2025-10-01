<?php
/**
 * 🔍 DIAGNOSTIC SYNCHRONISATION APP ↔ ADMIN
 */

require_once __DIR__ . '/config.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🔍 DIAGNOSTIC SYNCHRONISATION APP ↔ ADMIN              ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. État des commandes dans la base
    echo "📊 ÉTAT RÉEL DANS LA BASE DE DONNÉES:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $statsStmt = $pdo->query("
        SELECT statut, COUNT(*) as total
        FROM commandes
        GROUP BY statut
        ORDER BY 
            CASE statut
                WHEN 'nouvelle' THEN 1
                WHEN 'en_attente' THEN 2
                WHEN 'attribuee' THEN 3
                WHEN 'acceptee' THEN 4
                WHEN 'en_cours' THEN 5
                WHEN 'recuperee' THEN 6
                WHEN 'livree' THEN 7
                WHEN 'annulee' THEN 8
                ELSE 9
            END
    ");
    
    $statsDB = [];
    while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
        $statut = $row['statut'];
        $total = (int)$row['total'];
        $statsDB[$statut] = $total;
        
        $icon = match($statut) {
            'nouvelle' => '🆕',
            'en_attente' => '⏳',
            'attribuee' => '📌',
            'acceptee' => '✅',
            'en_cours' => '🚚',
            'recuperee' => '📦',
            'livree' => '🎉',
            'annulee' => '❌',
            default => '❓'
        };
        
        printf("%-20s %3d commandes\n", "$icon $statut", $total);
    }
    
    echo "\n";
    
    // 2. Ce que l'API MOBILE retourne
    echo "📱 CE QUE L'API MOBILE RETOURNE (mobile_sync_api.php):\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $coursier_id = 5; // ZALLE
    
    $mobileStmt = $pdo->prepare("
        SELECT 
            id, code_commande, statut, coursier_id,
            adresse_depart, adresse_arrivee,
            created_at
        FROM commandes 
        WHERE coursier_id = ? 
        AND statut IN ('nouvelle', 'attribuee', 'acceptee', 'en_cours', 'retiree')
        ORDER BY created_at DESC
    ");
    $mobileStmt->execute([$coursier_id]);
    $commandesMobile = $mobileStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Coursier ID: $coursier_id (ZALLE)\n";
    echo "Statuts recherchés: 'nouvelle', 'attribuee', 'acceptee', 'en_cours', 'retiree'\n";
    echo "Résultat: " . count($commandesMobile) . " commandes\n\n";
    
    if (count($commandesMobile) > 0) {
        foreach ($commandesMobile as $cmd) {
            echo sprintf(
                "  ✓ #%s - %s - %s\n",
                $cmd['code_commande'],
                strtoupper($cmd['statut']),
                substr($cmd['adresse_depart'], 0, 40)
            );
        }
    } else {
        echo "  ⚠️  AUCUNE COMMANDE RETOURNÉE!\n";
    }
    
    echo "\n";
    
    // 3. Ce que l'ADMIN affiche (avec filtre attribuee)
    echo "🖥️  CE QUE L'ADMIN AFFICHE (filtre statut=attribuee):\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $adminStmt = $pdo->query("
        SELECT id, code_commande, statut, coursier_id
        FROM commandes
        WHERE statut = 'attribuee'
    ");
    $commandesAdmin = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Statut filtré: 'attribuee'\n";
    echo "Résultat: " . count($commandesAdmin) . " commandes\n\n";
    
    if (count($commandesAdmin) > 0) {
        foreach ($commandesAdmin as $cmd) {
            echo sprintf(
                "  ✓ #%s - Coursier: %s\n",
                $cmd['code_commande'],
                $cmd['coursier_id'] ?? 'Non assigné'
            );
        }
    } else {
        echo "  ⚠️  ZÉRO COMMANDE 'attribuee' (NORMAL!)\n";
    }
    
    echo "\n";
    
    // 4. DIAGNOSTIC DU PROBLÈME
    echo "🔍 DIAGNOSTIC:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $problems = [];
    
    // Problème 1: Statut 'attribuee' vs statuts actifs
    if (count($commandesAdmin) === 0 && count($commandesMobile) > 0) {
        echo "❗ PROBLÈME IDENTIFIÉ:\n";
        echo "   - L'admin filtre sur 'attribuee' mais il y a 0 commandes avec ce statut\n";
        echo "   - Les commandes actives sont au statut: 'acceptee' (✅)\n";
        echo "   - Vous cherchez au mauvais endroit!\n\n";
        
        echo "💡 SOLUTION:\n";
        echo "   - Pour voir les commandes actives dans l'admin:\n";
        echo "   - Filtrer par statut 'acceptee' ou 'en_cours'\n";
        echo "   - PAS par 'attribuee' (qui signifie 'assignée mais pas encore acceptée')\n\n";
    }
    
    // Problème 2: Statut 'recuperee' manquant dans l'API
    echo "⚠️  STATUT MANQUANT DANS L'API MOBILE:\n";
    echo "   - L'API ne cherche pas le statut 'recuperee'\n";
    echo "   - Si un coursier récupère un colis, il disparaîtra de son app!\n";
    echo "   - CORRECTION NÉCESSAIRE\n\n";
    
    // 5. État attendu vs état réel
    echo "📋 COMPARAISON STATUTS:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $cycles = [
        'nouvelle' => 'Créée, en attente d\'attribution',
        'en_attente' => 'Pas de coursier disponible',
        'attribuee' => 'Assignée à un coursier (pas encore acceptée)',
        'acceptee' => '✅ Coursier a accepté (ACTIVE dans app)',
        'en_cours' => '🚚 Coursier en route (ACTIVE dans app)',
        'recuperee' => '📦 Colis récupéré (ACTIVE dans app)',
        'livree' => 'Terminée avec succès',
        'annulee' => 'Annulée'
    ];
    
    foreach ($cycles as $statut => $description) {
        $count = $statsDB[$statut] ?? 0;
        $inMobile = in_array($statut, ['nouvelle', 'attribuee', 'acceptee', 'en_cours', 'retiree']) ? '✓' : '✗';
        
        printf("%-15s [DB: %3d] [API: %s] - %s\n", $statut, $count, $inMobile, $description);
    }
    
    echo "\n";
    
    // 6. Recommandations
    echo "🎯 RECOMMANDATIONS:\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "1. ✅ AJOUTER 'recuperee' dans l'API mobile\n";
    echo "2. ✅ Dans l'admin, filtrer par 'acceptee' ou 'en_cours' pour voir les actives\n";
    echo "3. ✅ Le statut 'attribuee' = assignée mais PAS ENCORE acceptée par le coursier\n\n";
    
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║   ✅ DIAGNOSTIC TERMINÉ                                  ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
