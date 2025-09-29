<?php
/**
 * Migration: add_last_ping_to_device_tokens.php
 * - Adds `last_ping` TIMESTAMP NULL to `device_tokens` if missing
 * - Safe: checks information_schema before applying
 * Usage: php add_last_ping_to_device_tokens.php
 */

require_once __DIR__ . '/../../config.php';

try {
    $pdo = getPDO();

    // Check if column exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'device_tokens' AND COLUMN_NAME = 'last_ping'");
    $stmt->execute();
    $exists = (int)$stmt->fetchColumn(0);

    if ($exists) {
        echo "Column `last_ping` already exists in device_tokens.\n";
        exit(0);
    }

    // Apply migration. Prefer adding after device_info when possible, otherwise add at the end.
    // Check if device_info exists
    $colStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'device_tokens' AND COLUMN_NAME = 'device_info'");
    $colStmt->execute();
    $hasDeviceInfo = (int)$colStmt->fetchColumn(0);

    try {
        if ($hasDeviceInfo) {
            $sql = "ALTER TABLE device_tokens ADD COLUMN last_ping TIMESTAMP NULL AFTER device_info";
        } else {
            $sql = "ALTER TABLE device_tokens ADD COLUMN last_ping TIMESTAMP NULL";
        }
        $pdo->exec($sql);
        echo "Column `last_ping` added successfully.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed to add column with error: " . $e->getMessage() . "\nAttempting fallback add without position...\n");
        // Try fallback without specifying position
        $pdo->exec("ALTER TABLE device_tokens ADD COLUMN last_ping TIMESTAMP NULL");
        echo "Column `last_ping` added successfully (fallback).\n";
    }

    // Optionally initialize existing rows to NOW() where null (commented by default)
    // $pdo->exec("UPDATE device_tokens SET last_ping = NOW() WHERE last_ping IS NULL");

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
