<?php
file_put_contents(__DIR__ . '/../diagnostic_logs/diagnostics_errors.log', date('[Y-m-d H:i:s] ') . "HELLO submit_order.php\n", FILE_APPEND);
// Protection anti-sortie parasite : buffer de sortie
ob_start();
// VERSION CORRIGÉE 2025-09-04 21:00 - PROTECTION CLIENT_ID FOREIGN KEY
// Unified JSON response and error handling
ini_set('display_errors', '0');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin', '*');
// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
// Loguer la méthode, les headers et le corps brut dès le début
logMessage('diagnostics_errors.log', 'REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'N/A'));
if (function_exists('getallheaders')) {
    logMessage('diagnostics_errors.log', 'REQUEST_HEADERS: ' . json_encode(getallheaders()));
}
$rawInput = file_get_contents('php://input');
logMessage('diagnostics_errors.log', 'RAW_INPUT: ' . $rawInput);
// Convert errors to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
    // Log exception details TRÈS DÉTAILLÉ
    logMessage('diagnostics_errors.log', '🚨 EXCEPTION CRITIQUE: ' . $e->getMessage());
    logMessage('diagnostics_errors.log', '📁 Fichier: ' . $e->getFile() . ' ligne ' . $e->getLine());
    logMessage('diagnostics_errors.log', '📋 Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'debug' => 'Exception logged']);
    exit;
});

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../logger.php';
require_once __DIR__ . '/../lib/db_maintenance.php';

if (!function_exists('submitOrderLoadPricingConfig')) {
    function submitOrderLoadPricingConfig(\PDO $pdo): array
    {
        $config = [
            'frais_base' => 500.0,
            'prix_kilometre' => 300.0
        ];
        try {

            header('Content-Type: application/json');
            echo json_encode(["success" => true, "message" => "submit_order.php SQUELETTE OK"]);
                    $config[$row['parametre']] = (float) $row['valeur'];
