<?php
require_once __DIR__ . '/../lib/util.php';
/**
 * Configuration sécurisée pour l'administration - VERSION SIMPLIFIÉE
 * Contient uniquement les constantes de configuration
 * Les fonctions sont définies dans admin.php pour éviter les conflits
 */

// Configuration admin (variables d'environnement recommandées avec fallback sécurisé)
$admin_username = $_ENV['ADMIN_USERNAME'] ?? 'admin';
$admin_password_hash = $_ENV['ADMIN_PASSWORD_HASH'] ?? '$2y$10$cghxhfKIMC7w3WjTRdSnre7kxTJBgwXt4BuuhiIBDykfJbp0xgVGK'; // "suzosky2024"

// Définir les constantes seulement si elles ont des valeurs valides
if (!defined('ADMIN_USERNAME') && !empty($admin_username)) {
    define('ADMIN_USERNAME', $admin_username);
}
if (!defined('ADMIN_PASSWORD_HASH') && !empty($admin_password_hash)) {
    define('ADMIN_PASSWORD_HASH', $admin_password_hash);
}

// Configuration de sécurité
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 28800); // 8 heures
}
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'admin_csrf_token');
}
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 3);
}
if (!defined('LOGIN_LOCKOUT_TIME')) {
    define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
}

// Note: Les fonctions (generateCSRFToken, verifyCSRFToken, etc.) sont définies dans admin.php
?>
