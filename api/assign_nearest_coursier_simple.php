<?php
// assign_nearest_coursier_simple.php - Version simplifiée pour attribution automatique
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);
    // Utilitaires communs
    require_once __DIR__ . '/../lib/geo_utils.php';

try {
    // Connexion directe à la base
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=coursier_prod;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Lire les données d'entrée
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['order_id'])) {
        throw new Exception('order_id manquant');
    }

    $orderId = intval($data['order_id']);
    $lat = floatval($data['departure_lat'] ?? 5.3364); // Abidjan par défaut
    $lng = floatval($data['departure_lng'] ?? -4.0267);
    // haversine() importé depuis lib/geo_utils.php
    }

    // Récupérer les coursiers disponibles avec leurs positions récentes
    $sql = "
        SELECT DISTINCT a.id as coursier_id, CONCAT(a.prenoms,' ',a.nom) AS nom, p.latitude, p.longitude
        FROM agents_suzosky a
        JOIN coursier_positions p ON a.id = p.coursier_id
        WHERE a.status = 'actif'
          AND p.timestamp > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ORDER BY p.timestamp DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($coursiers)) {
        throw new Exception('Aucun coursier disponible trouvé');
    }

    // Calculer le coursier le plus proche
    $minDist = null;
    $selectedCoursier = null;
    
    foreach ($coursiers as $c) {
        $dist = haversine($lat, $lng, $c['latitude'], $c['longitude']);
        if ($minDist === null || $dist < $minDist) {
            $minDist = $dist;
            $selectedCoursier = $c;
        }
    }

    if (!$selectedCoursier) {
        throw new Exception('Impossible de sélectionner un coursier');
    }

    // Attribuer la commande
    $updateStmt = $pdo->prepare("
        UPDATE commandes 
        SET coursier_id = ?, statut = 'assignee', assigned_at = NOW(), updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$selectedCoursier['coursier_id'], $orderId]);

    // Log simple de l'attribution
    error_log("Attribution: Commande $orderId -> Coursier {$selectedCoursier['coursier_id']} ({$selectedCoursier['nom']}) - Distance: {$minDist}km");

    // Envoyer notification FCM (version simple)
    $cols = $pdo->query("SHOW COLUMNS FROM device_tokens")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('agent_id', $cols)) {
        try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN agent_id INT NULL"); } catch (Throwable $e) {}
    }
    $tokenStmt = $pdo->prepare("SELECT token FROM device_tokens WHERE agent_id = ? OR coursier_id = ? ORDER BY updated_at DESC LIMIT 1");
    $tokenStmt->execute([$selectedCoursier['coursier_id'], $selectedCoursier['coursier_id']]);
    $tokenData = $tokenStmt->fetch(PDO::FETCH_ASSOC);

    $fcmSent = false;
    if ($tokenData) {
        require_once __DIR__ . '/lib/fcm_enhanced.php';
        $res = fcm_send_with_log([
            $tokenData['token']
        ], 'Nouvelle commande', 'Une nouvelle course vous a été attribuée', [
            'type' => 'new_order',
            'order_id' => (string)$orderId,
            '_data_only' => true
        ], $selectedCoursier['coursier_id'], $orderId);
        $fcmSent = !empty($res['success']);
        error_log('FCM Enhanced envoyé: ' . ($fcmSent ? 'OUI' : 'NON')); 
    }

    echo json_encode([
        'success' => true,
        'coursier_id' => $selectedCoursier['coursier_id'],
        'coursier_nom' => $selectedCoursier['nom'],
        'distance_km' => round($minDist, 2),
        'fcm_sent' => $fcmSent
    ]);

} catch (Exception $e) {
    error_log("Erreur attribution: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>