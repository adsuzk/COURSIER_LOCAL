<?php
// ping_coursier.php - Endpoint léger pour que l'app coursier (heartbeat) signale qu'elle est connectée
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
if ($agentId <= 0) {
    echo json_encode(['success' => false, 'error' => 'MISSING_AGENT_ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    // Vérifier existence agent
    $stmt = $pdo->prepare('SELECT id, status FROM agents_suzosky WHERE id = ?');
    $stmt->execute([$agentId]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$agent) {
        echo json_encode(['success' => false, 'error' => 'AGENT_NOT_FOUND']);
        exit;
    }
    // Marquer heartbeat (ajout colonne si absente)
    $cols = $pdo->query("SHOW COLUMNS FROM agents_suzosky")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('last_heartbeat_at', $cols, true)) {
        try { $pdo->exec("ALTER TABLE agents_suzosky ADD COLUMN last_heartbeat_at TIMESTAMP NULL DEFAULT NULL"); } catch (Throwable $e) {}
    }
    $upd = $pdo->prepare('UPDATE agents_suzosky SET last_heartbeat_at = NOW() WHERE id = ?');
    $upd->execute([$agentId]);
    echo json_encode([
        'success' => true,
        'agent_id' => $agentId,
        'status' => $agent['status'],
        'server_time' => date('c')
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'SERVER_ERROR', 'message' => $e->getMessage()]);
}
?>