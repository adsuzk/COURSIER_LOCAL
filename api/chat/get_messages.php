<?php
/**
 * Récupère les messages d'une conversation
 */
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$conversationId = (int)($data['conversation_id'] ?? 0);
type:
if (!$conversationId) {
    echo json_encode(['success' => false, 'message' => 'Conversation ID requis']);
    exit;
}

try {
    $pdo = getDBConnection();
    switch ($data['type'] ?? 'particulier') {
        case 'business':
            $tbl = 'business_conversations'; break;
        case 'agents':
            $tbl = 'agent_conversations'; break;
        default:
            $tbl = 'client_conversations';
    }
    $stmt = $pdo->prepare("SELECT sender_type, message, timestamp FROM $tbl WHERE conversation_id = ? ORDER BY timestamp ASC");
    $stmt->execute([$conversationId]);
    $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $msgs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
