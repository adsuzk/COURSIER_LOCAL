<?php
// Script d'initialisation automatique du système de versions APK
// À inclure dans les pages admin pour garantir que les métadonnées sont à jour

$uploadsDir = __DIR__ . '/uploads';
$metadataFile = $uploadsDir . '/latest_apk.json';

// Vérifier si une mise à jour des métadonnées est nécessaire
$needsUpdate = true;
if (file_exists($metadataFile)) {
    $metadata = json_decode(file_get_contents($metadataFile), true);
    if (is_array($metadata) && !empty($metadata['file'])) {
        $apkFile = $uploadsDir . '/' . $metadata['file'];
        if (file_exists($apkFile)) {
            // Vérifier si le fichier a été modifié depuis la dernière détection
            $lastModified = filemtime($apkFile);
            $lastDetection = strtotime($metadata['uploaded_at'] ?? '1970-01-01');
            $needsUpdate = ($lastModified > $lastDetection);
        }
    }
}

// Mettre à jour si nécessaire
if ($needsUpdate) {
    $updateScript = __DIR__ . '/update_apk_metadata.php';
    if (file_exists($updateScript)) {
        ob_start();
        include $updateScript;
        ob_end_clean(); // Supprimer la sortie pour éviter l'affichage
    }
}
?>