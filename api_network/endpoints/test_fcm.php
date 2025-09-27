<?php
/**
 * API NETWORK - TEST FCM SERVICE
 * Test micro-service pour Firebase Cloud Messaging
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../fcm_manager.php';

header('Content-Type: application/json; charset=utf-8');

function testFCMService() {
    try {
        // Test FCM Manager
        $fcm = new FCMManager();
        
        // Vérifier tokens actifs
        $pdo = getDBConnection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM device_tokens WHERE is_active = 1');
        $activeTokens = $stmt->fetchColumn();
        
        // Test structure table device_tokens
        $stmt = $pdo->query('DESCRIBE device_tokens');
        $tableStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'service' => 'fcm_notifications',
            'status' => 'online', 
            'data' => [
                'fcm_manager_loaded' => class_exists('FCMManager'),
                'active_tokens' => $activeTokens,
                'table_structure_ok' => count($tableStructure) > 0,
                'config_file' => file_exists('coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'service' => 'fcm_notifications',
            'status' => 'error',
            'error' => $e->getMessage(), 
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

echo json_encode(testFCMService(), JSON_PRETTY_PRINT);
?>