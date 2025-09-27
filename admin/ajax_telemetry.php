<?php
// admin/ajax_telemetry.php
// AJAX endpoint pour télémétrie: détails appareil + signalement

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'db_connect_failed']);
    exit;
}

if ($method === 'GET' && $action === 'device_details') {
    $deviceId = $_GET['device_id'] ?? '';
    if (!$deviceId) { echo json_encode(['success'=>false,'error'=>'missing_device_id']); exit; }

    // Détails appareil
    $stmt = $pdo->prepare("SELECT device_id, device_brand, device_model, android_version, app_version_name, app_version_code, last_seen, total_sessions FROM app_devices WHERE device_id=? LIMIT 1");
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Localisation via dernier event avec lat/lng dans JSON
    $locStmt = $pdo->prepare("SELECT event_data, occurred_at FROM app_events WHERE device_id=? AND JSON_EXTRACT(event_data, '$.lat') IS NOT NULL ORDER BY occurred_at DESC LIMIT 1");
    $locStmt->execute([$deviceId]);
    $locRow = $locStmt->fetch(PDO::FETCH_ASSOC);
    $location = null;
    if ($locRow) {
        $jd = json_decode($locRow['event_data'], true);
        if (isset($jd['lat'], $jd['lng'])) {
            $location = ['lat'=>(float)$jd['lat'],'lng'=>(float)$jd['lng'],'when'=>$locRow['occurred_at']];
        }
    }

    // Crashes récents
    $crStmt = $pdo->prepare("SELECT crash_type, exception_class, exception_message, app_version_code, last_occurred FROM app_crashes WHERE device_id=? ORDER BY last_occurred DESC LIMIT 20");
    $crStmt->execute([$deviceId]);
    $crashes = $crStmt->fetchAll(PDO::FETCH_ASSOC);

    // Sessions récentes
    $seStmt = $pdo->prepare("SELECT started_at, ended_at, duration_seconds, crashed FROM app_sessions WHERE device_id=? ORDER BY started_at DESC LIMIT 20");
    $seStmt->execute([$deviceId]);
    $sessions = $seStmt->fetchAll(PDO::FETCH_ASSOC);

    // Événements récents
    $evStmt = $pdo->prepare("SELECT event_type, event_name, occurred_at FROM app_events WHERE device_id=? ORDER BY occurred_at DESC LIMIT 20");
    $evStmt->execute([$deviceId]);
    $events = $evStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'device'=>$device,'location'=>$location,'crashes'=>$crashes,'sessions'=>$sessions,'events'=>$events]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $input['action'] ?? $action;

    if ($action === 'mark_issue') {
        $deviceId = $input['device_id'] ?? '';
        $message = trim($input['message'] ?? '');
        if (!$deviceId) { echo json_encode(['success'=>false,'error'=>'missing_device_id']); exit; }

        // On enregistre un crash synthétique de type REPORT issu de l'admin, pour faire remonter l'alerte
        $stmt = $pdo->prepare("INSERT INTO app_crashes (device_id, crash_hash, app_version_code, crash_type, exception_class, exception_message, is_resolved) VALUES (?, ?, ?, 'REPORT', 'AdminReport', ?, 0)");
        $hash = substr(sha1($deviceId.':ADMIN_REPORT:'.microtime(true)),0,32);
        // Version inconnue: prendre celle de l'appareil si possible
        $stmtVc = $pdo->prepare("SELECT app_version_code FROM app_devices WHERE device_id=? LIMIT 1");
        $stmtVc->execute([$deviceId]);
        $vc = (int)($stmtVc->fetchColumn() ?: 0);
        $stmt->execute([$deviceId, $hash, $vc, $message ?: 'Application signalée comme ne fonctionnant pas']);
        echo json_encode(['success'=>true]);
        exit;
    }

    if ($action === 'resolve_issues') {
        $deviceId = $input['device_id'] ?? '';
        if (!$deviceId) { echo json_encode(['success'=>false,'error'=>'missing_device_id']); exit; }
        // Marquer comme résolus les incidents récents pour cet appareil
        $stmt = $pdo->prepare("UPDATE app_crashes SET is_resolved=1 WHERE device_id=? AND is_resolved=0");
        $stmt->execute([$deviceId]);
        echo json_encode(['success'=>true]);
        exit;
    }
}

echo json_encode(['success'=>false,'error'=>'invalid_request']);
