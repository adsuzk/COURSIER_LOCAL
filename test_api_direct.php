<?php
// Test direct de l'API de paiement
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test API Paiement Direct</h1>";

// Test des données comme envoyées par le formulaire
$testData = [
    'order_number' => 'SZK' . time(),
    'amount' => '3224',
    'senderPhone' => '+225 07 12 34 56 78',
    'email' => 'test@example.com'
];

echo "<h2>Données de test:</h2>";
echo "<pre>" . print_r($testData, true) . "</pre>";

echo "<h2>Test de connexion DB:</h2>";
try {
    require_once __DIR__ . '/config.php';
    $pdo = getDBConnection();
    echo "✅ Connexion DB OK<br>";
    
    // Test query simple
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Query test OK: " . print_r($result, true) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erreur DB: " . $e->getMessage() . "<br>";
}

echo "<h2>Test CinetPay Integration:</h2>";
try {
    require_once __DIR__ . '/cinetpay/cinetpay_integration.php';
    $integration = new SuzoskyCinetPayIntegration();
    echo "✅ Classe CinetPay chargée<br>";
    
    // Test initiation paiement
    $result = $integration->initiateOrderPayment(
        $testData['order_number'],
        floatval($testData['amount']),
        'XOF',
        'Test paiement',
        $testData['senderPhone'],
        $testData['email']
    );
    
    echo "<h3>Résultat initiation:</h3>";
    echo "<pre>" . print_r($result, true) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ Erreur CinetPay: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>