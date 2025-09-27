<?php
// api/timeline_sync.php - API pour synchronisation temps réel de la timeline
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config.php';

if (!function_exists('timelineNormalizeDateValue')) {
    function timelineNormalizeDateValue($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_int($value)) {
            if ($value <= 0) {
                return null;
            }
            return date('Y-m-d H:i:s', $value);
        }
        if (is_numeric($value)) {
            $value = (int)$value;
            if ($value <= 0) {
                return null;
            }
            return date('Y-m-d H:i:s', $value);
        }
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || $value === '0000-00-00 00:00:00' || $value === '0000-00-00') {
            return null;
        }
        return $value;
    }
}

if (!function_exists('timelinePickDateValue')) {
    function timelinePickDateValue(array $row, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $normalized = timelineNormalizeDateValue($row[$key]);
            if ($normalized !== null) {
                return $normalized;
            }
        }
        return null;
    }
}

if (!function_exists('timelineTimestampFromValue')) {
    function timelineTimestampFromValue(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return null;
        }
        return $ts;
    }
}

try {
    // Connexion à la base de données
    $pdo = getDBConnection();

    $order_id = $_GET['order_id'] ?? '';
    $code_commande = $_GET['code_commande'] ?? '';
    $last_check = $_GET['last_check'] ?? 0;

    if (empty($order_id) && empty($code_commande)) {
        throw new Exception('order_id ou code_commande requis');
    }

    // Construire la requête selon les paramètres
    if (!empty($order_id)) {
        $whereClause = 'c.id = :identifier';
        $identifier = $order_id;
    } else {
        $whereClause = 'c.code_commande = :identifier';
        $identifier = $code_commande;
    }

    // Récupérer les informations complètes de la commande
    $sql = "
        SELECT 
            c.*,
            cour.id as coursier_id,
            cour.nom as coursier_nom,
            cour.telephone as coursier_telephone,
            NULL as coursier_lat,
            NULL as coursier_lng,
            NULL as last_position_update
        FROM commandes c
        LEFT JOIN coursiers cour ON c.coursier_id = cour.id
        WHERE {$whereClause}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['identifier' => $identifier]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Commande non trouvée');
    }

    $createdAtRaw = timelinePickDateValue($order, [
        'created_at', 'date_creation', 'created_on', 'date_commande', 'commande_date', 'date'
    ]);
    $updatedAtRaw = timelinePickDateValue($order, [
        'updated_at', 'date_modification', 'modified_at', 'date_update', 'derniere_update', 'last_update'
    ]) ?? $createdAtRaw;
    $assignedAtRaw = timelinePickDateValue($order, [
        'assigned_at', 'date_attribution', 'heure_attribution', 'heure_acceptation', 'date_affectation'
    ]) ?? $updatedAtRaw;
    $pickedUpAtRaw = timelinePickDateValue($order, [
        'picked_up_at', 'date_ramassage', 'date_retrait', 'heure_retrait', 'pickup_time'
    ]);
    $deliveredAtRaw = timelinePickDateValue($order, [
        'delivered_at', 'date_livraison', 'heure_livraison', 'date_livraison_reelle', 'completed_at'
    ]);

    $createdTimestamp = timelineTimestampFromValue($createdAtRaw);
    $lastUpdateTimestamp = timelineTimestampFromValue($updatedAtRaw);
    $assignedTimestamp = timelineTimestampFromValue($assignedAtRaw);
    $pickedUpTimestamp = timelineTimestampFromValue($pickedUpAtRaw);
    $deliveredTimestamp = timelineTimestampFromValue($deliveredAtRaw);

    if ($lastUpdateTimestamp === null) {
        $lastUpdateTimestamp = $createdTimestamp ?? time();
    }
    if ($createdTimestamp === null) {
        $createdTimestamp = $lastUpdateTimestamp;
    }
    if ($assignedTimestamp === null) {
        $assignedTimestamp = $lastUpdateTimestamp;
    }

    $last_check = is_numeric($last_check) ? (int)$last_check : 0;
    $hasUpdates = ($lastUpdateTimestamp > $last_check);

    $statut = strtolower((string)($order['statut'] ?? 'nouvelle'));
    $statusToStage = [
        'nouvelle' => 0,
        'assignee' => 1,
        'acceptee' => 2,
        'picked_up' => 3,
        'en_cours' => 4,
        'livree' => 5,
    ];
    $currentStage = $statusToStage[$statut] ?? 0;

    // Timeline détaillée avec états et transitions
    $timeline_steps = [
        [
            'key' => 'pending',
            'label' => 'Commande reçue',
            'description' => 'Votre commande a été enregistrée dans notre système',
            'icon' => '📦',
            'timestamp' => $createdTimestamp,
            'datetime' => $createdAtRaw
        ],
        [
            'key' => 'confirmed',
            'label' => 'Coursier confirmé',
            'description' => $order['coursier_nom'] ?
                "Coursier {$order['coursier_nom']} confirmé pour votre course" :
                'Recherche du meilleur coursier disponible...',
            'icon' => '✅',
            'timestamp' => $assignedTimestamp,
            'datetime' => $assignedAtRaw,
            'coursier' => $order['coursier_nom']
        ],
        [
            'key' => 'pickup',
            'label' => 'En route pour collecte',
            'description' => $order['coursier_nom'] ?
                'Le coursier se rend vers le point de collecte' :
                'En attente de confirmation du coursier',
            'icon' => '📍',
            'timestamp' => $assignedTimestamp,
            'datetime' => $assignedAtRaw
        ],
        [
            'key' => 'transit',
            'label' => 'Colis récupéré',
            'description' => in_array($statut, ['picked_up', 'en_cours', 'livree'], true) ?
                'Le colis est pris en charge et prêt pour la livraison' :
                'En attente de récupération du colis',
            'icon' => '🚚',
            'timestamp' => $pickedUpTimestamp,
            'datetime' => $pickedUpAtRaw
        ],
        [
            'key' => 'delivery',
            'label' => 'Livraison en cours',
            'description' => in_array($statut, ['en_cours', 'livree'], true) ?
                'Le coursier se dirige vers la destination finale' :
                'Livraison en préparation',
            'icon' => '🏠',
            'timestamp' => in_array($statut, ['en_cours', 'livree'], true) ? $lastUpdateTimestamp : null,
            'datetime' => in_array($statut, ['en_cours', 'livree'], true) ? $updatedAtRaw : null
        ],
        [
            'key' => 'completed',
            'label' => 'Commande terminée',
            'description' => $statut === 'livree' ?
                'Livraison confirmée avec succès' :
                'Validation finale en attente',
            'icon' => '✨',
            'timestamp' => $deliveredTimestamp,
            'datetime' => $deliveredAtRaw
        ]
    ];

    $lastIndex = count($timeline_steps) - 1;
    foreach ($timeline_steps as $index => &$step) {
        if ($currentStage > $index) {
            $step['status'] = 'completed';
        } elseif ($currentStage === $index) {
            $step['status'] = ($index === $lastIndex && $statut === 'livree') ? 'completed' : 'active';
        } else {
            $step['status'] = 'pending';
        }
    }
    unset($step);

    // Position du coursier en temps réel
    $coursier_position = null;
    if (!empty($order['coursier_lat']) && !empty($order['coursier_lng'])) {
        $coursier_position = [
            'lat' => (float)$order['coursier_lat'],
            'lng' => (float)$order['coursier_lng'],
            'last_update' => $order['last_position_update'],
            'accuracy' => 'high' // ou récupérer la vraie précision si stockée
        ];
    }

    // Estimation de livraison (simple pour le moment)
    $estimated_delivery = null;
    if ($order['statut'] === 'en_cours') {
        $estimated_delivery = date('H:i', $lastUpdateTimestamp + (25 * 60));
    }

    // Messages temps réel
    $messages = [];
    if (in_array($statut, ['assignee', 'acceptee'], true) && $order['coursier_nom']) {
        $messages[] = [
            'type' => 'info',
            'text' => "Le coursier {$order['coursier_nom']} se rend au point de récupération",
            'timestamp' => time()
        ];
    } elseif ($statut === 'picked_up') {
        $messages[] = [
            'type' => 'success',
            'text' => 'Le colis a été pris en charge, la livraison démarre.',
            'timestamp' => time()
        ];
    } elseif ($statut === 'en_cours') {
        $messages[] = [
            'type' => 'success',
            'text' => 'Votre colis est en route vers sa destination',
            'timestamp' => time()
        ];
    } elseif ($statut === 'livree') {
        $messages[] = [
            'type' => 'success',
            'text' => 'Livraison confirmée. Merci pour votre confiance !',
            'timestamp' => time()
        ];
    }

    echo json_encode([
        'success' => true,
        'hasUpdates' => $hasUpdates,
        'data' => [
            'order_id' => $order['id'],
            'code_commande' => $order['code_commande'],
            'statut' => $order['statut'],
            'coursier_id' => $order['coursier_id'],
            'coursier' => $order['coursier_id'] ? [
                'id' => (int)$order['coursier_id'],
                'nom' => $order['coursier_nom'] ?? null,
                'telephone' => $order['coursier_telephone'] ?? null
            ] : null,
            'timeline' => $timeline_steps,
            'coursier_position' => $coursier_position,
            'estimated_delivery' => $estimated_delivery,
            'messages' => $messages,
            'last_update' => $lastUpdateTimestamp,
            'departure' => $order['lieu_depart'],
            'destination' => $order['lieu_arrivee']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>