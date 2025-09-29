<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/config.php';
try {
    $pdo = getDBConnection();
    $row = $pdo->query('SELECT 1 AS ok')->fetch();
    $db = $pdo->query('SELECT DATABASE() AS db')->fetch()['db'] ?? 'n/a';
    $tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    header('Content-Type: text/plain');
    echo "DB_OK\n";
    echo "database={$db}\n";
    echo "tables={$tables}\n";
    echo "select1={$row['ok']}\n";
    exit(0);
} catch (Throwable $e) {
    header('Content-Type: text/plain', true, 500);
    echo 'DB_FAIL: ' . $e->getMessage();
    exit(1);
}
