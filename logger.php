<?php
// logger.php - Utility for logging diagnostic information

// Define log directory
if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/diagnostic_logs/');
}

/**
 * Append a message to a log file
 *
 * @param string $fileName Name of the log file within LOG_DIR
 * @param string $message  Message to log
 */
function logMessage(string $fileName, string $message): void {
    // Create log directory if necessary
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    $filePath = LOG_DIR . $fileName;
    $time = date('[Y-m-d H:i:s] ');
    file_put_contents($filePath, $time . $message . PHP_EOL, FILE_APPEND);
}
