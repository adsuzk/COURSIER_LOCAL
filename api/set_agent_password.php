<?php
// set_agent_password.php - DEV/URGENCE : force un mot de passe exact pour un agent (matricule ou téléphone)
// Usage (GET ou POST): ?identifier=CM20250001&new_password=TONPASS
// NE PAS déployer en production sans protection supplémentaire.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$params = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) { $params = $tmp; }
    if (empty($params) && !empty($_POST)) { $params = $_POST; }
} else {
    $params = $_GET;
}

$identifier = trim($params['identifier'] ?? '');
$newPassword = (string)($params['new_password'] ?? '');
if ($identifier === '') { echo json_encode(['success'=>false,'error'=>'MISSING_IDENTIFIER']); exit; }
if ($newPassword === '') { $newPassword = 'TONPASS'; }
if (strlen($newPassword) > 64) { echo json_encode(['success'=>false,'error'=>'PASSWORD_TOO_LONG']); exit; }

try {
    $pdo = function_exists('getPDO') ? getPDO() : getDBConnection();
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'DB_CONN_FAILED','detail'=>$e->getMessage()]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM agents_suzosky WHERE LOWER(matricule)=LOWER(?) OR telephone=? LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $id = $stmt->fetchColumn();
    if (!$id) { echo json_encode(['success'=>false,'error'=>'AGENT_NOT_FOUND']); exit; }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = NULL, updated_at = NOW() WHERE id = ?");
    $upd->execute([$hash, (int)$id]);

    echo json_encode(['success'=>true,'agent_id'=>(int)$id,'fixed_password'=>$newPassword]);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>'SET_FAIL','detail'=>$e->getMessage()]);
}
