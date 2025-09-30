<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
$coursierId = 5;
try {
    $sql = "SELECT * FROM tracking_coursiers WHERE coursier_id = ? ORDER BY COALESCE(updated_at, created_at, `timestamp`) DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coursierId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        echo "No tracking rows for coursier_id={$coursierId}\n";
        exit(0);
    }
    foreach ($rows as $r) {
        $time = $r['updated_at'] ?? $r['created_at'] ?? $r['timestamp'] ?? 'n/a';
        $lat = $r['latitude'] ?? $r['lat'] ?? 'n/a';
        $lng = $r['longitude'] ?? $r['lng'] ?? 'n/a';
        echo "{$time} -> {$lat},{$lng} (id={$r['id']})\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
