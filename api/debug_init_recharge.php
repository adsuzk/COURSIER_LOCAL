<?php
// debug_init_recharge.php - Script de debug pour voir ce que reçoit l'API

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'debug' => true,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'get_params' => $_GET,
    'post_params' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'headers' => getallheaders(),
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN',
    'time' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>