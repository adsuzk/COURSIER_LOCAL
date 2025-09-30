<?php
// Test d'intégration rapide pour vérifier la compatibilité des anciens champs
// avec /api/submit_order.php

$url = 'http://127.0.0.1/COURSIER_LOCAL/api/submit_order.php';
$payload = json_encode([
    'departure' => 'Adresse Test A',
    'destination' => 'Adresse Test B',
    'senderPhone' => '+2250707070707',
    'receiverPhone' => '+2250808080808',
    'priority' => 'normale',
    'paymentMethod' => 'cash',
    'price' => 1200
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false) {
    echo "Curl error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP: $code\n";
    echo "Response: $res\n";
}
curl_close($ch);
