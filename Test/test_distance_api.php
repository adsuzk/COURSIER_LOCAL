<?php
// Test de l'API Google Distance Matrix
header('Content-Type: application/json');

// Clé API (même que dans coursier.php)
$api_key = 'AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A';

// Adresses de test
$origin = $_GET['origin'] ?? 'Cocody, Abidjan';
$destination = $_GET['destination'] ?? 'Plateau, Abidjan';

// Construire l'URL pour l'API Distance Matrix
$url = "https://maps.googleapis.com/maps/api/distancematrix/json?" . http_build_query([
    'origins' => $origin,
    'destinations' => $destination,
    'mode' => 'driving',
    'units' => 'metric',
    'language' => 'fr',
    'key' => $api_key
]);

try {
    // Faire la requête
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'method' => 'GET'
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Erreur de connexion à l\'API Google');
    }
    
    $data = json_decode($response, true);
    
    if ($data['status'] === 'OK') {
        $element = $data['rows'][0]['elements'][0];
        
        if ($element['status'] === 'OK') {
            $distance = $element['distance'];
            $duration = $element['duration'];
            
            // Calculs de prix pour les trois priorités
            $calculations = [
                'normale' => [
                    'name' => 'Normal',
                    'baseFare' => 300,
                    'perKmRate' => 300,
                    'distanceKm' => $distance['value'] / 1000,
                    'distanceCost' => ceil(($distance['value'] / 1000) * 300),
                    'totalPrice' => 300 + ceil(($distance['value'] / 1000) * 300)
                ],
                'urgente' => [
                    'name' => 'Urgent',
                    'baseFare' => 1000,
                    'perKmRate' => 500,
                    'distanceKm' => $distance['value'] / 1000,
                    'distanceCost' => ceil(($distance['value'] / 1000) * 500),
                    'totalPrice' => 1000 + ceil(($distance['value'] / 1000) * 500)
                ],
                'express' => [
                    'name' => 'Express',
                    'baseFare' => 1500,
                    'perKmRate' => 700,
                    'distanceKm' => $distance['value'] / 1000,
                    'distanceCost' => ceil(($distance['value'] / 1000) * 700),
                    'totalPrice' => 1500 + ceil(($distance['value'] / 1000) * 700)
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'origin' => $origin,
                'destination' => $destination,
                'distance' => $distance,
                'duration' => $duration,
                'calculations' => $calculations,
                'api_response' => $data
            ], JSON_PRETTY_PRINT);
            
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Impossible de calculer la distance: ' . $element['status'],
                'origin' => $origin,
                'destination' => $destination
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Erreur API Google: ' . $data['status'],
            'details' => $data['error_message'] ?? 'Aucun détail',
            'origin' => $origin,
            'destination' => $destination
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Exception: ' . $e->getMessage(),
        'origin' => $origin,
        'destination' => $destination
    ]);
}
?>
