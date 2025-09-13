<?php
// Using local integration class to avoid missing vendor SDK
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cinetpay/cinetpay_integration.php';
require_once __DIR__ . '/../logger.php';
header('Content-Type: application/json');
// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Si ce n'est pas du JSON valide, essayer avec $_POST (FormData)
if (json_last_error() !== JSON_ERROR_NONE) {
    if (!empty($_POST)) {
        $data = $_POST;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données invalides - ni JSON ni FormData']);
        exit;
    }
}

 // Accept both snake_case and camelCase order number
 $orderNumber = $data['order_number'] ?? $data['orderNumber'] ?? '';
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;

if (!$orderNumber || $amount <= 0) {
    http_response_code(400);
    logMessage('diagnostics_errors.log', 'Missing payment data in initiate_order_payment.php: ' . json_encode($data));
    echo json_encode(['success' => false, 'message' => 'Données de paiement manquantes']);
    exit;
}

// Log initiation paiement
logMessage('diagnostics_db.log', 'Initiation paiement pour order_number: ' . $orderNumber);
// Extract optional contact info
$phone = $data['phone'] ?? $data['senderPhone'] ?? '';
$email = $data['email'] ?? '';

try {
    // Utilisation de la classe d'intégration interne
    $integration = new SuzoskyCinetPayIntegration();
    $response = $integration->initiateOrderPayment(
        $orderNumber,
        $amount,
        'XOF',
        'Paiement commande ' . $orderNumber,
        $phone,
        $email
    );
    if (!empty($response['success'])) {
        logMessage('diagnostics_db.log', 'Paiement initié URL: ' . $response['payment_url']);
        echo json_encode([
            'success' => true,
            'payment_url' => $response['payment_url'],
            'transaction_id' => $response['transaction_id']
        ]);
    } else {
        // Log error details
        logMessage('diagnostics_errors.log', 'Paiement error: ' . ($response['error'] ?? json_encode($response)));
        http_response_code(500);
    logMessage('diagnostics_errors.log', 'Payment init error: ' . ($response['error'] ?? json_encode($response)));
    echo json_encode(['success' => false, 'message' => $response['error'] ?? 'Erreur initiation paiement']);
    }
} catch (Exception $e) {
    logMessage('diagnostics_errors.log', 'Exception paiement: ' . $e->getMessage());
    logMessage('diagnostics_errors.log', 'Exception in initiate_order_payment.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
