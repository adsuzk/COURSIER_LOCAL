<?php
require 'config.php';
$conn = getDBConnection();

echo "🧪 TEST DE LA REQUÊTE COMPTABILITÉ\n";
echo str_repeat("=", 60) . "\n\n";

$dateDebut = '2025-10-01';
$dateFin = '2025-10-02';

try {
    // Test 1: Requête CA global
    echo "✅ Test 1: CA Global...\n";
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as nb_livraisons,
            SUM(prix_total) as ca_total,
            AVG(prix_total) as prix_moyen
        FROM commandes 
        WHERE statut = 'livree' 
        AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   Livraisons: " . $result['nb_livraisons'] . "\n";
    echo "   CA Total: " . number_format($result['ca_total'], 0, ',', ' ') . " FCFA\n";
    echo "   Prix moyen: " . number_format($result['prix_moyen'], 0, ',', ' ') . " FCFA\n\n";
    
    // Test 2: Requête avec taux historiques
    echo "✅ Test 2: Commandes avec taux historiques...\n";
    $stmt = $conn->prepare("
        SELECT 
            c.id,
            c.prix_total,
            c.created_at,
            c.coursier_id,
            c.adresse_retrait,
            c.adresse_livraison,
            
            (SELECT taux_commission FROM config_tarification 
             WHERE date_application <= c.created_at 
             ORDER BY date_application DESC LIMIT 1) as taux_commission_suzosky,
            
            (SELECT frais_plateforme FROM config_tarification 
             WHERE date_application <= c.created_at 
             ORDER BY date_application DESC LIMIT 1) as frais_plateforme,
            
            (SELECT frais_publicitaires FROM config_tarification 
             WHERE date_application <= c.created_at 
             ORDER BY date_application DESC LIMIT 1) as frais_publicitaires
            
        FROM commandes c
        WHERE c.statut = 'livree'
        AND c.created_at BETWEEN ? AND ?
        ORDER BY c.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Nombre de résultats: " . count($results) . "\n";
    foreach ($results as $row) {
        echo "   - Commande #{$row['id']}: {$row['prix_total']} FCFA ";
        echo "(Commission: {$row['taux_commission_suzosky']}%)\n";
    }
    echo "\n";
    
    echo "🎉 TOUTES LES REQUÊTES FONCTIONNENT !\n";
    
} catch (PDOException $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
