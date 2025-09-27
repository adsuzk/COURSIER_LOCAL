<?php
/**
 * Test API direct via URL absolue pour contourner .htaccess
 */

require_once __DIR__ . '/../config.php';

$resolveUrl = function (string $path) {
    if (function_exists('appUrl')) {
        return appUrl($path);
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $prefix = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '') ?: '/', '/');
    $base = $prefix === '' ? '' : $prefix . '/';
    return $scheme . '://' . $host . '/' . $base . ltrim($path, '/');
};

echo "=== TEST API AVEC URL ABSOLUE ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: API register_device_token_simple avec URL complète
echo "=== TEST 1: REGISTER DEVICE TOKEN SIMPLE ===\n";

$testData = [
    'coursier_id' => 1,
    'token' => 'test_url_absolue_' . time()
];

$jsonData = json_encode($testData);
$url = $resolveUrl('api/register_device_token_simple.php');

echo "URL: $url\n";
echo "Données: $jsonData\n\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: TestScript/1.0'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) echo "Erreur cURL: $error\n";
echo "Réponse: $response\n\n";

// Test 2: API timeline_sync avec URL complète  
echo "=== TEST 2: TIMELINE SYNC ===\n";

$url2 = $resolveUrl('api/timeline_sync.php?order_id=102');

echo "URL: $url2\n";

$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => $url2,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: TestScript/1.0'
    ],
    CURLOPT_FOLLOWLOCATION => true
]);

$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$error2 = curl_error($ch2);
curl_close($ch2);

echo "HTTP Code: $httpCode2\n";
if ($error2) echo "Erreur cURL: $error2\n";
echo "Réponse: $response2\n\n";

// Test 3: submit_order (le plus important!)
echo "=== TEST 3: SUBMIT ORDER ===\n";

$orderData = [
    'departure' => 'Test Départ Cocody',
    'destination' => 'Test Destination Plateau',
    'senderPhone' => '+225 07 07 07 07 07',
    'receiverPhone' => '+225 08 08 08 08 08',
    'priority' => 'normale',
    'paymentMethod' => 'cash',
    'departure_lat' => 5.3364,
    'departure_lng' => -4.0267,
    'destination_lat' => 5.3500,
    'destination_lng' => -4.0100
];

$orderJson = json_encode($orderData);
$url3 = $resolveUrl('api/submit_order.php');

echo "URL: $url3\n";
echo "Données: $orderJson\n\n";

$ch3 = curl_init();
curl_setopt_array($ch3, [
    CURLOPT_URL => $url3,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $orderJson,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: TestScript/1.0'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true
]);

$response3 = curl_exec($ch3);
$httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$error3 = curl_error($ch3);
curl_close($ch3);

echo "HTTP Code: $httpCode3\n";
if ($error3) echo "Erreur cURL: $error3\n";
echo "Réponse: $response3\n\n";

echo "=== FIN DES TESTS URL ABSOLUE ===\n";
?>