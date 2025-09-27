<?php
/**
 * Marque une conversation comme lue (réinitialise unread_count)
 */
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$conversation_id = (int)($data['conversation_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['success' => false, 'message' => 'Conversation ID requis']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('UPDATE chat_conversations SET unread_count = 0 WHERE id = ?');
    $stmt->execute([$conversation_id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
