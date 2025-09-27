<?php
// debug_agent_auth.php - Diagnostics pour authentification coursier/agent
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$out = [
  'time' => date('c'),
  'session' => [
     'id' => session_id(),
     'vars' => array_intersect_key($_SESSION, array_flip([
        'coursier_logged_in','coursier_id','coursier_matricule','coursier_session_token'
     ]))
  ],
  'agent_row' => null,
  'auth_health' => [],
   'suggestion' => 'Utilise reset_agent_password.php pour régénérer le mot de passe CM20250001 si le login échoue',
];

try { $pdo = getDBConnection(); } catch (Throwable $e) { echo json_encode(['error'=>'DB_CONN_FAILED','detail'=>$e->getMessage()]); exit; }

// Vérifier table agents_suzosky
try {
  $hasTable = $pdo->query("SHOW TABLES LIKE 'agents_suzosky'")->rowCount()>0;
  $out['auth_health']['table_agents_suzosky'] = $hasTable;
  if ($hasTable) {
     $cols = $pdo->query("SHOW COLUMNS FROM agents_suzosky")->fetchAll(PDO::FETCH_COLUMN);
     $need = ['matricule','telephone','password'];
     $missing = array_values(array_diff($need,$cols));
     $out['auth_health']['missing_columns'] = $missing;
     $stmt = $pdo->prepare("SELECT id, matricule, telephone, current_session_token, last_login_at FROM agents_suzosky WHERE LOWER(matricule)=LOWER(?) OR telephone=? LIMIT 1");
     $stmt->execute(['CM20250001','0575584340']);
     if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $out['agent_row'] = $row;
     } else {
        $out['agent_row'] = 'Aucun agent CM20250001';
     }
  }
} catch (Throwable $e) { $out['auth_health']['error_agents_check'] = $e->getMessage(); }

echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
?>
