<?php
/**
 * 🔍 VÉRIFICATION DES STATUTS DE COMMANDES
 * Script pour s'assurer que tous les statuts sont à jour
 */

require_once __DIR__ . '/config.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🔍 VÉRIFICATION DES STATUTS DE COMMANDES               ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. Récupérer tous les statuts utilisés dans la base
    echo "📊 STATUTS ACTUELLEMENT DANS LA BASE:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $stmt = $pdo->query("
        SELECT statut, COUNT(*) as total 
        FROM commandes 
        GROUP BY statut 
        ORDER BY total DESC
    ");
    
    $statusInDB = [];
    $totalCommandes = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statut = $row['statut'] ?: '(NULL)';
        $total = (int)$row['total'];
        $statusInDB[$statut] = $total;
        $totalCommandes += $total;
        
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
        
        $percentage = $totalCommandes > 0 ? round(($total / $totalCommandes) * 100, 1) : 0;
        
        printf("%-20s %s %4d commandes (%5.1f%%)\n", 
            $icon . ' ' . strtoupper($statut), 
            str_repeat('█', min(30, (int)($percentage * 0.5))),
            $total, 
            $percentage
        );
    }
    
    echo "\nTotal: $totalCommandes commandes\n\n";
    
    // 2. Vérifier les statuts attendus
    echo "✅ STATUTS ATTENDUS PAR LE SYSTÈME:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $expectedStatuses = [
        'nouvelle' => 'Commande vient d\'être créée',
        'en_attente' => 'En attente d\'attribution',
        'attribuee' => 'Assignée à un coursier',
        'acceptee' => 'Coursier a accepté',
        'en_cours' => 'Coursier en route (ACTIF)',
        'recuperee' => 'Colis récupéré',
        'livree' => 'Livraison terminée',
        'annulee' => 'Commande annulée'
    ];
    
    foreach ($expectedStatuses as $status => $description) {
        $count = $statusInDB[$status] ?? 0;
        $icon = $count > 0 ? '✅' : '⚠️';
        printf("%s %-15s (%3d) - %s\n", $icon, $status, $count, $description);
    }
    
    // 3. Détecter les statuts non standard
    echo "\n🔍 STATUTS NON STANDARD DÉTECTÉS:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $unexpectedFound = false;
    foreach ($statusInDB as $status => $count) {
        if (!isset($expectedStatuses[$status]) && $status !== '(NULL)') {
            echo "⚠️  '$status' - $count commandes (non documenté)\n";
            $unexpectedFound = true;
        }
    }
    
    if (!$unexpectedFound) {
        echo "✅ Aucun statut non standard trouvé\n";
    }
    
    // 4. Commandes actives en ce moment
    echo "\n🚚 COMMANDES ACTUELLEMENT ACTIVES:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $activeStmt = $pdo->query("
        SELECT c.id, c.code_commande, c.statut, c.adresse_depart, c.adresse_arrivee,
               a.nom as coursier_nom, c.created_at
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.statut IN ('acceptee', 'en_cours', 'recuperee')
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
    
    $activeCount = 0;
    while ($cmd = $activeStmt->fetch(PDO::FETCH_ASSOC)) {
        $activeCount++;
        $duree = round((time() - strtotime($cmd['created_at'])) / 60);
        
        echo sprintf(
            "#%s - %s (%s) - Coursier: %s - Depuis: %d min\n",
            $cmd['code_commande'],
            strtoupper($cmd['statut']),
            substr($cmd['adresse_depart'], 0, 30) . '...',
            $cmd['coursier_nom'] ?: 'Non assigné',
            $duree
        );
    }
    
    if ($activeCount === 0) {
        echo "ℹ️  Aucune commande active en ce moment\n";
    } else {
        echo "\nTotal actives: $activeCount\n";
    }
    
    // 5. Statistiques temporelles
    echo "\n📅 STATISTIQUES DES DERNIÈRES 24H:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN statut = 'nouvelle' THEN 1 END) as nouvelles,
            COUNT(CASE WHEN statut IN ('acceptee', 'en_cours', 'recuperee') THEN 1 END) as actives,
            COUNT(CASE WHEN statut = 'livree' THEN 1 END) as livrees,
            COUNT(CASE WHEN statut = 'annulee' THEN 1 END) as annulees
        FROM commandes
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    
    $stats24h = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total créées: {$stats24h['total']}\n";
    echo "🆕 Nouvelles: {$stats24h['nouvelles']}\n";
    echo "🚚 Actives: {$stats24h['actives']}\n";
    echo "🎉 Livrées: {$stats24h['livrees']}\n";
    echo "❌ Annulées: {$stats24h['annulees']}\n";
    
    if ($stats24h['total'] > 0) {
        $tauxLivraison = round(($stats24h['livrees'] / $stats24h['total']) * 100, 1);
        echo "\n📈 Taux de livraison 24h: $tauxLivraison%\n";
    }
    
    // 6. Recommandations
    echo "\n💡 RECOMMANDATIONS:\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $warnings = [];
    
    if (($statusInDB['en_attente'] ?? 0) > 5) {
        $warnings[] = "⚠️  {$statusInDB['en_attente']} commandes en attente - Vérifier disponibilité coursiers";
    }
    
    if (($statusInDB['nouvelle'] ?? 0) > 3) {
        $warnings[] = "⚠️  {$statusInDB['nouvelle']} nouvelles commandes non attribuées - Système d'attribution OK?";
    }
    
    if (($statusInDB['en_cours'] ?? 0) > 10) {
        $warnings[] = "🔥 {$statusInDB['en_cours']} commandes en cours - Pic d'activité!";
    }
    
    if (empty($warnings)) {
        echo "✅ Tout semble normal\n";
    } else {
        foreach ($warnings as $warning) {
            echo "$warning\n";
        }
    }
    
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║   ✅ VÉRIFICATION TERMINÉE                               ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
?>
