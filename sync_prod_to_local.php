<?php
/**
 * sync_prod_to_local.php
 *
 * Compare files in `coursier_prod` to local files and replace local ones when they differ.
 * Usage: php sync_prod_to_local.php
 */

$prodDir  = __DIR__ . DIRECTORY_SEPARATOR . 'coursier_prod';
$localDir = __DIR__;

if (!is_dir($prodDir)) {
    fwrite(STDERR, "Erreur: le dossier 'coursier_prod' n'existe pas dans le répertoire local.\n");
    exit(1);
}

/**
 * Recursively sync directories
 * 
 * @param string $source Source directory (prod)
 * @param string $target Target directory (local)
 */
function syncDir($source, $target)
{
    $items = scandir($source);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $srcPath = $source . DIRECTORY_SEPARATOR . $item;
        $tgtPath = $target . DIRECTORY_SEPARATOR . $item;

        if (is_dir($srcPath)) {
            // Ensure target directory exists
            if (!is_dir($tgtPath)) {
                mkdir($tgtPath, 0777, true);
                echo "Création du dossier: $tgtPath\n";
            }
            syncDir($srcPath, $tgtPath);
        } else {
            $shouldCopy = false;
            if (!file_exists($tgtPath)) {
                $shouldCopy = true;
                echo "Fichier absent: $tgtPath\n";
            } else {
                $srcHash = md5_file($srcPath);
                $tgtHash = md5_file($tgtPath);
                if ($srcHash !== $tgtHash) {
                    $shouldCopy = true;
                    echo "Modification détectée: $tgtPath\n";
                }
            }

            if ($shouldCopy) {
                // Backup existing file
                if (file_exists($tgtPath)) {
                    $backupPath = $tgtPath . '.bak_' . date('Ymd_His');
                    if (copy($tgtPath, $backupPath)) {
                        echo "Sauvegarde: $tgtPath -> $backupPath\n";
                    } else {
                        fwrite(STDERR, "Échec de la sauvegarde de $tgtPath\n");
                    }
                }
                // Copy file from prod to local
                if (copy($srcPath, $tgtPath)) {
                    echo "Copie: $srcPath -> $tgtPath\n";
                } else {
                    fwrite(STDERR, "Échec de la copie de $srcPath vers $tgtPath\n");
                }
            }
        }
    }
}

// Lancement de la synchronisation
echo "Démarrage de la synchronisation prod -> local...\n";
syncDir($prodDir, $localDir);
echo "Synchronisation terminée.\n";
