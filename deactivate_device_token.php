<?php
// deactivate_device_token.php
// API pour désactiver un token FCM (logout coursier)
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

// Supporte POST (prod), GET et CLI (tests locaux uniquement)
$token = isset($_POST['token']) ? trim($_POST['token']) : '';
if (!$token && isset($_GET['token'])) {
    $token = trim($_GET['token']);
}
if (!$token && php_sapi_name() === 'cli' && !empty($argv[1])) {
    $token = trim($argv[1]);
}
if (!$token) {
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('UPDATE device_tokens SET is_active = 0 WHERE token = ?');
    $stmt->execute([$token]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'token_preview' => substr($token, 0, 20) . '...']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucun token trouvé']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
