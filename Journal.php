<?php
/**
 * Journal redirect
 * Permet d'accéder au journal universel via /Journal.php
 */
// Redirection vers le dossier JOURNAL
$base = dirname(__FILE__);
// Redirection vers l'interface de consultation des logs
$target = '/JOURNAL/index.php';
if (file_exists($base . $target)) {
    header('Location: ' . $target);
    exit;
}
// Si le fichier n'existe pas, afficher un message
http_response_code(404);
echo "<h1>404 Not Found</h1>";
echo "<p>Le journal n'est pas disponible. Assurez-vous que le dossier JOURNAL existe et contient Journal.php.</p>";
