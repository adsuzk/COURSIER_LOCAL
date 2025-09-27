<?php
require_once(__DIR__ . '/config.php');

echo "=== TEST FINAL APRÈS CORRECTIONS ===\n\n";

$pdo = getDBConnection();

// Test avec la nouvelle requête incluant is_recent_activity
$stmt = $pdo->query("
SELECT 
    id,
    matricule, 
    nom, 
    prenoms,
    statut_connexion,
    current_session_token,
    last_login_at,
    (last_login_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) AS is_recent_activity
FROM agents_suzosky 
ORDER BY last_login_at DESC
");

$tous_coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== TOUS LES COURSIERS ===\n";
foreach ($tous_coursiers as $coursier) {
    $hasToken = !empty($coursier['current_session_token']);
    $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
    $isRecentActivity = !empty($coursier['is_recent_activity']);
    $isConnected = $hasToken && $isOnline && $isRecentActivity;
    
    echo "Coursier : " . $coursier['matricule'] . " (" . $coursier['nom'] . ")\n";
    echo "  - Token : " . ($hasToken ? "✅" : "❌") . "\n";
    echo "  - En ligne : " . ($isOnline ? "✅" : "❌") . "\n";
    echo "  - Activité récente : " . ($isRecentActivity ? "✅" : "❌") . "\n";
    echo "  - VISIBLE : " . ($isConnected ? "✅ OUI" : "❌ NON") . "\n\n";
}

// Test spécifique pour les coursiers connectés
$coursiersConnectes = [];
foreach ($tous_coursiers as $coursier) {
    $hasToken = !empty($coursier['current_session_token']);
    $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
    $isRecentActivity = !empty($coursier['is_recent_activity']);
    $isConnected = $hasToken && $isOnline && $isRecentActivity;
    
    if ($isConnected) {
        $coursiersConnectes[] = $coursier;
    }
}

echo "🎯 RÉSULTAT FINAL :\n";
echo "Nombre de coursiers connectés visibles : " . count($coursiersConnectes) . "\n\n";

if (count($coursiersConnectes) > 0) {
    echo "📋 LISTE DES COURSIERS CONNECTÉS VISIBLES :\n";
    foreach ($coursiersConnectes as $coursier) {
        echo "✅ " . $coursier['matricule'] . " - " . $coursier['nom'] . " " . $coursier['prenoms'] . "\n";
    }
} else {
    echo "❌ Aucun coursier connecté visible.\n";
}

// Vérifier spécifiquement CM20250003
$cm_found = false;
foreach ($coursiersConnectes as $coursier) {
    if ($coursier['matricule'] === 'CM20250003') {
        echo "\n🎊 ✅ CM20250003 (ZALLE) EST MAINTENANT VISIBLE dans la carte !\n";
        $cm_found = true;
        break;
    }
}

if (!$cm_found) {
    echo "\n❌ CM20250003 n'est toujours pas visible.\n";
}
?>