<?php
// api/register_device_token.php - Enregistre le token FCM d'un coursier
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
    
    // Gérer les données JSON ET POST
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $coursierId = intval($input['coursier_id'] ?? $_POST['coursier_id'] ?? 0);
    $agentId = intval($input['agent_id'] ?? $_POST['agent_id'] ?? 0); // support futur unifié
    $token = trim($input['token'] ?? $_POST['token'] ?? '');
    
    // Log pour débogage
    error_log("FCM Registration - Coursier: $coursierId, Agent: $agentId, Token: " . substr($token, 0, 20) . "...");
    
    if ($coursierId <= 0 || $token === '') {
        echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
        exit;
    }
    // Assurer un schéma robuste: token en TEXT et contrainte d'unicité via hash (évite la troncature et problèmes d'index)
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coursier_id INT NOT NULL,
        agent_id INT NULL,
        token TEXT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_token_hash (token_hash),
        KEY idx_coursier (coursier_id),
        KEY idx_agent (agent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migration douce sur table existante
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token TEXT NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER token"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE device_tokens SET token_hash = SHA2(token,256) WHERE token_hash IS NULL OR token_hash = ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token_hash CHAR(64) NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD UNIQUE KEY uniq_token_hash (token_hash)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN agent_id INT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD KEY idx_agent (agent_id)"); } catch (Throwable $e) {}

    $hash = hash('sha256', $token);
    // Stratégie: si agent_id fourni (>0) le stocker aussi
    if ($agentId > 0) {
        $stmt = $pdo->prepare("INSERT INTO device_tokens (coursier_id, agent_id, token, token_hash) VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE coursier_id = VALUES(coursier_id), agent_id = VALUES(agent_id), token = VALUES(token), updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$coursierId, $agentId, $token, $hash]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO device_tokens (coursier_id, token, token_hash) VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE coursier_id = VALUES(coursier_id), token = VALUES(token), updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$coursierId, $token, $hash]);
    }
    echo json_encode([
        'success' => true,
        'message' => 'Token enregistré',
        'data' => [
            'coursier_id' => $coursierId,
            'token' => $token,
            'agent_id' => $agentId > 0 ? $agentId : null,
            'updated_at' => date('c')
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
