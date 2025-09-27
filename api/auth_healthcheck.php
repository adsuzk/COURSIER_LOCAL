<?php
// auth_healthcheck.php - Diagnostic complet de l'environnement d'authentification coursier
// Objectif: afficher en un seul JSON tout ce qui peut bloquer le login de l'app
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$result = [
  'timestamp' => date('c'),
  'env' => [
     'is_production' => isProductionEnvironment(),
     'app_base_url' => function_exists('getAppBaseUrl') ? getAppBaseUrl() : null,
     'php_version' => PHP_VERSION,
  ],
  'db' => [ 'status' => 'pending' ],
  'agents_table' => null,
  'test_agent' => null,
  'session' => null,
  'recommendations' => []
];

if (session_status() === PHP_SESSION_NONE) session_start();
$result['session'] = [
  'id' => session_id(),
  'vars' => array_intersect_key($_SESSION, array_flip([
     'coursier_logged_in','coursier_id','coursier_matricule','coursier_session_token'
  ]))
];

try {
  $pdo = getDBConnection();
  $result['db']['status'] = 'ok';
} catch (Throwable $e) {
  $result['db'] = ['status' => 'error', 'message' => $e->getMessage()];
  echo json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); exit;
}

// Vérifier table agents_suzosky
try {
  $hasTable = $pdo->query("SHOW TABLES LIKE 'agents_suzosky'")->rowCount() > 0;
  if (!$hasTable) {
     $result['agents_table'] = ['exists' => false];
     $result['recommendations'][] = 'Créer/importer la table agents_suzosky (aucun agent disponible).';
  } else {
     $cols = $pdo->query("SHOW COLUMNS FROM agents_suzosky")->fetchAll(PDO::FETCH_COLUMN);
     $required = ['id','matricule','telephone'];
     $missing = array_values(array_diff($required, $cols));
     $count = (int)$pdo->query("SELECT COUNT(*) FROM agents_suzosky")->fetchColumn();
     $result['agents_table'] = [
       'exists' => true,
       'row_count' => $count,
       'missing_core_columns' => $missing,
       'has_password_col' => in_array('password',$cols,true),
       'has_plain_password_col' => in_array('plain_password',$cols,true),
       'has_current_session_token' => in_array('current_session_token',$cols,true)
     ];
     if ($count === 0) {
        $result['recommendations'][] = 'Insérer au moins un agent (ex: CM20250001) pour tester la connexion.';
     }
     // Chercher l'agent de test actif
     $stmt = $pdo->prepare("SELECT id, matricule, telephone, LENGTH(password) AS hash_len, (plain_password IS NOT NULL AND plain_password!='') AS has_plain, current_session_token, DATE_FORMAT(last_login_at, '%Y-%m-%d %H:%i:%s') AS last_login_at FROM agents_suzosky WHERE LOWER(matricule)=LOWER(?) OR telephone=? LIMIT 1");
     $stmt->execute(['CM20250001','0575584340']);
     if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result['test_agent'] = $row;
        if (empty($row['hash_len']) && empty($row['has_plain'])) {
           $result['recommendations'][] = 'Régénérer un mot de passe pour CM20250001 via reset_agent_password.php.';
        }
     } else {
        $result['test_agent'] = 'Aucun agent CM20250001';
        $result['recommendations'][] = 'Créer l’agent CM20250001 (matricule CM20250001) pour tests rapides.';
     }
  }
} catch (Throwable $e) {
  $result['agents_table'] = ['error' => $e->getMessage()];
}

// Vérifier cookies / headers potentiels (debug basique)
$result['http_request'] = [
  'method' => $_SERVER['REQUEST_METHOD'] ?? null,
  'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
  'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
  'cookie_header_present' => isset($_SERVER['HTTP_COOKIE'])
];

// Recos génériques si environnement mal détecté
if ($result['env']['is_production'] && strpos(($result['env']['app_base_url'] ?? ''), 'localhost') !== false) {
   $result['recommendations'][] = 'La détection PROD est vraie mais le host semble local: vérifier isProductionEnvironment().' ;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
