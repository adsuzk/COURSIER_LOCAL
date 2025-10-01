<?php
/**
 * API Simple de Tracking
 * Retourne les données de suivi d'une commande
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $commandeId = isset($_GET['commande_id']) ? (int) $_GET['commande_id'] : 0;
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'live';
    
    if ($commandeId <= 0) {
        throw new Exception("ID de commande invalide");
    }
    
    $pdo = getDBConnection();
    
    // Récupérer les informations de la commande
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            c.adresse_depart,
            c.adresse_arrivee,
            c.adresse_retrait,
            c.adresse_livraison,
            a.nom AS coursier_nom,
            a.prenoms AS coursier_prenoms,
            a.telephone AS coursier_telephone,
            a.latitude AS coursier_lat,
            a.longitude AS coursier_lng,
            CASE 
                WHEN a.last_activity_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'en_ligne'
                ELSE 'hors_ligne'
            END AS coursier_statut
        FROM commandes c
        LEFT JOIN agents a ON c.coursier_id = a.id
        WHERE c.id = ?
    ");
    $stmt->execute([$commandeId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        throw new Exception("Commande introuvable");
    }
    
    // Calculer la durée
    $duree = [];
    if (!empty($commande['created_at'])) {
        $debut = strtotime($commande['created_at']);
        $duree['debut'] = date('d/m/Y à H:i:s', $debut);
        
        if (!empty($commande['updated_at']) && in_array($commande['statut'], ['livree', 'annulee'])) {
            $fin = strtotime($commande['updated_at']);
            $duree['fin'] = date('d/m/Y à H:i:s', $fin);
            
            $duree_secondes = $fin - $debut;
            $duree_minutes = floor($duree_secondes / 60);
            $duree_heures = floor($duree_minutes / 60);
            $duree_min_restant = $duree_minutes % 60;
            
            if ($duree_heures > 0) {
                $duree['duree_formatted'] = "{$duree_heures}h {$duree_min_restant}min";
            } else {
                $duree['duree_formatted'] = "{$duree_minutes} min";
            }
        } else {
            // Course en cours
            $maintenant = time();
            $duree_secondes = $maintenant - $debut;
            $duree_minutes = floor($duree_secondes / 60);
            $duree_heures = floor($duree_minutes / 60);
            $duree_min_restant = $duree_minutes % 60;
            
            if ($duree_heures > 0) {
                $duree['duree_formatted'] = "{$duree_heures}h {$duree_min_restant}min (en cours)";
            } else {
                $duree['duree_formatted'] = "{$duree_minutes} min (en cours)";
            }
        }
    }
    
    // Position du coursier
    $position = null;
    if (!empty($commande['coursier_lat']) && !empty($commande['coursier_lng'])) {
        $position = [
            'lat' => (float) $commande['coursier_lat'],
            'lng' => (float) $commande['coursier_lng']
        ];
    }
    
    // Réponse
    $response = [
        'success' => true,
        'mode' => $mode,
        'commande' => [
            'id' => $commande['id'],
            'code_commande' => $commande['code_commande'] ?? $commande['order_number'] ?? 'N/A',
            'statut' => $commande['statut'],
            'adresse_depart' => $commande['adresse_depart'] ?? $commande['adresse_retrait'] ?? 'N/A',
            'adresse_arrivee' => $commande['adresse_arrivee'] ?? $commande['adresse_livraison'] ?? 'N/A',
            'prix_estime' => $commande['prix_estime'],
            'created_at' => $commande['created_at'],
            'updated_at' => $commande['updated_at']
        ],
        'coursier' => [
            'nom' => trim(($commande['coursier_prenoms'] ?? '') . ' ' . ($commande['coursier_nom'] ?? '')),
            'telephone' => $commande['coursier_telephone'] ?? 'N/A',
            'statut' => $commande['coursier_statut'] ?? 'inconnu'
        ],
        'duree' => $duree,
        'position' => $position,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
