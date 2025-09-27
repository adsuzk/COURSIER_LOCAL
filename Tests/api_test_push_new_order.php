<?php
// test_push_new_order.php - Déclenche manuellement une notification new_order pour un agent
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/fcm_enhanced.php';

$agentId = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
if ($agentId <= 0) { echo json_encode(['success'=>false,'error'=>'MISSING_AGENT_ID']); exit; }

try {
    $pdo = getDBConnection();
    // Assurer la colonne agent_id si elle n'existe pas
    $cols = $pdo->query("SHOW COLUMNS FROM device_tokens")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('agent_id', $cols)) {
        try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN agent_id INT NULL"); } catch (Throwable $e) {}
    }
    $tok = $pdo->prepare('SELECT token FROM device_tokens WHERE agent_id = ? OR coursier_id = ? ORDER BY updated_at DESC LIMIT 1');
    $tok->execute([$agentId, $agentId]);
    $row = $tok->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['success'=>false,'error'=>'NO_TOKEN']); exit; }
    $res = fcm_send_with_log([$row['token']], 'Nouvelle commande (test)', 'Simulation push pour debug', [
        'type' => 'new_order',
        'order_id' => (string)$orderId,
        '_data_only' => true,
        'debug' => '1'
    ], $agentId, $orderId ?: 'TEST_PUSH');
    echo json_encode(['success'=>!empty($res['success']), 'method'=>$res['method'] ?? null, 'details'=>$res]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'SERVER_ERROR','message'=>$e->getMessage()]);
}
?>