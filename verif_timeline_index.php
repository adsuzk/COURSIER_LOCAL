<?php
/**
 * VÉRIFICATION TIMELINE INDEX
 * Vérifier que la commande apparaît correctement sur l'index
 */

require_once 'config.php';

$pdo = getDBConnection();
$commandeId = 120; // ID de la dernière commande de test

echo "=== VÉRIFICATION TIMELINE INDEX ===\n\n";

// 1. Vérifier la commande dans la base
echo "1. COMMANDE DANS LA BASE:\n";
$stmt = $pdo->prepare("
    SELECT c.*, a.nom as coursier_nom, a.prenoms as coursier_prenoms
    FROM commandes c
    LEFT JOIN agents_suzosky a ON c.coursier_id = a.id  
    WHERE c.id = ?
");
$stmt->execute([$commandeId]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if ($commande) {
    echo "   ✅ Commande trouvée: {$commande['code_commande']}\n";
    echo "   👤 Client: {$commande['client_nom']}\n";
    echo "   📱 Tél: {$commande['client_telephone']}\n";
    echo "   🚚 Coursier: " . ($commande['coursier_nom'] ? $commande['coursier_nom'] . ' ' . $commande['coursier_prenoms'] : 'Non assigné') . "\n";
    echo "   🔄 Statut: {$commande['statut']}\n";
    echo "   💰 Prix: " . number_format($commande['prix_total'], 0) . " FCFA\n";
    echo "   📍 Retrait: {$commande['adresse_retrait']}\n";
    echo "   📍 Livraison: {$commande['adresse_livraison']}\n\n";
} else {
    echo "   ❌ Commande {$commandeId} introuvable\n";
    exit;
}

// 2. Simuler l'acceptation par le coursier  
echo "2. SIMULATION ACCEPTATION COURSIER:\n";
$stmt = $pdo->prepare("
    UPDATE commandes 
    SET statut = 'accepte', heure_acceptation = NOW(), updated_at = NOW()
    WHERE id = ?
");
$result = $stmt->execute([$commandeId]);

if ($result) {
    echo "   ✅ Commande acceptée par le coursier\n";
    echo "   🕒 Heure acceptation: " . date('H:i:s') . "\n\n";
}

// 3. Timeline progressive 
echo "3. SIMULATION PROGRESSION COMMANDE:\n";

$etapes = [
    ['statut' => 'en_route_retrait', 'heure' => 'NOW()', 'label' => 'En route vers enlèvement'],
    ['statut' => 'arrivee_retrait', 'heure' => 'DATE_ADD(NOW(), INTERVAL 5 MINUTE)', 'label' => 'Arrivé sur lieu de retrait'],
    ['statut' => 'colis_recupere', 'heure' => 'heure_retrait', 'label' => 'Colis récupéré', 'set_heure_retrait' => true],
    ['statut' => 'en_route_livraison', 'heure' => 'DATE_ADD(NOW(), INTERVAL 10 MINUTE)', 'label' => 'En route vers livraison'],
];

foreach ($etapes as $i => $etape) {
    sleep(2); // Pause pour simuler le temps réel
    
    $query = "UPDATE commandes SET statut = ?, updated_at = NOW()";
    $params = [$etape['statut']];
    
    if (isset($etape['set_heure_retrait'])) {
        $query .= ", heure_retrait = NOW()";
    }
    
    $query .= " WHERE id = ?";
    $params[] = $commandeId;
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    echo "   ✅ Étape " . ($i + 1) . ": {$etape['label']}\n";
}

// 4. État final
echo "\n4. ÉTAT FINAL COMMANDE:\n";
$stmt = $pdo->prepare("
    SELECT statut, heure_acceptation, heure_retrait, heure_livraison, updated_at
    FROM commandes 
    WHERE id = ?
");
$stmt->execute([$commandeId]);
$final = $stmt->fetch(PDO::FETCH_ASSOC);

echo "   🔄 Statut final: {$final['statut']}\n";
echo "   🕒 Acceptée: {$final['heure_acceptation']}\n";
echo "   🕓 Retrait: " . ($final['heure_retrait'] ?? 'N/A') . "\n";
echo "   🕔 Livraison: " . ($final['heure_livraison'] ?? 'N/A') . "\n";
echo "   ⏰ Dernière MAJ: {$final['updated_at']}\n\n";

echo "💡 VÉRIFICATION MANUELLE:\n";
echo "   🌐 Ouvrir: https://localhost/COURSIER_LOCAL/index.php\n";
echo "   🔍 Chercher commande: {$commande['code_commande']}\n";
echo "   📊 Vérifier que le statut affiche: {$final['statut']}\n";
echo "   ⏱️  Vérifier que la timeline est mise à jour\n\n";

echo "✅ DONNÉES PRÉPARÉES POUR VÉRIFICATION VISUELLE!\n";
?>