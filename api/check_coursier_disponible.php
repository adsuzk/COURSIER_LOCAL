<?php
// API minimaliste pour vérifier la disponibilité des coursiers (tokens FCM actifs)
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT coursier_id) FROM device_tokens WHERE is_active = 1');
    $stmt->execute();
    $count = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'available' => $count > 0, 'count' => $count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
