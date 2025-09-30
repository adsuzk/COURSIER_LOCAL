<?php
// Force token test pour bypass FCM auth error
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
    
    // Force un token factice pour le coursier 5
    $coursierId = 5;
    $testToken = 'test_token_' . time() . '_debug';
    $hash = hash('sha256', $testToken);
    
    // Désactiver les anciens tokens
    $pdo->prepare('UPDATE device_tokens SET is_active = 0 WHERE coursier_id = ?')->execute([$coursierId]);
    
    // Insérer nouveau token avec last_ping récent
    $sql = "INSERT INTO device_tokens (coursier_id, token, token_hash, device_type, platform, app_version, is_active, created_at, updated_at, last_used, last_ping)
            VALUES (?, ?, ?, 'mobile', 'android', '1.0.0-debug', 1, NOW(), NOW(), NOW(), NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coursierId, $testToken, $hash]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Token de test créé',
        'coursier_id' => $coursierId,
        'token_preview' => substr($testToken, 0, 20) . '...',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>