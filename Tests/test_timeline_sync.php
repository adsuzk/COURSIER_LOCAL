<?php
// Test timeline sync API
require_once __DIR__ . '/../config.php';

echo "=== TEST TIMELINE SYNC API ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test avec une commande récente
try {
    $pdo = getDBConnection();
    
    // Récupérer les dernières commandes
    $stmt = $pdo->query("SELECT id, code_commande, statut, created_at FROM commandes ORDER BY created_at DESC LIMIT 5");
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== Dernières commandes ===\n";
    foreach ($commandes as $cmd) {
        echo "ID: {$cmd['id']}, Code: {$cmd['code_commande']}, Statut: {$cmd['statut']}, Date: {$cmd['created_at']}\n";
    }
    
    if (empty($commandes)) {
        echo "Aucune commande trouvée.\n";
        exit;
    }
    
    $testOrder = $commandes[0];
    echo "\n=== Test avec commande ID: {$testOrder['id']} ===\n";
    
    // Test de l'API timeline_sync
    $url = "http://localhost/coursier_prod/api/timeline_sync.php?order_id=" . $testOrder['id'];
    
    echo "URL testée: $url\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "\n=== RÉSULTATS ===\n";
    echo "HTTP Code: $httpCode\n";
    if ($error) {
        echo "Erreur cURL: $error\n";
    }
    echo "Réponse: $response\n";
    
    // Parser la réponse JSON
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && $data) {
        echo "\nStructure de la réponse:\n";
        echo "- success: " . ($data['success'] ? 'true' : 'false') . "\n";
        if (isset($data['data'])) {
            echo "- timeline: " . (isset($data['data']['timeline']) ? count($data['data']['timeline']) . ' étapes' : 'absent') . "\n";
            echo "- messages: " . (isset($data['data']['messages']) ? count($data['data']['messages']) . ' messages' : 'absent') . "\n";
            echo "- statut: " . ($data['data']['statut'] ?? 'non défini') . "\n";
        }
    } else {
        echo "Erreur JSON: " . json_last_error_msg() . "\n";
    }
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}
?>