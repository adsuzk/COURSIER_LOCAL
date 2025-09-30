<?php
// Tests/inspect_assignment_state.php
// Quick diagnostic: checks last order, commandes_coursiers, device_tokens, agents_suzosky and tracking tables
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    echo json_encode(['error' => 'DB connection failed', 'message' => $e->getMessage()]);
    exit(1);
}

$out = [];

// 1) Last order
try {
    $stmt = $pdo->query("SELECT * FROM commandes ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    $out['last_order'] = $last ?: null;
} catch (Throwable $e) {
    $out['last_order_error'] = $e->getMessage();
}

$orderId = $last['id'] ?? null;

// 2) commandes_coursiers for that order
if ($orderId) {
    try {
        $st = $pdo->prepare("SELECT * FROM commandes_coursiers WHERE commande_id = ?");
        $st->execute([$orderId]);
        $out['commandes_coursiers'] = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $out['commandes_coursiers_error'] = $e->getMessage();
    }
}

// 3) device_tokens recent
try {
    $stmt = $pdo->query("SELECT id, coursier_id, agent_id, LEFT(token,40) as token_preview, updated_at FROM device_tokens ORDER BY updated_at DESC LIMIT 50");
    $out['device_tokens_sample'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $out['device_tokens_error'] = $e->getMessage();
}

// 4) agents_suzosky online
try {
    $stmt = $pdo->query("SELECT id, nom, telephone, statut_connexion, solde_wallet FROM agents_suzosky ORDER BY id DESC LIMIT 200");
    $out['agents_suzosky_sample'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt2 = $pdo->query("SELECT id, nom, telephone FROM agents_suzosky WHERE statut_connexion = 'en_ligne' AND (solde_wallet IS NULL OR solde_wallet > 0)");
    $out['agents_online'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $out['agents_error'] = $e->getMessage();
}

// 5) Check tracking-like tables
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'tracking%'")->fetchAll(PDO::FETCH_COLUMN);
    $out['tracking_tables'] = $tables;
    if (!empty($tables)) {
        $tbl = $tables[0];
        $stmt = $pdo->query("SELECT * FROM `".addslashes($tbl)."` ORDER BY id DESC LIMIT 50");
        $out['tracking_sample'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $out['tracking_error'] = $e->getMessage();
}

// 6) Recent FCM logs tail
$logFile = __DIR__ . '/../diagnostic_logs/detailed_fcm.log';
if (file_exists($logFile)) {
    $lines = array_slice(file($logFile), -200);
    $out['fcm_log_tail'] = implode("\n", $lines);
} else {
    $out['fcm_log_tail'] = 'no file';
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

