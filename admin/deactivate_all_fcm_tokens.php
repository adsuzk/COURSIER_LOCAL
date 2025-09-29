<?php
require_once __DIR__ . '/../config.php';
$pdo = getDBConnection();
$pdo->exec('UPDATE device_tokens SET is_active = 0');
echo "Tous les tokens FCM ont été désactivés.\n";
