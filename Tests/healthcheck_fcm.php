<?php
// healthcheck_fcm.php — Vérifie accès HTTP v1 FCM (option validate_only) et renvoie un JSON compact
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/lib/fcm_enhanced.php';

$pdo = null; $err = null; $res = null;
try { $pdo = getDBConnection(); } catch (Throwable $e) { $err = 'DB: '.$e->getMessage(); }

$token = null;
if ($pdo) {
  try {
    $stmt = $pdo->query("SELECT token FROM device_tokens WHERE coursier_id=6 ORDER BY updated_at DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) $token = $row['token'];
  } catch (Throwable $e) { $err = 'Token: '.$e->getMessage(); }
}

if (!$token) {
  echo json_encode(['ok'=>false,'stage'=>'token','error'=>$err ?: 'Aucun token']);
  exit;}

// Activer validate_only sans toucher l'environnement global
$old = getenv('FCM_VALIDATE_ONLY');
putenv('FCM_VALIDATE_ONLY=1');
$res = fcm_send_with_log([$token], 'HC', 'healthcheck', [ 'type'=>'health', '_data_only'=>true, 'ts'=>time() ], 6, 'HEALTHCHECK');
// Restaurer
if ($old === false) putenv('FCM_VALIDATE_ONLY'); else putenv('FCM_VALIDATE_ONLY='.$old);

$out = [
  'ok' => !empty($res['success']),
  'method' => $res['method'] ?? 'unknown',
  'validate_only' => true,
  'http_codes' => array_map(function($r){ return $r['code'] ?? null; }, $res['result'] ?? []),
  'ts' => date('c')
];
if (empty($res['success'])) $out['raw'] = $res;

echo json_encode($out, JSON_UNESCAPED_UNICODE);
