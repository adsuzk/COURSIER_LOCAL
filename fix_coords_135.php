<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    // Ajouter les coordonnées à la commande #135
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET 
            latitude_retrait = 5.362,
            longitude_retrait = -3.987,
            adresse_retrait = 'Cocody Angré, près du Carrefour',
            latitude_livraison = 5.320,
            longitude_livraison = -4.012,
            adresse_livraison = 'Plateau, Rue du Commerce'
        WHERE id = 135
    ");
    
    $stmt->execute();
    echo "✅ Coordonnées ajoutées à la commande #135\n";
    
    // Vérifier
    $stmt = $pdo->prepare("SELECT id, latitude_retrait, longitude_retrait, latitude_livraison, longitude_livraison FROM commandes WHERE id = 135");
    $stmt->execute();
    $cmd = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📍 Vérification commande #135:\n";
    echo "   Enlèvement: ({$cmd['latitude_retrait']}, {$cmd['longitude_retrait']})\n";
    echo "   Livraison: ({$cmd['latitude_livraison']}, {$cmd['longitude_livraison']})\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
