<?php
// Simple script to validate that logout -> device_tokens.is_active = 0
// Usage (CLI): php scripts/check_logout_token.php --coursier_id=123
// Or via web: scripts/check_logout_token.php?coursier_id=123

$dsn = "mysql:host=127.0.0.1;dbname=coursier;charset=utf8mb4"; // adjust if needed
$db_user = 'root';
$db_pass = '';

$coursier_id = null;
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--coursier_id=') === 0) {
            $coursier_id = (int)substr($arg, strlen('--coursier_id='));
        }
    }
} else {
    if (isset($_GET['coursier_id'])) $coursier_id = (int)$_GET['coursier_id'];
}

if (empty($coursier_id)) {
    echo "Usage: provide --coursier_id=ID or ?coursier_id=ID\n";
    exit(1);
}

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->prepare('SELECT token, is_active, created_at, last_seen FROM device_tokens WHERE coursier_id = :id ORDER BY created_at DESC');
    $stmt->execute([':id' => $coursier_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "Aucun token trouvé pour coursier_id={$coursier_id}\n";
        exit(0);
    }
    echo "Tokens pour coursier_id={$coursier_id}:\n";
    foreach ($rows as $r) {
        echo sprintf("token=%s is_active=%s created_at=%s last_seen=%s\n", $r['token'], $r['is_active'], $r['created_at'], $r['last_seen']);
    }
    // Quick check: any token still active?
    $active = array_filter($rows, function($r){ return intval($r['is_active']) === 1; });
    if (count($active) === 0) {
        echo "RESULT: OK - Aucun token actif\n";
        exit(0);
    } else {
        echo "RESULT: ALERT - Tokens actifs détectés: " . count($active) . "\n";
        // Return non-zero so monitoring sees failure
        exit(2);
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(3);
}
