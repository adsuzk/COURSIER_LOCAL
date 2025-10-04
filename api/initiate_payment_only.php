<?php
/**
 * initiate_payment_only.php
 * Génère uniquement l'URL de paiement CinetPay SANS enregistrer la commande
 * La commande sera enregistrée APRÈS confirmation du paiement
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cinetpay/config.php';

// Log de démarrage
error_log("[PAYMENT_ONLY] === Initialisation paiement SANS enregistrement commande ===");

try {
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Méthode non autorisée");
    }
    
    // Récupérer les données (support JSON et x-www-form-urlencoded)
    $raw = file_get_contents('php://input');
    $json = [];
    if (!empty($raw)) {
        $tmp = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($tmp)) {
            $json = $tmp;
        }
    }
    // Fusion douce: JSON prioritaire, sinon POST
    $orderNumber = $json['order_number'] ?? ($_POST['order_number'] ?? ('SZK' . time()));
    $amountVal = $json['amount'] ?? ($_POST['amount'] ?? 1500);
    $amount = intval($amountVal);
    if ($amount <= 0) { $amount = 1500; }
    
    // Générer les informations client
    $clientName = $json['client_name'] ?? ($_POST['client_name'] ?? 'Client Suzosky');
    $clientPhone = $json['client_phone'] ?? ($_POST['client_phone'] ?? '');
    $clientEmail = $json['client_email'] ?? ($_POST['client_email'] ?? 'client@suzosky.com');
    
    error_log("[PAYMENT_ONLY] Génération URL paiement - Commande: $orderNumber, Montant: $amount FCFA");
    
    // Configuration CinetPay
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    // Utiliser les credentials CLIENT pour le site index (distincts de l'app coursier)
    $clientCfg = getClientCinetPayConfig();
    $cinetpay_config = [
        'apikey' => $clientCfg['apikey'],
        'site_id' => $clientCfg['site_id'],
        'notify_url' => $baseUrl . '/COURSIER_LOCAL/api/cinetpay_callback.php',
        'return_url' => $baseUrl . '/COURSIER_LOCAL/?payment_success=1',
        'cancel_url' => $baseUrl . '/COURSIER_LOCAL/?payment_cancelled=1',
    ];
    
    // Préparer la requête CinetPay
    $paymentData = [
        'apikey' => $cinetpay_config['apikey'],
        'site_id' => $cinetpay_config['site_id'],
        'transaction_id' => $orderNumber,
        'amount' => $amount,
        'currency' => 'XOF',
        'description' => "Course Suzosky - Commande $orderNumber",
        'customer_name' => $clientName,
        'customer_surname' => 'Client',
        'customer_email' => $clientEmail,
        'customer_phone_number' => $clientPhone,
        'customer_address' => 'Abidjan',
        'customer_city' => 'Abidjan',
        'customer_country' => 'CI',
        'customer_state' => 'CI',
        'customer_zip_code' => '00225',
        'notify_url' => $cinetpay_config['notify_url'],
        'return_url' => $cinetpay_config['return_url'],
        'cancel_url' => $cinetpay_config['cancel_url'],
        'metadata' => json_encode([
            'order_type' => 'course',
            'order_number' => $orderNumber,
            'created_at' => date('Y-m-d H:i:s')
        ])
    ];
    
    // Appeler l'API CinetPay
    $ch = curl_init(($clientCfg['endpoint'] ?? 'https://api-checkout.cinetpay.com/v2/payment'));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("Erreur CURL: $curlError");
    }
    
    $cinetpayResponse = json_decode($response, true);
    
    if ($httpCode === 200 && isset($cinetpayResponse['data']['payment_url'])) {
        $paymentUrl = $cinetpayResponse['data']['payment_url'];
        
        error_log("[PAYMENT_ONLY] ✅ URL générée: $paymentUrl");
        
        echo json_encode([
            'success' => true,
            'payment_url' => $paymentUrl,
            'transaction_id' => $orderNumber,
            'message' => 'URL de paiement générée avec succès'
        ]);
    } else {
        $errorMsg = $cinetpayResponse['message'] ?? 'Erreur inconnue';
        error_log("[PAYMENT_ONLY] ❌ Échec CinetPay: $errorMsg");
        
        echo json_encode([
            'success' => false,
            'message' => "Erreur CinetPay: $errorMsg"
        ]);
    }
    
} catch (Exception $e) {
    error_log("[PAYMENT_ONLY] ❌ ERREUR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
