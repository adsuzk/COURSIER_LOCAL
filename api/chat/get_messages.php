<?php
/**
 * Récupère les messages d'une conversation
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../logger.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$conversationId = (int)($data['conversation_id'] ?? 0);
if (!$conversationId) {
    echo json_encode(['success' => false, 'message' => 'Conversation ID requis']);
    exit;
}

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, sender_type, sender_id, message, timestamp FROM chat_messages WHERE conversation_id = ? ORDER BY timestamp ASC, id ASC");
    $stmt->execute([$conversationId]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    logMessage('chat_api.log', json_encode([
        'endpoint' => 'get_messages',
        'conversation_id' => $conversationId,
        'count' => count($msgs)
    ], JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'data' => $msgs]);
} catch (Exception $e) {
    logMessage('chat_api.log', json_encode([
        'endpoint' => 'get_messages',
        'error' => $e->getMessage(),
        'conversation_id' => $conversationId
    ], JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
