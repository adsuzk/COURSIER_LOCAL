<?php
// Test script pour submit_order API
$data = json_encode([
    'departure' => 'Test Depart',
    'destination' => 'Test Dest', 
    'senderPhone' => '+225 07 07 07 07 07',
    'receiverPhone' => '+225 08 08 08 08 08',
    'priority' => 'normal',
    'paymentMethod' => 'cash',
    'departure_lat' => 5.3364,
    'departure_lng' => -4.0267,
    'destination_lat' => 5.3500,
    'destination_lng' => -4.0100
]);

$ch = curl_init('http://localhost/coursier_prod/api/submit_order.php');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true
]);

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $result\n";