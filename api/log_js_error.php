<?php
// Endpoint to log JavaScript errors from client side
require_once __DIR__ . '/../logger.php';
header('Content-Type: application/json');
// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$message = $data['message'] ?? 'Unknown JS error';
$details = json_encode($data, JSON_UNESCAPED_SLASHES);
// Log message and details
logMessage('diagnostics_js_errors.log', "$message | Details: $details");
// Respond success
echo json_encode(['success' => true]);
