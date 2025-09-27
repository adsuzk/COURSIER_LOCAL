<?php
/**
 * Webhook CinetPay pour les notifications de paiement
 * Réception automatique des confirmations de paiement
 * 
 * URL à configurer dans CinetPay: https://votre-domaine.com/webhook_cinetpay.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/lib/finances_sync.php';

// Compatibilité CLI: définir getallheaders si absent
if (!function_exists('getallheaders')) {
    function getallheaders(): array {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }
}

// Log de la requête entrante
$input = file_get_contents('php://input');
$headers = getallheaders();
logActivity("WEBHOOK_CINETPAY_RECEIVED", "Headers: " . json_encode($headers) . " | Body: " . $input);

// Décoder les données JSON
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    logError("WEBHOOK_CINETPAY_ERROR", "JSON invalide: " . $input);
    echo json_encode(['status' => 'error', 'message' => 'JSON invalide']);
    exit;
}

// Vérification des données obligatoires
$required_fields = ['cpm_trans_id', 'cpm_result', 'cpm_trans_status'];
foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        logError("WEBHOOK_CINETPAY_ERROR", "Champ manquant: {$field}");
        echo json_encode(['status' => 'error', 'message' => "Champ manquant: {$field}"]);
        exit;
    }
}

try {
    $pdo = getPDO();
    
    // Extraire les données du webhook
    $transaction_id = $data['cpm_trans_id'];
    $result = $data['cpm_result'];
    $status = $data['cpm_trans_status'];
    $amount = isset($data['cpm_amount']) ? floatval($data['cpm_amount']) : 0;
    $site_id = isset($data['cpm_site_id']) ? $data['cpm_site_id'] : '';
    $order_id = isset($data['cpm_order_id']) ? $data['cpm_order_id'] : '';
    $phone = isset($data['cpm_phone_number']) ? $data['cpm_phone_number'] : '';
    $payment_method = isset($data['cpm_payment_method']) ? $data['cpm_payment_method'] : '';
    
    // Déterminer le type de paiement selon le Site ID
    $payment_type = 'unknown';
    if ($site_id === '5875732') {
        $payment_type = 'client_order'; // Paiement de commande client
    } elseif ($site_id === '219503') {
        $payment_type = 'coursier_recharge'; // Recharge de compte coursier
    }
    
    logActivity("WEBHOOK_CINETPAY_PROCESS", "Transaction: {$transaction_id}, Type: {$payment_type}, Status: {$status}, Result: {$result}");
    
    // Créer la table des notifications webhook si elle n'existe pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webhook_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(100) NOT NULL,
            payment_type ENUM('client_order', 'coursier_recharge', 'unknown') DEFAULT 'unknown',
            result VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) DEFAULT 0,
            site_id VARCHAR(50),
            order_id VARCHAR(100),
            phone VARCHAR(20),
            payment_method VARCHAR(50),
            raw_data JSON,
            processed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            INDEX idx_transaction (transaction_id),
            INDEX idx_order (order_id),
            INDEX idx_processed (processed),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Enregistrer la notification
    $insert_webhook = $pdo->prepare("
        INSERT INTO webhook_notifications 
        (transaction_id, payment_type, result, status, amount, site_id, order_id, phone, payment_method, raw_data) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $insert_webhook->execute([
        $transaction_id, $payment_type, $result, $status, $amount, 
        $site_id, $order_id, $phone, $payment_method, json_encode($data)
    ]);
    
    $webhook_id = $pdo->lastInsertId();
    
    // Traitement selon le type et le statut
    if ($result === '00' && $status === 'ACCEPTED') {
        // Paiement réussi
        switch ($payment_type) {
            case 'client_order':
                processClientOrderPayment($pdo, $order_id, $transaction_id, $amount, $phone);
                break;
                
            case 'coursier_recharge':
                processCoursierRecharge($pdo, $order_id, $transaction_id, $amount, $phone);
                break;
                
            default:
                logError("WEBHOOK_CINETPAY_UNKNOWN", "Type de paiement inconnu: {$payment_type} pour transaction {$transaction_id}");
        }
        
        // Marquer comme traité
        $update_processed = $pdo->prepare("
            UPDATE webhook_notifications 
            SET processed = TRUE, processed_at = NOW() 
            WHERE id = ?
        ");
        $update_processed->execute([$webhook_id]);
        
    } elseif ($result !== '00' || $status === 'REFUSED') {
        // Paiement échoué ou refusé
        logError("WEBHOOK_CINETPAY_FAILED", "Paiement échoué - Transaction: {$transaction_id}, Result: {$result}, Status: {$status}");
        
        // Traiter l'échec selon le type
        switch ($payment_type) {
            case 'client_order':
                handleFailedOrderPayment($pdo, $order_id, $transaction_id, $result);
                break;
                
            case 'coursier_recharge':
                handleFailedRecharge($pdo, $order_id, $transaction_id, $result);
                break;
        }
    }
    
    // Réponse de succès pour CinetPay
    http_response_code(200);
    echo json_encode([
        'status' => 'success', 
        'message' => 'Webhook traité avec succès',
        'webhook_id' => $webhook_id
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    logError("WEBHOOK_CINETPAY_EXCEPTION", "Erreur traitement webhook: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Erreur serveur']);
}

/**
 * Traiter un paiement de commande client réussi
 */
