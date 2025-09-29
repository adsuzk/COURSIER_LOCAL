<?php
// Script de diagnostic pour tester la connectivité réseau avec l'application Android

// Headers pour éviter les problèmes de CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Répondre aux requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log de la requête
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'headers' => getallheaders(),
    'get' => $_GET,
    'post' => $_POST,
    'body' => file_get_contents('php://input')
];

// Sauvegarder le log
file_put_contents('debug_connectivity.log', json_encode($log_data) . "\n", FILE_APPEND | LOCK_EX);

// Réponse de diagnostic
$response = [
    'status' => 'success',
    'message' => 'Connexion réseau OK',
    'server_time' => date('Y-m-d H:i:s'),
    'client_ip' => $_SERVER['REMOTE_ADDR'],
    'server_info' => [
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>