<?php
/**
 * API simplifiée pour debug get_coursier_orders.php
 * Optimisée pour éviter les NetworkOnMainThreadException
 */

// Headers optimisés pour performance
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: close');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/db_maintenance.php';
require_once __DIR__ . '/schema_utils.php';

// Récupérer coursier_id
$coursier_id = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : null;
$limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 20) : 5;

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
    $clientsInfo = ensureLegacyClientsTable($pdo);
    $hasLegacyClients = $clientsInfo['exists'] ?? false;
    $coordinateExpr = commandeCoordinateExpressions($pdo);

    $clientJoin = $hasLegacyClients ? ' LEFT JOIN clients cl ON c.client_id = cl.id' : '';
    $clientNomExpr = $hasLegacyClients
        ? "COALESCE(cl.nom, c.client_nom, 'Client')"
        : "COALESCE(c.client_nom, 'Client')";
    $clientTelExpr = $hasLegacyClients
        ? "COALESCE(cl.telephone, c.client_telephone, c.telephone_expediteur)"
        : "COALESCE(c.client_telephone, c.telephone_expediteur)";
    
    // Vérifier que le coursier existe
    $coursier = null;
    try {
        $check_sql = "SELECT id, nom, statut FROM coursiers WHERE id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$coursier_id]);
        $coursier = $check_stmt->fetch();
    } catch (Throwable $e) {
        // ignore, we'll attempt the agents_suzosky fallback below
    }

    if (!$coursier) {
        try {
            $check_sql = "SELECT id, CONCAT(nom, ' ', prenoms) AS nom, 'actif' AS statut FROM agents_suzosky WHERE id = ?";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->execute([$coursier_id]);
            $coursier = $check_stmt->fetch();
        } catch (Throwable $e2) { /* ignore */ }
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
    
    // Query commandes simplifiée - FILTRER les commandes terminées/annulées
    $sql = "
        SELECT 
            c.id,
            c.client_id,
            {$clientNomExpr} as client_nom,
            {$clientTelExpr} as client_telephone,
            COALESCE(c.adresse_depart, c.adresse_retrait) as adresse_depart,
            COALESCE(c.adresse_arrivee, c.adresse_livraison) as adresse_arrivee,
            COALESCE(c.distance_estimee, 0) as distance_estimee,
            COALESCE(c.statut, 'nouvelle') as statut,
            COALESCE(c.prix_total, c.prix_estime, 0) AS montant_total,
            COALESCE(c.description_colis, '') AS description,
            c.created_at AS date_creation,
            {$coordinateExpr['pickup_lat']} AS pickup_latitude,
            {$coordinateExpr['pickup_lng']} AS pickup_longitude,
            {$coordinateExpr['drop_lat']} AS dropoff_latitude,
            {$coordinateExpr['drop_lng']} AS dropoff_longitude
        FROM commandes c
        {$clientJoin}
        WHERE c.coursier_id = ?
          AND c.statut NOT IN ('terminee', 'annulee', 'refusee', 'cancelled')
        ORDER BY c.created_at DESC
        LIMIT ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coursier_id, $limit]);
    $commandes_raw = $stmt->fetchAll();
    
    // Formater pour l'app
    $commandes = [];
    foreach ($commandes_raw as $cmd) {
        $dateCreation = $cmd['date_creation'] ?? '';
        $dateOnly = '';
        $timeOnly = '';

        if ($dateCreation) {
            try {
                $parts = explode(' ', $dateCreation);
                $dateOnly = $parts[0] ?? '';
                $timeOnly = $parts[1] ?? '';
            } catch (Throwable $e) {
                $dateOnly = date('Y-m-d');
                $timeOnly = date('H:i:s');
            }
        }

    $statut_raw = $cmd['statut'];
    // Mapping front-end (l'app compte seulement 'nouvelle' ou 'attente' pour pending)
    $front_statut = $statut_raw;
    if ($statut_raw === 'assignee') { $front_statut = 'nouvelle'; }
    elseif ($statut_raw === 'picked_up') { $front_statut = 'recupere'; }
    // Alias éventuel legacy pour compat (attribuee)
    $statut_alias = ($statut_raw === 'assignee') ? 'attribuee' : $front_statut;

        $distanceKm = (float)($cmd['distance_estimee'] ?? 0);
        $montantTotal = (float)$cmd['montant_total'];

        // Objet principal (camelCase) déjà utilisé dans certains tests récents
        $obj = [
            'id' => (int)$cmd['id'],
            'clientNom' => $cmd['client_nom'],
            'clientTelephone' => $cmd['client_telephone'],
            'adresseEnlevement' => $cmd['adresse_depart'],
            'adresseLivraison' => $cmd['adresse_arrivee'],
            'distanceKm' => $distanceKm,
            'prix' => $montantTotal,
            'statut' => $front_statut,
            'statut_raw' => $statut_raw,
            'statut_alias' => $statut_alias,
            'dateCommande' => $dateOnly,
            'heureCommande' => $timeOnly
        ];

        $pickupLatRaw = $cmd['pickup_latitude'] ?? null;
        $pickupLngRaw = $cmd['pickup_longitude'] ?? null;
        $hasPickup = is_numeric($pickupLatRaw) && is_numeric($pickupLngRaw) && (abs((float)$pickupLatRaw) > 0.0001 || abs((float)$pickupLngRaw) > 0.0001);
        if ($hasPickup) {
            $pickupCoords = [
                'latitude' => (float)$pickupLatRaw,
                'longitude' => (float)$pickupLngRaw
            ];
            $obj['coordonneesEnlevement'] = $pickupCoords;
            $obj['coordonnees_enlevement'] = $pickupCoords;
        }

        $dropLatRaw = $cmd['dropoff_latitude'] ?? null;
        $dropLngRaw = $cmd['dropoff_longitude'] ?? null;
        $hasDrop = is_numeric($dropLatRaw) && is_numeric($dropLngRaw) && (abs((float)$dropLatRaw) > 0.0001 || abs((float)$dropLngRaw) > 0.0001);
        if ($hasDrop) {
            $dropCoords = [
                'latitude' => (float)$dropLatRaw,
                'longitude' => (float)$dropLngRaw
            ];
            $obj['coordonneesLivraison'] = $dropCoords;
            $obj['coordonnees_livraison'] = $dropCoords;
        }

        // Ajout des aliases snake_case attendus par le code Android actuel (voir ApiService parsing)
        $obj['client_nom'] = $obj['clientNom'];
        $obj['client_telephone'] = $obj['clientTelephone'];
        $obj['adresse_depart'] = $obj['adresseEnlevement'];
        $obj['adresse_arrivee'] = $obj['adresseLivraison'];
        $obj['distance_km'] = $distanceKm; // utilisé par optDouble("distance_km")
        $obj['montant_total'] = $montantTotal; // utilisé par optDouble("montant_total")
        $obj['date_creation'] = $dateCreation ?: ($dateOnly . ' ' . $timeOnly);

        // Compat: certains écrans peuvent regarder gain_commission (sinon 0) => on mappe sur montant_total pour tests
        $obj['gain_commission'] = $montantTotal;

        // Description & code commande (si utiles plus tard côté app)
        if (isset($cmd['description'])) $obj['description'] = $cmd['description'];
        if (isset($cmd['code_commande'])) $obj['code_commande'] = $cmd['code_commande'];

        $commandes[] = $obj;
    }
    
    // Statistiques basiques
    $stats_sql = "SELECT COUNT(*) as total FROM commandes WHERE coursier_id = ?";
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$coursier_id]);
    $total = $stats_stmt->fetchColumn();
    
    // Réponse
    $response = [
        'success' => true,
        'data' => [
            'coursier' => [
                'id' => $coursier['id'],
                'nom' => $coursier['nom'],
                'statut' => $coursier['statut']
            ],
            'commandes' => $commandes,
            'pagination' => [
                'total' => (int)$total,
                'limit' => $limit,
                'offset' => 0,
                'pages' => 1,
                'current_page' => 1
            ],
            'statistiques' => [
                'total_commandes' => (int)$total,
                'commandes_terminees' => 0,
                'commandes_actives' => count($commandes),
                'commandes_annulees' => 0,
                'revenus_total' => 0.0,
                'taux_reussite' => 0
            ],
            'gains' => [
                'commandes_payantes' => 0,
                'total_commissions' => 0.0,
                'commission_moyenne' => 0.0
            ]
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'error' => 'SYSTEM_ERROR',
        'message' => 'Erreur: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>