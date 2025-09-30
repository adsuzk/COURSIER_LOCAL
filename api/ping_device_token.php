<?php
// api/ping_device_token.php
// Objectif: ping ultra-léger côté app Android pour ouvrir le formulaire instantanément
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();

    // Lecture JSON ou formulaire ou GET (pour tests locaux)
    $input = [];
    $raw = file_get_contents('php://input');
    if ($raw) {
        $j = json_decode($raw, true);
        if (is_array($j)) $input = $j;
    }
    if (empty($input)) {
        $input = array_merge($_GET ?? [], $_POST ?? []);
    }

    $coursierId = (int)($input['coursier_id'] ?? 0);
    $token = trim((string)($input['token'] ?? ''));
    $platform = trim((string)($input['platform'] ?? 'android')) ?: 'android';
    $appVersion = trim((string)($input['app_version'] ?? '1.0.0')) ?: '1.0.0';

    if ($coursierId <= 0 || $token === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants (coursier_id et token requis)']);
        return;
    }

    // Assurer le schéma minimal
    try { $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
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
        last_ping DATETIME NULL,
        UNIQUE KEY uniq_token_hash (token_hash),
        INDEX idx_coursier (coursier_id),
        INDEX idx_agent (agent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN token_hash CHAR(64) NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE device_tokens SET token_hash = SHA2(token,256) WHERE token_hash IS NULL OR token_hash = ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token_hash CHAR(64) NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD UNIQUE KEY uniq_token_hash (token_hash)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN last_ping DATETIME NULL"); } catch (Throwable $e) {}

    $hash = hash('sha256', $token);

    // Upsert "ping": réactiver le token et mettre à jour last_ping immédiatement
    $sql = "INSERT INTO device_tokens (coursier_id, token, token_hash, device_type, platform, app_version, is_active, created_at, updated_at, last_used, last_ping)
            VALUES (:cid, :tok, :th, 'mobile', :platform, :appv, 1, NOW(), NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE coursier_id = VALUES(coursier_id), token = VALUES(token), device_type = VALUES(device_type), platform = VALUES(platform), app_version = VALUES(app_version), is_active = 1, updated_at = NOW(), last_used = NOW(), last_ping = NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'cid' => $coursierId,
        'tok' => $token,
        'th'  => $hash,
        'platform' => $platform,
        'appv' => $appVersion,
    ]);

    // Optionnel: marquer le coursier en_ligne si table présente
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS agents_suzosky (
            id INT PRIMARY KEY,
            statut_connexion VARCHAR(32) NULL,
            current_session_token VARCHAR(255) NULL,
            last_login_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $s = $pdo->prepare("UPDATE agents_suzosky SET statut_connexion='en_ligne', last_login_at = COALESCE(last_login_at, NOW()) WHERE id = ?");
        $s->execute([$coursierId]);
    } catch (Throwable $e) { /* best-effort */ }

    echo json_encode([
        'success' => true,
        'message' => 'Ping enregistré',
        'coursier_id' => $coursierId,
        'token_preview' => substr($token, 0, 24) . '…',
        'fresh_window_seconds' => (int)(getenv('FCM_AVAILABILITY_THRESHOLD_SECONDS') ?: 60),
        'server_time' => date('c')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
