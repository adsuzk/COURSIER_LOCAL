<?php
require_once __DIR__ . '/../../config.php';
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SHOW COLUMNS FROM device_tokens");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "COLUMNS:\n";
    foreach ($cols as $c) echo " - $c\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
