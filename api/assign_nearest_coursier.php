<?php
// API appelée lors de la création d'une commande pour attribuer le coursier le plus proche
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/orders_table_resolver.php';
require_once __DIR__ . '/../lib/geo_utils.php';
require_once __DIR__ . '/lib/fcm_enhanced.php';
require_once __DIR__ . '/lib/tracking_helpers.php';

// haversine() désormais fourni par lib/geo_utils.php

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['order_id']) || !isset($data['departure_lat']) || !isset($data['departure_lng'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}
$orderId = intval($data['order_id']);
$lat = floatval($data['departure_lat']);
$lng = floatval($data['departure_lng']);

try {
    $pdo = getDBConnection();
    // Préparer colonnes pour mises à jour conditionnelles
    $columns = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
    $hasStatut = in_array('statut', $columns, true);
    $hasAssignedAt = in_array('assigned_at', $columns, true);
    // Récupérer la dernière position de chaque coursier (schema-agnostic)
    $rows = tracking_select_latest_positions($pdo, 180);
    // Normalize to expected keys
    $coursiers = array_map(function($r){
        return [
            'coursier_id' => (int)$r['coursier_id'],
            'lat' => (float)$r['latitude'],
            'lng' => (float)$r['longitude'],
            'updated_at' => $r['derniere_position'] ?? null,
        ];
    }, $rows);
    if (!$coursiers) throw new Exception('Aucun coursier connecté');
    // Calculer la distance pour chaque coursier
    $minDist = null;
    $selected = null;
    foreach ($coursiers as $c) {
        $dist = haversine($lat, $lng, $c['lat'], $c['lng']);
        if ($minDist === null || $dist < $minDist) {
            $minDist = $dist;
            $selected = $c['coursier_id'];
        }
    }
    if (!$selected) throw new Exception('Aucun coursier trouvé');
    // Attribuer la commande au coursier (table legacy ou classiques ?)
    $targetTable = resolvePrimaryOrdersTable($pdo);
    // 1) Fixer le coursier et l'horodatage d'assignation si possible
    if ($hasAssignedAt) {
        $stmt = $pdo->prepare("UPDATE $targetTable SET coursier_id = ?, assigned_at = COALESCE(assigned_at, NOW()) WHERE id = ?");
        $stmt->execute([$selected, $orderId]);
    } else {
        $stmt = $pdo->prepare("UPDATE $targetTable SET coursier_id = ? WHERE id = ?");
        $stmt->execute([$selected, $orderId]);
    }
    // 2) Mettre à jour le statut si la colonne existe
    if ($hasStatut) {
        try {
            $pdo->prepare("UPDATE $targetTable SET statut = 'assignee' WHERE id = ? AND (statut IS NULL OR statut = 'nouvelle')")->execute([$orderId]);
        } catch (Throwable $e) { /* non bloquant */ }
    }
    // Table de liaison commandes_coursiers
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS commandes_coursiers (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            commande_id INT NOT NULL,\n            coursier_id INT NOT NULL,\n            statut VARCHAR(32) DEFAULT 'assignee',\n            active TINYINT(1) DEFAULT 0,\n            date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            UNIQUE KEY uq_commande_coursier (commande_id,coursier_id),\n            KEY idx_coursier (coursier_id),\n            KEY idx_statut (statut)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $insLink = $pdo->prepare("INSERT IGNORE INTO commandes_coursiers (commande_id, coursier_id, statut, active, date_attribution) VALUES (?,?,?,?, NOW())");
        $insLink->execute([$orderId, $selected, 'assignee', 0]);
    } catch (Throwable $e) { /* pas bloquant */ }
    // Envoyer notification push au coursier sélectionné
    // Schéma unifié (aligné avec api/register_device_token.php) : token en TEXT + token_hash unique
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coursier_id INT NOT NULL,
        agent_id INT NULL,
        token TEXT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_token_hash (token_hash),
        KEY idx_coursier (coursier_id),
        KEY idx_agent (agent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migrations douces si table existante dans un autre format
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token TEXT NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER token"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE device_tokens SET token_hash = SHA2(token,256) WHERE token_hash IS NULL OR token_hash = ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token_hash CHAR(64) NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD UNIQUE KEY uniq_token_hash (token_hash)"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN agent_id INT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD KEY idx_agent (agent_id)"); } catch (Throwable $e) {}

    $stmtTok = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC");
    $stmtTok->execute([$selected]);
    $tokens = array_column($stmtTok->fetchAll(PDO::FETCH_ASSOC), 'token');
    if (!empty($tokens)) {
        // Envoi via version améliorée avec journalisation (inclut son et priorité)
        fcm_send_with_log(
            $tokens,
            'Nouvelle commande',
            'Une nouvelle course vous a été attribuée',
            [
                'type' => 'new_order',
                'order_id' => $orderId
            ],
            $selected,
            $orderId
        );
    }
    echo json_encode(['success' => true, 'coursier_id' => $selected, 'distance_km' => $minDist]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
