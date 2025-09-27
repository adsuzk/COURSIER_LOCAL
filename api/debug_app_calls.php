<?php
// Debug endpoint pour tracer les appels getCoursierData depuis l'app
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$coursierId = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : 0;
if (!$coursierId) {
    echo json_encode(['success'=>false,'error'=>'coursier_id requis']);
    exit;
}

$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'get_coursier_orders_simple';

echo json_encode([
    'debug' => true,
    'timestamp' => date('c'),
    'requested_coursier_id' => $coursierId,
    'endpoint_tested' => $endpoint,
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
    'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'message' => 'App appelle bien le serveur - verifie parsing côté Android'
]);
?>