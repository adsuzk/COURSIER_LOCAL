<?php
/**
 * CONFIGURATION CINETPAY - SUZOSKY COURSIER
 * Configuration centralisée pour l'intégration CinetPay
 * 
 * @version 2.1.0
 * @author Suzosky Development Team
 * @date 2025-09-05
 */

// Configuration CinetPay
define('CINETPAY_API_KEY', 'YOUR_API_KEY_HERE'); // À configurer en production
define('CINETPAY_SITE_ID', 'YOUR_SITE_ID_HERE'); // À configurer en production
define('CINETPAY_SECRET_KEY', 'YOUR_SECRET_KEY_HERE'); // À configurer en production

// URLs de notification et de retour
define('CINETPAY_NOTIFY_URL', 'https://coursier.conciergerie-privee-suzosky.com/cinetpay/payment_notify.php');
define('CINETPAY_RETURN_URL', 'https://coursier.conciergerie-privee-suzosky.com/cinetpay/payment_return.php');

// Configuration générale
define('CINETPAY_CURRENCY', 'XOF'); // Franc CFA
define('CINETPAY_LANG', 'fr'); // Français
define('CINETPAY_ENV', 'PROD'); // 'PROD' ou 'TEST'

// URLs API CinetPay
define('CINETPAY_API_URL', 'https://api-checkout.cinetpay.com/v2/payment');
define('CINETPAY_CHECK_URL', 'https://api-checkout.cinetpay.com/v2/payment/check');

// Configuration des méthodes de paiement acceptées
$CINETPAY_PAYMENT_METHODS = [
    'ORANGE_MONEY_CI' => 'Orange Money',
    'MTN_MONEY_CI' => 'MTN Mobile Money', 
    'MOOV_MONEY_CI' => 'Moov Money',
    'WAVE_CI' => 'Wave',
    'VISA_CARD' => 'Carte Visa',
    'MASTER_CARD' => 'Carte MasterCard'
];

// Configuration des timeouts
define('CINETPAY_TIMEOUT', 30); // Timeout en secondes
define('CINETPAY_MAX_RETRIES', 3); // Nombre maximum de tentatives

// Configuration des logs
define('CINETPAY_LOG_ENABLED', true);
define('CINETPAY_LOG_PATH', __DIR__ . '/logs/');
define('CINETPAY_LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Configuration des montants
define('CINETPAY_MIN_AMOUNT', 100); // Montant minimum en FCFA
define('CINETPAY_MAX_AMOUNT', 500000); // Montant maximum en FCFA

// Configuration des frais
define('CINETPAY_FEES_ENABLED', false); // Frais à la charge du client
define('CINETPAY_FEES_PERCENTAGE', 0); // Pourcentage de frais

// Messages de statut
$CINETPAY_STATUS_MESSAGES = [
    'ACCEPTED' => 'Paiement accepté avec succès',
    'REFUSED' => 'Paiement refusé',
    'CANCELLED' => 'Paiement annulé par l\'utilisateur',
    'PENDING' => 'Paiement en attente',
    'FAILED' => 'Échec du paiement'
];

// Configuration de sécurité
define('CINETPAY_HASH_ALGO', 'sha256');
define('CINETPAY_SIGNATURE_REQUIRED', true);

// Configuration de développement
if (CINETPAY_ENV === 'TEST') {
    // URLs de test
    define('CINETPAY_TEST_API_URL', 'https://api-checkout.cinetpay.com/v2/payment');
    define('CINETPAY_TEST_CHECK_URL', 'https://api-checkout.cinetpay.com/v2/payment/check');
    
    // Données de test
    define('CINETPAY_TEST_API_KEY', 'test_api_key');
    define('CINETPAY_TEST_SITE_ID', 'test_site_id');
}

/**
 * Fonction pour récupérer la configuration CinetPay
 * @return array Configuration complète
 */
function getCinetPayConfig() {
    return [
        'api_key' => CINETPAY_API_KEY,
        'site_id' => CINETPAY_SITE_ID,
        'secret_key' => CINETPAY_SECRET_KEY,
        'notify_url' => CINETPAY_NOTIFY_URL,
        'return_url' => CINETPAY_RETURN_URL,
        'currency' => CINETPAY_CURRENCY,
        'lang' => CINETPAY_LANG,
        'env' => CINETPAY_ENV,
        'api_url' => CINETPAY_API_URL,
        'check_url' => CINETPAY_CHECK_URL,
        'timeout' => CINETPAY_TIMEOUT,
        'max_retries' => CINETPAY_MAX_RETRIES,
        'min_amount' => CINETPAY_MIN_AMOUNT,
        'max_amount' => CINETPAY_MAX_AMOUNT
    ];
}

/**
 * Validation de la configuration CinetPay
 * @return bool True si la configuration est valide
 */
function validateCinetPayConfig() {
    $required = ['CINETPAY_API_KEY', 'CINETPAY_SITE_ID', 'CINETPAY_SECRET_KEY'];
    
    foreach ($required as $constant) {
        if (!defined($constant) || constant($constant) === 'YOUR_' . substr($constant, 9) . '_HERE') {
            return false;
        }
    }
    
    return true;
}

/**
 * Logger pour CinetPay
 * @param string $message Message à logger
 * @param string $level Niveau de log
 */
function logCinetPay($message, $level = 'INFO') {
    if (!CINETPAY_LOG_ENABLED) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    $logFile = CINETPAY_LOG_PATH . 'cinetpay_' . date('Y-m-d') . '.log';
    
    // Créer le dossier de logs s'il n'existe pas
    if (!is_dir(CINETPAY_LOG_PATH)) {
        mkdir(CINETPAY_LOG_PATH, 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}
