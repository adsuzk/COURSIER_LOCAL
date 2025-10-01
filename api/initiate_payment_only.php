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
    
    // Récupérer les données
    $orderNumber = $_POST['order_number'] ?? 'SZK' . time();
    $amount = intval($_POST['amount'] ?? 1500);
    
    // Générer les informations client
    $clientName = $_POST['client_name'] ?? 'Client Suzosky';
    $clientPhone = $_POST['client_phone'] ?? '';
    $clientEmail = $_POST['client_email'] ?? 'client@suzosky.com';
    
    error_log("[PAYMENT_ONLY] Génération URL paiement - Commande: $orderNumber, Montant: $amount FCFA");
    
    // Configuration CinetPay
    $cinetpay_config = [
        'apikey' => CINETPAY_API_KEY,
        'site_id' => CINETPAY_SITE_ID,
        'notify_url' => SITE_URL . '/api/cinetpay_callback.php',
        'return_url' => SITE_URL . '/index.php?payment_success=1',
        'cancel_url' => SITE_URL . '/index.php?payment_cancelled=1',
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
    $ch = curl_init('https://api-checkout.cinetpay.com/v2/payment');
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
