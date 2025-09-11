<?php
// Script de diagnostic pour l'endpoint payment
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Log de toutes les informations de la requête
$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    'http_host' => $_SERVER['HTTP_HOST'] ?? '',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'post_data' => $_POST,
    'get_data' => $_GET,
    'files_data' => $_FILES,
    'raw_input' => file_get_contents('php://input'),
    'headers' => getallheaders()
];

// Écrire le diagnostic dans un fichier
file_put_contents(__DIR__ . '/diagnostic_payment.log', json_encode($diagnostics, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Retourner aussi la réponse
echo json_encode([
    'success' => true,
    'message' => 'Diagnostic endpoint - requête reçue',
    'received_data' => $diagnostics,
    'api_file_exists' => file_exists(__DIR__ . '/api/initiate_order_payment.php'),
    'api_file_path' => __DIR__ . '/api/initiate_order_payment.php'
]);
?>
