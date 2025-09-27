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

    // Récupération des informations de base (coursiers + agents_suzosky)
    $infoSql = "
        SELECT 
            c.id,
            c.nom AS coursier_nom,
            c.telephone AS coursier_telephone,
            c.email AS coursier_email,
            c.statut AS coursier_statut,
            c.date_inscription,
            c.derniere_activite,
            c.zone_travail AS coursier_zone_travail,
            c.vehicule_type AS coursier_vehicule_type,
            a.nom AS agent_nom,
            a.prenoms AS agent_prenoms,
            a.telephone AS agent_telephone,
            a.email AS agent_email,
            a.statut_connexion AS agent_statut_connexion,
            a.zone_travail AS agent_zone_travail,
            a.type_vehicule AS agent_vehicule_type,
            a.solde_wallet AS agent_solde_wallet,
            a.last_login_at AS agent_last_login_at,
            a.matricule AS agent_matricule
        FROM coursiers c
        LEFT JOIN agents_suzosky a ON a.id = c.id
        WHERE c.id = ?
        LIMIT 1
    ";
    $stmt = $pdo->prepare($infoSql);
    $stmt->execute([$coursier_id]);
    $coursierRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coursierRow) {
        // Fallback si le pont coursiers n'est pas synchronisé
        $fallback = $pdo->prepare("
            SELECT 
                id,
                nom AS agent_nom,
                prenoms AS agent_prenoms,
                telephone AS agent_telephone,
                email AS agent_email,
                statut_connexion AS agent_statut_connexion,
                zone_travail AS agent_zone_travail,
                type_vehicule AS agent_vehicule_type,
                COALESCE(solde_wallet, 0) AS agent_solde_wallet,
                last_login_at AS agent_last_login_at,
                matricule AS agent_matricule
            FROM agents_suzosky
            WHERE id = ?
            LIMIT 1
        ");
        $fallback->execute([$coursier_id]);
        $agentRow = $fallback->fetch(PDO::FETCH_ASSOC);

        if ($agentRow) {
            $coursierRow = [
                'id' => (int)$agentRow['id'],
                'coursier_nom' => trim(($agentRow['agent_prenoms'] ?? '') . ' ' . ($agentRow['agent_nom'] ?? '')),
                'coursier_telephone' => $agentRow['agent_telephone'] ?? null,
                'coursier_email' => $agentRow['agent_email'] ?? null,
                'coursier_statut' => $agentRow['agent_statut_connexion'] ?? 'actif',
                'date_inscription' => null,
                'derniere_activite' => $agentRow['agent_last_login_at'] ?? null,
                'coursier_zone_travail' => $agentRow['agent_zone_travail'] ?? null,
                'coursier_vehicule_type' => $agentRow['agent_vehicule_type'] ?? null,
                'agent_nom' => $agentRow['agent_nom'] ?? null,
                'agent_prenoms' => $agentRow['agent_prenoms'] ?? null,
                'agent_telephone' => $agentRow['agent_telephone'] ?? null,
                'agent_email' => $agentRow['agent_email'] ?? null,
                'agent_statut_connexion' => $agentRow['agent_statut_connexion'] ?? null,
                'agent_zone_travail' => $agentRow['agent_zone_travail'] ?? null,
                'agent_vehicule_type' => $agentRow['agent_vehicule_type'] ?? null,
                'agent_solde_wallet' => $agentRow['agent_solde_wallet'] ?? 0,
                'agent_last_login_at' => $agentRow['agent_last_login_at'] ?? null,
                'agent_matricule' => $agentRow['agent_matricule'] ?? null
            ];
        }
    }

    if (!$coursierRow) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'COURSIER_NOT_FOUND',
            'message' => 'Coursier non trouvé'
        ]);
        exit;
    }

    // Jeu de statuts (nouvelle taxonomie unifiée)
    $completedSet = [ 'livree', 'termine' ]; // 'termine' legacy
    $activeSet    = [ 'assignee','attribuee','acceptee','en_cours','picked_up','recupere','nouvelle' ];

    // Statistiques de commandes consolidées
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_commandes,
            SUM(CASE WHEN statut IN ('livree','termine') THEN 1 ELSE 0 END) AS commandes_terminees,
            SUM(CASE WHEN statut IN ('livree','termine') AND DATE(COALESCE(created_at, date_creation)) = CURDATE() THEN 1 ELSE 0 END) AS commandes_jour,
            SUM(CASE WHEN statut IN ('livree','termine') AND DATE(COALESCE(created_at, date_creation)) = CURDATE() THEN COALESCE(montant_total, prix_total, prix_estime, 0) ELSE 0 END) AS gains_jour,
            SUM(CASE WHEN statut IN ('livree','termine') THEN COALESCE(montant_total, prix_total, prix_estime, 0) ELSE 0 END) AS gains_total,
            MAX(COALESCE(created_at, date_creation)) AS derniere_commande
        FROM commandes
        WHERE coursier_id = ?
    ");
    $statsStmt->execute([$coursier_id]);
    $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
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