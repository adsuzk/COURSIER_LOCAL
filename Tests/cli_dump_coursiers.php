<?php
require __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT * FROM coursiers LIMIT 5");
    $rows = $stmt->fetchAll();
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
