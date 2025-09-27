<?php
// api/cinetpay_stub.php - Simule l'API CinetPay en environnement local
// Répond comme l'endpoint officiel avec un code 201 et une URL de paiement factice

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if (isProductionEnvironment()) {
    http_response_code(400);
    echo json_encode(['code' => '400', 'message' => 'Stub non autorisé en production']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    // Essayer en x-www-form-urlencoded
    parse_str($raw, $data);
}

$tx = $data['transaction_id'] ?? ('LOCAL_' . time());
$amount = $data['amount'] ?? 0;
$desc = $data['description'] ?? 'Paiement test local';

// URL de paiement simulée (page simple qui affiche succès)
$paymentUrl = appUrl('cinetpay/stub_payment_page.php?tid=' . urlencode($tx) . '&amount=' . urlencode($amount));

http_response_code(201);
echo json_encode([
    'code' => '201',
    'message' => 'CREATED',
    'data' => [
        'payment_url' => $paymentUrl,
        'payment_token' => base64_encode($tx),
        'description' => $desc
    ]
]);
