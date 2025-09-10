<?php
// test_api_simple.php - Test basique de l'API auth
require_once 'config.php';

// Test de connexion à la base
try {
    $pdo = getDBConnection();
    echo "✅ Base de données : OK<br>";
} catch (Exception $e) {
    echo "❌ Base de données : " . $e->getMessage() . "<br>";
    exit;
}

// Test de l'API auth directement
echo "<h2>Test API d'authentification</h2>";

// Simuler une requête POST
$_POST['action'] = 'check_session';

// Capturer la sortie de l'API
ob_start();
include 'api/auth.php';
$apiOutput = ob_get_clean();

echo "<h3>Sortie de l'API :</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
echo htmlspecialchars($apiOutput);
echo "</pre>";

// Test JSON
$json = json_decode($apiOutput, true);
if ($json) {
    echo "<h3>JSON parsé :</h3>";
    echo "<pre>";
    print_r($json);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>❌ Réponse JSON invalide</p>";
}

echo '<br><a href="index.php" style="display: inline-block; margin: 20px 0; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;">🏠 Retour à l\'accueil</a>';
?>
