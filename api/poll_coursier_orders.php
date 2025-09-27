<?php
// API pour que l'app coursier récupère en temps réel les commandes qui lui sont attribuées
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/orders_table_resolver.php';

$coursierId = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : 0;
if ($coursierId <= 0) {
    echo json_encode(['success' => false, 'message' => 'coursier_id manquant']);
    exit;
}

try {
    $pdo = getDBConnection();
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    try { $primaryTable = resolvePrimaryOrdersTable($pdo); } catch (Throwable $e) {
        echo json_encode(['success'=>false,'error'=>'No orders table']); exit; }
    $active = getActiveOrderStatuses();
    $in = "('".implode("','", array_map(fn($s)=>addslashes($s), $active))."')";
    $sql = "SELECT * FROM $primaryTable WHERE coursier_id = ? AND statut IN $in ORDER BY id DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coursierId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order) {
        echo json_encode(['success' => true, 'order' => $order]);
    } else {
        echo json_encode(['success' => true, 'order' => null]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