function processClientOrderPayment($pdo, $order_id, $transaction_id, $amount, $phone) {
    try {
        // Chercher la commande
        $find_order = $pdo->prepare("
            SELECT id, statut, montant_total 
            FROM commandes 
            WHERE numero_commande = ? OR id = ?
        ");
        $find_order->execute([$order_id, $order_id]);
        $commande = $find_order->fetch();
        
        if ($commande) {
            // Mettre à jour le statut de la commande
            $update_order = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'paye', 
                    transaction_id = ?,
                    date_paiement = NOW(),
                    date_modification = NOW()
                WHERE id = ?
            ");
            $update_order->execute([$transaction_id, $commande['id']]);
            
            logActivity("PAYMENT_SUCCESS", "Commande #{$commande['id']} payée - Transaction: {$transaction_id}, Montant: {$amount} FCFA");

            // Enregistrer la transaction dans le journal financier pour visibilité immédiate côté admin
            // Créer la table si nécessaire (même schéma que pour les recharges)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS transactions_financieres (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type ENUM('credit', 'debit') NOT NULL,
                    montant DECIMAL(10,2) NOT NULL,
                    compte_type ENUM('coursier', 'client') NOT NULL,
                    compte_id INT NOT NULL,
                    reference VARCHAR(100) NOT NULL,
                    description TEXT,
                    statut ENUM('en_attente', 'reussi', 'echoue') DEFAULT 'reussi',
                    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            // Insérer une ligne de transaction côté "client" (paiement client reçu)
            $insTxn = $pdo->prepare("
                INSERT INTO transactions_financieres (type, montant, compte_type, compte_id, reference, description, statut)
                VALUES ('credit', ?, 'client', 0, ?, ?, 'reussi')
            ");
            $insTxn->execute([
                $amount,
                $transaction_id,
                "Paiement client commande #{$order_id} - Transaction: {$transaction_id}"
            ]);
            
            // Optionnel: Notifier le coursier si assigné
            // ... code de notification ...
            
        } else {
            logError("PAYMENT_ORDER_NOT_FOUND", "Commande non trouvée pour order_id: {$order_id}");
        }
        
    } catch (Exception $e) {
        logError("PROCESS_ORDER_PAYMENT_ERROR", "Erreur traitement paiement commande: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Traiter une recharge coursier réussie
 */
function processCoursierRecharge($pdo, $order_id, $transaction_id, $amount, $phone) {
    try {
        // Chercher la demande de recharge
        $find_recharge = $pdo->prepare("
            SELECT r.*, c.nom as coursier_nom 
            FROM recharges_coursiers r
            LEFT JOIN coursiers c ON r.coursier_id = c.id
            WHERE r.reference_paiement = ? OR r.id = ?
        ");
        $find_recharge->execute([$order_id, $order_id]);
        $recharge = $find_recharge->fetch();
        
        if ($recharge) {
            // Mettre à jour la recharge
            $update_recharge = $pdo->prepare("
                UPDATE recharges_coursiers 
                SET statut = 'validee', 
                    transaction_id = ?,
                    date_validation = NOW()
                WHERE id = ?
            ");
            $update_recharge->execute([$transaction_id, $recharge['id']]);
            
            // Créditer le compte coursier
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS comptes_coursiers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    coursier_id INT NOT NULL UNIQUE,
                    solde DECIMAL(10,2) DEFAULT 0.00,
                    statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
                    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (coursier_id) REFERENCES coursiers(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $credit_account = $pdo->prepare("
                INSERT INTO comptes_coursiers (coursier_id, solde) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE 
                solde = solde + VALUES(solde),
                date_modification = NOW()
            ");
            $credit_account->execute([$recharge['coursier_id'], $amount]);

            adjustCoursierRechargeBalance($pdo, (int)$recharge['coursier_id'], (float)$amount, [
                'reason' => 'recharge',
                'affect_total' => true,
            ]);
            
            // Enregistrer la transaction
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS transactions_financieres (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type ENUM('credit', 'debit') NOT NULL,
                    montant DECIMAL(10,2) NOT NULL,
                    compte_type ENUM('coursier', 'client') NOT NULL,
                    compte_id INT NOT NULL,
                    reference VARCHAR(100) NOT NULL,
                    description TEXT,
                    statut ENUM('en_attente', 'reussi', 'echoue') DEFAULT 'reussi',
                    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $insert_transaction = $pdo->prepare("
                INSERT INTO transactions_financieres 
                (type, montant, compte_type, compte_id, reference, description, statut) 
                VALUES ('credit', ?, 'coursier', ?, ?, ?, 'reussi')
            ");
            $insert_transaction->execute([
                $amount, 
                $recharge['coursier_id'], 
                $transaction_id,
                "Recharge compte via CinetPay - Transaction: {$transaction_id}"
            ]);
            
            logActivity("RECHARGE_SUCCESS", "Recharge coursier #{$recharge['coursier_id']} ({$recharge['coursier_nom']}) - {$amount} FCFA - Transaction: {$transaction_id}");
            
        } else {
            logError("RECHARGE_NOT_FOUND", "Demande de recharge non trouvée pour order_id: {$order_id}");
        }
        
    } catch (Exception $e) {
        logError("PROCESS_RECHARGE_ERROR", "Erreur traitement recharge: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Gérer un échec de paiement de commande
 */
function handleFailedOrderPayment($pdo, $order_id, $transaction_id, $result) {
    try {
        $find_order = $pdo->prepare("
            SELECT id 
            FROM commandes 
            WHERE numero_commande = ? OR id = ?
        ");
        $find_order->execute([$order_id, $order_id]);
        $commande = $find_order->fetch();
        
        if ($commande) {
            $update_order = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'paiement_echoue',
                    transaction_id = ?,
                    date_modification = NOW()
                WHERE id = ?
            ");
            $update_order->execute([$transaction_id, $commande['id']]);
            
            logActivity("PAYMENT_FAILED", "Échec paiement commande #{$commande['id']} - Transaction: {$transaction_id}, Code: {$result}");
        }
        
    } catch (Exception $e) {
        logError("HANDLE_FAILED_PAYMENT_ERROR", "Erreur gestion échec paiement: " . $e->getMessage());
    }
}

/**
 * Gérer un échec de recharge
 */
function handleFailedRecharge($pdo, $order_id, $transaction_id, $result) {
    try {
        $find_recharge = $pdo->prepare("
            SELECT id 
            FROM recharges_coursiers 
            WHERE reference_paiement = ? OR id = ?
        ");
        $find_recharge->execute([$order_id, $order_id]);
        $recharge = $find_recharge->fetch();
        
        if ($recharge) {
            $update_recharge = $pdo->prepare("
                UPDATE recharges_coursiers 
                SET statut = 'refusee',
                    transaction_id = ?,
                    motif_refus = ?,
                    date_validation = NOW()
                WHERE id = ?
            ");
            $update_recharge->execute([$transaction_id, "Paiement refusé - Code: {$result}", $recharge['id']]);
            
            logActivity("RECHARGE_FAILED", "Échec recharge #{$recharge['id']} - Transaction: {$transaction_id}, Code: {$result}");
        }
        
    } catch (Exception $e) {
        logError("HANDLE_FAILED_RECHARGE_ERROR", "Erreur gestion échec recharge: " . $e->getMessage());
    }
}
?>