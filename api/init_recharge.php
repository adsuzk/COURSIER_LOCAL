<?php
// api/init_recharge.php
require_once __DIR__ . '/../config.php';
// Disable error display to prevent invalid JSON
ini_set('display_errors', '0');
error_reporting(0);

// Send JSON header before any other output
header('Content-Type: application/json; charset=utf-8');

// DEBUG: Log des paramètres reçus
error_log("🔍 DEBUG init_recharge.php - Méthode: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log("🔍 GET params: " . json_encode($_GET));
error_log("🔍 POST params: " . json_encode($_POST));
error_log("🔍 Raw input: " . file_get_contents('php://input'));

// Récupérer les paramètres
$userId = intval($_POST['coursier_id'] ?? $_GET['coursier_id'] ?? 0);
$amount = floatval($_POST['montant'] ?? $_GET['montant'] ?? 0);

error_log("🔍 Paramètres extraits - userId: $userId, amount: $amount");

if ($userId <= 0 || $amount <= 0) {
    error_log("❌ Paramètres invalides détectés");
    http_response_code(400);
    echo json_encode([
        'success'=>false,
        'error'=>'Paramètres invalides',
        'debug' => [
            'userId' => $userId,
            'amount' => $amount,
            'post' => $_POST,
            'get' => $_GET
        ]
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    // Auto-créer la table recharges si elle n'existe pas
    $pdo->exec("CREATE TABLE IF NOT EXISTS recharges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coursier_id INT NOT NULL,
        montant DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'XOF',
        cinetpay_transaction_id VARCHAR(255) UNIQUE,
        status ENUM('pending','success','failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        details JSON,
        INDEX idx_coursier (coursier_id),
        INDEX idx_status (status),
        FOREIGN KEY (coursier_id) REFERENCES clients_particuliers(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    // Insérer transaction pending
    $stmt = $pdo->prepare("INSERT INTO recharges (coursier_id, montant, currency, status) VALUES (?, ?, 'XOF', 'pending')");
    $stmt->execute([$userId, $amount]);
    $rechargeId = $pdo->lastInsertId();

    // FORCER L'UTILISATION DE CINETPAY - PAS DE MODE TEST
    // Toujours utiliser CinetPay maintenant
    /*
    if (!isProductionEnvironment() && empty($_POST['force_prod'])) {
        // Mode test désactivé - toujours utiliser CinetPay
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        if ($host === '127.0.0.1' || $host === 'localhost') {
            $host = '10.0.2.2';
        }
        $basePath = rtrim(str_replace(basename($script), '', $script), '/');
        $paymentUrl = "$scheme://$host$basePath/test_fetch.html";
        echo json_encode(['success' => true, 'payment_url' => $paymentUrl]);
        exit;
    }
    */
    // Appel API CinetPay (v2) - utilise les credentials centralisés
    $cp = getCinetPayConfig();
    $url = $cp['endpoint'] ?? 'https://api-checkout.cinetpay.com/v2/payment';
    // Construire les URLs de callback basées sur l'hôte courant
    // URL de callback basée sur helpers (fonctionne en sous-dossier/local/prod)
    $callbackUrl = appUrl('api/cinetpay_callback.php');
    $data = [
        'apikey'        => $cp['apikey'] ?? '',
        'site_id'       => $cp['site_id'] ?? '',
        'transaction_id'=> $rechargeId . '_' . time(),
        'amount'        => $amount,
        'currency'      => 'XOF',
        'description'   => "Recharge coursier #$userId",
        'return_url'    => $callbackUrl,
        'notify_url'    => $callbackUrl
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 secondes
    
    // DEBUG: Log de la requête CinetPay
    error_log("🔄 Requête CinetPay - URL: $url");
    error_log("🔄 Données envoyées: " . json_encode($data));
    
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("📥 Réponse CinetPay - Code HTTP: $httpCode, Erreur CURL: $errno");
    error_log("📥 Réponse brute: " . $response);

    if ($errno) throw new Exception("Erreur CURL: $errno");
    
    $resp = json_decode($response, true);
    error_log("🔍 JSON parsé: " . json_encode($resp));
    
    // CinetPay v2: '201' = CREATED (succès), '00' = succès (ancienne API)
    $successCodes = ['00', '201'];
    if (!$resp || !isset($resp['code']) || !in_array($resp['code'], $successCodes)) {
        $errorMsg = 'CinetPay error: ' . ($resp['message'] ?? $resp['description'] ?? 'Code: ' . ($resp['code'] ?? 'unknown'));
        error_log("❌ Erreur CinetPay: $errorMsg");
        throw new Exception($errorMsg);
    }
    
    $paymentUrl = $resp['data']['payment_url'] ?? null;
    if (!$paymentUrl) {
        throw new Exception('Pas d\'URL de paiement dans la réponse CinetPay');
    }
    
    error_log("✅ URL de paiement extraite: $paymentUrl");
    echo json_encode(['success'=>true,'payment_url'=>$paymentUrl]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Échec init recharge: '.$e->getMessage()]);
}
