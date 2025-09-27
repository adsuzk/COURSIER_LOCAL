<?php
// Script d'auto-détection de la dernière APK et mise à jour de latest_apk.json (avec historique)
$uploadsDir = __DIR__ . '/uploads';
// Support both legacy and new application release directories
$releaseDirs = [
    dirname(__DIR__) . '/Applications APK/Coursiers APK/release',
    dirname(__DIR__) . '/Applications/Appli Coursiers/release',
];
$metadataFile = $uploadsDir . '/latest_apk.json';
$outputMetaFile = $releaseDir . '/output-metadata.json';

// 1. Lister les APKs des deux dossiers par date décroissante
$apks = [];

// Scanner le dossier uploads (racine et sous-dossiers coursier/client)
if (is_dir($uploadsDir)) {
    $scanDirs = [$uploadsDir, $uploadsDir . '/coursier', $uploadsDir . '/client'];
    foreach ($scanDirs as $dir) {
        if (!is_dir($dir)) continue;
        $uploadApks = array_filter(scandir($dir), function($f) use ($dir) {
            return preg_match('/\.apk$/i', $f) && is_file($dir . '/' . $f);
        });
        foreach ($uploadApks as $f) {
            $apks[] = [
                'file' => $f,
                'path' => $dir . '/' . $f,
                'url' => '/admin/download_apk.php?file=' . urlencode($f),
                'mtime' => filemtime($dir . '/' . $f),
                'size' => filesize($dir . '/' . $f),
                'source' => 'uploads'
            ];
        }
    }
}

// Scanner les dossiers release connus
foreach ($releaseDirs as $releaseDir) {
    if (!is_dir($releaseDir)) continue;
    $releaseApks = array_filter(scandir($releaseDir), function($f) use ($releaseDir) {
        return preg_match('/\.apk$/i', $f) && is_file($releaseDir . '/' . $f);
    });
    foreach ($releaseApks as $f) {
        $apks[] = [
            'file' => $f,
            'path' => $releaseDir . '/' . $f,
            'url' => '/admin/download_apk.php?file=' . urlencode($f),
            'mtime' => filemtime($releaseDir . '/' . $f),
            'size' => filesize($releaseDir . '/' . $f),
            'source' => 'release'
        ];
    }
}
// Trier par date décroissante (le plus récent en premier)
usort($apks, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

if (count($apks) === 0) {
    exit('Aucune APK trouvée dans uploads/ ou release/.');
}

// 2. Lire la version depuis output-metadata.json si dispo
$version_code = 1;
$version_name = '1.0';
if (file_exists($outputMetaFile)) {
    $meta = json_decode(file_get_contents($outputMetaFile), true);
    if (!empty($meta['elements'][0]['versionCode'])) {
        $version_code = $meta['elements'][0]['versionCode'];
    }
    if (!empty($meta['elements'][0]['versionName'])) {
        $version_name = $meta['elements'][0]['versionName'];
    }
}

// 3. Charger l'ancien metadata pour historique
$previous = null;
if (file_exists($metadataFile)) {
    $old = json_decode(file_get_contents($metadataFile), true);
    if (is_array($old) && !empty($old['file'])) {
        $previous = $old;
    }
}

// 4. Construire le nouveau metadata
$latest = $apks[0];
$data = [
    'file' => $latest['file'],
    'url' => $latest['url'],
    'source' => $latest['source'],
    'version_code' => $version_code,
    'version_name' => $version_name,
    'apk_size' => $latest['size'],
    'uploaded_at' => date('c', $latest['mtime'])
];
if ($previous && $previous['file'] !== $latest['file']) {
    $data['previous'] = $previous;
}

file_put_contents($metadataFile, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "Dernière APK : {$latest['file']} (v$version_name, code $version_code, {$latest['size']} octets) - Source: {$latest['source']}\n";
if ($previous) {
    echo "Version précédente : {$previous['file']} (v{$previous['version_name']}, code {$previous['version_code']})\n";
}
