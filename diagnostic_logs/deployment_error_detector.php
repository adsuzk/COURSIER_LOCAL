<?php
/**
 * DEPLOYMENT ERROR DETECTOR
 * Détecte et log toutes les erreurs de déploiement et runtime
 */

// Configuration des logs d'erreurs de déploiement
$deploymentLogFile = __DIR__ . '/deployment_errors.log';

// Fonction pour logger les erreurs de déploiement
function logDeploymentError($error, $context = []) {
    global $deploymentLogFile;
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] DEPLOYMENT ERROR: " . $error;
    
    if (!empty($context)) {
        $logEntry .= " - Context: " . json_encode($context);
    }
    
    $logEntry .= "\n";
    
    file_put_contents($deploymentLogFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Handler d'erreurs personnalisé
function deploymentErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'FATAL ERROR',
        E_WARNING => 'WARNING', 
        E_PARSE => 'PARSE ERROR',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
        E_USER_ERROR => 'USER ERROR',
        E_USER_WARNING => 'USER WARNING',
        E_USER_NOTICE => 'USER NOTICE',
        E_STRICT => 'STRICT NOTICE',
        E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER DEPRECATED'
    ];
    
    $errorType = $errorTypes[$errno] ?? 'UNKNOWN ERROR';
    
    logDeploymentError("{$errorType}: {$errstr}", [
        'file' => $errfile,
        'line' => $errline,
        'error_number' => $errno
    ]);
    
    return false; // Permet à PHP de continuer le traitement normal
}

// Handler d'exceptions non capturées
function deploymentExceptionHandler($exception) {
    logDeploymentError("UNCAUGHT EXCEPTION: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
}

// Handler pour les erreurs fatales
function deploymentFatalErrorHandler() {
    $error = error_get_last();
    
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        logDeploymentError("FATAL ERROR: " . $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type']
        ]);
    }
}

// Vérifier si les fonctions essentielles existent
function checkEssentialFunctions() {
    $essentialFunctions = [
        'logInfo',
        'logError', 
        'initLogging',
        'logDebug'
    ];
    
    $missingFunctions = [];
    
    foreach ($essentialFunctions as $func) {
        if (!function_exists($func)) {
            $missingFunctions[] = $func;
        }
    }
    
    if (!empty($missingFunctions)) {
        logDeploymentError("MISSING FUNCTIONS DETECTED", [
            'missing_functions' => $missingFunctions,
            'current_file' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown'
        ]);
    }
    
    return empty($missingFunctions);
}

// Vérifier si les fichiers essentiels existent
function checkEssentialFiles() {
    $essentialFiles = [
        __DIR__ . '/logging_hooks.php',
        __DIR__ . '/../config.php',
        __DIR__ . '/../index.php'
    ];
    
    $missingFiles = [];
    
    foreach ($essentialFiles as $file) {
        if (!file_exists($file)) {
            $missingFiles[] = $file;
        }
    }
    
    if (!empty($missingFiles)) {
        logDeploymentError("MISSING FILES DETECTED", [
            'missing_files' => $missingFiles
        ]);
    }
    
    return empty($missingFiles);
}

// Activer les handlers d'erreurs
set_error_handler('deploymentErrorHandler');
set_exception_handler('deploymentExceptionHandler');
register_shutdown_function('deploymentFatalErrorHandler');

// Log initial pour indiquer que le détecteur est actif
logDeploymentError("DEPLOYMENT ERROR DETECTOR ACTIVATED", [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
]);

// Vérifications initiales
checkEssentialFunctions();
checkEssentialFiles();

?>
