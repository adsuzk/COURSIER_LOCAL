<?php
// api/assign_courier.php - Assigne le coursier le plus proche pour une commande
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../coursier.php'; // contient assignNearestCourier()
// Démarrer session pour récupérer user session si besoin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
// Récupérer le JSON envoyé
$input = json_decode(file_get_contents('php://input'), true);
$pickup = $input['pickup'] ?? null;
if (!$pickup || !isset($pickup['lat'], $pickup['lng'])) {
    echo json_encode(['success' => false, 'error' => 'Coordonnées de départ manquantes']);
    exit;
}
// Appeler la fonction d'assignation
$courier = assignNearestCourier($pickup['lat'], $pickup['lng']);
if ($courier) {
    // Notifier le coursier assigné (si un order_id est fourni dans le payload)
    try {
        $orderId = isset($input['order_id']) ? (int)$input['order_id'] : null;
        require_once __DIR__ . '/lib/fcm_enhanced.php';
        $pdo = getDBConnection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stTok = $pdo->prepare('SELECT token FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC');
        $stTok->execute([(int)$courier['id_coursier']]);
        $tokens = array_column($stTok->fetchAll(PDO::FETCH_ASSOC), 'token');
        if (!empty($tokens)) {
            fcm_send_with_log(
                $tokens,
                'Nouvelle commande',
                'Une nouvelle course vous a été attribuée',
                [ 'type' => 'new_order', 'order_id' => $orderId ],
                (int)$courier['id_coursier'],
                $orderId
            );
        }
    } catch (Throwable $e) { /* silencieux */ }
    echo json_encode(['success' => true, 'courier' => $courier]);
} else {
    echo json_encode(['success' => false, 'error' => 'Aucun coursier disponible']);
}
