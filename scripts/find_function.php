<?php
require_once __DIR__ . '/../lib/util.php';

$content = file_get_contents(__DIR__ . '/../admin/admin.php');
$pos = strpos($content, 'function generateRandomPassword');
// ...existing code...
?>
