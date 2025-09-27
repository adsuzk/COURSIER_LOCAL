<?php
/**
 * HOOKS D'INTÉGRATION POUR LOGGING AVANCÉ
 * À inclure au début de chaque interface principale
 */

// Auto-include du système de logging si disponible
if (file_exists(__DIR__ . '/advanced_logger.php')) {
    require_once __DIR__ . '/advanced_logger.php';
    
    // Initialiser le logging avec contexte spécifique
    function initLogging($interface) {
        $start_time = microtime(true);
        
        // Log du démarrage de l'interface
        logInfo("Démarrage interface $interface", [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'session_status' => session_status(),
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit')
        ], $interface);
        
        // Enregistrer le temps de démarrage pour mesurer la performance
        $_SESSION['interface_start_time'] = $start_time;
        $_SESSION['current_interface'] = $interface;
        
        // Hook pour logging automatique à la fin de la requête
        register_shutdown_function(function() use ($interface, $start_time) {
            $end_time = microtime(true);
            $execution_time = $end_time - $start_time;
            
            // Log de fin avec métriques de performance
            logPerformance("interface_$interface", $start_time, $end_time, $interface);
            
            logInfo("Fin interface $interface", [
                'execution_time' => round($execution_time, 4),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'included_files' => count(get_included_files()),
                'response_code' => http_response_code()
            ], $interface);
        });
        
        return $start_time;
    }
    
    // Hook pour logging des erreurs de base de données
    function logDatabaseOperation($query, $params = [], $execution_time = 0) {
        if (isset($_SESSION['current_interface'])) {
            logDatabase($query, $params, $execution_time, $_SESSION['current_interface']);
        } else {
            logDatabase($query, $params, $execution_time);
        }
    }
    
    // Hook pour logging des actions utilisateur
    function logUserOperation($action, $user_id = null, $details = []) {
        $interface = $_SESSION['current_interface'] ?? 'UNKNOWN';
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? 'anonymous');
        
        logUserAction($action, $user_id, $details, $interface);
    }
    
    // Hook pour logging des erreurs personnalisées
    function logInterfaceError($message, $context = []) {
        $interface = $_SESSION['current_interface'] ?? 'UNKNOWN';
        $context['interface_context'] = [
            'current_page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'post_data' => !empty($_POST) ? array_keys($_POST) : [],
            'get_data' => !empty($_GET) ? array_keys($_GET) : []
        ];
        
        logError($message, $context, $interface);
    }
    
    // Hook pour logging des paiements
    function logPaymentOperation($action, $data) {
        $interface = $_SESSION['current_interface'] ?? 'PAYMENT';
        logPayment($action, $data, $interface);
    }
    
    // Hook pour logging de sécurité
    function logSecurityEvent($event, $severity = 'medium', $details = []) {
        $interface = $_SESSION['current_interface'] ?? 'SECURITY';
        logSecurity($event, $severity, $details, $interface);
    }
    
} else {
    // Fallback si le système de logging n'est pas disponible
    function initLogging($interface) { return microtime(true); }
    function logDatabaseOperation($query, $params = [], $execution_time = 0) {}
    function logUserOperation($action, $user_id = null, $details = []) {}
    function logInterfaceError($message, $context = []) {}
    function logPaymentOperation($action, $data) {}
    function logSecurityEvent($event, $severity = 'medium', $details = []) {}
}
?>
