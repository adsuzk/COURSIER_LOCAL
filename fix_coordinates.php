<?php
// Script pour ajouter des coordonnées GPS aux commandes de test
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new PDO('mysql:host=localhost;dbname=coursier_local;charset=utf8mb4', 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Coordonnées Abidjan :
    // Cocody (départ) : 5.3592, -4.0079
    // Plateau (arrivée) : 5.3274, -4.0274
    
    $stmt = $db->prepare("UPDATE commandes SET 
        adresse_retrait = 'Cocody, Abidjan', 
        latitude_retrait = 5.3592, 
        longitude_retrait = -4.0079,
        adresse_livraison = 'Plateau, Abidjan', 
        latitude_livraison = 5.3274, 
        longitude_livraison = -4.0274
        WHERE id IN (159, 160, 161)");
    
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "✅ Coordonnées GPS ajoutées à $affected commandes",
        'affected_rows' => $affected
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
