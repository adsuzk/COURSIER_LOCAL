<?php
// Test API avec les paramètres attendus
echo "=== TEST API AVEC PARAMÈTRES CORRECTS ===\n";

// Simulation environnement HTTP
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';

// Données exactes attendues par l'API
$_POST = [
    'order_number' => 'ORDER_' . uniqid() . '_TEST',
    'amount' => 1500.00,
    'phone' => '0123456789',
    'email' => 'test@example.com'
];

echo "Numéro commande : " . $_POST['order_number'] . "\n";
echo "Montant : " . $_POST['amount'] . " FCFA\n";
echo "Téléphone : " . $_POST['phone'] . "\n";

try {
    // Capture la sortie de l'API
    ob_start();
    include 'api/initiate_order_payment.php';
    $apiOutput = ob_get_clean();
    
    echo "\n=== RÉPONSE API ===\n";
    echo $apiOutput . "\n";
    
    // Vérifier si c'est du JSON valide
    $jsonResponse = json_decode($apiOutput, true);
    if ($jsonResponse) {
        echo "\n=== ANALYSE RÉPONSE ===\n";
        echo "Success: " . ($jsonResponse['success'] ? 'OUI ✓' : 'NON ✗') . "\n";
        
        if (isset($jsonResponse['payment_url'])) {
            echo "URL CinetPay générée ✓\n";
            echo "URL: " . $jsonResponse['payment_url'] . "\n";
        }
        
        if (isset($jsonResponse['transaction_id'])) {
            echo "Transaction ID: " . $jsonResponse['transaction_id'] . "\n";
        }
        
        if (isset($jsonResponse['message'])) {
            echo "Message: " . $jsonResponse['message'] . "\n";
        }
        
        if ($jsonResponse['success']) {
            echo "\n🎉 API DE PAIEMENT FONCTIONNELLE ! 🎉\n";
        } else {
            echo "\n❌ Erreur dans l'API\n";
        }
    } else {
        echo "Erreur: Réponse non-JSON\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>