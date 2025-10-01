<?php
// Ajouter des coordonnées GPS aux commandes de test
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    // Coordonnées de test pour Abidjan
    // Point d'enlèvement : Cocody Angré (5.362, -3.987)
    // Point de livraison : Plateau (5.320, -4.012)
    
    // Mise à jour des 2 commandes du coursier #5
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET 
            latitude_retrait = 5.362,
            longitude_retrait = -3.987,
            adresse_retrait = 'Cocody Angré, près du Carrefour',
            latitude_livraison = 5.320,
            longitude_livraison = -4.012,
            adresse_livraison = 'Plateau, Rue du Commerce'
        WHERE coursier_id = 5 
        AND statut = 'nouvelle'
        LIMIT 2
    ");
    
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo "✅ Coordonnées GPS ajoutées à $affected commande(s)\n";
    
    // Vérifier les données
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            client_nom, 
            adresse_retrait, 
            latitude_retrait, 
            longitude_retrait,
            adresse_livraison,
            latitude_livraison,
            longitude_livraison,
            statut
        FROM commandes 
        WHERE coursier_id = 5 
        AND statut = 'nouvelle'
    ");
    $stmt->execute();
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n📍 Commandes avec coordonnées GPS:\n";
    foreach ($commandes as $cmd) {
        echo "  - Commande #{$cmd['id']}: {$cmd['client_nom']}\n";
        echo "    Enlèvement: {$cmd['adresse_retrait']} ({$cmd['latitude_retrait']}, {$cmd['longitude_retrait']})\n";
        echo "    Livraison: {$cmd['adresse_livraison']} ({$cmd['latitude_livraison']}, {$cmd['longitude_livraison']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
