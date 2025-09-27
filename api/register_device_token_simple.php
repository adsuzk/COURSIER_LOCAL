<?php
// api/register_device_token_simple.php - Version simplifiée compatible structures variables
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

    // Déterminer la clé primaire exacte de la table coursiers
    $coursierColumns = [];
    $res = $pdo->query('SHOW COLUMNS FROM coursiers');
    foreach ($res as $col) {
        $coursierColumns[$col['Field']] = $col;
    }
    $coursierIdColumn = findColumn($coursierColumns, ['id', 'coursier_id', 'id_coursier', 'courier_id']);
    if (!$coursierIdColumn) {
        throw new Exception('Structure coursiers incompatible : colonne ID introuvable');
    }

    $verifyCoursier = $pdo->prepare(sprintf('SELECT `%s` FROM coursiers WHERE `%s` = ? LIMIT 1', $coursierIdColumn, $coursierIdColumn));
    $verifyCoursier->execute([$coursierId]);
    if (!$verifyCoursier->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Coursier non trouvé']);
        return;
    }

    // Garantir l'existence de la table device_tokens
    $hasDeviceTokens = (bool)$pdo->query("SHOW TABLES LIKE 'device_tokens'")->fetchColumn();
    if (!$hasDeviceTokens) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coursier_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            platform VARCHAR(32) DEFAULT 'android',
            app_version VARCHAR(64) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            agent_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_coursier (coursier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Lire les colonnes effectives de device_tokens
    $tokenColumns = [];
    $res = $pdo->query('SHOW COLUMNS FROM device_tokens');
    foreach ($res as $col) {
        $tokenColumns[$col['Field']] = $col;
    }

    $tokenCoursierCol = findColumn($tokenColumns, ['coursier_id', 'id_coursier', 'courier_id']);
    $tokenValueCol = findColumn($tokenColumns, ['token', 'device_token', 'fcm_token', 'firebase_token']);
    if (!$tokenCoursierCol || !$tokenValueCol) {
        throw new Exception('Structure device_tokens incompatible : colonnes coursier/token manquantes');
    }

    $platformCol = findColumn($tokenColumns, ['platform', 'device_type', 'type']);
    $appVersionCol = findColumn($tokenColumns, ['app_version', 'version_app', 'version']);
    $isActiveCol = findColumn($tokenColumns, ['is_active', 'active']);
    $agentCol = $agentId > 0 ? findColumn($tokenColumns, ['agent_id', 'utilisateur_id', 'user_id']) : null;
    $createdCol = findColumn($tokenColumns, ['created_at', 'date_creation']);
    $updatedCol = findColumn($tokenColumns, ['updated_at', 'date_modification', 'updated']);
    $lastUsedCol = findColumn($tokenColumns, ['last_used', 'last_use', 'dernier_usage']);

    // Supprimer les anciens tokens pour ce coursier
    $deleteSql = sprintf('DELETE FROM device_tokens WHERE `%s` = ?', $tokenCoursierCol);
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute([$coursierId]);

    // Construire l'INSERT dynamique en fonction des colonnes disponibles
    $components = [
        ['field' => sprintf('`%s`', $tokenCoursierCol), 'param' => 'coursier', 'value' => $coursierId],
        ['field' => sprintf('`%s`', $tokenValueCol), 'param' => 'token', 'value' => $token]
    ];

    if ($platformCol) {
        $components[] = ['field' => sprintf('`%s`', $platformCol), 'param' => 'platform', 'value' => $platform];
    }
    if ($appVersionCol) {
        $components[] = ['field' => sprintf('`%s`', $appVersionCol), 'param' => 'app_version', 'value' => $appVersion];
    }
    if ($isActiveCol) {
        $components[] = ['field' => sprintf('`%s`', $isActiveCol), 'param' => 'is_active', 'value' => 1];
    }
    if ($agentCol) {
        $components[] = ['field' => sprintf('`%s`', $agentCol), 'param' => 'agent_id', 'value' => $agentId];
    }

    $fieldsSql = [];
    $placeholdersSql = [];
    $params = [];
    foreach ($components as $component) {
        $fieldsSql[] = $component['field'];
        $placeholdersSql[] = ':' . $component['param'];
        $params[$component['param']] = $component['value'];
    }

    $insertSql = sprintf(
        'INSERT INTO device_tokens (%s) VALUES (%s)',
        implode(', ', $fieldsSql),
        implode(', ', $placeholdersSql)
    );

    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute($params);
    $tokenId = (int)$pdo->lastInsertId();

    // Mettre à jour les colonnes temporelles si présentes
    $timestampSets = [];
    if ($createdCol) {
        $timestampSets[] = sprintf('`%s` = NOW()', $createdCol);
    }
    if ($updatedCol) {
        $timestampSets[] = sprintf('`%s` = NOW()', $updatedCol);
    }
    if ($lastUsedCol) {
        $timestampSets[] = sprintf('`%s` = NOW()', $lastUsedCol);
    }
    if ($timestampSets) {
        $updateSql = sprintf('UPDATE device_tokens SET %s WHERE id = ?', implode(', ', $timestampSets));
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$tokenId]);
    }

    echo json_encode([
        'success' => true,
        'token_id' => $tokenId,
        'coursier_id' => $coursierId,
        'agent_id' => $agentId ?: null,
        'message' => 'Token enregistré avec succès'
    ]);
} catch (Throwable $e) {
    error_log('FCM Registration Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}