<?php
/**
 * API pour récupérer les informations et statut d'un coursier
 * 
 * GET /api/get_coursier_info.php?coursier_id=123
 * POST /api/get_coursier_info.php avec JSON: {"coursier_id": 123}
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/lib/tracking_helpers.php';

// Récupérer l'ID du coursier
$coursier_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $coursier_id = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : null;
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $coursier_id = isset($data['coursier_id']) ? intval($data['coursier_id']) : null;
}

if (!$coursier_id || $coursier_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'MISSING_COURSIER_ID',
        'message' => 'ID coursier manquant ou invalide'
    ]);
    exit;
}

try {
    $pdo = getPDO();
    
    // Jeu de statuts (nouvelle taxonomie unifiée)
    $completedSet = [ 'livree', 'termine' ]; // 'termine' legacy
    $activeSet    = [ 'assignee','attribuee','acceptee','en_cours','picked_up','recupere','nouvelle' ];

    // Récupérer les infos du coursier avec stats (compat legacy + nouveau)
    $sql = "
        SELECT 
            c.*,
            COUNT(DISTINCT cmd.id) as total_commandes,
            COUNT(DISTINCT CASE WHEN cmd.statut IN ('livree','termine') THEN cmd.id END) as commandes_terminees,
            MAX(COALESCE(cmd.created_at, cmd.date_creation)) as derniere_commande
        FROM coursiers c
        LEFT JOIN commandes cmd ON c.id = cmd.coursier_id
        WHERE c.id = ?
        GROUP BY c.id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coursier_id]);
    $coursier = $stmt->fetch();
    // Latest position via helper
    $pos = tracking_select_positions_for_courier($pdo, $coursier_id, 1);
    $derLat = null; $derLng = null; $derTs = null;
    if ($pos && isset($pos[0])) { $derLat = (float)$pos[0]['latitude']; $derLng = (float)$pos[0]['longitude']; $derTs = $pos[0]['created_at']; }
    
    if (!$coursier) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'COURSIER_NOT_FOUND',
            'message' => 'Coursier non trouvé'
        ]);
        exit;
    }
    
    // Récupérer les commandes actives (inclure nouveaux statuts + alias legacy)
    $placeholders = implode(',', array_fill(0, count($activeSet), '?'));
    $commandes_sql = "
        SELECT 
            id,
            COALESCE(client_nom, 'Client') AS client_nom,
            COALESCE(adresse_depart, adresse_retrait) AS adresse_depart,
            COALESCE(adresse_arrivee, adresse_livraison) AS adresse_arrivee,
            statut,
            COALESCE(montant_total, prix_total, prix_estime, 0) AS montant_calcule,
            COALESCE(created_at, date_creation) AS date_creation
        FROM commandes 
        WHERE coursier_id = ? AND statut IN ($placeholders)
        ORDER BY COALESCE(created_at, date_creation) DESC
        LIMIT 20
    ";
    $params = array_merge([$coursier_id], $activeSet);
    $stmt_commandes = $pdo->prepare($commandes_sql);
    $stmt_commandes->execute($params);
    $commandes_raw = $stmt_commandes->fetchAll(PDO::FETCH_ASSOC);

    // Normalisation des statuts pour sortie (expose alias legacy + canonique)
    $normalized = [];
    foreach ($commandes_raw as $row) {
        $s = $row['statut'];
        $canon = $s;
        if ($s === 'attribuee') $canon = 'assignee';
        if ($s === 'recupere') $canon = 'picked_up';
    $row['statut_canon'] = $canon;
    // Harmoniser clé montant_total pour sortie même si colonne physique absente
    if (!isset($row['montant_total'])) { $row['montant_total'] = $row['montant_calcule']; }
        // Alias pour ancienne app
        $row['statut_alias'] = ($canon === 'assignee') ? 'attribuee' : $canon;
        $normalized[] = $row;
    }
    $commandes_actives = $normalized;
    
    // Calculer le taux de réussite
    $taux_reussite = $coursier['total_commandes'] > 0 
        ? round(($coursier['commandes_terminees'] / $coursier['total_commandes']) * 100, 1) 
        : 0;
    
    // Formater la réponse
    $response = [
        'success' => true,
        'data' => [
            'coursier_id' => $coursier['id'],
            'nom' => $coursier['nom'],
            'telephone' => $coursier['telephone'],
            'email' => $coursier['email'],
            'statut' => $coursier['statut'],
            'date_inscription' => $coursier['date_inscription'],
            'derniere_activite' => $coursier['derniere_activite'],
            'zone_travail' => $coursier['zone_travail'],
            'vehicule_type' => $coursier['vehicule_type'],
            
            // Statistiques
            'statistiques' => [
                'total_commandes' => (int)$coursier['total_commandes'],
                'commandes_terminees' => (int)$coursier['commandes_terminees'],
                'taux_reussite' => $taux_reussite,
                'derniere_commande' => $coursier['derniere_commande']
            ],
            
            // Position actuelle
            'position' => [
                'latitude' => $derLat,
                'longitude' => $derLng,
                'timestamp' => $derTs
            ],
            
            // Commandes actives
            'commandes_actives' => $commandes_actives,
            'commandes_actives_count' => count($commandes_actives),
            // Statut de disponibilité (ex: libre si moins de 3 actives en file)
            'disponible' => $coursier['statut'] === 'actif' && count($commandes_actives) < 3,
            // Jeu de statuts actif exposé pour debug app
            'active_status_set' => $activeSet,
            'completed_status_set' => $completedSet
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    if (function_exists('logError')) { logError("GET_COURSIER_INFO_ERROR", $e->getMessage()); }
    
    echo json_encode([
        'success' => false,
        'error' => 'SYSTEM_ERROR',
        'message' => 'Erreur lors de la récupération des informations',
        'debug' => [ 'err' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile() ]
    ]);
}
?>