<?php
require __DIR__ . '/config.php';
$pdo = getDBConnection();
$stmt = $pdo->query('SELECT component, status, last_seen_at FROM system_sync_heartbeats ORDER BY component');
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
print_r($rows);
