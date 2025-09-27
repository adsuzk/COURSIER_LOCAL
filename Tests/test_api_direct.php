<?php
/**
 * Test direct API register_device_token_simple
 * Simulation d'un appel sans cURL pour diagnostiquer 403
 */

echo "=== TEST DIRECT API REGISTER_DEVICE_TOKEN_SIMPLE ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Simuler les données POST comme si elles venaient de l'app Android
$_POST = [
    'coursier_id' => 1,
    'agent_id' => 1,
    'token' => 'test_direct_token_' . time()
];

// Simuler les headers
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

echo "Données simulées:\n";
print_r($_POST);
echo "\n";

// Inclure directement l'API
ob_start();
try {
    include __DIR__ . '/../api/register_device_token_simple.php';
    $output = ob_get_clean();
    echo "✅ API exécutée avec succès\n";
    echo "Sortie: $output\n";
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Erreur API: " . $e->getMessage() . "\n";
}

// Test aussi timeline_sync
echo "\n=== TEST DIRECT API TIMELINE_SYNC ===\n";

$_GET = ['order_id' => 101];
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
try {
    include __DIR__ . '/../api/timeline_sync.php';
    $output = ob_get_clean();
    echo "✅ Timeline API exécutée avec succès\n";
    echo "Sortie: $output\n";
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Erreur Timeline API: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DES TESTS DIRECTS ===\n";
?>