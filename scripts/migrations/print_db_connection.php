<?php
require_once __DIR__ . '/../../config.php';
try {
    $pdo = getPDO();
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $host = $pdo->query('SELECT @@hostname')->fetchColumn();
    echo "DATABASE={$db}\nHOST={$host}\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
