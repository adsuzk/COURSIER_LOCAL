<?php
// API pour récupérer la position de tous les coursiers connectés (pour attribution et suivi temps réel)
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/tracking_helpers.php';

try {
    $pdo = getDBConnection();
    $rows = tracking_select_latest_positions($pdo, 180);
    // Keep compatibility keys for older consumers
    $compat = array_map(function($r){
        return [
            'coursier_id' => (int)$r['coursier_id'],
            'lat' => isset($r['latitude'])? (float)$r['latitude'] : null,
            'lng' => isset($r['longitude'])? (float)$r['longitude'] : null,
            'updated_at' => $r['derniere_position'] ?? null
        ];
    }, $rows);
    echo json_encode(['success' => true, 'coursiers' => $compat, 'normalized' => $rows]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
