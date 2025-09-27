<?php
require_once(__DIR__ . '/config.php');

echo "=== TEST COURSIER CM20250003 VISIBILITÉ ===\n";

$pdo = getDBConnection();

// Test direct de la requête des coursiers connectés
$stmt = $pdo->query("
SELECT 
    id,
    matricule, 
    nom, 
    prenoms,
    statut_connexion,
    current_session_token,
    last_login_at,
    TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_depuis_activite
FROM agents_suzosky 
WHERE current_session_token IS NOT NULL 
    AND current_session_token != '' 
    AND statut_connexion = 'en_ligne'
    AND last_login_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
ORDER BY last_login_at DESC
");

$coursiers_connectes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Nombre de coursiers connectés trouvés : " . count($coursiers_connectes) . "\n\n";

if (count($coursiers_connectes) > 0) {
    foreach ($coursiers_connectes as $coursier) {
        echo "✅ COURSIER CONNECTÉ :\n";
        echo "   - ID : " . $coursier['id'] . "\n";
        echo "   - Matricule : " . $coursier['matricule'] . "\n";
        echo "   - Nom : " . $coursier['nom'] . " " . $coursier['prenoms'] . "\n";
        echo "   - Statut : " . $coursier['statut_connexion'] . "\n";
        echo "   - Token : " . (substr($coursier['current_session_token'], 0, 20) . "...") . "\n";
        echo "   - Dernière activité : " . $coursier['last_login_at'] . "\n";
        echo "   - Minutes écoulées : " . $coursier['minutes_depuis_activite'] . "\n\n";
    }
} else {
    echo "❌ Aucun coursier connecté trouvé.\n";
}

// Test spécifique CM20250003
echo "=== RECHERCHE SPÉCIFIQUE CM20250003 ===\n";
$stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE matricule = 'CM20250003'");
$stmt->execute();
$cm_data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cm_data) {
    echo "Détails CM20250003 :\n";
    echo "- Statut connexion : " . $cm_data['statut_connexion'] . "\n";
    echo "- Token : " . ($cm_data['current_session_token'] ? "✅ OUI" : "❌ NON") . "\n";
    echo "- Dernière activité : " . $cm_data['last_login_at'] . "\n";
    echo "- Minutes écoulées : " . (time() - strtotime($cm_data['last_login_at'] ?? '0'))/60 . "\n";
    
    // Vérifications étape par étape
    echo "\nVérifications :\n";
    echo "1. A un token : " . (!empty($cm_data['current_session_token']) ? "✅" : "❌") . "\n";
    echo "2. Statut en ligne : " . (($cm_data['statut_connexion'] ?? '') === 'en_ligne' ? "✅" : "❌") . "\n";
    $lastActivity = strtotime($cm_data['last_login_at'] ?? '0');
    $is30MinRecent = $lastActivity > (time() - 1800);
    echo "3. Activité récente (30 min) : " . ($is30MinRecent ? "✅" : "❌") . "\n";
} else {
    echo "❌ CM20250003 non trouvé.\n";
}
?>