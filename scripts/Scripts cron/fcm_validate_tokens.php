<?php
/**
 * Cron: Validate active device_tokens by sending a lightweight FCM message
 * - Dry-run by default (no DB changes) unless --apply is passed
 * - Marks tokens is_active = 0 when FCM returns permanent error (NOT_REGISTERED...)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/fcm_helper.php';

$apply = in_array('--apply', $argv ?? []);
$limit = 50; // max tokens per run

try {
    $pdo = getDBConnection();

    // Fetch active tokens ordered by last_ping desc
    $stmt = $pdo->prepare("SELECT id, coursier_id, token, is_active, last_ping, updated_at FROM device_tokens WHERE is_active = 1 ORDER BY last_ping DESC, updated_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $report = ['checked' => count($tokens), 'deactivated' => 0, 'errors' => []];

    foreach ($tokens as $t) {
        $token = $t['token'];
        $id = $t['id'];
        $coursierId = $t['coursier_id'];

        // Send a minimal silent notification (data-only) to validate token
        $res = sendFCMNotificationV1($token, 'ping', ['type' => 'validation_ping', 'coursier_id' => $coursierId], ['title' => '']);

        if ($res['success']) {
            // OK
            continue;
        }

        $err = $res['error'] ?? ($res['response']['error'] ?? json_encode($res));

        if (isPermanentFcmError($err)) {
            $report['deactivated']++;
            $report['errors'][$id] = ['coursier_id' => $coursierId, 'error' => $err];

            if ($apply) {
                $u = $pdo->prepare("UPDATE device_tokens SET is_active = 0, updated_at = NOW() WHERE id = ?");
                $u->execute([$id]);
            }
        }
    }

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]) . "\n";
}

?>
