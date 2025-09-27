<?php
// Test connectivity from mobile device to local server
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = [
    'success' => true,
    'message' => 'Local server is reachable',
    'server_info' => [
        'timestamp' => date('Y-m-d H:i:s'),
        'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ],
    'database_status' => 'unknown'
];

// Test database connection
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM agents_suzosky WHERE matricule = 'CM20250001'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['database_status'] = 'connected';
    $response['test_agent_found'] = $result['count'] > 0;
} catch (Exception $e) {
    $response['database_status'] = 'error: ' . $e->getMessage();
}

// Log the connectivity test
error_log("MOBILE_CONNECTIVITY_TEST: " . json_encode($response));

echo json_encode($response, JSON_PRETTY_PRINT);
?>