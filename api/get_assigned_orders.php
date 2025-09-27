<?php
// API simple pour récupérer les commandes du coursier via table de liaison
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$coursierId = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : 0;
if ($coursierId <= 0) {
    echo json_encode(['success' => false, 'message' => 'coursier_id manquant']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Récupérer les commandes attribuées via la table de liaison
    $sql = "SELECT c.*, cc.statut as attribution_statut, cc.date_attribution 
            FROM commandes c 
            JOIN commandes_coursiers cc ON c.id = cc.commande_id 
            WHERE cc.coursier_id = ? AND cc.statut IN ('assignee', 'acceptee', 'en_cours') 
            ORDER BY cc.date_attribution DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coursierId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'orders' => $orders, 'count' => count($orders)]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>