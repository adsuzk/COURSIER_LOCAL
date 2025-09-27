<?php
// logger.php - Utility for logging diagnostic information

// Define log directory
if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/diagnostic_logs/');
}

// Charger le logger avancé si disponible pour bénéficier des fonctions globales (logInfo, logError, ...)
$advancedLoggerPath = __DIR__ . '/diagnostic_logs/advanced_logger.php';
if (file_exists($advancedLoggerPath)) {
    require_once $advancedLoggerPath;
}

/**
 * Append a message to a log file
 *
 * @param string $fileName Name of the log file within LOG_DIR
 * @param string $message  Message to log
 */
function logMessage(string $fileName, string $message): void {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }

    $filePath = LOG_DIR . $fileName;
    $time = date('[Y-m-d H:i:s] ');
    file_put_contents($filePath, $time . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Journalise une activité métier de façon centralisée
 */
if (!function_exists('logActivity')) {
    function logActivity(string $event, string $message, array $context = []): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';

        $entry = sprintf(
            '[%s] [%s] %s | ctx=%s | ip=%s | agent=%s',
            date('Y-m-d H:i:s'),
            $event,
            $message,
            json_encode($context, JSON_UNESCAPED_UNICODE),
            $ip,
            $userAgent
        );

        logMessage('activity.log', $entry);

        if (function_exists('logInfo')) {
            $logContext = array_merge($context, [
                'event' => $event,
                'ip' => $ip,
                'user_agent' => $userAgent
            ]);
            logInfo($message, $logContext, 'ACTIVITY');
        }
    }
}

// Fallback minimal pour logInfo/logError si le logger avancé est indisponible
if (!function_exists('logInfo')) {
    function logInfo(string $message, array $context = [], string $interface = 'GENERAL'): void {
        $entry = sprintf('[%s][INFO][%s] %s | ctx=%s', date('Y-m-d H:i:s'), $interface, $message, json_encode($context));
        logMessage('application.log', $entry);
    }
}

if (!function_exists('logError')) {
    function logError(string $event, $details = null, $context = [], string $interface = 'GENERAL'): void {
        if (is_array($details) && empty($context)) {
            $context = $details;
            $details = null;
        }
        $message = $details !== null ? $details : $event;
        $entry = sprintf('[%s][ERROR][%s] %s | ctx=%s', date('Y-m-d H:i:s'), $interface, $message, json_encode($context));
        logMessage('diagnostics_errors.log', $entry);
    }
}
