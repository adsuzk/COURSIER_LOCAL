<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    $coursierId = 5;
    
    $activeStatuses = ['assignee', 'nouvelle', 'acceptee', 'en_cours', 'picked_up'];
    $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
    
    echo "=== TEST REQUÊTE get_coursier_data.php ===\n\n";
    echo "Statuts recherchés: " . implode(', ', $activeStatuses) . "\n\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            id,
            COALESCE(client_nom, 'Client') as client_nom,
            COALESCE(client_telephone, telephone_expediteur) as client_telephone,
            COALESCE(adresse_depart, adresse_retrait) as adresse_enlevement,
            COALESCE(adresse_arrivee, adresse_livraison) as adresse_livraison,
            COALESCE(prix_total, prix_estime, 0) as prix_livraison,
            statut,
            COALESCE(created_at, date_creation) as date_commande,
            COALESCE(description_colis, description, '') as description,
            COALESCE(distance_estimee, 0) as distance
        FROM commandes 
        WHERE coursier_id = ? 
        AND statut IN ($placeholders)
        ORDER BY COALESCE(created_at, date_creation) DESC
        LIMIT 10
    ");
    
    $params = array_merge([$coursierId], $activeStatuses);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Commandes trouvées: " . count($commandes) . "\n\n";
    
    foreach ($commandes as $cmd) {
        echo "#{$cmd['id']} - {$cmd['statut']} - {$cmd['client_nom']} - {$cmd['adresse_enlevement']} → {$cmd['adresse_livraison']}\n";
    }
    
    echo "\n=== VÉRIFICATION DIRECTE DES COMMANDES DU COURSIER #5 ===\n\n";
    $stmt2 = $pdo->prepare("SELECT id, code_commande, statut, coursier_id FROM commandes WHERE coursier_id = 5 ORDER BY id DESC LIMIT 5");
    $stmt2->execute();
    
    while ($row = $stmt2->fetch()) {
        echo "#{$row['id']} - {$row['code_commande']} - Statut: {$row['statut']} - Coursier: {$row['coursier_id']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>