<?php
// check_update.php : renvoie la dernière version de l'appli et le lien APK
header('Content-Type: application/json');
$applis = require __DIR__ . '/../applis.php';
$appli = $applis[0]; // On suppose une seule appli pour l'instant
$response = [
    'version' => $appli['version'],
    'apk_url' => $appli['lien'],
    'notes' => $appli['notes'] ?? ''
];
echo json_encode($response);