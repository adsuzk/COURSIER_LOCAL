<?php
// reset_agent_password.php - Régénère un mot de passe simple (5 chars) pour un agent matricule ou téléphone
// Usage: POST action=reset matricule=CM20250001
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'POST_REQUIRED']); exit; }
$raw = file_get_contents('php://input');
$data = json_decode($raw,true) ?: $_POST;
$mat = trim($data['matricule'] ?? ($data['identifier'] ?? ''));
if ($mat === '') { echo json_encode(['success'=>false,'error'=>'MISSING_MATRICULE']); exit; }

try { $pdo = getDBConnection(); } catch (Throwable $e) { echo json_encode(['success'=>false,'error'=>'DB_FAIL','detail'=>$e->getMessage()]); exit; }

try {
  $stmt = $pdo->prepare("SELECT id FROM agents_suzosky WHERE LOWER(matricule)=LOWER(?) OR telephone=? LIMIT 1");
  $stmt->execute([$mat,$mat]);
  $id = $stmt->fetchColumn();
  if (!$id) { echo json_encode(['success'=>false,'error'=>'AGENT_NOT_FOUND']); exit; }
  $newPlain = generateUnifiedAgentPassword(5);
  $hash = password_hash($newPlain, PASSWORD_DEFAULT);
  $upd = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = NULL, updated_at = NOW() WHERE id = ?");
  $upd->execute([$hash,$id]);
  echo json_encode(['success'=>true,'agent_id'=>(int)$id,'new_password'=>$newPlain]);
} catch (Throwable $e) {
  echo json_encode(['success'=>false,'error'=>'RESET_FAIL','detail'=>$e->getMessage()]);
}
?>
