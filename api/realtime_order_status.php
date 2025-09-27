<?php
// api/realtime_order_status.php - API pour synchronisation temps réel des statuts de commande
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';

try {
    // Connexion à la base de données
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    $order_id = $_GET['order_id'] ?? '';
    $code_commande = $_GET['code_commande'] ?? '';
    $last_update = $_GET['last_update'] ?? '0';

    if (empty($order_id) && empty($code_commande)) {
        throw new Exception('order_id ou code_commande requis');
    }

    // Construire la requête selon les paramètres
    if (!empty($order_id)) {
        $whereClause = 'id = :identifier';
        $identifier = $order_id;
    } else {
        $whereClause = 'code_commande = :identifier';
        $identifier = $code_commande;
    }

    // Récupérer les informations de la commande avec les détails du coursier
    $sql = "
        SELECT 
            c.*,
            cour.nom as coursier_nom,
            cour.telephone as coursier_telephone,
            cour.position_lat as coursier_lat,
            cour.position_lng as coursier_lng,
            cour.last_position_update,
            UNIX_TIMESTAMP(c.updated_at) as last_update_timestamp
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

    // Vérifier s'il y a des changements depuis la dernière mise à jour
    $hasUpdates = ($order['last_update_timestamp'] > $last_update);

    // Timeline des étapes avec timestamps
    $timeline = [
        'nouvelle' => [
            'status' => 'completed',
            'timestamp' => $order['created_at'],
            'label' => 'Commande créée',
            'description' => 'Votre commande a été enregistrée'
        ],
        'assignee' => [
            'status' => in_array($order['statut'], ['assignee', 'en_cours', 'livree']) ? 'completed' : 'pending',
            'timestamp' => $order['assigned_at'] ?? null,
            'label' => 'Coursier assigné',
            'description' => $order['coursier_nom'] ? "Assignée à {$order['coursier_nom']}" : 'Recherche d\'un coursier...'
        ],
        'en_cours' => [
            'status' => in_array($order['statut'], ['en_cours', 'livree']) ? 'completed' : 'pending',
            'timestamp' => $order['picked_up_at'] ?? null,
            'label' => 'Colis récupéré',
            'description' => 'Le coursier a récupéré votre colis'
        ],
        'livree' => [
            'status' => $order['statut'] === 'livree' ? 'completed' : 'pending',
            'timestamp' => $order['delivered_at'] ?? null,
            'label' => 'Livré',
            'description' => 'Votre colis a été livré'
        ]
    ];

    // Informations du coursier (si assigné)
    $coursier_info = null;
    if ($order['coursier_id'] && $order['coursier_nom']) {
        $coursier_info = [
            'nom' => $order['coursier_nom'],
            'telephone' => $order['coursier_telephone'],
            'position' => [
                'lat' => $order['coursier_lat'],
                'lng' => $order['coursier_lng'],
                'last_update' => $order['last_position_update']
            ]
        ];
    }

    // Estimation du temps de livraison (si en cours)
    $estimated_delivery = null;
    if ($order['statut'] === 'en_cours' && $order['coursier_lat'] && $order['coursier_lng']) {
        // Simple estimation basée sur la distance (à améliorer avec une vraie API de routing)
        $estimated_delivery = date('H:i', strtotime('+30 minutes'));
    }

    echo json_encode([
        'success' => true,
        'hasUpdates' => $hasUpdates,
        'data' => [
            'order_id' => $order['id'],
            'code_commande' => $order['code_commande'],
            'statut' => $order['statut'],
            'timeline' => $timeline,
            'coursier' => $coursier_info,
            'estimated_delivery' => $estimated_delivery,
            'last_update' => $order['last_update_timestamp']
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