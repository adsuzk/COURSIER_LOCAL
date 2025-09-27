<?php
require_once __DIR__ . '/config.php';
/**
 * SYSTÈME D'INTÉGRATION CINETPAY - SUZOSKY COURSIER V2.1
 * =====================================================
 * Gestion des paiements pour recharge de comptes coursier
 * - 10% de commission par course
 * - Gestion solde et crédit
 * - Intégration Mobile Money, Wave, Carte bancaire
 */

class SuzoskyCinetPayIntegration {
    
    private $apiKey;
    private $siteId;
    private $secretKey;
    private $apiUrl;
    
    public function __construct() {
        // Configuration CINETPAY fournie par l'utilisateur
        $this->apiKey = '8338609805877a8eaac7eb6.01734650';
        $this->siteId = '5875732';
        $this->secretKey = '830006136690110164ddb1.29156844';
        $this->apiUrl = 'https://api-checkout.cinetpay.com/v2/payment';
    }
    
    /**
     * Initier un paiement de recharge
     */
    public function initiateRecharge($coursierId, $amount, $currency = 'XOF', $description = 'Recharge compte coursier Suzosky') {
        try {
            // Génération d'un ID de transaction unique
            $transactionId = 'SUZOSKY_' . $coursierId . '_' . time() . '_' . rand(1000, 9999);
            
            // Données de la transaction
            $paymentData = [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'return_url' => $this->getReturnUrl(),
                'notify_url' => $this->getNotifyUrl(),
                'metadata' => json_encode([
                    'coursier_id' => $coursierId,
                    'type' => 'recharge',
                    'platform' => 'suzosky_coursier_v2.1'
                ]),
                'customer_surname' => $this->getCourrierName($coursierId),
                'customer_name' => 'Coursier Suzosky',
                'customer_email' => 'coursier' . $coursierId . '@suzosky.com',
                'customer_phone_number' => $this->getCourrierPhone($coursierId),
                'customer_address' => 'Côte d\'Ivoire',
                'customer_city' => 'Abidjan',
                'customer_country' => 'CI',
                'customer_state' => 'Abidjan',
                'customer_zip_code' => '00225'
            ];
            
            // Enregistrer la transaction en BDD
            $this->saveTransactionRecord($transactionId, $coursierId, $amount, 'pending');
            
            // Appel API CINETPAY
            $response = $this->callCinetPayAPI($paymentData);
            
            if ($response && isset($response['code']) && $response['code'] == '201') {
                return [
                    'success' => true,
                    'payment_url' => $response['data']['payment_url'],
                    'transaction_id' => $transactionId,
                    'payment_token' => $response['data']['payment_token']
                ];
            } else {
                throw new Exception('Erreur lors de l\'initialisation du paiement: ' . 
                    (isset($response['message']) ? $response['message'] : 'Erreur inconnue'));
            }
            
        } catch (Exception $e) {
            error_log("Erreur CINETPAY initiation: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus($transactionId) {
        try {
            $checkData = [
                'apikey' => $this->apiKey,
                'site_id' => $this->siteId,
                'transaction_id' => $transactionId
            ];
            
            $response = $this->callCinetPayAPI($checkData, 'https://api-checkout.cinetpay.com/v2/payment/check');
            
            if ($response && isset($response['code']) && $response['code'] == '00') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'amount' => $response['data']['amount'],
                    'operator_id' => $response['data']['operator_id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => $response['message'] ?? 'Paiement échoué'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Erreur vérification paiement: " . $e->getMessage());
            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Traitement des notifications de paiement
     */
    public function handlePaymentNotification($postData) {
        try {
            // Vérification de la signature
            if (!$this->verifyNotificationSignature($postData)) {
                throw new Exception('Signature de notification invalide');
            }
            
            $transactionId = $postData['cpm_trans_id'];
            $amount = $postData['cpm_amount'];
            $status = $postData['cpm_result'];
            
            // Récupérer les détails de la transaction
            $transaction = $this->getTransactionRecord($transactionId);
            
            if (!$transaction) {
                throw new Exception('Transaction non trouvée: ' . $transactionId);
            }
            
            if ($status == '00') {
                // Paiement réussi
                $this->processSuccessfulPayment($transaction, $amount);
                return ['status' => 'success', 'message' => 'Paiement traité avec succès'];
            } else {
                // Paiement échoué
                $this->updateTransactionStatus($transactionId, 'failed');
                return ['status' => 'failed', 'message' => 'Paiement échoué'];
            }
            
        } catch (Exception $e) {
            error_log("Erreur traitement notification: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Traiter un paiement réussi
     */
    private function processSuccessfulPayment($transaction, $amount) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Mettre à jour le statut de la transaction
            $this->updateTransactionStatus($transaction['transaction_id'], 'completed');
            
            // Créditer le compte du coursier
            $coursierId = $transaction['coursier_id'];
            $this->creditCourrierAccount($coursierId, $amount);
            
            // Enregistrer l'historique
            $this->logAccountHistory($coursierId, $amount, 'credit', 'Recharge via CINETPAY');
            
            // Envoyer notification au coursier
            $this->sendRechargeNotification($coursierId, $amount);
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * Créditer le compte d'un coursier
     */
    private function creditCourrierAccount($coursierId, $amount) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("UPDATE coursiers SET credit_balance = credit_balance + ? WHERE id = ?");
        
        if (!$stmt->execute([$amount, $coursierId])) {
            throw new Exception('Erreur lors du crédit du compte');
        }
    }
    
    /**
     * Enregistrer une transaction
     */
    private function saveTransactionRecord($transactionId, $coursierId, $amount, $status) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("INSERT INTO payment_transactions (transaction_id, coursier_id, amount, status, created_at) VALUES (?, ?, ?, ?, NOW())");
        
        if (!$stmt->execute([$transactionId, $coursierId, $amount, $status])) {
            throw new Exception('Erreur lors de l\'enregistrement de la transaction');
        }
    }
    
    /**
     * Mettre à jour le statut d'une transaction
     */
    private function updateTransactionStatus($transactionId, $status) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("UPDATE payment_transactions SET status = ?, updated_at = NOW() WHERE transaction_id = ?");
        
        if (!$stmt->execute([$status, $transactionId])) {
            throw new Exception('Erreur lors de la mise à jour du statut');
        }
    }
    
    /**
     * Récupérer une transaction
     */
    private function getTransactionRecord($transactionId) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM payment_transactions WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        
        return $stmt->fetch();
    }
    
    /**
     * Enregistrer l'historique des comptes
     */
    private function logAccountHistory($coursierId, $amount, $type, $description) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("INSERT INTO account_history (coursier_id, amount, type, description, created_at) VALUES (?, ?, ?, ?, NOW())");
        
        if (!$stmt->execute([$coursierId, $amount, $type, $description])) {
            throw new Exception('Erreur lors de l\'enregistrement de l\'historique');
        }
    }
    
    /**
     * Appel API CINETPAY
     */
    private function callCinetPayAPI($data, $url = null) {
        $apiUrl = $url ?: $this->apiUrl;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Erreur cURL: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erreur HTTP: ' . $httpCode);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Vérifier la signature de notification
     */
    private function verifyNotificationSignature($data) {
        // Implémentation de la vérification de signature selon CINETPAY
        $signature = $data['signature'] ?? '';
        $computedSignature = hash('sha256', 
            $data['cpm_site_id'] . 
            $data['cpm_trans_id'] . 
            $data['cpm_trans_date'] . 
            $data['cpm_amount'] . 
            $data['cpm_currency'] . 
            $data['signature'] . 
            $this->secretKey
        );
        
        return hash_equals($signature, $computedSignature);
    }
    
    /**
     * URLs de callback
     */
    private function getReturnUrl() {
        return appUrl('payment_return.php');
    }
    
    private function getNotifyUrl() {
        return appUrl('payment_notify.php');
    }
    
    /**
     * Récupérer le nom du coursier
     */
    private function getCourrierName($coursierId) {
        $pdo = getDBConnection();
        
    // Le modèle courant utilise agents_suzosky
    $stmt = $pdo->prepare("SELECT nom FROM agents_suzosky WHERE id = ?");
        $stmt->execute([$coursierId]);
        
        $coursier = $stmt->fetch();
        
        return $coursier ? $coursier['nom'] : 'Coursier';
    }
    
    /**
     * Récupérer le téléphone du coursier
     */
    private function getCourrierPhone($coursierId) {
        $pdo = getDBConnection();
        
    // Le modèle courant utilise agents_suzosky
    $stmt = $pdo->prepare("SELECT telephone FROM agents_suzosky WHERE id = ?");
        $stmt->execute([$coursierId]);
        
        $coursier = $stmt->fetch();
        
        return $coursier ? $coursier['telephone'] : '+225';
    }
    
    /**
     * Envoyer notification de recharge
     */
    private function sendRechargeNotification($coursierId, $amount) {
        // Notification système (peut être étendue avec SMS/Email)
        $message = "Votre compte a été crédité de {$amount} FCFA via CINETPAY";
        
        // Log système
        error_log("Notification recharge - Coursier {$coursierId}: {$message}");
    }
    
    /**
     * Calculer le solde disponible (avec commission 10%)
     */
    public function calculateAvailableBalance($deliveryEarnings, $creditBalance) {
        // Le solde = gains des livraisons (moins 10% commission) + crédit de recharge
        $availableFromDeliveries = $deliveryEarnings * 0.9; // 90% des gains (10% commission)
        return $availableFromDeliveries + $creditBalance;
    }
    
    /**
     * Déduire le coût d'une course du solde
     */
    public function deductDeliveryCost($coursierId, $amount) {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();
            
            // Vérifier le solde disponible
            $stmt = $pdo->prepare("SELECT delivery_earnings, credit_balance FROM coursiers WHERE id = ?");
            $stmt->execute([$coursierId]);
            $coursier = $stmt->fetch();
            
            if (!$coursier) {
                throw new Exception('Coursier non trouvé');
            }
            
            $availableBalance = $this->calculateAvailableBalance($coursier['delivery_earnings'], $coursier['credit_balance']);
            
            if ($availableBalance < $amount) {
                throw new Exception('Solde insuffisant');
            }
            
            // Déduire d'abord du crédit, puis des gains
            if ($coursier['credit_balance'] >= $amount) {
                // Déduction complète du crédit
                $stmt = $pdo->prepare("UPDATE coursiers SET credit_balance = credit_balance - ? WHERE id = ?");
                $stmt->execute([$amount, $coursierId]);
            } else {
                // Déduction du crédit + des gains
                $remainingAmount = $amount - $coursier['credit_balance'];
                $stmt = $pdo->prepare("UPDATE coursiers SET credit_balance = 0, delivery_earnings = delivery_earnings - ? WHERE id = ?");
                $stmt->execute([$remainingAmount / 0.9, $coursierId]); // Diviser par 0.9 pour tenir compte de la commission
            }
            
            // Enregistrer l'historique
            $this->logAccountHistory($coursierId, -$amount, 'debit', 'Coût course Suzosky');
            
            $pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
    }
}

// Créer la table des transactions si elle n'existe pas
function createPaymentTables() {
    try {
        $pdo = getDBConnection();
        
        if ($pdo === null) {
            error_log("Erreur: Connexion PDO null dans createPaymentTables()");
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS payment_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(100) UNIQUE NOT NULL,
            coursier_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (coursier_id) REFERENCES coursiers(id)
        )";
        $pdo->exec($sql);

        $sql2 = "CREATE TABLE IF NOT EXISTS account_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coursier_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type ENUM('credit', 'debit') NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (coursier_id) REFERENCES coursiers(id)
        )";
        $pdo->exec($sql2);
        
        // Ajouter les colonnes de balance si elles n'existent pas
        try {
            $pdo->exec("ALTER TABLE coursiers ADD COLUMN delivery_earnings DECIMAL(10,2) DEFAULT 0");
        } catch (PDOException $e) {
            // Colonne existe déjà, ignorer l'erreur
        }
        try {
            $pdo->exec("ALTER TABLE coursiers ADD COLUMN credit_balance DECIMAL(10,2) DEFAULT 0");
        } catch (PDOException $e) {
            // Colonne existe déjà, ignorer l'erreur
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la création des tables de paiement: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("Erreur générale dans createPaymentTables(): " . $e->getMessage());
        return false;
    }
}

?>
