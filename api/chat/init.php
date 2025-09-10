<?php
/**
 * Initialise ou récupère une conversation de chat
 */
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$type = $data['type'] ?? 'particulier';
$client_id = (int)($data['client_id'] ?? 0);

if (!$client_id) {
    echo json_encode(['success' => false, 'message' => 'Client ID requis']);
    exit;
}

try {
    $pdo = getDBConnection();
    // Cherche conversation existante
    $stmt = $pdo->prepare("SELECT id FROM chat_conversations WHERE client_id = ? AND type = ?");
    $stmt->execute([$client_id, $type]);
    $convId = $stmt->fetchColumn();

    if (!$convId) {
        // Crée nouvelle conversation
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO chat_conversations (type, client_id, last_message, last_timestamp) VALUES (?, ?, '', ?)");
        $stmt->execute([$type, $client_id, $now]);
        $convId = $pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'conversation_id' => (int)$convId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
