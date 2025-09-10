<?php
require_once __DIR__ . '/lib/util.php';
 // Lire le fichier admin pour rechercher des fonctions
$content = file_get_contents(__DIR__ . '/lib/util.php');
$pos = strpos($content, 'function generatePassword');
if($pos !== false) {
    $line_num = substr_count(substr($content, 0, $pos), "\n") + 1;
    echo "Fonction trouvée ligne: $line_num\n";
    
    // Afficher quelques lignes autour
    $lines = file(__DIR__ . '/lib/util.php');
    $start = max(0, $line_num - 3);
    $end = min(count($lines), $line_num + 5);
    
    for($i = $start; $i < $end; $i++) {
        $marker = ($i + 1 == $line_num) ? ">>> " : "    ";
        echo $marker . ($i + 1) . ": " . rtrim($lines[$i]) . "\n";
    }
} else {
    echo "Fonction non trouvée\n";
}
?>
