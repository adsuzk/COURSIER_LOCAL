<?php
/**
 * duplicate_audit.php
 *
 * Scanne les dossiers clés pour détecter les fichiers portant le même nom
 * (hors dossier CloneCoursierApp) afin d'éviter conflits ou doublons.
 * Usage CLI : php duplicate_audit.php
 */

$base = __DIR__; // base du workspace
$scanDir = $base . '/coursier_prod'; // n’auditer que le dossier web principal
if (is_dir($scanDir)) {
    $base = $scanDir;
}

$ignore = ['CloneCoursierApp', 'build', 'gradle', 'scripts', 'assets'];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
$map = [];
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getRealPath();
    // Ignorer certains dossiers
    foreach ($ignore as $skip) {
        if (strpos($path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR) !== false) continue 2;
    }
    $name = $file->getFilename();
    $dir = dirname(substr($path, strlen($base) + 1));
    $map[$name][$dir] = true;
}
// Rechercher les noms récurrents
$duplicates = array_filter($map, fn($dirs) => count($dirs) > 1);
if (empty($duplicates)) {
    echo "Aucun doublon détecté parmi les dossiers clés.\n";
} else {
    echo "Fichiers portant le même nom détectés :\n";
    foreach ($duplicates as $name => $dirs) {
        echo " - $name dans : " . implode(', ', array_keys($dirs)) . "\n";
    }
}
