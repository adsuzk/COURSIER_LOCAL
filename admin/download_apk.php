<?php
// admin/download_apk.php - Force le téléchargement d'une APK depuis différents emplacements
// Sécurisé pour ne servir que des fichiers .apk depuis les répertoires autorisés

$baseDir = dirname(__DIR__); // Racine du site
$uploadDirs = [
    'admin/uploads/',
    'admin/uploads/coursier/',
    'admin/uploads/client/',
    'Applications APK/Coursiers APK/release/',
    'Applications/Appli Coursiers/release/',
    'Applications/Appli Clients/release/'
];

// Supporter latest=1 (utiliser le pointeur latest_apk.json)
$fileParam = isset($_GET['latest']) && $_GET['latest'] === '1'
    ? (function() use ($baseDir) {
        $meta = @json_decode(@file_get_contents($baseDir . '/admin/uploads/latest_apk.json'), true);
        return is_array($meta) && !empty($meta['file']) ? $meta['file'] : '';
    })()
    : ($_GET['file'] ?? '');

$fileParam = basename($fileParam); // neutraliser chemins
if ($fileParam === '') {
    http_response_code(404);
    exit('File not specified');
}

// Rechercher le fichier dans tous les répertoires autorisés
$filePath = null;
foreach ($uploadDirs as $dir) {
    $testPath = $baseDir . '/' . $dir . $fileParam;
    if (is_file($testPath) && strtolower(pathinfo($testPath, PATHINFO_EXTENSION)) === 'apk') {
        $filePath = $testPath;
        break;
    }
}

if ($filePath === null) {
    http_response_code(404);
    exit('APK not found in authorized directories');
}

// Envoyer les bons headers
$fname = $fileParam;
header('Content-Type: application/vnd.android.package-archive');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($filePath);
exit;
?>
