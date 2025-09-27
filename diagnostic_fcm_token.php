<?php
require_once(__DIR__ . '/config.php');

echo "=== DIAGNOSTIC FCM TOKEN - COURSIER CM20250003 ===\n\n";

$pdo = getDBConnection();

// 1. Vérifier le coursier dans agents_suzosky
echo "1. STATUT CONNEXION agents_suzosky :\n";
$stmt = $pdo->prepare("SELECT 
    id, 
    matricule, 
    nom, 
    prenoms,
    statut_connexion,
    current_session_token,
    last_login_at,
    TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_depuis_connexion
FROM agents_suzosky WHERE matricule = 'CM20250003'");
$stmt->execute();
$coursier = $stmt->fetch(PDO::FETCH_ASSOC);

if ($coursier) {
    echo "✅ Coursier trouvé :\n";
    echo "   - ID : " . $coursier['id'] . "\n";
    echo "   - Nom : " . $coursier['nom'] . " " . $coursier['prenoms'] . "\n";
    echo "   - Statut connexion : " . $coursier['statut_connexion'] . "\n";
    echo "   - Session token : " . ($coursier['current_session_token'] ? "✅ OUI" : "❌ NON") . "\n";
    echo "   - Dernière connexion : " . $coursier['last_login_at'] . "\n";
    echo "   - Minutes depuis connexion : " . $coursier['minutes_depuis_connexion'] . "\n\n";
    
    $coursier_id = $coursier['id'];
} else {
    echo "❌ Coursier CM20250003 non trouvé\n\n";
    exit;
}

// 2. Vérifier les tokens FCM dans device_tokens
echo "2. TOKENS FCM device_tokens :\n";
$stmt = $pdo->prepare("SELECT 
    id,
    coursier_id,
    token,
    platform,
    is_active,
    created_at,
    updated_at,
    last_used,
    TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as minutes_depuis_maj
FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC");
$stmt->execute([$coursier_id]);
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($tokens) > 0) {
    echo "✅ " . count($tokens) . " token(s) trouvé(s) :\n";
    foreach ($tokens as $token) {
        $token_preview = substr($token['token'], 0, 30) . "...";
        $is_active = $token['is_active'] ?? 1;
        echo "   - ID " . $token['id'] . " : " . $token_preview . "\n";
        echo "     Platform : " . ($token['platform'] ?? 'N/A') . "\n";
        echo "     Actif : " . ($is_active ? "✅ OUI" : "❌ NON") . "\n";
        echo "     Créé : " . $token['created_at'] . "\n";
        echo "     MAJ : " . $token['updated_at'] . " (il y a " . $token['minutes_depuis_maj'] . " min)\n";
        echo "     Dernier usage : " . ($token['last_used'] ?? 'N/A') . "\n\n";
    }
} else {
    echo "❌ AUCUN token FCM trouvé pour ce coursier !\n";
    echo "🔥 C'EST LE PROBLÈME ! L'application mobile n'a pas enregistré son token FCM.\n\n";
}

// 3. Vérifier les logs de notifications FCM
echo "3. LOGS NOTIFICATIONS FCM :\n";
try {
    $stmt = $pdo->prepare("SELECT 
        id,
        notification_type,
        title,
        message,
        success,
        fcm_response_code,
        created_at
    FROM notifications_log_fcm 
    WHERE coursier_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5");
    $stmt->execute([$coursier_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "⚠️ Table notifications_log_fcm n'existe pas encore\n";
    $logs = [];
}

if (count($logs) > 0) {
    echo "✅ " . count($logs) . " log(s) de notifications récent(s) :\n";
    foreach ($logs as $log) {
        echo "   - " . $log['created_at'] . " : " . $log['title'] . "\n";
        echo "     Type : " . $log['notification_type'] . "\n";
        echo "     Succès : " . ($log['success'] ? "✅ OUI" : "❌ NON") . "\n";
        echo "     Code réponse : " . ($log['fcm_response_code'] ?? 'N/A') . "\n\n";
    }
} else {
    echo "ℹ️ Aucun log de notification FCM pour ce coursier\n\n";
}

// 4. Diagnostic général du système FCM
echo "4. DIAGNOSTIC SYSTÈME FCM :\n";

// Vérifier la configuration FCM
$fcm_key_file = __DIR__ . '/data/secret_fcm_key.txt';
$firebase_sa_file = __DIR__ . '/coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json';

echo "Configuration FCM :\n";
if (file_exists($fcm_key_file)) {
    echo "   - FCM Server Key : ✅ Fichier présent\n";
} else {
    echo "   - FCM Server Key : ❌ Fichier manquant\n";
}

if (file_exists($firebase_sa_file)) {
    echo "   - Firebase Service Account : ✅ Fichier présent\n";
} else {
    echo "   - Firebase Service Account : ❌ Fichier manquant\n";
}

// Vérifier les tokens actifs globaux
$stmt = $pdo->query("SELECT 
    COUNT(*) as total_tokens,
    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_tokens,
    COUNT(DISTINCT coursier_id) as coursiers_with_tokens
FROM device_tokens");
$global_stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\nStatistiques globales tokens :\n";
echo "   - Total tokens : " . $global_stats['total_tokens'] . "\n";
echo "   - Tokens actifs : " . $global_stats['active_tokens'] . "\n";
echo "   - Coursiers avec tokens : " . $global_stats['coursiers_with_tokens'] . "\n\n";

// 5. Recommandations
echo "5. RECOMMANDATIONS :\n";

if (count($tokens) == 0) {
    echo "🚨 PROBLÈME CRITIQUE : Aucun token FCM pour CM20250003\n";
    echo "📱 L'application mobile doit :\n";
    echo "   1. Demander l'autorisation notifications\n";
    echo "   2. Générer un token FCM Firebase\n";
    echo "   3. L'envoyer au serveur via register_device_token_simple.php\n";
    echo "   4. Le renouveler périodiquement\n\n";
    
    echo "🔧 ACTIONS CORRECTIVES :\n";
    echo "   1. Vérifier les permissions notifications sur l'appareil\n";
    echo "   2. Redémarrer l'application mobile\n";
    echo "   3. Vérifier les logs Android (adb logcat | grep FCMService)\n";
    echo "   4. Tester l'enregistrement manuel du token\n\n";
} else {
    $active_tokens = array_filter($tokens, function($t) { return ($t['is_active'] ?? 1) == 1; });
    if (count($active_tokens) == 0) {
        echo "⚠️ Tokens présents mais tous inactifs\n";
        echo "🔧 Réactiver les tokens ou en générer de nouveaux\n\n";
    } else {
        echo "✅ Tokens actifs présents - problème ailleurs\n";
        echo "🔍 Vérifier les logs FCM et la réception des notifications\n\n";
    }
}

echo "6. TEST DIRECT TOKEN FCM :\n";
if (count($tokens) > 0) {
    $latest_token = $tokens[0]['token'];
    echo "Test avec le token le plus récent...\n";
    
    // Test simple sans log
    require_once(__DIR__ . '/api/lib/fcm_enhanced.php');
    $result = fcm_send_with_log(
        [$latest_token], 
        "🔔 Test Urgence", 
        "Test de réception FCM pour CM20250003", 
        ['type' => 'test', 'urgency' => 'high'],
        $coursier_id,
        null
    );
    
    echo "Résultat test FCM :\n";
    echo "   - Succès : " . ($result['success'] ? "✅ OUI" : "❌ NON") . "\n";
    echo "   - Méthode : " . ($result['method'] ?? 'N/A') . "\n";
    echo "   - Erreur : " . ($result['error'] ?? 'Aucune') . "\n";
} else {
    echo "❌ Impossible de tester - aucun token disponible\n";
}
?>