<?php
// admin/api/mark_crashes_resolved.php
// API pour marquer les crashes d'un appareil comme résolus

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

require_once __DIR__ . '/../../config.php';

try {
    $pdo = getPDO();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['device_id']) || empty($input['device_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID appareil requis']);
        exit;
    }
    
    $deviceId = $input['device_id'];
    
    // Marquer tous les crashes de cet appareil comme résolus
    $stmt = $pdo->prepare("
        UPDATE app_crashes 
        SET is_resolved = 1, resolved_at = NOW() 
        WHERE device_id = ? AND is_resolved = 0
    ");
    
    $result = $stmt->execute([$deviceId]);
    
    if ($result) {
        $affectedRows = $stmt->rowCount();
        echo json_encode([
            'success' => true, 
            'message' => "Marqué $affectedRows crashes comme résolus",
            'affected_rows' => $affectedRows
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données : ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
}
?>