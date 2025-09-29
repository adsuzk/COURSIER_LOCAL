<?php
// Test FCM HTTP v1 direct
require_once __DIR__ . '/../config.php';

echo "=== TEST FCM DIRECT HTTP v1 ===\n";

// 1. Lire le fichier service account
$saPath = __DIR__ . '/../coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json';
if (!file_exists($saPath)) {
    die("Fichier service account non trouvé: $saPath\n");
}

$sa = json_decode(file_get_contents($saPath), true);
if (!$sa) {
    die("Fichier service account invalide\n");
}

echo "✓ Service Account chargé - Project ID: {$sa['project_id']}\n";

// 2. Générer JWT pour obtenir access token
function generateJWT($serviceAccount) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
    $now = time();
    $payload = json_encode([
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = '';
    $success = openssl_sign($base64Header . '.' . $base64Payload, $signature, $serviceAccount['private_key'], 'sha256WithRSAEncryption');
    if (!$success) {
        throw new Exception('Erreur signature JWT');
    }
    
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
}

function getAccessToken($serviceAccount) {
    $jwt = generateJWT($serviceAccount);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Erreur OAuth: HTTP $httpCode - $result");
    }
    
    $data = json_decode($result, true);
    return $data['access_token'] ?? null;
}

try {
    echo "Génération JWT...\n";
    $accessToken = getAccessToken($sa);
    if (!$accessToken) {
        die("Erreur: Impossible d'obtenir access token\n");
    }
    echo "✓ Access Token obtenu: " . substr($accessToken, 0, 20) . "...\n";
    
    // 3. Test avec un token topic au lieu d'un token device
    echo "Test 1: Topic notification...\n";
    
    $message1 = [
        'topic' => 'test_topic',
        'data' => [
            'title' => 'Test FCM Suzosky Topic',
            'body' => 'Vérification configuration Firebase via topic',
            'type' => 'test_direct'
        ]
    ];
    
    $payload1 = ['message' => $message1, 'validate_only' => true];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload1));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $result1 = curl_exec($ch);
    $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Topic Test - HTTP: $httpCode1, Réponse: $result1\n\n";
    
    // 4. Test avec un format de token valide (simulé mais bien formaté)
    echo "Test 2: Token simulé mais bien formaté...\n";
    $simulatedToken = str_repeat('A', 152) . ':' . str_repeat('B', 10); // Format FCM typique
    
    $message = [
        'token' => $simulatedToken,
        'data' => [
            'title' => 'Test FCM Suzosky',
            'body' => 'Vérification configuration Firebase',
            'type' => 'test_direct'
        ],
        'android' => [
            'priority' => 'HIGH'
        ]
    ];
    
    $payload = ['message' => $message, 'validate_only' => true];
    
    echo "Envoi notification test (validate_only)...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Résultat HTTP: $httpCode\n";
    echo "Réponse: $result\n";
    
    if ($httpCode === 200) {
        echo "✓ Configuration Firebase VALIDE - Prêt pour notifications réelles\n";
    } else {
        echo "✗ Erreur dans la configuration Firebase\n";
    }
    
} catch (Exception $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}

echo "=== FIN TEST FCM ===\n";
?>