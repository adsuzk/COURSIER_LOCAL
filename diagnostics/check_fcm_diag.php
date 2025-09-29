<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../fcm_token_security.php';
try {
    $s = new FCMTokenSecurity(['verbose' => true]);
    $r = $s->canAcceptNewOrders();
    echo "CAN_ACCEPT_RETURN=";
    var_export($r);
    echo "\n";
    $pdo = getDBConnection();
    $stmt = $pdo->query('SELECT id, coursier_id, is_active, last_ping, updated_at FROM device_tokens ORDER BY COALESCE(last_ping, updated_at) DESC LIMIT 10');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "RECENT_TOKENS=" . json_encode($rows) . "\n";
} catch (Throwable $e) {
    echo "ERROR:" . $e->getMessage() . "\n";
}
