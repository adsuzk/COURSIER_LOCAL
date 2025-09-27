<?php
// API pour recevoir la position du coursier en temps réel
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/lib/tracking_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['coursier_id']) || !isset($data['lat']) || !isset($data['lng'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

$coursierId = intval($data['coursier_id']);
$lat = floatval($data['lat']);
$lng = floatval($data['lng']);

try {
    $pdo = getDBConnection();
    // Insert using schema-agnostic helper
    tracking_insert_position($pdo, $coursierId, $lat, $lng, isset($data['accuracy'])? floatval($data['accuracy']) : null);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
