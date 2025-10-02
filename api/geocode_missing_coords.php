<?php
/**
 * Script de géocodage automatique des adresses manquantes
 * À exécuter périodiquement ou lors de la création de commandes
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    
    // Trouver les commandes avec des coordonnées manquantes
    $stmt = $pdo->query("
        SELECT id, adresse_depart, adresse_arrivee,
               latitude_depart, longitude_depart,
               latitude_arrivee, longitude_arrivee
        FROM commandes
        WHERE statut NOT IN ('livree', 'annulee', 'terminee')
          AND (
              (latitude_depart IS NULL OR longitude_depart IS NULL OR latitude_depart = 0 OR longitude_depart = 0)
              OR (latitude_arrivee IS NULL OR longitude_arrivee IS NULL OR latitude_arrivee = 0 OR longitude_arrivee = 0)
          )
        ORDER BY created_at DESC
        LIMIT 50
    ");
    
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updated = 0;
    $errors = [];
    
    foreach ($commandes as $cmd) {
        $id = $cmd['id'];
        $needsPickup = empty($cmd['latitude_depart']) || empty($cmd['longitude_depart']) || 
                       $cmd['latitude_depart'] == 0 || $cmd['longitude_depart'] == 0;
        $needsDelivery = empty($cmd['latitude_arrivee']) || empty($cmd['longitude_arrivee']) ||
                         $cmd['latitude_arrivee'] == 0 || $cmd['longitude_arrivee'] == 0;
        
        // Géocoder l'adresse de départ si nécessaire
        if ($needsPickup && !empty($cmd['adresse_depart'])) {
            $coords = geocodeAddress($cmd['adresse_depart']);
            if ($coords) {
                $updateStmt = $pdo->prepare("
                    UPDATE commandes 
                    SET latitude_depart = ?, longitude_depart = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$coords['lat'], $coords['lng'], $id]);
                $updated++;
            } else {
                $errors[] = "Commande $id: Impossible de géocoder '{$cmd['adresse_depart']}'";
            }
        }
        
        // Géocoder l'adresse d'arrivée si nécessaire
        if ($needsDelivery && !empty($cmd['adresse_arrivee'])) {
            $coords = geocodeAddress($cmd['adresse_arrivee']);
            if ($coords) {
                $updateStmt = $pdo->prepare("
                    UPDATE commandes 
                    SET latitude_arrivee = ?, longitude_arrivee = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$coords['lat'], $coords['lng'], $id]);
                $updated++;
            } else {
                $errors[] = "Commande $id: Impossible de géocoder '{$cmd['adresse_arrivee']}'";
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'commandes_analysees' => count($commandes),
        'coordonnees_ajoutees' => $updated,
        'erreurs' => $errors
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Géocode une adresse en utilisant l'API Nominatim (OpenStreetMap)
 * Gratuit, pas de clé API requise
 */
function geocodeAddress($address) {
    // Ajouter "Abidjan, Côte d'Ivoire" si pas déjà présent
    if (stripos($address, 'Abidjan') === false) {
        $address .= ', Abidjan, Côte d\'Ivoire';
    }
    
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $address,
        'format' => 'json',
        'limit' => 1,
        'addressdetails' => 1
    ]);
    
    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: SuzoskyCourierApp/1.0\r\n",
            'timeout' => 5
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data) || !isset($data[0]['lat']) || !isset($data[0]['lon'])) {
        return null;
    }
    
    return [
        'lat' => floatval($data[0]['lat']),
        'lng' => floatval($data[0]['lon'])
    ];
}
