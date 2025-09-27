<?php
// Test de debugging pour ordre et logs détaillés
header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST SUBMIT ORDER DEBUG ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Simuler une requête comme celle de production 
$testData = [
    'departure' => 'Cocody Centre',
    'destination' => 'Plateau Rue du Commerce', 
    'senderPhone' => '+225 07 07 07 07 07',
    'receiverPhone' => '+225 08 08 08 08 08',
    'priority' => 'normale', // Test avec la bonne valeur
    'paymentMethod' => 'cash',
    'departure_lat' => 5.3364,
    'departure_lng' => -4.0267,
    'destination_lat' => 5.3500,
    'destination_lng' => -4.0100,
    'packageDescription' => 'Document urgent'
];

echo "Données envoyées:\n";
print_r($testData);
echo "\n";

$jsonData = json_encode($testData);
echo "JSON encodé: $jsonData\n\n";

$ch = curl_init('http://localhost/coursier_prod/api/submit_order.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_VERBOSE => true,
    CURLOPT_STDERR => $verbose = fopen('php://temp', 'rw+')
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== RÉSULTATS ===\n";
echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "Erreur cURL: $error\n";
}

echo "Réponse brute:\n";
echo $response . "\n\n";

// Décoder la réponse JSON
$decoded = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON décodé:\n";
    print_r($decoded);
} else {
    echo "Erreur JSON: " . json_last_error_msg() . "\n";
}

// Vérifier les logs récents
echo "\n=== LOGS RÉCENTS ===\n";
$logFile = __DIR__ . '/diagnostic_logs/diagnostics_errors.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $lines = explode("\n", $logs);
    $recent = array_slice($lines, -10); // 10 dernières lignes
    echo implode("\n", $recent);
} else {
    echo "Fichier de log non trouvé: $logFile\n";
}

?>