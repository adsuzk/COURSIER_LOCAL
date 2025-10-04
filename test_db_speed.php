<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();
    $start = microtime(true);
    $st = $pdo->query('SELECT COUNT(*) as cnt FROM commandes');
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $elapsed = round((microtime(true) - $start) * 1000, 2);
    
    // Test index page basic query
    $start2 = microtime(true);
    $st2 = $pdo->query('SELECT id, statut FROM commandes ORDER BY id DESC LIMIT 50');
    $rows = $st2->fetchAll(PDO::FETCH_ASSOC);
    $elapsed2 = round((microtime(true) - $start2) * 1000, 2);
    
    echo json_encode([
        'success' => true,
        'count_query_ms' => $elapsed,
        'commandes_count' => $r['cnt'],
        'index_query_ms' => $elapsed2,
        'sample_rows' => count($rows)
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
