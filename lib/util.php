<?php
// lib/util.php - common utilities: DB connection and password generation

 // Load configuration and functions (avoid multiple includes)
 require_once __DIR__ . '/../config.php';

// Provide getPDO if not already defined
if (!function_exists('getPDO')) {
    /**
     * Returns a shared PDO instance based on configuration
     * @return PDO
     * @throws PDOException
     */
    function getPDO(): PDO {
        // Utilize config.php's getDBConnection alias
        return getDBConnection();
    }

    // Safe HTML escape helper available globally
    if (!function_exists('h')) {
        function h($s) {
            return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        }
    }
}

