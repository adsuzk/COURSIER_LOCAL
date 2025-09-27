<?php
/**
 * ============================================================================
 * 🌐 API TRACKING TEMPS RÉEL - COURSIER_LOCAL
 * ============================================================================
 *
 * API pour le suivi en temps réel des commandes avec géolocalisation
 * Compatible avec l'interface admin_commandes_enhanced.php
 *
 * @version 2.0.0 - 2025-09-25
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';

/**
 * Map agent schema to ensure compatibility whatever the column names.
 */
function getAgentsSchemaInfo(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $columns = [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM agents_suzosky');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        // ignore, fallback defaults below
    }

    $joinColumn = isset($columns['id_coursier']) ? 'id_coursier' : 'id';

    $cache = [
        'columns' => $columns,
        'join_column' => $joinColumn,
    ];

    return $cache;
}

function firstNonEmpty(array $values)
{
    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }
        if ($value === '') {
            continue;
        }
        return $value;
    }
    return null;
}

function formatTimestamp(?string $value, string $format = 'd/m/Y H:i'): ?string
{
    if (!$value) {
        return null;
    }
    $time = strtotime($value);
    if ($time === false) {
        return null;
    }
    return date($format, $time);
}

function resolveStageState(int $stageIndex, int $activeStage, string $status): string
{
    $status = strtolower($status);
    if (in_array($status, ['annulee', 'annule', 'cancelled'], true)) {
        if ($stageIndex < $activeStage) {
            return 'completed';
        }
        if ($stageIndex === $activeStage) {
            return 'cancelled';
        }
        return 'pending';
    }

    if ($stageIndex < $activeStage) {
        return 'completed';
    }

    if ($stageIndex === $activeStage) {
        return in_array($status, ['livree', 'delivered'], true) ? 'completed' : 'active';
    }

    return 'pending';
}

function buildPoint(array $commande, array $latKeys, array $lngKeys, array $addressKeys): ?array
{
    $lat = null;
    foreach ($latKeys as $key) {
        if (isset($commande[$key]) && $commande[$key] !== null && $commande[$key] !== '') {
            $lat = (float) $commande[$key];
            break;
        }
    }

    $lng = null;
    foreach ($lngKeys as $key) {
        if (isset($commande[$key]) && $commande[$key] !== null && $commande[$key] !== '') {
            $lng = (float) $commande[$key];
            break;
        }
    }

    $address = null;
    foreach ($addressKeys as $key) {
        if (!empty($commande[$key])) {
            $address = $commande[$key];
            break;
        }
    }

    if ($lat !== null && $lng !== null) {
        return [
            'lat' => $lat,
            'lng' => $lng,
            'address' => $address,
        ];
    }

    if ($address) {
        return [
            'lat' => null,
            'lng' => null,
            'address' => $address,
        ];
    }

    return null;
}

