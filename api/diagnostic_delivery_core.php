<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$result = [
    'success' => true,
    'env' => [
        'php_version' => PHP_VERSION,
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
        'script' => __FILE__,
        'base_dir' => dirname(__DIR__),
    ],
    'files' => [],
    'database' => [ 'connected' => false, 'tables' => [] ],
    'warnings' => [],
];

// Required API files for the delivery core
$required = [
    'assign_with_lock.php',
    'update_order_status.php',
    'set_active_order.php',
    'directions_proxy.php',
    'confirm_delivery.php',
    'upload_proof.php',
    'generate_delivery_otp.php',
];

foreach ($required as $file) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . $file;
    $result['files'][$file] = [
        'exists' => file_exists($path),
        'path' => $path,
        'readable' => is_readable($path)
    ];
}

// Database checks
try {
    $pdo = getPDO();
    $result['database']['connected'] = true;
    $tablesToCheck = [
        'dispatch_locks',
        'order_status_history',
        'commandes_classiques'
    ];
    foreach ($tablesToCheck as $t) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$t]);
        $result['database']['tables'][$t] = $stmt->rowCount() > 0;
    }
} catch (Throwable $e) {
    $result['database']['error'] = $e->getMessage();
}

// Warnings summary
foreach ($result['files'] as $fname => $info) {
    if (!$info['exists']) {
        $result['warnings'][] = "Fichier manquant: api/$fname";
        $result['success'] = false;
    }
}
if (!$result['database']['connected']) {
    $result['warnings'][] = 'Connexion base de données échouée';
    $result['success'] = false;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
<?php /* EOF */
}