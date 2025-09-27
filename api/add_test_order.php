<?php
// api/add_test_order.php - Ajouter une commande de test pour déclencher les notifications sonores

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/lib/fcm_enhanced.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();
    
    // Créer une nouvelle commande de test
    $testOrders = [
        [
            'client_nom' => 'Test Client ' . rand(1, 100),
            'client_telephone' => '06' . rand(10000000, 99999999),
            'adresse_enlevement' => 'Point de retrait ' . rand(1, 50) . ' Rue de Test, Paris',
            'adresse_livraison' => rand(1, 100) . ' Avenue de Livraison, Paris',
            'distance' => rand(10, 50) / 10, // Entre 1.0 et 5.0 km
            'prix_livraison' => rand(1500, 5000), // Entre 1500 et 5000 FCFA
            'description' => 'Commande de test automatique - ' . date('H:i:s'),
            'statut' => 'nouvelle'
        ]
    ];
    
    $order = $testOrders[0];
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes_coursier (
            coursier_id, client_nom, client_telephone, 
            adresse_enlevement, adresse_livraison, 
            distance, prix_livraison, description, statut
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        1, // coursier_id = 1
        $order['client_nom'],
        $order['client_telephone'],
        $order['adresse_enlevement'],
        $order['adresse_livraison'],
        $order['distance'],
        $order['prix_livraison'],
        $order['description'],
        $order['statut']
    ]);
    
    if ($result) {
        $newOrderId = $pdo->lastInsertId();
        // Récupérer les tokens FCM du coursier concerné (id=1 pour ce test)
        $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $stmtTok = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ?");
        $stmtTok->execute([1]);
        $tokens = array_column($stmtTok->fetchAll(PDO::FETCH_ASSOC), 'token');
        if (!empty($tokens)) {
            fcm_send_with_log(
                $tokens,
                'Nouvelle commande',
                'Une nouvelle course vous a été attribuée',
                [
                    'type' => 'new_order',
                    'order_id' => $newOrderId
                ],
                1,
                $newOrderId
            );
        }
        echo json_encode([
            'success' => true,
            'message' => 'Nouvelle commande de test créée',
            'order_id' => $newOrderId,
            'order_details' => $order
        ]);
    } else {
        throw new Exception('Erreur lors de l\'insertion de la commande');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>