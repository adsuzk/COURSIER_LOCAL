<?php
// Wrapper minimal pour réutiliser la page commandes améliorée
require_once __DIR__ . '/functions.php';

if (!function_exists('checkAdminAuth') || !checkAdminAuth()) {
    header('Location: admin.php');
    exit;
}

require_once __DIR__ . '/../admin_commandes_enhanced.php';
