<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();

$coursierId = 5;
$lat = 5.345320;
$lng = -4.024430;
$now = date('Y-m-d H:i:s');

try {
    // Ensure tracking table exists (best-effort)
    $pdo->exec("CREATE TABLE IF NOT EXISTS tracking_coursiers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coursier_id INT NOT NULL,
        latitude DECIMAL(10,7) NULL,
        longitude DECIMAL(10,7) NULL,
        accuracy VARCHAR(64) NULL,
        timestamp DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare('INSERT INTO tracking_coursiers (coursier_id, latitude, longitude, accuracy, timestamp, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$coursierId, $lat, $lng, null, $now, $now, $now]);
    echo "Inserted tracking for coursier_id={$coursierId} at {$lat},{$lng}\n";
} catch (Throwable $e) {
    echo "Error inserting tracking: " . $e->getMessage() . "\n";
}

?>
