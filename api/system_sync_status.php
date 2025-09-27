<?php
/**
 * ============================================================================
 * 🔄 API SYSTEM SYNC STATUS - SUZOSKY
 * ============================================================================
 *
 * Retourne un instantané temps réel de la synchronisation globale
 * entre les modules critiques (index, FCM, APIs, admin, chat, etc.).
 *
 * @version 1.0.0
 * @date 25 septembre 2025
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/SystemSync.php';

echo json_encode(SystemSync::snapshot(), JSON_UNESCAPED_UNICODE);
