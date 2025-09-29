<?php
/**
 * init_last_ping_values.php
 * - Initialize last_ping = NOW() for rows where last_ping IS NULL
 * Usage: php init_last_ping_values.php
 */
require_once __DIR__ . '/../../config.php';
try {
    $pdo = getPDO();
    // Count before
    $before = (int)$pdo->query("SELECT COUNT(*) FROM device_tokens WHERE last_ping IS NULL")->fetchColumn();
    echo "Rows with last_ping IS NULL before: {$before}\n";

    if ($before === 0) {
        echo "No rows to update.\n";
        exit(0);
    }

    $affected = $pdo->exec("UPDATE device_tokens SET last_ping = NOW() WHERE last_ping IS NULL");
    echo "Updated rows: {$affected}\n";

    $after = (int)$pdo->query("SELECT COUNT(*) FROM device_tokens WHERE last_ping IS NULL")->fetchColumn();
    echo "Rows with last_ping IS NULL after: {$after}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
