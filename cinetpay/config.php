<?php
require_once dirname(__DIR__) . '/config.php';
/**
 * CONFIGURATION CINETPAY - SUZOSKY COURSIER
 * Centralisée et dérivée de getCinetPayConfig() pour éviter les valeurs codées en dur.
 */

$cp = getCinetPayConfig('coursier'); // par défaut, l'app coursier
$mode = strtolower($cp['mode'] ?? 'live');
$useStub = ($mode === 'stub');

define('CINETPAY_MODE', $mode);

// Clés/API
define('CINETPAY_API_KEY', $cp['apikey']);
define('CINETPAY_SITE_ID', $cp['site_id']);
define('CINETPAY_SECRET_KEY', $cp['secret_key']);

// URLs de notification et de retour (dynamiques selon l'environnement)
define('CINETPAY_NOTIFY_URL', appUrl('cinetpay/payment_notify.php'));
define('CINETPAY_RETURN_URL', appUrl('cinetpay/payment_return.php'));

// Configuration générale
define('CINETPAY_CURRENCY', 'XOF'); // Franc CFA
define('CINETPAY_LANG', 'fr'); // Français
define('CINETPAY_ENV', $useStub ? 'TEST' : 'PROD'); // 'PROD' ou 'TEST'

// URLs API CinetPay (en DEV, endpoint stub local via getCinetPayConfig)
define('CINETPAY_API_URL', $cp['endpoint']);

$customCheck = getenv('CINETPAY_CHECK_ENDPOINT');
$checkEndpoint = $customCheck ?: (function () use ($cp) {
    $base = rtrim($cp['endpoint'], '/');
    if (substr($base, -6) === '/check') {
        return $base;
    }
    if (substr($base, -8) === '/payment') {
        return $base . '/check';
    }
    return $base;
})();

define('CINETPAY_CHECK_URL', $checkEndpoint);

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

// Configuration de développement (réduite: on s'appuie sur getCinetPayConfig)

/**
 * Fonction pour récupérer la configuration CinetPay
 * @return array Configuration complète
 * NOTE: Cette fonction est commentée car elle existe déjà dans config.php
 */
/*
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
*/

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
 * Fournit les identifiants CinetPay pour le compte client (site index)
 */
function getClientCinetPayConfig(): array {
    return getCinetPayConfig('client');
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
