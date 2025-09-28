<?php
require_once 'config.php';

$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT id, nom, prenoms, statut_connexion, current_session_token, last_login_at,
           (last_login_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) AS activite_recente_30min,
           TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) AS minutes_depuis_connexion
    FROM agents_suzosky 
    WHERE statut_connexion = 'en_ligne'
");

echo "ANALYSE DÉTAILLÉE DES COURSIERS 'en_ligne':\n";
while($row = $stmt->fetch()) {
    echo "\n--- {$row['nom']} {$row['prenoms']} (ID: {$row['id']}) ---\n";
    echo "✅ Statut connexion: {$row['statut_connexion']}\n";
    echo "✅ Session token: " . ($row['current_session_token'] ? 'OUI' : 'NON') . "\n";
    echo "🕒 Dernier login: {$row['last_login_at']}\n";
    echo "📊 Minutes depuis connexion: {$row['minutes_depuis_connexion']}\n";
    echo "🔄 Activité récente (30min): " . ($row['activite_recente_30min'] ? 'OUI ✅' : 'NON ❌') . "\n";
    
    // Test logique unified
    $hasToken = !empty($row['current_session_token']);
    $isOnline = $row['statut_connexion'] === 'en_ligne';
    $isRecentActivity = (bool) $row['activite_recente_30min']; // 30min comme dans le code
    
    echo "\n🧮 ÉVALUATION getConnectedCouriers():\n";
    echo "   Token: " . ($hasToken ? '✅' : '❌') . "\n";
    echo "   Online: " . ($isOnline ? '✅' : '❌') . "\n";
    echo "   Activité récente: " . ($isRecentActivity ? '✅' : '❌') . "\n";
    echo "   RÉSULTAT: " . (($hasToken && $isOnline && $isRecentActivity) ? '✅ CONNECTÉ' : '❌ FILTRÉ') . "\n";
}
?>