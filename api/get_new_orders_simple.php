<?php
/**
 * API ULTRA-SIMPLE POUR APPLICATION MOBILE
 * Polling direct des nouvelles commandes - PAS DE FCM REQUIS
 * L'application mobile appelle cette API toutes les 10 secondes
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    $coursier_id = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : 0;
    
    if ($coursier_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'coursier_id requis'
        ]);
        exit;
    }
    
    // SIMPLE: Récupérer TOUTES les commandes "nouvelle" pour ce coursier
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            code_commande, 
            order_number,
            adresse_depart, 
            adresse_arrivee, 
            telephone_expediteur,
            telephone_destinataire,
            description_colis,
            prix_estime as prix_total,
            priorite,
            statut,
            created_at,
            latitude_depart,
            longitude_depart,
            distance_estimee
        FROM commandes 
        WHERE coursier_id = ? 
        AND statut = 'nouvelle'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$coursier_id]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formater les dates
    foreach ($commandes as &$cmd) {
        $cmd['id'] = (int)$cmd['id'];
        $cmd['prix_total'] = floatval($cmd['prix_total'] ?? 0);
        $cmd['distance_estimee'] = floatval($cmd['distance_estimee'] ?? 0);
        $cmd['latitude_depart'] = floatval($cmd['latitude_depart'] ?? 0);
        $cmd['longitude_depart'] = floatval($cmd['longitude_depart'] ?? 0);
    }
    
    echo json_encode([
        'success' => true,
        'nouvelles_commandes' => $commandes,
        'count' => count($commandes),
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => count($commandes) > 0 
            ? count($commandes) . ' nouvelle(s) commande(s) en attente' 
            : 'Aucune nouvelle commande'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'error' => $e->getMessage()
    ]);
}
?>
