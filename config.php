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
            'name'     => 'coursier_local',
            'user'     => 'root',
            'password' => '',
        ],
    ],
    'api' => [
        'secret' => 'your_api_secret_key',
    ],
    'admin' => [
        // Static admin API token for securing dashboard fetch/SSE; rotate regularly.
        // Can be overridden by environment variable ADMIN_API_TOKEN (recommended in production)
        'api_token' => getenv('ADMIN_API_TOKEN') ?: 'CHANGE_ME_ADMIN_TOKEN_20250916'
    ],
    'app' => [
        'password_length' => 5,
    ],
    // Configuration SMTP pour l'envoi des emails (PHPMailer)
    'smtp' => [
        'host' => getenv('SMTP_HOST') ?: '',
        'username' => getenv('SMTP_USER') ?: '',
        'password' => getenv('SMTP_PASS') ?: '',
        'port' => getenv('SMTP_PORT') ?: 587,
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'no-reply@votre-domaine.com',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'Suzosky'
    ],
];

// Optional environment override file (not committed). Allows setting ENV vars like DB_HOST, ENVIRONMENT, etc.
$__envOverride = __DIR__ . '/env_override.php';
if (file_exists($__envOverride)) {
    // This file can call putenv('DB_HOST=...') etc. and is ignored from VCS.
    @require_once $__envOverride;
}

/**
 * Detects environment: production vs development
 * @return bool
 */
function isProductionEnvironment(): bool {
    // 0) Explicit overrides first
    if (getenv('FORCE_DB') === 'production') {
        return true;
    }
    if (getenv('ENVIRONMENT') === 'production') {
        return true;
    }
    if (file_exists(__DIR__ . '/FORCE_PRODUCTION_DB')) {
        return true;
    }

    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
        // Domaines de production
        $productionDomains = [
            'suzosky',
            'conciergerie-privee-suzosky.com',
            'coursier.conciergerie-privee-suzosky.com',
            'lws-hosting.com',
            'lws.fr'
        ];
        
        foreach ($productionDomains as $domain) {
            if (strpos($host, $domain) !== false) {
                return true;
            }
        }
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
    $errors = [];

    // 1) ENV overrides take precedence if provided
    $envOverride = [
        'host' => getenv('DB_HOST') ?: null,
        'port' => getenv('DB_PORT') ?: null,
        'name' => getenv('DB_NAME') ?: null,
        'user' => getenv('DB_USER') ?: null,
        'password' => getenv('DB_PASS') ?: null,
    ];
    $hasOverride = array_filter($envOverride, fn($v) => !is_null($v) && $v !== '');
    if (!empty($hasOverride)) {
        $host = $envOverride['host'] ?: '127.0.0.1';
        $port = $envOverride['port'] ?: '3306';
        $name = $envOverride['name'] ?: '';
        $user = $envOverride['user'] ?: '';
        $password = $envOverride['password'] ?: '';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            $errors[] = 'ENV override DB connect failed: ' . $e->getMessage();
        }
    }

    // 2) Use detected environment config
    $env = isProductionEnvironment() ? 'production' : 'development';
    $dbConfigs = $config['db'] ?? [];
    $tryOrder = [];
    if (isset($dbConfigs[$env]) && is_array($dbConfigs[$env])) $tryOrder[] = $dbConfigs[$env];
    // 3) Fallback to the other environment if first fails
    $fallbackEnv = $env === 'production' ? 'development' : 'production';
    if (isset($dbConfigs[$fallbackEnv]) && is_array($dbConfigs[$fallbackEnv])) $tryOrder[] = $dbConfigs[$fallbackEnv];
    // 4) As last resort, use empty config to fail with clear error
    if (empty($tryOrder)) $tryOrder[] = ['host' => '', 'name' => '', 'user' => '', 'password' => '', 'port' => '3306'];

    foreach ($tryOrder as $dbConfig) {
        $host = $dbConfig['host'] ?? '';
        $name = $dbConfig['name'] ?? '';
        $user = $dbConfig['user'] ?? '';
        $password = $dbConfig['password'] ?? '';
        $port = $dbConfig['port'] ?? '3306';
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $e) {
            $errors[] = "DB connect failed for {$host}/{$name}: " . $e->getMessage();
            // Continue to next config
        }
    }

    // Log errors for troubleshooting
    try {
        $logDir = __DIR__ . '/diagnostic_logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
        @file_put_contents($logDir . '/db_connection_errors.log', date('c') . "\n" . implode("\n", $errors) . "\n\n", FILE_APPEND);
    } catch (Throwable $e) { /* ignore logging errors */ }

    // Throw combined error
    throw new PDOException('All DB connection attempts failed. Details: ' . implode(' | ', $errors));
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

// =========================
// Helpers d'URL et d'environnement
// =========================
/**
 * Scheme de requête (http/https) fiable
 */
function getRequestScheme(): string {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
    if (!empty($_SERVER['REQUEST_SCHEME'])) return $_SERVER['REQUEST_SCHEME'];
    // Reverse proxy headers (optionnel)
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) return $_SERVER['HTTP_X_FORWARDED_PROTO'];
    return 'http';
}

/**
 * Nom d'hôte (host) de façon robuste
 */
function getServerHost(): string {
    return $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
}

/**
 * Chemin de base de l'application (ex: /coursier_prod ou /)
 * Détecté via SCRIPT_NAME pour supporter sous-dossiers en local
 */
function getAppBasePath(): string {
    $host = getServerHost();
    // En production réelle (domaine public), l'app est servie à la racine
    if (isProductionEnvironment() && !in_array($host, ['localhost', '127.0.0.1'], true)) {
        return '/';
    }
    // Local ou dev: base = dossier projet (ex: /coursier_prod)
    $projectBase = '/' . basename(str_replace('\\', '/', __DIR__));
    return $projectBase;
}

/**
 * URL de base complète (scheme + host + base path)
 */
function getAppBaseUrl(): string {
    $scheme = getRequestScheme();
    $host = getServerHost();
    $base = getAppBasePath();
    return $scheme . '://' . $host . $base; // ex: http://localhost/coursier_prod
}

/**
 * Construit une URL absolue depuis un chemin relatif à la base app
 * appUrl('api/foo.php') -> http://host/base/api/foo.php
 */
function appUrl(string $path = ''): string {
    $prefix = getAppBaseUrl();
    $path = ltrim($path, '/');
    return $prefix . $path;
}

/**
 * Construit un chemin relatif à la racine app (pour headers Location ou JSON)
 * routePath('coursier.php') -> /coursier_prod/coursier.php
 */
function routePath(string $path = ''): string {
    $base = getAppBasePath();
    $path = ltrim($path, '/');
    // If no specific path, return base without trailing slash
    if ($path === '') {
        return $base;
    }
    if ($base === '/') {
        return '/' . $path;
    }
    return $base . '/' . $path;
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
 * Unified helper used across APIs to generate short agent passwords
 * Falls back to global config length (default 5). Optional custom length.
 */
function generateUnifiedAgentPassword(int $length = null): string {
    global $config;
    $len = $length ?? ($config['app']['password_length'] ?? 5);
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $password = '';
    for ($i = 0; $i < $len; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $password;
}
/**
 * Get CinetPay configuration parameters
 * @return array
 */
function getCinetPayConfig(): array {
    // CinetPay credentials - CORRIGÉS 2025-09-18
    return [
        'apikey'     => '8338609805877a8eaac7eb6.01734650',
        'site_id'    => '219503',
        'secret_key' => '17153003105e7ca6606cc157.46703056',
        'endpoint'   => 'https://api-checkout.cinetpay.com/v2/payment'
    ];
}
