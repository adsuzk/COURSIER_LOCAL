<?php
/**
 * Script: fcm_auto_cleanup.php
 * Purpose: Cron job to deactivate stale/orphan FCM device tokens.
 * - Deactivates tokens with is_active = 1 and last_ping older than threshold (minutes)
 * - Skips emergency/debug/local_test tokens
 * - Supports --dry-run and --verbose CLI flags
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config.php';

$argv = isset($_SERVER['argv']) ? $_SERVER['argv'] : array();
$hasApplyFlag = in_array('--apply', $argv, true) || in_array('--no-dry-run', $argv, true);
$isDryRun = ! $hasApplyFlag;
// Autoriser explicitement le dry-run avec --dry-run / -n (même si c'est le mode par défaut)
if (!$isDryRun) {
    $isDryRun = in_array('--dry-run', $argv, true) || in_array('-n', $argv, true);
}
$isVerbose = in_array('--verbose', $argv, true) || in_array('-v', $argv, true);

// Threshold in minutes (default 1). Peut être surchargé par l'env FCM_CLEANUP_THRESHOLD_MIN
// NOTE: réduit à 1 minute pour cohérence UX
$envVal = getenv('FCM_CLEANUP_THRESHOLD_MIN');
$staleMinutes = (int)(($envVal !== false && $envVal !== '') ? $envVal : 1);

// Log file
$logDir = __DIR__ . '/../../diagnostic_logs';
if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
$logFile = $logDir . '/fcm_auto_cleanup.log';

function clog($msg) {
    global $logFile, $isVerbose;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    if ($isVerbose) echo $line;
}

try {
    $pdo = getPDO();

        // Find stale tokens
        // Detect whether the table has a 'last_ping' column; fall back to 'updated_at' if absent.
        $colStmt = $pdo->prepare("SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'device_tokens' AND COLUMN_NAME = :col");
        $colStmt->execute([':col' => 'last_ping']);
        $hasLastPing = ($colStmt->fetchColumn(0) > 0);
        $timeCol = $hasLastPing ? 'last_ping' : 'updated_at';

        $sql = "SELECT id, token, coursier_id, agent_id, updated_at, {$timeCol} as time_col
                        FROM device_tokens
                        WHERE is_active = 1
                            AND ({$timeCol} IS NULL OR {$timeCol} < DATE_SUB(NOW(), INTERVAL :mins MINUTE))
                            AND token NOT LIKE 'emergency_%' AND token NOT LIKE 'debug_%' AND token NOT LIKE 'local_test_%'
                        LIMIT 1000";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':mins' => $staleMinutes]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($rows);
    $modeLabel = $isDryRun ? 'DRY RUN' : 'APPLY';
    clog("Found {$count} stale active token(s) older than {$staleMinutes} minutes ({$modeLabel})");

    if ($count === 0) {
        exit(0);
    }

    $ids = array_map(function($r) { return (int)$r['id']; }, $rows);

    if ($isDryRun) {
        foreach ($rows as $r) {
            $coursier_id = isset($r['coursier_id']) ? $r['coursier_id'] : 'NULL';
            $agent_id = isset($r['agent_id']) ? $r['agent_id'] : 'NULL';
            $updated_at = isset($r['updated_at']) ? $r['updated_at'] : 'NULL';
            $last_ping = isset($r['last_ping']) ? $r['last_ping'] : 'NULL';
            echo sprintf("DRY: id=%d coursier_id=%s agent_id=%s token=%s updated_at=%s last_ping=%s\n",
                $r['id'], $coursier_id, $agent_id, substr($r['token'],0,40), $updated_at, $last_ping);
        }
        exit(0);
    }

    // Apply deactivation in a single update
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $updateSql = "UPDATE device_tokens SET is_active = 0, updated_at = NOW() WHERE id IN ({$placeholders})";
    $updateStmt = $pdo->prepare($updateSql);
    $updated = $updateStmt->execute($ids);

    if ($updated) {
        clog("Deactivated {$count} token(s).");
        // Write a short report to STDOUT as well
        foreach ($rows as $r) {
            $coursier_id = isset($r['coursier_id']) ? $r['coursier_id'] : 'NULL';
            $agent_id = isset($r['agent_id']) ? $r['agent_id'] : 'NULL';
            $updated_at = isset($r['updated_at']) ? $r['updated_at'] : 'NULL';
            $last_ping = isset($r['last_ping']) ? $r['last_ping'] : 'NULL';
            echo sprintf("DEACTIVATED: id=%d coursier_id=%s agent_id=%s token=%s updated_at=%s last_ping=%s\n",
                $r['id'], $coursier_id, $agent_id, substr($r['token'],0,40), $updated_at, $last_ping);
        }
        exit(0);
    } else {
        clog('ERROR: Failed to execute update statement for deactivation.');
        exit(2);
    }

} catch (Throwable $e) {
    $msg = 'Exception: ' . $e->getMessage();
    clog($msg);
    if ($isVerbose) echo $msg . "\n";
    exit(3);
}

?>
