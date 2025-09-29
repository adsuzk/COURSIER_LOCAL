<?php
// test_submit_order.php : Test automatique de soumission de commande à l'API

$url = 'http://localhost/COURSIER_LOCAL/api/submit_order.php';

$data = [
    'departure' => 'Cocody',
    'destination' => 'Plateau',
    'senderPhone' => '+2250700000000',
    'receiverPhone' => '+2250700000001',
    'packageDescription' => 'Petit colis test',
    'priority' => 'normale',
    'paymentMethod' => 'cash',
    'price' => 1500,
    'distance' => '5 km',
    'duration' => '15 min',
    'departure_lat' => 5.35,
    'departure_lng' => -4.01,
    'destination_lat' => 5.32,
    'destination_lng' => -4.02
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'timeout' => 10
    ]
];
$context  = stream_context_create($options);

$result = file_get_contents($url, false, $context);
if ($result === FALSE) {
    echo "Erreur lors de la requête HTTP.\n";
    exit(1);
}

$response = json_decode($result, true);

if (isset($response['success']) && $response['success']) {
    echo "✅ Commande acceptée :\n";
    print_r($response);
} else {
    echo "❌ Erreur de validation ou d'insertion :\n";
    print_r($response);
}
