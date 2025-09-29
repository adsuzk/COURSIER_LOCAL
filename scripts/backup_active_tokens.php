<?php
require_once __DIR__ . '/../config.php';

try {
    $pdo = getDBConnection();
    $rows = $pdo->query('SELECT * FROM device_tokens WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC);
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $path = $dir . '/device_tokens_backup_' . date('Ymd_His') . '.json';
    file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "WROTE: $path\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
