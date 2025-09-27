<?php
// Test simple pour vérifier la sortie AJAX des agents
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test AJAX Agents</h1>";

// Test de l'endpoint via admin.php
require_once __DIR__ . '/../config.php';
$url = appUrl('admin.php?section=agents&ajax=true');
$response = file_get_contents($url);

echo "<h2>Réponse brute :</h2>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Test parsing JSON
echo "<h2>Test parsing JSON :</h2>";
$json = json_decode($response, true);
if ($json === null) {
    echo "<strong>ERREUR JSON :</strong> " . json_last_error_msg();
} else {
    echo "<strong>JSON OK :</strong> " . count($json) . " agents trouvés";
}
?>