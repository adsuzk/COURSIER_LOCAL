<?php
// deactivate_device_token.php
// API pour désactiver un token FCM (logout coursier)
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE device_tokens SET is_active = 0 WHERE token = ?');
    $stmt->execute([$token]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucun token trouvé']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
