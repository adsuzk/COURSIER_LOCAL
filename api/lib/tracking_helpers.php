<?php
// Helper utilities to interact with tracking_coursiers with varying schemas
// Handles both (lat,lng,updated_at) and (latitude,longitude,created_at/timestamp)

require_once __DIR__ . '/../../config.php';

/**
 * Get list of columns present in tracking_coursiers table.
 * @return array<string,bool> map of column name => true
 */
function tracking_get_columns(PDO $pdo): array {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM tracking_coursiers")->fetchAll(PDO::FETCH_COLUMN);
        $out = [];
        foreach ($cols as $c) { $out[$c] = true; }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Ensure a unified table exists if tracking_coursiers is missing.
 * We avoid altering existing schemas; only creates when table is absent.
 */
function tracking_ensure_table_unified(PDO $pdo): void {
    $cols = tracking_get_columns($pdo);
    if (!empty($cols)) return; // table exists
    $sql = "CREATE TABLE IF NOT EXISTS tracking_coursiers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coursier_id INT NOT NULL,
        -- Preferred columns
        latitude DECIMAL(10,8) NULL,
        longitude DECIMAL(11,8) NULL,
        accuracy DECIMAL(6,2) NULL,
        `timestamp` DATETIME NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        -- Backward-compatible duplicates
        lat DOUBLE NULL,
        lng DOUBLE NULL,
        INDEX idx_coursier_created (coursier_id, created_at),
        INDEX idx_coursier_updated (coursier_id, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
}

/**
 * Insert a new position for a courier, writing to available columns.
 */
function tracking_insert_position(PDO $pdo, int $coursierId, float $lat, float $lng, ?float $accuracy = null): void {
    tracking_ensure_table_unified($pdo);
    $cols = tracking_get_columns($pdo);

    // Build dynamic insert
    $fields = ['coursier_id'];
    $params = [$coursierId];
    $placeholders = ['?'];

    // Write both styles if present
    if (isset($cols['latitude'])) { $fields[] = 'latitude'; $placeholders[] = '?'; $params[] = $lat; }
    if (isset($cols['longitude'])) { $fields[] = 'longitude'; $placeholders[] = '?'; $params[] = $lng; }
    if (isset($cols['lat'])) { $fields[] = 'lat'; $placeholders[] = '?'; $params[] = $lat; }
    if (isset($cols['lng'])) { $fields[] = 'lng'; $placeholders[] = '?'; $params[] = $lng; }
    if (isset($cols['accuracy']) && $accuracy !== null) { $fields[] = 'accuracy'; $placeholders[] = '?'; $params[] = $accuracy; }

    // Time columns
    $nowFunc = 'NOW()';
    if (isset($cols['timestamp'])) { $fields[] = 'timestamp'; $placeholders[] = $nowFunc; }
    if (isset($cols['created_at'])) { $fields[] = 'created_at'; $placeholders[] = $nowFunc; }
    if (isset($cols['updated_at'])) { $fields[] = 'updated_at'; $placeholders[] = $nowFunc; }

    $sql = 'INSERT INTO tracking_coursiers (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

/**
 * Return latest position per courier in a normalized shape.
 * Output rows: [id_coursier, coursier_id, latitude, longitude, derniere_position]
 */
function tracking_select_latest_positions(PDO $pdo, int $sinceMinutes = 120): array {
    $cols = tracking_get_columns($pdo);
    if (empty($cols)) return [];

    $hasLatLng = isset($cols['lat']) && isset($cols['lng']);
    $hasLatLong = isset($cols['latitude']) && isset($cols['longitude']);

    // Choose time column
    $timeCol = null;
    if (isset($cols['updated_at'])) $timeCol = 'updated_at';
    if (isset($cols['created_at'])) $timeCol = $timeCol ? $timeCol : 'created_at';
    if (isset($cols['timestamp'])) $timeCol = $timeCol ? $timeCol : 'timestamp';
    if (!$timeCol) $timeCol = $hasLatLng ? 'updated_at' : ($hasLatLong ? 'created_at' : null);

    // Build select parts
    $latExpr = $hasLatLng ? 't1.lat' : 't1.latitude';
    $lngExpr = $hasLatLng ? 't1.lng' : 't1.longitude';
    $timeExpr = 't1.' . $timeCol;

    $sql = "SELECT t1.coursier_id, $latExpr AS latitude, $lngExpr AS longitude, $timeExpr AS derniere_position
            FROM tracking_coursiers t1
            INNER JOIN (
                SELECT coursier_id, MAX($timeCol) AS max_time
                FROM tracking_coursiers
                WHERE $timeCol >= DATE_SUB(NOW(), INTERVAL :mins MINUTE)
                GROUP BY coursier_id
            ) t2 ON t1.coursier_id = t2.coursier_id AND $timeExpr = t2.max_time";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':mins' => $sinceMinutes]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { $r['id_coursier'] = $r['coursier_id']; }
    return $rows;
}

/**
 * Return last N positions for a courier, normalized columns [latitude, longitude, created_at].
 */
function tracking_select_positions_for_courier(PDO $pdo, int $coursierId, int $limit = 100): array {
    $cols = tracking_get_columns($pdo);
    if (empty($cols)) return [];
    $hasLatLng = isset($cols['lat']) && isset($cols['lng']);
    $hasLatLong = isset($cols['latitude']) && isset($cols['longitude']);
    $timeCol = isset($cols['created_at']) ? 'created_at' : (isset($cols['timestamp']) ? 'timestamp' : (isset($cols['updated_at']) ? 'updated_at' : null));
    if (!$timeCol) $timeCol = 'updated_at';
    $latExpr = $hasLatLng ? 'lat' : 'latitude';
    $lngExpr = $hasLatLng ? 'lng' : 'longitude';
    $sql = "SELECT $latExpr AS latitude, $lngExpr AS longitude, $timeCol AS created_at
            FROM tracking_coursiers WHERE coursier_id = ? ORDER BY $timeCol DESC LIMIT ?";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $coursierId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
