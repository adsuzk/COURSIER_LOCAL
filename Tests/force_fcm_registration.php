<?php
/**
 * API Test pour enregistrement token FCM direct via ADB
 * Utilisation: http://localhost/COURSIER_LOCAL/Tests/force_fcm_registration.php?coursier_id=X&token=Y
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
    
    $coursier_id = $_GET['coursier_id'] ?? $_POST['coursier_id'] ?? null;
    $fcm_token = $_GET['token'] ?? $_POST['token'] ?? null;
    
    if (!$coursier_id || !$fcm_token) {
        echo json_encode([
            'success' => false,
            'message' => 'Paramètres manquants: coursier_id et token requis',
            'usage' => 'force_fcm_registration.php?coursier_id=X&token=Y'
        ]);
        exit;
    }
    
    // Générer hash du token
    $token_hash = hash('sha256', $fcm_token);
    
    // Supprimer les anciens tokens pour ce coursier
    $stmt = $pdo->prepare("DELETE FROM device_tokens WHERE coursier_id = ? OR agent_id = ?");
    $stmt->execute([$coursier_id, $coursier_id]);
    
    // Insérer le nouveau token
    $stmt = $pdo->prepare("
        INSERT INTO device_tokens 
        (coursier_id, token, token_hash, device_type, platform, app_version, is_active, last_ping, created_at, updated_at)
        VALUES (?, ?, ?, 'mobile', 'android', '7.0.0', 1, NOW(), NOW(), NOW())
    ");
    
    $result = $stmt->execute([$coursier_id, $fcm_token, $token_hash]);
    
    if ($result) {
        // Test immédiat de notification
        require_once __DIR__ . '/../api/lib/fcm_enhanced.php';
        $notif_result = fcm_send_with_log(
            [$fcm_token],
            '🎯 Token FCM Enregistré!',
            'Le token FCM a été enregistré avec succès. Test de notification.',
            [
                'type' => 'token_registration_success',
                'coursier_id' => (string)$coursier_id,
                'timestamp' => (string)time()
            ],
            $coursier_id
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Token FCM enregistré et notification envoyée',
            'data' => [
                'coursier_id' => $coursier_id,
                'token_preview' => substr($fcm_token, 0, 20) . '...',
                'token_hash' => substr($token_hash, 0, 16) . '...',
                'notification_sent' => $notif_result
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement du token'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>