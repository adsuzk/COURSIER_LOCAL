<?php
require_once(__DIR__ . '/config.php');

echo "=== TEST FINAL - Carte Coursiers Connectés ===\n\n";

// Simuler l'appel de la fonction renderCoursiersStatusContent
require_once(__DIR__ . '/admin_commandes_enhanced.php');

// Récupérer tous les coursiers
$coursiers = getAllCoursiers();
echo "📊 Nombre total de coursiers récupérés : " . count($coursiers) . "\n\n";

// Appliquer le même filtre que dans renderCoursiersStatusContent
$coursiersConnectes = [];
foreach ($coursiers as $coursier) {
    // Un coursier est connecté s'il a un token ET est en ligne ET a une activité récente
    $hasToken = !empty($coursier['current_session_token']);
    $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
    $lastActivity = strtotime($coursier['last_login_at'] ?? '0');
    $isRecentActivity = $lastActivity > (time() - 1800); // 30 minutes
    
    $isConnected = $hasToken && $isOnline && $isRecentActivity;
    
    if ($isConnected) {
        $coursiersConnectes[] = $coursier;
        echo "✅ COURSIER CONNECTÉ : " . $coursier['nom'] . " (" . ($coursier['id'] ?? 'N/A') . ")\n";
        echo "   - Token : " . (!empty($coursier['current_session_token']) ? "✅ OUI" : "❌ NON") . "\n";
        echo "   - Statut : " . ($coursier['statut_connexion'] ?? 'N/A') . "\n";
        echo "   - Dernière activité : " . ($coursier['last_login_at'] ?? 'N/A') . "\n";
        echo "   - Minutes écoulées : " . round((time() - strtotime($coursier['last_login_at'] ?? '0'))/60) . "\n\n";
    }
}

echo "🎯 RÉSULTAT FINAL :\n";
echo "Coursiers connectés visibles dans la carte : " . count($coursiersConnectes) . "\n";

// Chercher spécifiquement CM20250003
$cm_found = false;
foreach ($coursiersConnectes as $c) {
    if (($c['id'] ?? '') === 'CM20250003' || strpos($c['nom'] ?? '', 'ZALLE') !== false) {
        echo "🎊 CM20250003 (ZALLE) TROUVÉ dans la liste des coursiers connectés !\n";
        $cm_found = true;
        break;
    }
}

if (!$cm_found) {
    echo "❌ CM20250003 (ZALLE) NON trouvé dans la liste des coursiers connectés.\n";
}
?>