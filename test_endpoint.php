<?php
// Test script pour vérifier l'endpoint initiate_order_payment.php
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test de l'endpoint initiate_order_payment.php</h1>";

// Test 1: Vérifier que le fichier existe
$apiFile = __DIR__ . '/api/initiate_order_payment.php';
echo "<h2>1. Vérification existence du fichier</h2>";
echo "Chemin: " . $apiFile . "<br>";
echo "Existe: " . (file_exists($apiFile) ? "✅ OUI" : "❌ NON") . "<br>";

// Test 2: Simuler un appel POST
echo "<h2>2. Test de l'endpoint avec données POST</h2>";

// Préparer les données de test
$postData = [
    'order_number' => 'TEST' . date('Ymd') . '001',
    'amount' => 1500,
    'senderPhone' => '+225 01 02 03 04 05',
    'email' => 'test@example.com'
];

echo "Données envoyées:<br>";
echo "<pre>" . print_r($postData, true) . "</pre>";

// Simuler l'appel via cURL interne
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/initiate_order_payment.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Réponse de l'endpoint:</h3>";
echo "Code HTTP: <strong>" . $httpCode . "</strong><br>";

if ($error) {
    echo "Erreur cURL: <span style='color:red'>" . $error . "</span><br>";
} else {
    // Séparer headers et body
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    echo "<h4>Headers:</h4>";
    echo "<pre>" . htmlspecialchars($headers) . "</pre>";
    
    echo "<h4>Body:</h4>";
    echo "<pre>" . htmlspecialchars($body) . "</pre>";
    
    // Vérifier si c'est du JSON valide
    $jsonData = json_decode($body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<h4>JSON Décodé:</h4>";
        echo "<pre>" . print_r($jsonData, true) . "</pre>";
    } else {
        echo "<span style='color:red'>⚠️ Réponse n'est pas du JSON valide!</span><br>";
        echo "Erreur JSON: " . json_last_error_msg() . "<br>";
    }
}

// Test 3: Vérifier la structure URL
echo "<h2>3. Vérification structure URL</h2>";
echo "URL de test: http://localhost:8000/api/initiate_order_payment.php<br>";
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script actuel: " . $_SERVER['SCRIPT_NAME'] . "<br>";

// Test 4: Vérifier ROOT_PATH
echo "<h2>4. Vérification ROOT_PATH</h2>";
$rootPath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
echo "ROOT_PATH calculé: '" . $rootPath . "'<br>";
echo "URL complète attendue: http://localhost:8000" . $rootPath . "/api/initiate_order_payment.php<br>";
?>
