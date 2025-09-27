<?php
require_once(__DIR__ . '/config.php');

echo "=== DIAGNOSTIC TEMPOREL ===\n";
echo "Heure PHP actuelle : " . date('Y-m-d H:i:s') . "\n";
echo "Timestamp PHP : " . time() . "\n\n";

$pdo = getDBConnection();

$stmt = $pdo->query('SELECT NOW() as mysql_time, UNIX_TIMESTAMP() as mysql_timestamp');
$result = $stmt->fetch();
echo "Heure MySQL : " . $result['mysql_time'] . "\n";
echo "Timestamp MySQL : " . $result['mysql_timestamp'] . "\n\n";

// Données CM20250003
$stmt = $pdo->prepare("SELECT 
    last_login_at,
    UNIX_TIMESTAMP(last_login_at) as login_timestamp,
    TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as diff_minutes,
    TIMESTAMPDIFF(SECOND, last_login_at, NOW()) as diff_seconds,
    (last_login_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) as is_recent_mysql
FROM agents_suzosky WHERE matricule = 'CM20250003'");
$stmt->execute();
$cm = $stmt->fetch();

if ($cm) {
    echo "=== CM20250003 TEMPOREL ===\n";
    echo "Last login MySQL : " . $cm['last_login_at'] . "\n";
    echo "Login timestamp : " . $cm['login_timestamp'] . "\n";
    echo "Différence minutes (MySQL) : " . $cm['diff_minutes'] . "\n";
    echo "Différence secondes (MySQL) : " . $cm['diff_seconds'] . "\n";
    echo "Récent selon MySQL (30min) : " . ($cm['is_recent_mysql'] ? "OUI" : "NON") . "\n\n";
    
    // Calcul PHP
    $login_timestamp = strtotime($cm['last_login_at']);
    $diff_php = (time() - $login_timestamp) / 60;
    echo "Différence minutes (PHP) : " . round($diff_php, 2) . "\n";
    echo "Récent selon PHP (30min) : " . ($diff_php <= 30 ? "OUI" : "NON") . "\n";
}

// Mettre à jour l'heure de connexion pour le test
echo "\n=== MISE À JOUR TEST ===\n";
$stmt = $pdo->prepare("UPDATE agents_suzosky SET last_login_at = NOW() WHERE matricule = 'CM20250003'");
$stmt->execute();
echo "✅ Heure de connexion mise à jour pour CM20250003\n";

// Re-tester
$stmt = $pdo->prepare("SELECT 
    TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_fresh
FROM agents_suzosky WHERE matricule = 'CM20250003'");
$stmt->execute();
$fresh = $stmt->fetch();
echo "Nouvelles minutes écoulées : " . $fresh['minutes_fresh'] . "\n";
?>