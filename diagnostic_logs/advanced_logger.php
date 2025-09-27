<?php
/**
 * SYSTÈME DE LOGGING AVANCÉ - COURSIER PROD
 * Logging extrêmement précis pour toutes les interfaces
 * Créé le: <?php echo date('Y-m-d H:i:s'); ?>
 */

class AdvancedLogger {
    private static $instance = null;
    private $logPath;
    private $maxLogSize = 10485760; // 10MB
    private $maxBackups = 5;

    private function __construct() {
        $this->logPath = __DIR__ . '/';
        $this->ensureLogDirectory();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function ensureLogDirectory() {
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Log avec niveau de détail extrême
     */
    public function log($level, $message, $context = [], $interface = 'GENERAL') {
        $timestamp = date('Y-m-d H:i:s.u');
        $microtime = microtime(true);
        $memory = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        // Informations de contexte système
        $systemInfo = [
            'timestamp' => $timestamp,
            'microtime' => $microtime,
            'memory_usage' => $this->formatBytes($memory),
            'peak_memory' => $this->formatBytes($peak_memory),
            'interface' => $interface,
            'level' => strtoupper($level),
            'pid' => getmypid(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'localhost',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'session_id' => session_id() ?: 'no_session',
            'execution_time' => isset($_SERVER['REQUEST_TIME_FLOAT']) ? 
                round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) : 0
        ];

        // Stack trace pour debugging avancé
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $backtrace[1] ?? [];
        $systemInfo['file'] = basename($caller['file'] ?? 'unknown');
        $systemInfo['line'] = $caller['line'] ?? 0;
        $systemInfo['function'] = $caller['function'] ?? 'unknown';
        $systemInfo['class'] = $caller['class'] ?? '';

        // Construction du message de log détaillé
        $logEntry = [
            'system' => $systemInfo,
            'message' => $message,
            'context' => $context,
            'stack_trace' => array_slice($backtrace, 0, 3)
        ];

        $logLine = sprintf(
            "[%s] [%s] [%s:%d] [%s] %s | Context: %s | Memory: %s\n",
            $timestamp,
            $level,
            $systemInfo['file'],
            $systemInfo['line'],
            $interface,
            $message,
            json_encode($context, JSON_UNESCAPED_UNICODE),
            $systemInfo['memory_usage']
        );

        // Écriture dans fichier principal
        $this->writeLog('application.log', $logLine);
        
        // Écriture dans fichier spécifique à l'interface
        $interfaceFile = strtolower($interface) . '.log';
        $this->writeLog($interfaceFile, $logLine);
        
        // Log détaillé JSON pour analyse avancée
        $detailedLog = json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        $this->writeLog('detailed_' . $interfaceFile, $detailedLog);

        // Alertes pour erreurs critiques
        if (in_array(strtolower($level), ['error', 'critical', 'emergency'])) {
            $this->writeErrorAlert($logEntry);
        }
    }

    /**
     * Logs spécialisés par type d'événement
     */
    public function logPayment($action, $data, $interface = 'PAYMENT') {
        $paymentContext = [
            'action' => $action,
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'XOF',
            'transaction_id' => $data['transaction_id'] ?? null,
            'payment_method' => $data['payment_method'] ?? 'cinetpay',
            'order_id' => $data['order_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? 'pending'
        ];
        
        $this->log('INFO', "Paiement: $action", $paymentContext, $interface);
        $this->writeLog('payments.log', sprintf(
            "[%s] PAYMENT_%s | Order: %s | Amount: %s | Status: %s | TxnID: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($action),
            $paymentContext['order_id'],
            $paymentContext['amount'],
            $paymentContext['status'],
            $paymentContext['transaction_id']
        ));
    }

    public function logDatabase($query, $params = [], $execution_time = 0, $interface = 'DATABASE') {
        $dbContext = [
            'query' => $this->sanitizeQuery($query),
            'params' => $params,
            'execution_time' => $execution_time,
            'affected_rows' => 0
        ];
        
        $this->log('DEBUG', "Requête DB exécutée", $dbContext, $interface);
        $this->writeLog('database.log', sprintf(
            "[%s] DB_QUERY | Time: %ss | Query: %s | Params: %s\n",
            date('Y-m-d H:i:s'),
            number_format($execution_time, 4),
            $this->sanitizeQuery($query),
            json_encode($params)
        ));
    }

    public function logUserAction($action, $user_id, $details = [], $interface = 'USER') {
        $userContext = [
            'action' => $action,
            'user_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        $this->log('INFO', "Action utilisateur: $action", $userContext, $interface);
        $this->writeLog('user_actions.log', sprintf(
            "[%s] USER_ACTION | User: %s | Action: %s | IP: %s | Details: %s\n",
            date('Y-m-d H:i:s'),
            $user_id,
            $action,
            $userContext['ip_address'],
            json_encode($details)
        ));
    }

    public function logAPI($endpoint, $method, $response_code, $response_time, $interface = 'API') {
        $apiContext = [
            'endpoint' => $endpoint,
            'method' => $method,
            'response_code' => $response_code,
            'response_time' => $response_time,
            'request_size' => strlen(file_get_contents('php://input')),
            'response_size' => ob_get_length() ?: 0
        ];
        
        $this->log('INFO', "Appel API: $method $endpoint", $apiContext, $interface);
        $this->writeLog('api.log', sprintf(
            "[%s] API_CALL | %s %s | Code: %d | Time: %ss | Size: %d bytes\n",
            date('Y-m-d H:i:s'),
            $method,
            $endpoint,
            $response_code,
            number_format($response_time, 4),
            $apiContext['response_size']
        ));
    }

    public function logSecurity($event, $severity, $details = [], $interface = 'SECURITY') {
        $securityContext = [
            'event' => $event,
            'severity' => $severity,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id(),
            'details' => $details
        ];
        
        $this->log('WARNING', "Événement sécurité: $event", $securityContext, $interface);
        $this->writeLog('security.log', sprintf(
            "[%s] SECURITY_%s | Event: %s | IP: %s | Session: %s | Details: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($severity),
            $event,
            $securityContext['ip_address'],
            $securityContext['session_id'],
            json_encode($details)
        ));
    }

    /**
     * Performance monitoring
     */
    public function logPerformance($operation, $start_time, $end_time, $interface = 'PERFORMANCE') {
        $duration = $end_time - $start_time;
        $memory_usage = memory_get_usage(true);
        $peak_memory = memory_get_peak_usage(true);
        
        $perfContext = [
            'operation' => $operation,
            'duration' => $duration,
            'memory_usage' => $memory_usage,
            'peak_memory' => $peak_memory,
            'memory_limit' => ini_get('memory_limit')
        ];
        
        $level = $duration > 1 ? 'WARNING' : 'INFO';
        $this->log($level, "Performance: $operation", $perfContext, $interface);
        
        $this->writeLog('performance.log', sprintf(
            "[%s] PERF | Operation: %s | Duration: %ss | Memory: %s | Peak: %s\n",
            date('Y-m-d H:i:s'),
            $operation,
            number_format($duration, 4),
            $this->formatBytes($memory_usage),
            $this->formatBytes($peak_memory)
        ));
    }

    /**
     * Utilitaires privés
     */
    private function writeLog($filename, $content) {
        $filepath = $this->logPath . $filename;
        
        // Rotation des logs si nécessaire
        if (file_exists($filepath) && filesize($filepath) > $this->maxLogSize) {
            $this->rotateLog($filepath);
        }
        
        file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);
    }

    private function rotateLog($filepath) {
        for ($i = $this->maxBackups; $i > 0; $i--) {
            $old = $filepath . '.' . $i;
            $new = $filepath . '.' . ($i + 1);
            if (file_exists($old)) {
                if ($i == $this->maxBackups) {
                    unlink($old);
                } else {
                    rename($old, $new);
                }
            }
        }
        rename($filepath, $filepath . '.1');
    }

    private function writeErrorAlert($logEntry) {
        $alert = sprintf(
            "🚨 ALERTE ERREUR CRITIQUE 🚨\n" .
            "Timestamp: %s\n" .
            "Interface: %s\n" .
            "Message: %s\n" .
            "Fichier: %s:%d\n" .
            "Contexte: %s\n" .
            "Stack Trace: %s\n" .
            "=====================================\n",
            $logEntry['system']['timestamp'],
            $logEntry['system']['interface'],
            $logEntry['message'],
            $logEntry['system']['file'],
            $logEntry['system']['line'],
            json_encode($logEntry['context'], JSON_PRETTY_PRINT),
            json_encode($logEntry['stack_trace'], JSON_PRETTY_PRINT)
        );
        
        $this->writeLog('critical_errors.log', $alert);
    }

    private function sanitizeQuery($query) {
        // Masquer les mots de passe et informations sensibles
        $query = preg_replace('/password\s*=\s*[\'"][^\'"]*/i', 'password=***', $query);
        $query = preg_replace('/token\s*=\s*[\'"][^\'"]*/i', 'token=***', $query);
        return $query;
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

/**
 * Fonctions globales pour faciliter l'utilisation
 */
function logInfo($message, $context = [], $interface = 'GENERAL') {
    AdvancedLogger::getInstance()->log('INFO', $message, $context, $interface);
}

function logError($message, $context = [], $interface = 'GENERAL') {
    AdvancedLogger::getInstance()->log('ERROR', $message, $context, $interface);
}

function logDebug($message, $context = [], $interface = 'GENERAL') {
    AdvancedLogger::getInstance()->log('DEBUG', $message, $context, $interface);
}

function logWarning($message, $context = [], $interface = 'GENERAL') {
    AdvancedLogger::getInstance()->log('WARNING', $message, $context, $interface);
}

function logCritical($message, $context = [], $interface = 'GENERAL') {
    AdvancedLogger::getInstance()->log('CRITICAL', $message, $context, $interface);
}

function logPayment($action, $data, $interface = 'PAYMENT') {
    AdvancedLogger::getInstance()->logPayment($action, $data, $interface);
}

function logDatabase($query, $params = [], $execution_time = 0, $interface = 'DATABASE') {
    AdvancedLogger::getInstance()->logDatabase($query, $params, $execution_time, $interface);
}

function logUserAction($action, $user_id, $details = [], $interface = 'USER') {
    AdvancedLogger::getInstance()->logUserAction($action, $user_id, $details, $interface);
}

function logAPI($endpoint, $method, $response_code, $response_time, $interface = 'API') {
    AdvancedLogger::getInstance()->logAPI($endpoint, $method, $response_code, $response_time, $interface);
}

function logSecurity($event, $severity, $details = [], $interface = 'SECURITY') {
    AdvancedLogger::getInstance()->logSecurity($event, $severity, $details, $interface);
}

function logPerformance($operation, $start_time, $end_time, $interface = 'PERFORMANCE') {
    AdvancedLogger::getInstance()->logPerformance($operation, $start_time, $end_time, $interface);
}

// Gestionnaire global d'erreurs
set_error_handler(function($severity, $message, $file, $line) {
    $context = [
        'severity' => $severity,
        'file' => $file,
        'line' => $line,
        'error_type' => [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE',
            E_CORE_ERROR => 'CORE_ERROR',
            E_CORE_WARNING => 'CORE_WARNING',
            E_USER_ERROR => 'USER_ERROR',
            E_USER_WARNING => 'USER_WARNING',
            E_USER_NOTICE => 'USER_NOTICE'
        ][$severity] ?? 'UNKNOWN'
    ];
    
    logError("Erreur PHP: $message", $context, 'PHP_ERROR');
});

// Gestionnaire d'exceptions non capturées
set_exception_handler(function($exception) {
    $context = [
        'exception_class' => get_class($exception),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ];
    
    logCritical("Exception non gérée: " . $exception->getMessage(), $context, 'EXCEPTION');
});

// Auto-initialisation
AdvancedLogger::getInstance();
?>
