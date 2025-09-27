<?php
/**
 * TEST API MOBILE DIRECT
 * Test avec un ID coursier réel
 */

require_once 'config.php';

echo "=== TEST API MOBILE DIRECT ===\n\n";

// 1. Récupérer un coursier de test
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, nom, prenoms FROM agents_suzosky LIMIT 1");
$coursier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coursier) {
    echo "❌ Aucun coursier dans la base\n";
    exit;
}

echo "👤 Test avec coursier: {$coursier['nom']} {$coursier['prenoms']} (ID: {$coursier['id']})\n\n";

// 2. Test cURL GET
echo "📡 TEST GET:\n";
$url = "https://localhost/COURSIER_LOCAL/api/get_coursier_data.php?coursier_id={$coursier['id']}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   Code HTTP: {$httpCode}\n";

if ($error) {
    echo "   ❌ Erreur cURL: {$error}\n";
} else {
    echo "   📄 Réponse: " . substr($response, 0, 200) . "...\n";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "   ✅ JSON valide\n";
        echo "   🎯 Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        if (isset($data['data']['balance'])) {
            echo "   💰 Balance: {$data['data']['balance']} FCFA\n";
        }
    } else {
        echo "   ❌ JSON invalide: " . json_last_error_msg() . "\n";
    }
}

// 3. Test POST
echo "\n📡 TEST POST:\n";
$postData = json_encode(['coursier_id' => $coursier['id']]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://localhost/COURSIER_LOCAL/api/get_coursier_data.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "   Code HTTP: {$httpCode}\n";

if ($error) {
    echo "   ❌ Erreur cURL: {$error}\n";
} else {
    echo "   📄 Réponse: " . substr($response, 0, 200) . "...\n";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "   ✅ JSON valide\n";
        echo "   🎯 Success: " . ($data['success'] ? 'true' : 'false') . "\n";
    } else {
        echo "   ❌ JSON invalide\n";
    }
}

echo "\n✅ TEST TERMINÉ\n";
?>