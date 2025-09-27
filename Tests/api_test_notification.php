<?php
// Test d'envoi de notification push FCM réelle (protégé par token admin)
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/fcm_enhanced.php';

try {
    // Sécurité basique: requiert ADMIN_API_TOKEN en paramètre ?token=...
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
    $expected = (getenv('ADMIN_API_TOKEN') ?: ($GLOBALS['config']['admin']['api_token'] ?? ''));
    if ($expected === '' || !hash_equals($expected, (string)$token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }

    $pdo = getDBConnection();
    $coursierId = intval($_GET['coursier_id'] ?? $_POST['coursier_id'] ?? 1);
    $orderId    = intval($_GET['order_id'] ?? $_POST['order_id'] ?? 0) ?: null;
    $title      = trim($_GET['title'] ?? $_POST['title'] ?? 'Test Suzosky');
    $body       = trim($_GET['body']  ?? $_POST['body']  ?? 'Notification de test');

    $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = $pdo->prepare('SELECT token FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC');
    $st->execute([$coursierId]);
    $tokens = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'token');
    if (empty($tokens)) {
        echo json_encode(['success' => false, 'message' => 'Aucun token pour ce coursier']);
        exit;
    }

    $res = fcm_send_with_log($tokens, $title, $body, ['type' => 'admin_test', 'order_id' => $orderId], $coursierId, $orderId);
    echo json_encode(['success' => !empty($res['success']), 'details' => $res]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>