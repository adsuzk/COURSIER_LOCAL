<?php
require_once __DIR__ . '/../config.php';
try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    echo json_encode(['error' => 'DB connection failed', 'message' => $e->getMessage()]);
    exit(1);
}
$out = [];
$courierId = 5;
try {
    $st = $pdo->prepare("SELECT * FROM agents_suzosky WHERE id = ? LIMIT 1");
    $st->execute([$courierId]);
    $out['agent'] = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $out['agent_error'] = $e->getMessage();
}
try {
    $st = $pdo->prepare("SELECT id, coursier_id, agent_id, token, LEFT(token,60) as preview, is_active, updated_at FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC");
    $st->execute([$courierId]);
    $out['tokens'] = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $out['tokens_error'] = $e->getMessage();
}
echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
