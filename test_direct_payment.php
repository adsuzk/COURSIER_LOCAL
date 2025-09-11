<?php
// Test direct de l'endpoint payment avec FormData

echo "<h1>Test Direct Payment Endpoint</h1>";

// Simuler des données POST
$_POST = [
    'order_number' => 'TEST' . date('YmdHis'),
    'amount' => '1500',
    'senderPhone' => '+225 01 02 03 04 05',
    'email' => 'test@example.com'
];

echo "<h2>Données POST simulées:</h2>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

echo "<h2>Test de l'inclusion de l'endpoint:</h2>";

// Capturer la sortie de l'endpoint
ob_start();
$_SERVER['REQUEST_METHOD'] = 'POST';

try {
    include __DIR__ . '/api/initiate_order_payment.php';
    $output = ob_get_contents();
} catch (Exception $e) {
    $output = "Erreur: " . $e->getMessage();
} finally {
    ob_end_clean();
}

echo "<h3>Sortie de l'endpoint:</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Vérifier si c'est du JSON
$json = json_decode($output, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<h3>JSON décodé:</h3>";
    echo "<pre>" . print_r($json, true) . "</pre>";
} else {
    echo "<p style='color: red;'>La sortie n'est pas du JSON valide!</p>";
}
?>
