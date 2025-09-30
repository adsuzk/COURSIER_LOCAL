<?php
require_once __DIR__ . '/../config.php';

echo "Running DB connection check...\n";
try {
    $pdo = getDBConnection();
    echo "Connected to DB successfully.\n";
    $ver = $pdo->query('SELECT VERSION() AS v')->fetch(PDO::FETCH_ASSOC);
    echo "MySQL version: " . ($ver['v'] ?? 'unknown') . "\n";
    // Show current DB name and host from config (best-effort)
    try {
        $stmt = $pdo->query("SELECT DATABASE() AS db");
        $dbRow = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Current database: " . ($dbRow['db'] ?? 'unknown') . "\n";
    } catch (Throwable $e) {
        // ignore
    }
    // Log success
    $logDir = __DIR__ . '/../scripts/diagnostic_logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    @file_put_contents($logDir . '/db_connection_check.log', date('c') . " - OK - MySQL:" . ($ver['v'] ?? 'unknown') . "\n", FILE_APPEND);
    exit(0);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    echo "DB connection FAILED: " . $msg . "\n";
    $logDir = __DIR__ . '/../scripts/diagnostic_logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    @file_put_contents($logDir . '/db_connection_check.log', date('c') . " - FAIL - " . $msg . "\n", FILE_APPEND);
    exit(2);
}

?>
