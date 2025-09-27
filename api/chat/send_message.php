<?php
/**
 * Envoie un message et met à jour la conversation
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
$conversation_id = (int)($data['conversation_id'] ?? 0);
$sender_type = $data['sender_type'] ?? 'client';
$sender_id = (int)($data['sender_id'] ?? 0);
$message = trim($data['message'] ?? '');

if (!$conversation_id || !$sender_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'Informations manquantes']);
    exit;
}

try {
    $pdo = getDBConnection();
    // Insérer message
    $stmt = $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_type, sender_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$conversation_id, $sender_type, $sender_id, $message]);
    // Mettre à jour meta conversation
    $now = date('Y-m-d H:i:s');
    if ($sender_type === 'client') {
        // Messages clients augmentent le compteur non lus côté admin
        $stmt = $pdo->prepare("UPDATE chat_conversations SET last_message = ?, last_timestamp = ?, unread_count = unread_count + 1 WHERE id = ?");
        $stmt->execute([$message, $now, $conversation_id]);
    } else {
        // Messages admin n'augmentent pas le compteur côté admin
        $stmt = $pdo->prepare("UPDATE chat_conversations SET last_message = ?, last_timestamp = ? WHERE id = ?");
        $stmt->execute([$message, $now, $conversation_id]);
    }

    // Log succès envoi
    logMessage('chat_api.log', json_encode([
        'endpoint' => 'send_message',
        'conversation_id' => $conversation_id,
        'sender_type' => $sender_type,
        'sender_id' => $sender_id,
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ], JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    logMessage('chat_api.log', json_encode([
        'endpoint' => 'send_message',
        'error' => $e->getMessage(),
        'conversation_id' => $conversation_id
    ], JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
