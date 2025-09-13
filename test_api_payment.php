<?php
// Test direct de l'API initiate_order_payment.php
echo "=== TEST API INITIATE_ORDER_PAYMENT ===\n";

// Simuler une requête POST
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'order_number' => 'TEST123',
    'amount' => 3224
];

echo "Données envoyées:\n";
print_r($_POST);
echo "\n";

// Capturer la sortie
ob_start();

try {
    include 'api/initiate_order_payment.php';
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
echo "Réponse de l'API:\n";
echo $output;
echo "\n=== FIN TEST ===\n";
?>