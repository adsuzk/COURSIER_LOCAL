<?php
/**
 * API POUR CONFIGURER FCM DEPUIS L'INTERFACE WEB
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$fcmKey = trim($input['fcm_key'] ?? '');

if (empty($fcmKey)) {
    echo json_encode(['success' => false, 'message' => 'Clé FCM manquante']);
    exit;
}

if (!str_starts_with($fcmKey, 'AAAA')) {
    echo json_encode(['success' => false, 'message' => 'Format de clé invalide']);
    exit;
}

try {
    // Modifier fcm_manager.php
    $fcmManagerPath = __DIR__ . '/fcm_manager.php';
    
    if (!file_exists($fcmManagerPath)) {
        throw new Exception('Fichier fcm_manager.php introuvable');
    }
    
    $content = file_get_contents($fcmManagerPath);
    
    // Remplacer la ligne de retour dans getServerKey
    $pattern = '/return getenv\(\'FCM_SERVER_KEY\'\) \?: \'LEGACY_KEY_NOT_CONFIGURED\';/';
    $replacement = "return '$fcmKey'; // Clé configurée le " . date('Y-m-d H:i:s');
    
    $newContent = preg_replace($pattern, $replacement, $content);
    
    if ($newContent === null || $newContent === $content) {
        throw new Exception('Impossible de modifier la configuration');
    }
    
    file_put_contents($fcmManagerPath, $newContent);
    
    // Test d'envoi
    require_once 'config.php';
    require_once 'fcm_manager.php';
    
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT token FROM device_tokens WHERE coursier_id = 5 AND is_active = 1 LIMIT 1");
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $testResult = 'Token non trouvé pour test';
    
    if ($tokenData) {
        $fcm = new FCMManager();
        $result = $fcm->envoyerNotification(
            $tokenData['token'],
            '🎉 FCM Configuré!',
            'Les notifications push sont maintenant actives pour Suzosky Coursier!',
            ['type' => 'fcm_configured', 'timestamp' => time()]
        );
        
        if ($result['success']) {
            $testResult = 'Notification de test envoyée avec succès!';
        } else {
            $testResult = 'Notification échouée: ' . ($result['message'] ?? 'Erreur inconnue');
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Clé FCM configurée avec succès!',
        'test_result' => $testResult,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'details' => $e->getTraceAsString()
    ]);
}
?>
