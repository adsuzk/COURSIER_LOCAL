<?php
require 'config.php';
$conn = getDBConnection();

echo "🧪 TEST COMPLET DES REQUÊTES COMPTABILITÉ\n";
echo str_repeat("=", 70) . "\n\n";

$dateDebut = '2025-10-01';
$dateFin = '2025-10-02';

try {
    // Test 1: CA Global
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
    echo "   ✓ Livraisons: " . $result['nb_livraisons'] . "\n";
    echo "   ✓ CA: " . number_format($result['ca_total'], 0) . " FCFA\n\n";
    
    // Test 2: Commandes avec taux
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
             ORDER BY date_application DESC LIMIT 1) as taux_commission_suzosky
        FROM commandes c
        WHERE c.statut = 'livree'
        AND c.created_at BETWEEN ? AND ?
        LIMIT 1
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo "   ✓ Commande #" . $result['id'] . " récupérée\n";
        echo "   ✓ Taux commission: " . $result['taux_commission_suzosky'] . "%\n\n";
    } else {
        echo "   ⚠ Aucune commande dans cette période\n\n";
    }
    
    // Test 3: Stats par coursier
    echo "✅ Test 3: Statistiques par coursier...\n";
    $stmt = $conn->prepare("
        SELECT 
            c.coursier_id,
            a.nom as coursier_nom,
            a.prenoms as coursier_prenom,
            COUNT(*) as nb_livraisons,
            SUM(c.prix_total) as ca_coursier,
            AVG(c.prix_total) as prix_moyen
        FROM commandes c
        JOIN agents_suzosky a ON a.id = c.coursier_id
        WHERE c.statut = 'livree'
        AND c.created_at BETWEEN ? AND ?
        GROUP BY c.coursier_id
        ORDER BY ca_coursier DESC
        LIMIT 3
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        echo "   ✓ " . count($results) . " coursier(s) trouvé(s)\n";
        foreach ($results as $row) {
            echo "   - {$row['coursier_nom']} {$row['coursier_prenom']}: ";
            echo "{$row['nb_livraisons']} livraison(s), ";
            echo number_format($row['ca_coursier'], 0) . " FCFA\n";
        }
    } else {
        echo "   ⚠ Aucun coursier actif dans cette période\n";
    }
    echo "\n";
    
    // Test 4: Historique config
    echo "✅ Test 4: Historique configurations...\n";
    $stmt = $conn->query("SELECT * FROM config_tarification ORDER BY date_application DESC");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   ✓ " . count($configs) . " configuration(s) dans l'historique\n";
    foreach ($configs as $cfg) {
        echo "   - {$cfg['date_application']}: ";
        echo "Commission {$cfg['taux_commission']}%, ";
        echo "Plateforme {$cfg['frais_plateforme']}%, ";
        echo "Pub {$cfg['frais_publicitaires']}%\n";
    }
    echo "\n";
    
    // Test 5: Évolution journalière
    echo "✅ Test 5: Évolution journalière...\n";
    $stmt = $conn->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as nb_livraisons,
            SUM(prix_total) as ca_journalier
        FROM commandes
        WHERE statut = 'livree'
        AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59']);
    $evolution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($evolution) > 0) {
        echo "   ✓ " . count($evolution) . " jour(s) d'activité\n";
        foreach ($evolution as $jour) {
            echo "   - {$jour['date']}: {$jour['nb_livraisons']} livraisons, ";
            echo number_format($jour['ca_journalier'], 0) . " FCFA\n";
        }
    } else {
        echo "   ⚠ Aucune activité dans cette période\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "🎉 TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS !\n";
    echo str_repeat("=", 70) . "\n";
    
} catch (PDOException $e) {
    echo "\n❌ ERREUR SQL:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    exit(1);
}
