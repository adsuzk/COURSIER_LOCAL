<?php
// config.php - unified configuration and database connection

// Global settings
$config = [
    'db' => [
        'production' => [
            // Configuration MySQL fournie par le client
            'host'     => '185.98.131.214',   // Serveur MySQL externe
            'port'     => '3306',             // Port MySQL standard
            'name'     => 'conci2547642_1m4twb',
            'user'     => 'conci2547642_1m4twb',
            'password' => 'wN1!_TT!yHsK6Y6',  // Mot de passe mis à jour
        ],
        'development' => [
            'host'     => '127.0.0.1',
            'port'     => '3306',
            'name'     => 'coursier_prod',
            'user'     => 'root',
            'password' => '',
        ],
    ],
    'api' => [
        'secret' => 'your_api_secret_key',
    ],
    'app' => [
        'password_length' => 5,
    ],
];

/**
 * Detects environment: production vs development
 * @return bool
 */
function isProductionEnvironment(): bool {
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        if (strpos($host, 'suzosky') !== false || strpos($host, 'lws') !== false) {
            return true;
        }
    }
    if (getenv('ENVIRONMENT') === 'production') {
        return true;
    }
    return false;
}

/**
 * Returns a PDO connection based on environment
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(): PDO {
    global $config;
    $env = isProductionEnvironment() ? 'production' : 'development';
    // Safely retrieve database configurations array
    $dbConfigs = $config['db'] ?? [];
    if (!is_array($dbConfigs)) {
        $dbConfigs = [];
    }
    // Select environment config or fallback to development
    if (isset($dbConfigs[$env]) && is_array($dbConfigs[$env])) {
        $dbConfig = $dbConfigs[$env];
    } elseif (isset($dbConfigs['development']) && is_array($dbConfigs['development'])) {
        $dbConfig = $dbConfigs['development'];
    } else {
        // Default empty config to avoid warnings
        $dbConfig = ['host' => '', 'name' => '', 'user' => '', 'password' => ''];
    }
    // Extract values with default empty strings to avoid warnings
    $host = $dbConfig['host'] ?? '';
    $name = $dbConfig['name'] ?? '';
    $user = $dbConfig['user'] ?? '';
    $password = $dbConfig['password'] ?? '';
    $port = $dbConfig['port'] ?? '3306';
    // DSN simple TCP avec host et port
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

// Provide getPDO alias if not already defined
if (!function_exists('getPDO')) {
    /**
     * Alias for getDBConnection, for backward compatibility
     * @return PDO
     */
    function getPDO(): PDO {
        return getDBConnection();
    }
}
// End of config.php

// Journal universel : inclusion conditionnelle et instanciation
$journalFile = __DIR__ . '/JOURNAL/Journal.php';
if (file_exists($journalFile)) {
    include_once $journalFile;
    $globalJournal = new JournalUniverselCoursierProd();
} else {
    // Stub pour éviter erreur si JOURNAL manquant
    class JournalUniverselCoursierProd {
        public function logMaxDetail($type, $desc, $details = []) {}
    }
    $globalJournal = new JournalUniverselCoursierProd();
}

/**
 * Retourne l'instance globale du journal universel
 * @return JournalUniverselCoursierProd
 */
function getJournal(): JournalUniverselCoursierProd {
    global $globalJournal;
    return $globalJournal;
}
/**
 * Génération d'un mot de passe aléatoire
 * @return string
 */
function generatePassword(): string {
    global $config;
    $length = $config['app']['password_length'] ?? 5;
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $password;
}
/**
 * Get CinetPay configuration parameters
 * @return array
 */
function getCinetPayConfig(): array {
    // CinetPay credentials matching integration class
    return [
        'apikey'     => '8338609805877a8eaac7eb6.01734650',
        'site_id'    => '5875732',
        'secret_key' => '830006136690110164ddb1.29156844',
        'endpoint'   => 'https://api-checkout.cinetpay.com/v2/payment'
    ];
}
