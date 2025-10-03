<?php
// Simple loader test for Mailer/PHPMailer
try {
    require_once __DIR__ . '/lib/Mailer.php';
    echo "MAILER_OK";
} catch (Throwable $e) {
    echo "MAILER_ERR:" . $e->getMessage();
}
