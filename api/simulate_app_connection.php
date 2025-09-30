<?php
// api/simulate_app_connection.php
// Simulateur de connexion/déconnexion coursier pour tests
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$action = $_GET['action'] ?? 'connect';
$coursierId = (int)($_GET['coursier_id'] ?? 5); // ZALLE par défaut

try {
    $pdo = getDBConnection();
    
    if ($action === 'connect') {
        // Simule onNewToken + ping immédiat
        $newToken = 'app_token_' . time() . '_' . $coursierId;
        $hash = hash('sha256', $newToken);
        
        // Désactiver anciens tokens
        $pdo->prepare('UPDATE device_tokens SET is_active = 0 WHERE coursier_id = ?')->execute([$coursierId]);
        
        // Créer nouveau token avec last_ping récent
        $sql = "INSERT INTO device_tokens (coursier_id, token, token_hash, device_type, platform, app_version, is_active, created_at, updated_at, last_used, last_ping)
                VALUES (?, ?, ?, 'mobile', 'android', '1.0.0-real', 1, NOW(), NOW(), NOW(), NOW())";
        $pdo->prepare($sql)->execute([$coursierId, $newToken, $hash]);
        
        // Marquer coursier en ligne
        $pdo->prepare("UPDATE agents_suzosky SET statut_connexion='en_ligne', last_login_at=NOW() WHERE id = ?")->execute([$coursierId]);
        
        echo json_encode([
            'success' => true,
            'action' => 'connect',
            'message' => 'Coursier connecté',
            'coursier_id' => $coursierId,
            'token_preview' => substr($newToken, 0, 20) . '...',
            'timestamp' => date('c')
        ]);
        
    } elseif ($action === 'disconnect') {
        // Simule logout app
        $pdo->prepare('UPDATE device_tokens SET is_active = 0 WHERE coursier_id = ?')->execute([$coursierId]);
        $pdo->prepare("UPDATE agents_suzosky SET statut_connexion='hors_ligne' WHERE id = ?")->execute([$coursierId]);
        
        echo json_encode([
            'success' => true,
            'action' => 'disconnect',
            'message' => 'Coursier déconnecté',
            'coursier_id' => $coursierId,
            'timestamp' => date('c')
        ]);
        
    } elseif ($action === 'ping') {
        // Simule ping périodique app
        $sql = "UPDATE device_tokens SET last_ping = NOW(), updated_at = NOW() WHERE coursier_id = ? AND is_active = 1";
        $updated = $pdo->prepare($sql)->execute([$coursierId]);
        
        echo json_encode([
            'success' => $updated,
            'action' => 'ping',
            'message' => $updated ? 'Ping envoyé' : 'Aucun token actif à pinger',
            'coursier_id' => $coursierId,
            'timestamp' => date('c')
        ]);
        
    } else {
        echo json_encode(['success' => false, 'error' => "Action '$action' non reconnue"]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>