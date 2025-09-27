<?php
/**
 * ============================================================================
 * 🔔 API SYNCHRONISATION TOKENS FCM - COURSIER_LOCAL
 * ============================================================================
 * 
 * API pour la gestion des tokens FCM des coursiers
 * Synchronisation, validation, heartbeat
 * 
 * @version 1.0.0
 * @author Équipe Suzosky
 * @date 25 septembre 2025
 * ============================================================================
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/SystemSync.php';

try {
    $pdo = getPDO();
    
    // Récupérer les données POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Mode GET pour vérification rapide
        $agentId = $_GET['agent_id'] ?? null;
        
        if ($agentId) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as token_count,
                       MAX(last_used) as last_activity
                FROM agent_tokens 
                WHERE agent_id = ? AND is_active = 1
            ");
            $stmt->execute([$agentId]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'agent_id' => $agentId,
                'active_tokens' => $info['token_count'],
                'last_activity' => $info['last_activity']
            ]);
            exit;
        }
        
        throw new Exception('Paramètres requis manquants');
    }
    
    $agentId = $input['agent_id'] ?? null;
    $deviceId = $input['device_id'] ?? null;
    $token = $input['fcm_token'] ?? $input['token'] ?? null;
    
    if (!$agentId || !$deviceId || !$token) {
        throw new Exception('agent_id, device_id et token sont requis');
    }
    
    // Vérifier que l'agent existe
    $stmt = $pdo->prepare("SELECT id, nom FROM agents_suzosky WHERE id = ?");
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$agent) {
        throw new Exception('Agent introuvable');
    }
    
    // Insérer ou mettre à jour le token
    $stmt = $pdo->prepare("
        INSERT INTO agent_tokens (agent_id, device_id, token, device_type, last_used)
        VALUES (?, ?, ?, 'android', NOW())
        ON DUPLICATE KEY UPDATE 
            token = VALUES(token),
            is_active = 1,
            last_used = NOW(),
            updated_at = NOW()
    ");
    
    $stmt->execute([$agentId, $deviceId, $token]);
    
    // Mettre à jour le heartbeat de l'agent
    $stmt = $pdo->prepare("
        UPDATE agents_suzosky 
        SET last_heartbeat_at = NOW(),
            last_seen = NOW(),
            is_online = 1
        WHERE id = ?
    ");
    $stmt->execute([$agentId]);
    
    // Calculer les métriques
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tokens,
            COUNT(DISTINCT agent_id) as unique_agents,
            AVG(TIMESTAMPDIFF(SECOND, last_used, NOW())) as avg_last_used_seconds
        FROM agent_tokens 
        WHERE is_active = 1
    ");
    $stmt->execute();
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Vérifier la validité du token (basique)
    $tokenValid = strlen($token) > 100 && strpos($token, ':') !== false;
    
    // Calculer le heartbeat
    $stmt = $pdo->prepare("
        SELECT TIMESTAMPDIFF(SECOND, last_heartbeat_at, NOW()) as heartbeat_seconds
        FROM agents_suzosky 
        WHERE id = ?
    ");
    $stmt->execute([$agentId]);
    $heartbeatSeconds = $stmt->fetchColumn() ?: 0;
    
    // Qualité de synchronisation
    $syncQuality = 'excellent';
    if ($heartbeatSeconds > 300) $syncQuality = 'good';
    if ($heartbeatSeconds > 900) $syncQuality = 'fair';
    if ($heartbeatSeconds > 1800) $syncQuality = 'poor';
    
    $response = [
        'success' => true,
        'message' => 'Token synchronisé avec succès',
        'agent' => [
            'id' => $agent['id'],
            'nom' => $agent['nom']
        ],
        'token_valid' => $tokenValid,
        'heartbeat_seconds' => $heartbeatSeconds,
        'sync_quality' => $syncQuality,
        'metrics' => [
            'total_tokens' => (int) $metrics['total_tokens'],
            'unique_tokens' => (int) $metrics['unique_agents'],
            'avg_last_used' => round($metrics['avg_last_used_seconds'], 2)
        ],
        'timestamp' => date('c')
    ];
    
    SystemSync::record('fcm_sync', $syncQuality === 'poor' ? 'warning' : 'ok', [
        'agent_id' => (int) $agent['id'],
        'device_id' => $deviceId,
        'token_valid' => $tokenValid,
        'heartbeat_seconds' => (int) $heartbeatSeconds,
        'sync_quality' => $syncQuality,
        'metrics' => $response['metrics'],
    ]);

    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    SystemSync::record('fcm_sync', 'error', [
        'error' => $e->getMessage(),
        'agent_id' => $agentId ?? null,
        'device_id' => $deviceId ?? null,
    ]);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>