try {
    $pdo = getDBConnection();

    $commandeId = isset($_GET['commande_id']) ? (int) $_GET['commande_id'] : 0;
    if ($commandeId <= 0) {
        throw new RuntimeException('ID de commande requis');
    }

    $agentsInfo = getAgentsSchemaInfo($pdo);
    $agentColumns = $agentsInfo['columns'] ?? [];

    $agentSelectParts = [];
    $aliasMap = [
        'nom' => 'agent_nom',
        'prenoms' => 'agent_prenoms',
        'telephone' => 'agent_telephone',
        'statut_connexion' => 'agent_statut_connexion',
        'status' => 'agent_status',
        'is_online' => 'agent_is_online',
        'last_seen' => 'agent_last_seen',
        'position_timestamp' => 'agent_position_timestamp',
        'latitude' => 'agent_latitude',
        'longitude' => 'agent_longitude',
        'position_lat' => 'agent_position_lat',
        'position_lng' => 'agent_position_lng',
        'derniere_position_lat' => 'agent_last_lat',
        'derniere_position_lng' => 'agent_last_lng',
    ];

    foreach ($aliasMap as $column => $alias) {
        if (isset($agentColumns[$column])) {
            $agentSelectParts[] = "a.{$column} AS {$alias}";
        } else {
            $agentSelectParts[] = "NULL AS {$alias}";
        }
    }

    $agentSelect = '';
    if (!empty($agentSelectParts)) {
        $agentSelect = ', ' . implode(",\n               ", $agentSelectParts);
    }

    $sql = "
        SELECT c.*{$agentSelect}
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.{$agentsInfo['join_column']}
        WHERE c.id = ?
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$commandeId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commande) {
        throw new RuntimeException('Commande introuvable');
    }

    $status = strtolower($commande['statut'] ?? 'nouvelle');
    $statusStageMap = [
        'nouvelle' => 0,
        'en_attente' => 0,
        'pending' => 0,
        'assignee' => 1,
        'acceptee' => 2,
        'picked_up' => 3,
        'en_cours' => 4,
        'livree' => 5,
        'delivered' => 5,
        'annulee' => 5,
        'annule' => 5,
        'cancelled' => 5,
    ];
    $activeStage = $statusStageMap[$status] ?? 0;

    $codeCommande = $commande['code_commande'] ?? ($commande['order_number'] ?? $commande['id']);

    $courierNameParts = array_filter([
        $commande['agent_prenoms'] ?? null,
        $commande['agent_nom'] ?? null,
    ]);
    $courierFullName = trim(implode(' ', $courierNameParts));

    $latCandidates = [
        $commande['agent_latitude'] ?? null,
        $commande['agent_position_lat'] ?? null,
        $commande['agent_last_lat'] ?? null,
    ];
    $lngCandidates = [
        $commande['agent_longitude'] ?? null,
        $commande['agent_position_lng'] ?? null,
        $commande['agent_last_lng'] ?? null,
    ];

    $courierLat = firstNonEmpty($latCandidates);
    $courierLng = firstNonEmpty($lngCandidates);

    $positionCoursier = null;
    if ($courierLat !== null && $courierLng !== null) {
        $positionCoursier = [
            'lat' => (float) $courierLat,
            'lng' => (float) $courierLng,
            'timestamp' => $commande['agent_position_timestamp'] ?? ($commande['agent_last_seen'] ?? null),
            'is_online' => isset($commande['agent_is_online']) ? (bool) $commande['agent_is_online'] : null,
            'status' => $commande['agent_statut_connexion'] ?? ($commande['agent_status'] ?? null),
        ];
    }

    $pickupPoint = buildPoint(
        $commande,
        ['latitude_retrait', 'latitude_depart', 'lat_retrait', 'latitude_pickup'],
        ['longitude_retrait', 'longitude_depart', 'lng_retrait', 'longitude_pickup'],
        ['adresse_retrait', 'adresse_depart', 'lieu_depart']
    );

    $dropoffPoint = buildPoint(
        $commande,
        ['latitude_livraison', 'latitude_arrivee', 'lat_livraison'],
        ['longitude_livraison', 'longitude_arrivee', 'lng_livraison'],
        ['adresse_livraison', 'adresse_arrivee', 'lieu_arrivee']
    );

    // Queue du coursier
    $queueOrders = [];
    $queuePosition = null;
    if (!empty($commande['coursier_id'])) {
        $queueStmt = $pdo->prepare(
            "SELECT id, code_commande, statut, created_at
             FROM commandes
             WHERE coursier_id = ?
             AND statut IN ('nouvelle','en_attente','assignee','acceptee','en_cours')
             ORDER BY created_at ASC"
        );
        $queueStmt->execute([$commande['coursier_id']]);
        while ($row = $queueStmt->fetch(PDO::FETCH_ASSOC)) {
            $queueOrders[] = [
                'id' => (int) $row['id'],
                'code_commande' => $row['code_commande'],
                'statut' => $row['statut'],
                'created_at' => $row['created_at'],
                'created_at_formatted' => formatTimestamp($row['created_at']),
                'is_current' => ((int) $row['id'] === $commandeId),
            ];
        }

        foreach ($queueOrders as $index => $row) {
            if ($row['is_current']) {
                $queuePosition = $index + 1;
                break;
            }
        }
    }

    $totalQueue = count($queueOrders);

    if ($queuePosition === null && $totalQueue > 0) {
        // Commande pas trouvée dans la queue active, fallback position calculée
        $queuePosition = $totalQueue;
    }

    if ($queuePosition === null) {
        $queuePosition = 1;
    }

    // Metriques estimations
    $estimations = [
        'pickup_distance_km' => null,
        'pickup_eta_minutes' => null,
    ];

    if ($positionCoursier && $pickupPoint && $pickupPoint['lat'] !== null && $pickupPoint['lng'] !== null) {
        $latDiff = deg2rad($pickupPoint['lat'] - $positionCoursier['lat']);
        $lonDiff = deg2rad($pickupPoint['lng'] - $positionCoursier['lng']);

        $a = sin($latDiff / 2) ** 2 +
            cos(deg2rad($positionCoursier['lat'])) * cos(deg2rad($pickupPoint['lat'])) *
            sin($lonDiff / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distanceKm = 6371 * $c;

        $estimations['pickup_distance_km'] = round($distanceKm, 2);
        $estimations['pickup_eta_minutes'] = max(1, (int) round($distanceKm * 3));
    }

    $timelineSteps = [
        [
            'key' => 'pending',
            'label' => 'Commande reçue',
            'description' => 'Demande enregistrée dans le système',
            'icon' => '�',
            'state' => resolveStageState(0, $activeStage, $status),
            'timestamp' => $commande['created_at'] ?? null,
            'formatted' => formatTimestamp($commande['created_at'] ?? null),
        ],
        [
            'key' => 'confirmed',
            'label' => 'Coursier confirmé',
            'description' => $courierFullName ? "Coursier {$courierFullName} confirmé" : 'Recherche d’un coursier disponible',
            'icon' => '✅',
            'state' => resolveStageState(1, $activeStage, $status),
            'timestamp' => $commande['assigned_at'] ?? null,
            'formatted' => formatTimestamp($commande['assigned_at'] ?? null),
        ],
        [
            'key' => 'pickup',
            'label' => 'En route pour collecte',
            'description' => $courierFullName ? 'Déplacement vers le point de collecte' : 'En attente de confirmation du coursier',
            'icon' => '📍',
            'state' => resolveStageState(2, $activeStage, $status),
            'timestamp' => $commande['assigned_at'] ?? null,
            'formatted' => formatTimestamp($commande['assigned_at'] ?? null),
        ],
        [
            'key' => 'transit',
            'label' => 'Colis récupéré',
            'description' => 'Le colis est pris en charge et prêt pour la livraison',
            'icon' => '�',
            'state' => resolveStageState(3, $activeStage, $status),
            'timestamp' => $commande['picked_up_at'] ?? null,
            'formatted' => formatTimestamp($commande['picked_up_at'] ?? null),
        ],
        [
            'key' => 'delivery',
            'label' => 'Livraison en cours',
            'description' => 'Le coursier se dirige vers la destination finale',
            'icon' => '🏠',
            'state' => resolveStageState(4, $activeStage, $status),
            'timestamp' => $commande['updated_at'] ?? null,
            'formatted' => formatTimestamp($commande['updated_at'] ?? null),
        ],
        [
            'key' => 'completed',
            'label' => 'Commande terminée',
            'description' => 'Livraison confirmée et clôturée',
            'icon' => '✨',
            'state' => resolveStageState(5, $activeStage, $status),
            'timestamp' => $commande['delivered_at'] ?? null,
            'formatted' => formatTimestamp($commande['delivered_at'] ?? null),
        ],
    ];

    if (in_array($status, ['annulee', 'annule', 'cancelled'], true)) {
        $timelineSteps[] = [
            'key' => 'cancelled',
            'label' => 'Commande annulée',
            'description' => 'Cette course a été annulée',
            'icon' => '⚠️',
            'state' => 'cancelled',
            'timestamp' => $commande['updated_at'] ?? null,
            'formatted' => formatTimestamp($commande['updated_at'] ?? null),
        ];
    }

    $historiquePositions = array_map(static function (array $step): array {
        return [
            'label' => $step['label'],
            'timestamp' => $step['formatted'] ?? ($step['timestamp'] ?? null),
            'state' => $step['state'],
            'description' => $step['description'],
            'icon' => $step['icon'],
        ];
    }, $timelineSteps);

    $courierInfo = null;
    if (!empty($commande['coursier_id'])) {
        $courierInfo = [
            'id' => (int) $commande['coursier_id'],
            'nom' => $courierFullName ?: ($commande['agent_nom'] ?? null),
            'telephone' => $commande['agent_telephone'] ?? null,
            'statut_connexion' => $commande['agent_statut_connexion'] ?? ($commande['agent_status'] ?? null),
            'is_online' => isset($commande['agent_is_online']) ? (bool) $commande['agent_is_online'] : null,
            'last_seen' => $commande['agent_last_seen'] ?? null,
        ];
    }

    $response = [
        'success' => true,
        'commande' => [
            'id' => (int) $commande['id'],
            'code_commande' => $codeCommande,
            'order_number' => $commande['order_number'] ?? null,
            'statut' => $commande['statut'],
            'created_at' => $commande['created_at'],
            'created_at_formatted' => formatTimestamp($commande['created_at'] ?? null),
            'assigned_at' => $commande['assigned_at'] ?? null,
            'assigned_at_formatted' => formatTimestamp($commande['assigned_at'] ?? null),
            'picked_up_at' => $commande['picked_up_at'] ?? null,
            'picked_up_at_formatted' => formatTimestamp($commande['picked_up_at'] ?? null),
            'delivered_at' => $commande['delivered_at'] ?? null,
            'delivered_at_formatted' => formatTimestamp($commande['delivered_at'] ?? null),
            'adresse_retrait' => $commande['adresse_retrait'] ?? ($commande['adresse_depart'] ?? null),
            'adresse_livraison' => $commande['adresse_livraison'] ?? ($commande['adresse_arrivee'] ?? null),
            'prix_estime' => isset($commande['prix_estime']) ? (float) $commande['prix_estime'] : null,
        ],
        'coursier' => $courierInfo,
        'position_coursier' => $positionCoursier,
        'queue' => [
            'position' => $queuePosition,
            'total' => $totalQueue,
            'orders' => $queueOrders,
        ],
        'pickup' => $pickupPoint,
        'dropoff' => $dropoffPoint,
        'estimations' => $estimations,
        'timeline' => $timelineSteps,
        'historique_positions' => $historiquePositions,
        'last_status_update' => $commande['updated_at'] ?? null,
        'timestamp' => date('c'),
        'refresh_interval' => 20,
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c'),
    ]);
}

?>