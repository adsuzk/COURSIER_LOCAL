<?php
// api/register_device_token_simple.php - Version simplifiée robuste et compatible
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/schema_utils.php';

    $pdo = getDBConnection();

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = [];
    }
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $coursierId = (int)($input['coursier_id'] ?? 0);
    $agentId = (int)($input['agent_id'] ?? 0);
    $token = trim((string)($input['token'] ?? ''));
    $platform = trim((string)($input['platform'] ?? 'android')) ?: 'android';
    $appVersion = trim((string)($input['app_version'] ?? '1.0.0')) ?: '1.0.0';

    error_log(sprintf(
        'FCM Simple Registration - Coursier: %d, Agent: %d, Token preview: %s',
        $coursierId,
        $agentId,
        $token !== '' ? substr($token, 0, 20) . '…' : '(vide)'
    ));

    if ($coursierId <= 0 || $token === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants (coursier_id ou token)']);
        return;
    }

    // Helper: teste l'existence d'une table (robuste via information_schema)
    $tableExists = function (PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1"
            );
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            // Fallback ultime: tentative SHOW TABLES (sans param bind)
            try {
                $safe = str_replace('`', '``', $table);
                $res = $pdo->query("SHOW TABLES LIKE '" . $safe . "'");
                return $res && $res->fetchColumn() !== false;
            } catch (Throwable $e2) {
                return false;
            }
        }
    };

    // Vérifier que l'ID correspond à un coursier (agents_suzosky prioritaire)
    $agentFound = false;
    if ($tableExists($pdo, 'agents_suzosky')) {
        $stmt = $pdo->prepare('SELECT id FROM agents_suzosky WHERE id = ? LIMIT 1');
        $stmt->execute([$coursierId]);
        $agentFound = (bool)$stmt->fetchColumn();
    }
    if (!$agentFound && $tableExists($pdo, 'coursiers')) {
        // fallback legacy
        $stmt = $pdo->prepare('SELECT id FROM coursiers WHERE id = ? LIMIT 1');
        $stmt->execute([$coursierId]);
        $agentFound = (bool)$stmt->fetchColumn();
    }
    if (!$agentFound) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Coursier/Agent introuvable']);
        return;
    }

    // Garantir une table device_tokens robuste (TEXT + token_hash unique + colonnes utiles)
    if (!$tableExists($pdo, 'device_tokens')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coursier_id INT NOT NULL,
            agent_id INT NULL,
            token TEXT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            device_type VARCHAR(32) DEFAULT 'mobile',
            platform VARCHAR(32) DEFAULT 'android',
            app_version VARCHAR(64) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_token_hash (token_hash),
            INDEX idx_coursier (coursier_id),
            INDEX idx_agent (agent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Migration douce vers le schéma robuste
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token TEXT NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER token"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE device_tokens SET token_hash = SHA2(token,256) WHERE token_hash IS NULL OR token_hash = ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token_hash CHAR(64) NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD UNIQUE KEY uniq_token_hash (token_hash)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN device_type VARCHAR(32) DEFAULT 'mobile'"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN platform VARCHAR(32) DEFAULT 'android'"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN app_version VARCHAR(64) NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN is_active TINYINT(1) DEFAULT 1"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN agent_id INT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD INDEX idx_agent (agent_id)"); } catch (Throwable $e) {}

    $hash = hash('sha256', $token);

    // Désactiver les anciens tokens de ce coursier (sans supprimer)
    try {
        $deact = $pdo->prepare('UPDATE device_tokens SET is_active = 0 WHERE coursier_id = ?');
        $deact->execute([$coursierId]);
    } catch (Throwable $e) { /* best-effort */ }

    // Upsert par token_hash pour éviter duplicats; réactiver si même token
    if ($agentId > 0) {
        $sql = "INSERT INTO device_tokens (coursier_id, agent_id, token, token_hash, device_type, platform, app_version, is_active, created_at, updated_at, last_used)
                VALUES (:cid, :aid, :tok, :th, 'mobile', :platform, :appv, 1, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE coursier_id = VALUES(coursier_id), agent_id = VALUES(agent_id), token = VALUES(token), device_type = VALUES(device_type), platform = VALUES(platform), app_version = VALUES(app_version), is_active = 1, updated_at = NOW(), last_used = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'cid' => $coursierId,
            'aid' => $agentId,
            'tok' => $token,
            'th'  => $hash,
            'platform' => $platform,
            'appv' => $appVersion,
        ]);
    } else {
        $sql = "INSERT INTO device_tokens (coursier_id, token, token_hash, device_type, platform, app_version, is_active, created_at, updated_at, last_used)
                VALUES (:cid, :tok, :th, 'mobile', :platform, :appv, 1, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE coursier_id = VALUES(coursier_id), token = VALUES(token), device_type = VALUES(device_type), platform = VALUES(platform), app_version = VALUES(app_version), is_active = 1, updated_at = NOW(), last_used = NOW()";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'cid' => $coursierId,
            'tok' => $token,
            'th'  => $hash,
            'platform' => $platform,
            'appv' => $appVersion,
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Token enregistré et activé',
        'coursier_id' => $coursierId,
        'agent_id' => $agentId ?: null,
        'token_preview' => substr($token, 0, 24) . '…',
        'updated_at' => date('c')
    ]);
} catch (Throwable $e) {
    error_log('FCM Registration Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
