<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/config.php');

echo "<h2>🔍 DIAGNOSTIC - Coursier CM20250003 visibilité</h2>";

// Obtenir la connexion à la base de données
$pdo = getDBConnection();

// Vérification de la connexion de CM20250003
$stmt = $pdo->prepare("SELECT 
    id, 
    matricule, 
    nom, 
    telephone as phone, 
    statut_connexion,
    last_login_at as derniere_activite,
    current_session_token as token,
    DATE(last_login_at) as date_activite,
    TIME(last_login_at) as heure_activite,
    TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_depuis_activite
FROM agents_suzosky 
WHERE matricule = 'CM20250003'");
$stmt->execute();
$coursier = $stmt->fetch(PDO::FETCH_ASSOC);

if ($coursier) {
    echo "<h3>✅ Coursier trouvé :</h3>";
    echo "<pre>";
    print_r($coursier);
    echo "</pre>";
    
    // Test des conditions de filtrage
    echo "<h3>🔍 Tests des conditions de visibilité :</h3>";
    
    $hasToken = !empty($coursier['token']);
    $isOnline = ($coursier['statut_connexion'] === 'en_ligne' || $coursier['statut_connexion'] === 'libre');
    $recentActivity = $coursier['minutes_depuis_activite'] <= 30;
    
    echo "<ul>";
    echo "<li>A un token : " . ($hasToken ? "✅ OUI" : "❌ NON") . " (token: " . substr($coursier['token'] ?? '', 0, 20) . "...)</li>";
    echo "<li>Statut en ligne : " . ($isOnline ? "✅ OUI" : "❌ NON") . " (statut: " . $coursier['statut_connexion'] . ")</li>";
    echo "<li>Activité récente : " . ($recentActivity ? "✅ OUI" : "❌ NON") . " (il y a " . $coursier['minutes_depuis_activite'] . " minutes)</li>";
    echo "</ul>";
    
    $shouldBeVisible = $hasToken && $isOnline && $recentActivity;
    echo "<h3>📊 Résultat final :</h3>";
    echo "<p style='color: " . ($shouldBeVisible ? "green" : "red") . "; font-weight: bold;'>";
    echo $shouldBeVisible ? "✅ Le coursier DEVRAIT être visible" : "❌ Le coursier ne devrait PAS être visible";
    echo "</p>";
    
} else {
    echo "<h3>❌ Coursier CM20250003 NON trouvé dans la base</h3>";
}

// Test de la requête utilisée dans admin_commandes_enhanced.php
echo "<h3>🔍 Test de la requête de l'interface admin :</h3>";

$stmt = $pdo->prepare("SELECT 
    id, 
    matricule, 
    nom, 
    telephone as phone, 
    statut_connexion,
    last_login_at as derniere_activite
FROM agents_suzosky 
WHERE current_session_token IS NOT NULL 
    AND current_session_token != '' 
    AND statut_connexion IN ('en_ligne', 'libre')
    AND last_login_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
ORDER BY last_login_at DESC");

$stmt->execute();
$coursiers_connectes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Nombre de coursiers connectés trouvés : " . count($coursiers_connectes) . "</p>";

if (count($coursiers_connectes) > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Matricule</th><th>Nom</th><th>Statut</th><th>Dernière activité</th></tr>";
    foreach ($coursiers_connectes as $c) {
        $highlight = ($c['matricule'] === 'CM20250003') ? "background-color: yellow;" : "";
        echo "<tr style='$highlight'>";
        echo "<td>" . $c['id'] . "</td>";
        echo "<td>" . $c['matricule'] . "</td>";
        echo "<td>" . $c['nom'] . "</td>";
        echo "<td>" . $c['statut_connexion'] . "</td>";
        echo "<td>" . $c['derniere_activite'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Aucun coursier connecté trouvé avec les critères actuels</p>";
}

// Vérifier si CM20250003 apparaît dans la liste
$cm20250003_present = false;
foreach ($coursiers_connectes as $c) {
    if ($c['matricule'] === 'CM20250003') {
        $cm20250003_present = true;
        break;
    }
}

echo "<h3>📋 Conclusion :</h3>";
echo "<p style='color: " . ($cm20250003_present ? "green" : "red") . "; font-weight: bold;'>";
echo $cm20250003_present ? "✅ CM20250003 APPARAÎT dans la requête des coursiers connectés" : "❌ CM20250003 N'APPARAÎT PAS dans la requête des coursiers connectés";
echo "</p>";

?>