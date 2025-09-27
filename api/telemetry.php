<?php
// Télémétrie simplifiée temporaire pour débugger les problèmes de connexion
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Réponse simplifiée pour tous les endpoints
    echo json_encode([
        'success' => true,
        'message' => 'Telemetry received',
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'TELEMETRY_ERROR',
        'message' => $e->getMessage()
    ]);
}
?>