<?php
// applis.php : liste structurée des applications Suzosky
// Format : tableau associatif pour chaque appli
// Déterminer un lien dynamique pour la dernière APK via le pointeur latest_apk.json
require_once __DIR__ . '/config.php';

$toRoutePath = function (string $path): string {
    if (function_exists('routePath')) {
        return routePath($path);
    }
    return '/' . ltrim($path, '/');
};

/**
 * Retourne une URL de téléchargement APK basée sur la racine applicative
 */
$apkDownloadUrl = function (?string $file) use ($toRoutePath): ?string {
    if (!$file) {
        return null;
    }
    return $toRoutePath('admin/download_apk.php?file=' . urlencode($file));
};

$latestMetaPath = __DIR__ . '/admin/uploads/latest_apk.json';
$latestMeta = @json_decode(@file_get_contents($latestMetaPath), true);
$hasLatest = is_array($latestMeta) && !empty($latestMeta['file']);
$hasPrevious = $hasLatest && !empty($latestMeta['previous']['file']);
$dynamicApkLink = $hasLatest
    ? $apkDownloadUrl($latestMeta['file'])
    : $toRoutePath('admin/uploads/suzosky-coursier-production.apk');
$versionText = $hasLatest ? "v{$latestMeta['version_name']} (code {$latestMeta['version_code']})" : '1.0.0';

return [
    [
        'nom' => 'Coursier',
        'description' => 'Application de livraison urbaine avec tracking en temps réel, tarification dynamique et interface premium.',
        'plateformes' => ['Android', 'Web'],
        'version' => $versionText,
    'lien' => $dynamicApkLink,
    'lien_precedent' => $hasPrevious ? $apkDownloadUrl($latestMeta['previous']['file']) : null,
        'version_precedente' => $hasPrevious ? "v{$latestMeta['previous']['version_name']} (code {$latestMeta['previous']['version_code']})" : null,
    'icon' => $toRoutePath('assets/favicon.svg'),
        'date' => '2025-09-18',
        'notes' => 'Version production HTTPS avec SSL sécurisé, mises à jour automatiques, notifications sonores, système de paiement CinetPay intégré. Base de données LWS.'
    ],
    // Ajoute ici d’autres applications Suzosky au même format
];
