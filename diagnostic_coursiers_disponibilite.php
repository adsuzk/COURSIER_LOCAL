<?php
// diagnostic_coursiers_disponibilite.php - Diagnostic disponibilité coursiers en production
require_once __DIR__ . '/config.php';

echo "=== DIAGNOSTIC DISPONIBILITÉ COURSIERS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Environment: " . (defined('FORCE_PRODUCTION_DB') && FORCE_PRODUCTION_DB ? 'PRODUCTION' : 'LOCAL') . "\n\n";

try {
    // 1. Vérifier connexion DB
    echo "1. TEST CONNEXION BASE DE DONNÉES:\n";
    $stmt = $pdo->query("SELECT DATABASE() as db_name, NOW() as current_time");
    $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✅ Base connectée: {$dbInfo['db_name']}\n";
    echo "   ✅ Heure serveur: {$dbInfo['current_time']}\n\n";

    // 2. Vérifier table agents_suzosky
    echo "2. TEST TABLE AGENTS_SUZOSKY:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total_agents FROM agents_suzosky");
    $totalAgents = $stmt->fetchColumn();
    echo "   ✅ Total agents: $totalAgents\n";

    // 3. Vérifier coursiers connectés
    echo "3. TEST COURSIERS CONNECTÉS:\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
    $stmt->execute();
    $connectes = $stmt->fetchColumn();
    echo "   📊 Coursiers 'en_ligne': $connectes\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne' AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30");
    $stmt->execute();
    $connectesRecents = $stmt->fetchColumn();
    echo "   📊 Coursiers actifs (< 30min): $connectesRecents\n";

    // 4. Détail des coursiers
    echo "\n4. DÉTAIL COURSIERS:\n";
    $stmt = $pdo->query("SELECT id, username, statut_connexion, last_login_at, 
                        TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_inactif
                        FROM agents_suzosky 
                        ORDER BY last_login_at DESC LIMIT 5");
    $coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($coursiers as $coursier) {
        $status = $coursier['statut_connexion'];
        $inactif = $coursier['minutes_inactif'] ?? 'N/A';
        $lastLogin = $coursier['last_login_at'] ?? 'Jamais';
        echo "   📱 {$coursier['username']}: $status (inactif: {$inactif}min, dernier: $lastLogin)\n";
    }

    // 5. Test FCM Security
    echo "\n5. TEST FCM SECURITY:\n";
    $fcmSecurityPath = __DIR__ . '/fcm_token_security.php';
    $fcmSecurityPathCron = __DIR__ . '/Scripts/Scripts cron/fcm_token_security.php';
    
    if (file_exists($fcmSecurityPath)) {
        echo "   ✅ fcm_token_security.php trouvé à la racine\n";
        require_once $fcmSecurityPath;
        
        $tokenSecurity = new FCMTokenSecurity();
        $disponibilite = $tokenSecurity->canAcceptNewOrders();
        
        echo "   📊 Can accept orders: " . ($disponibilite['can_accept_orders'] ? 'OUI' : 'NON') . "\n";
        echo "   📊 Raison: " . ($disponibilite['reason'] ?? 'N/A') . "\n";
        
        if (!$disponibilite['can_accept_orders']) {
            $message = $tokenSecurity->getUnavailabilityMessage();
            echo "   📝 Message: $message\n";
        }
    } elseif (file_exists($fcmSecurityPathCron)) {
        echo "   ✅ fcm_token_security.php trouvé dans Scripts/Scripts cron/\n";
        require_once $fcmSecurityPathCron;
        
        $tokenSecurity = new FCMTokenSecurity();
        $disponibilite = $tokenSecurity->canAcceptNewOrders();
        
        echo "   📊 Can accept orders: " . ($disponibilite['can_accept_orders'] ? 'OUI' : 'NON') . "\n";
        echo "   📊 Raison: " . ($disponibilite['reason'] ?? 'N/A') . "\n";
        
        if (!$disponibilite['can_accept_orders']) {
            $message = $tokenSecurity->getUnavailabilityMessage();
            echo "   📝 Message: $message\n";
        }
    } else {
        echo "   ❌ Aucun fichier FCM Security trouvé\n";
        echo "   📁 Recherché dans: $fcmSecurityPath\n";
        echo "   📁 Recherché dans: $fcmSecurityPathCron\n";
    }

    // 6. Test device tokens
    echo "\n6. TEST DEVICE TOKENS:\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM device_tokens WHERE is_active = 1");
        $activeTokens = $stmt->fetchColumn();
        echo "   📊 Tokens FCM actifs: $activeTokens\n";
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT coursier_id) FROM device_tokens WHERE is_active = 1");
        $coursiersAvecToken = $stmt->fetchColumn();
        echo "   📊 Coursiers avec token actif: $coursiersAvecToken\n";
    } catch (Exception $e) {
        echo "   ⚠️  Table device_tokens: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";
?>