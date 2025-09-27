<?php
/**
 * FIX TEMPORAIRE: Redirection vers l'API simplifiée qui fonctionne
 * Évite les crashes NetworkOnMainThreadException
 */

// Redirection immédiate vers l'API qui fonctionne
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$newUrl = './get_coursier_orders_simple.php' . ($queryString ? '?' . $queryString : '');

header('Location: ' . $newUrl);
exit;

// CODE ORIGINAL COMMENTÉ TEMPORAIREMENT
/*
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

// Récupérer les paramètres
$coursier_id = null;
$status_filter = 'all';
$limit = 20;
$offset = 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $coursier_id = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : null;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 20;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($data) {
        $coursier_id = isset($data['coursier_id']) ? intval($data['coursier_id']) : null;
        $status_filter = isset($data['status']) ? $data['status'] : 'all';
        $limit = isset($data['limit']) ? min(intval($data['limit']), 100) : 20;
        $offset = isset($data['offset']) ? intval($data['offset']) : 0;
    }
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
    
    // Vérifier que le coursier existe (fallback vers agents_suzosky en local)
    $coursier = null;
    try {
        $check_sql = "SELECT id, nom, statut FROM coursiers WHERE id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$coursier_id]);
        $coursier = $check_stmt->fetch();
    } catch (Throwable $e) {
        // ignore, fallback below
    }
    if (!$coursier) {
        try {
            $check_sql = "SELECT id, CONCAT(nom, ' ', prenoms) AS nom, 'actif' AS statut FROM agents_suzosky WHERE id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$coursier_id]);
            $coursier = $check_stmt->fetch();
        } catch (Throwable $e) { /* ignore */ }
    }
    if (!$coursier) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'COURSIER_NOT_FOUND',
            'message' => 'Coursier non trouvé'
        ]);
        exit;
    }
    
    // Construire la requête selon le filtre de statut
    $where_clause = "c.coursier_id = ?";
    $params = [$coursier_id];
    
    if ($status_filter !== 'all') {
        if ($status_filter === 'active') {
            // Commandes en cours (non terminées)
            $where_clause .= " AND c.statut IN ('attribuee', 'en_cours', 'recupere', 'livre')";
        } else if ($status_filter === 'completed') {
            // Commandes terminées
            $where_clause .= " AND c.statut = 'termine'";
        } else if ($status_filter === 'cancelled') {
            // Commandes annulées ou avec problème
            $where_clause .= " AND c.statut IN ('annule', 'probleme')";
        } else {
            // Statut spécifique
            $where_clause .= " AND c.statut = ?";
            $params[] = $status_filter;
        }
    }
    
    // Requête principale pour récupérer les commandes
    $sql = "
        SELECT 
            c.id,
            c.client_id,
            cl.nom as client_nom,
            cl.telephone as client_telephone,
            c.adresse_depart,
            c.adresse_arrivee,
            c.latitude_depart,
            c.longitude_depart,
            c.latitude_arrivee,
            c.longitude_arrivee,
            c.distance_km,
            c.statut,
            COALESCE(c.montant_total, c.prix_estime) AS montant_total,
            COALESCE(c.description, c.description_colis) AS description,
            COALESCE(c.date_creation, c.created_at) AS date_creation,
            c.date_prise_en_charge,
            c.date_recuperation,
            c.date_livraison,
            COALESCE(c.date_completion, c.completed_at) AS date_completion,
            c.note_client,
            c.commentaire_client,
            gc.commission as gain_commission
        FROM commandes c
        LEFT JOIN clients cl ON c.client_id = cl.id  
        LEFT JOIN gains_coursiers gc ON c.id = gc.commande_id AND gc.coursier_id = c.coursier_id
        WHERE {$where_clause}
        ORDER BY COALESCE(c.date_creation, c.created_at) DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $commandes = $stmt->fetchAll();
    
    // Compter le total pour la pagination
    $count_sql = "SELECT COUNT(*) as total FROM commandes c WHERE {$where_clause}";
    $count_params = array_slice($params, 0, -2); // Enlever limit et offset
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_count = $count_stmt->fetch()['total'];
    
    // Pour chaque commande, récupérer l'historique de suivi
    $commandes_avec_suivi = [];
    foreach ($commandes as $commande) {
        $suivi_sql = "
            SELECT statut, commentaire, date_changement
            FROM suivi_commandes 
            WHERE commande_id = ? 
            ORDER BY date_changement ASC
        ";
        $suivi_stmt = $pdo->prepare($suivi_sql);
        $suivi_stmt->execute([$commande['id']]);
        $historique_suivi = $suivi_stmt->fetchAll();
        
        $commande['historique_suivi'] = $historique_suivi;
        $commandes_avec_suivi[] = $commande;
    }
    
    // Calculer les statistiques rapides
    $stats_sql = "
        SELECT 
            COUNT(*) as total_commandes,
            COUNT(CASE WHEN statut = 'termine' THEN 1 END) as commandes_terminees,
            COUNT(CASE WHEN statut IN ('attribuee', 'en_cours', 'recupere', 'livre') THEN 1 END) as commandes_actives,
            COUNT(CASE WHEN statut IN ('annule', 'probleme') THEN 1 END) as commandes_annulees,
            SUM(CASE WHEN statut = 'termine' THEN montant_total ELSE 0 END) as revenus_total
        FROM commandes 
        WHERE coursier_id = ?
    ";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$coursier_id]);
    $statistiques = $stats_stmt->fetch();
    
    // Gains du coursier (commission 70%)
    $gains_sql = "
        SELECT 
            COUNT(*) as commandes_payantes,
            SUM(commission) as total_commissions,
            AVG(commission) as commission_moyenne
        FROM gains_coursiers 
        WHERE coursier_id = ?
    ";
    
    $gains_stmt = $pdo->prepare($gains_sql);
    $gains_stmt->execute([$coursier_id]);
    $gains_stats = $gains_stmt->fetch();
    
    // Formater la réponse
    $response = [
        'success' => true,
        'data' => [
            'coursier' => [
                'id' => $coursier['id'],
                'nom' => $coursier['nom'],
                'statut' => $coursier['statut']
            ],
            'commandes' => $commandes_avec_suivi,
            'pagination' => [
                'total' => (int)$total_count,
                'limit' => $limit,
                'offset' => $offset,
                'pages' => ceil($total_count / $limit),
                'current_page' => floor($offset / $limit) + 1
            ],
            'statistiques' => [
                'total_commandes' => (int)$statistiques['total_commandes'],
                'commandes_terminees' => (int)$statistiques['commandes_terminees'],
                'commandes_actives' => (int)$statistiques['commandes_actives'],
                'commandes_annulees' => (int)$statistiques['commandes_annulees'],
                'revenus_total' => (float)$statistiques['revenus_total'],
                'taux_reussite' => $statistiques['total_commandes'] > 0 
                    ? round(($statistiques['commandes_terminees'] / $statistiques['total_commandes']) * 100, 1) 
                    : 0
            ],
            'gains' => [
                'commandes_payantes' => (int)($gains_stats['commandes_payantes'] ?? 0),
                'total_commissions' => (float)($gains_stats['total_commissions'] ?? 0),
                'commission_moyenne' => (float)($gains_stats['commission_moyenne'] ?? 0)
            ],
            'filters' => [
                'status_filter' => $status_filter,
                'available_statuses' => [
                    'all' => 'Toutes les commandes',
                    'active' => 'Commandes actives',
                    'completed' => 'Commandes terminées',
                    'cancelled' => 'Commandes annulées',
                    'attribuee' => 'Attribuées',
                    'en_cours' => 'En cours',
                    'recupere' => 'Récupérées',
                    'livre' => 'Livrées',
                    'termine' => 'Terminées'
                ]
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    // Use logger.php function instead of undefined logError
    if (function_exists('logMessage')) {
        logMessage('diagnostics_errors.log', "GET_COURSIER_ORDERS_ERROR - Coursier {$coursier_id}: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'SYSTEM_ERROR',
        'message' => 'Erreur lors de la récupération des commandes'
    ]);
}
?>