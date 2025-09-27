<?php
// Retourne la position temps réel du coursier pour une commande UNIQUEMENT si la commande est active
// GET: ?commande_id=123
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/tracking_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'METHOD_NOT_ALLOWED']);
        exit;
    }
    $commandeId = isset($_GET['commande_id']) ? (int)$_GET['commande_id'] : 0;
    if ($commandeId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'MISSING_COMMANDE_ID']);
        exit;
    }

    $pdo = getDBConnection();

    // Vérifier liaison active
    $hasLinkTable = $pdo->query("SHOW TABLES LIKE 'commandes_coursiers'")->rowCount() > 0;
    if (!$hasLinkTable) {
        echo json_encode(['success' => true, 'data' => ['live' => false, 'position' => null]]);
        exit;
    }
    $hasActiveCol = $pdo->query("SHOW COLUMNS FROM commandes_coursiers LIKE 'active'")->rowCount() > 0;

    $sql = "SELECT coursier_id, " . ($hasActiveCol ? "active" : "0 AS active") . " AS active
            FROM commandes_coursiers WHERE commande_id = ? ORDER BY date_attribution DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$commandeId]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$link || (int)$link['coursier_id'] <= 0 || intval($link['active']) !== 1) {
        echo json_encode(['success' => true, 'data' => ['live' => false, 'position' => null]]);
        exit;
    }

    // Récupérer dernière position du coursier
    $rows = tracking_select_latest_positions($pdo, 60);
    $pos = null;
    foreach ($rows as $r) {
        if ((int)$r['coursier_id'] === (int)$link['coursier_id']) {
            $pos = [
                'lat' => isset($r['latitude']) ? (float)$r['latitude'] : null,
                'lng' => isset($r['longitude']) ? (float)$r['longitude'] : null,
                'updated_at' => $r['derniere_position'] ?? null,
                'coursier_id' => (int)$link['coursier_id']
            ];
            break;
        }
    }

    echo json_encode(['success' => true, 'data' => ['live' => true, 'position' => $pos]]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
