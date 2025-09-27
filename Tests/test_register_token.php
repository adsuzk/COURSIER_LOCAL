<?php
// Test de l'API register_device_token.php
require_once __DIR__ . '/../config.php';

echo "=== TEST REGISTER_DEVICE_TOKEN API ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$coursierId = isset($_GET['coursier_id']) ? (int)$_GET['coursier_id'] : 1;
$agentId = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : 1;

$testData = [
    'coursier_id' => $coursierId,
    'agent_id' => $agentId,
    'token' => 'test_token_' . time() . '_android_device'
];

echo "Données envoyées:\n";
print_r($testData);
echo "\n";

$jsonData = json_encode($testData);
echo "JSON encodé: $jsonData\n\n";

$apiUrl = appUrl('api/register_device_token_simple.php');
echo "URL appelée: $apiUrl\n";

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
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
echo "Réponse: $response\n\n";

$decoded = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "JSON décodé:\n";
    print_r($decoded);
} else {
    echo "Erreur JSON: " . json_last_error_msg() . "\n";
}

echo "\n=== VÉRIFICATION TOKEN EN BASE ===\n";
try {
    $pdo = getDBConnection();
    $columns = $pdo->query('SHOW COLUMNS FROM device_tokens')->fetchAll(PDO::FETCH_COLUMN);

    $coursierCol = 'coursier_id';
    foreach (['coursier_id', 'id_coursier', 'courier_id'] as $candidate) {
        if (in_array($candidate, $columns, true)) {
            $coursierCol = $candidate;
            break;
        }
    }

    $tokenCol = in_array('token', $columns, true) ? 'token' : ($columns[0] ?? 'token');
    $updatedCol = in_array('updated_at', $columns, true) ? 'updated_at' : (in_array('updated', $columns, true) ? 'updated' : null);

    $sql = sprintf(
        'SELECT * FROM device_tokens WHERE `%s` = ? ORDER BY %s LIMIT 5',
        $coursierCol,
        $updatedCol ? "`$updatedCol` DESC" : 'id DESC'
    );
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$coursierId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Tokens enregistrés pour {$coursierCol}={$coursierId}:\n";
    if (!$tokens) {
        echo "(aucun)\n";
    } else {
        foreach ($tokens as $token) {
            $preview = isset($token[$tokenCol]) ? substr($token[$tokenCol], 0, 40) . '...' : '(token manquant)';
            $updated = $updatedCol && isset($token[$updatedCol]) ? $token[$updatedCol] : '(n/a)';
            $id = $token['id'] ?? '(id?)';
            echo "- ID: {$id}, Token: {$preview}, Mis à jour: {$updated}\n";
        }
    }
} catch (Exception $e) {
    echo "Erreur vérification DB: " . $e->getMessage() . "\n";
}

echo "\n=== FIN TEST ===\n";
?>