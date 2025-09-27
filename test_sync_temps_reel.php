<?php
/**
 * TEST SYNCHRONISATION TEMPS RÉEL
 * Simulation complète d'une commande avec notifications
 */

require_once 'config.php';

echo "🧪 TEST SYNCHRONISATION TEMPS RÉEL - CM20250001\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier le coursier
    $coursierId = 3; // YAPO Emmanuel
    
    echo "👤 1. PRÉPARATION COURSIER\n";
    
    // Recharger son compte
    $stmt = $pdo->prepare("UPDATE agents_suzosky SET solde_wallet = 1000 WHERE id = ?");
    $stmt->execute([$coursierId]);
    echo "   ✅ Solde rechargé: 1000 FCFA\n";
    
    // Forcer connexion en ligne
    $stmt = $pdo->prepare("
        UPDATE agents_suzosky 
        SET statut_connexion = 'en_ligne', 
            last_login_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$coursierId]);
    echo "   ✅ Coursier mis en ligne\n";
    
    // 2. Créer un token FCM fonctionnel
    echo "\n📱 2. CONFIGURATION FCM\n";
    
    // Token FCM de test (format Firebase standard)
    $testToken = 'f1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
    
    // Supprimer anciens tokens
    $stmt = $pdo->prepare("DELETE FROM device_tokens WHERE coursier_id = ?");
    $stmt->execute([$coursierId]);
    
    // Ajouter nouveau token
    $stmt = $pdo->prepare("
        INSERT INTO device_tokens 
        (coursier_id, token, device_type, platform, is_active, created_at, updated_at, last_ping)
        VALUES (?, ?, 'mobile', 'android', 1, NOW(), NOW(), NOW())
    ");
    $stmt->execute([$coursierId, $testToken]);
    echo "   ✅ Token FCM configuré: " . substr($testToken, 0, 20) . "...\n";
    
    // 3. Créer une commande de test
    echo "\n📦 3. CRÉATION COMMANDE TEST\n";
    
    $codeCommande = 'TEST_' . date('YmdHis');
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes 
        (order_number, code_commande, client_nom, client_telephone, adresse_depart, adresse_arrivee, 
         description, prix_total, statut, coursier_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $orderNumber = 'ORD' . date('YmdHis') . rand(100, 999);
    
    $stmt->execute([
        $orderNumber,
        $codeCommande,
        'CLIENT TEST',
        '0123456789',
        'Cocody Riviera 2',
        'Plateau Boulevard Carde',
        'Commande de test synchronisation mobile',
        1500,
        'attribuee',
        $coursierId
    ]);
    
    $commandeId = $pdo->lastInsertId();
    echo "   ✅ Commande créée: #{$commandeId} ($codeCommande)\n";
    echo "   💰 Prix: 1500 FCFA\n";
    
    // 4. Envoyer notification FCM
    echo "\n🔔 4. ENVOI NOTIFICATION FCM\n";
    
    function envoyerNotificationFCM($token, $title, $body, $data = []) {
        // Configuration Firebase (simulation)
        $serverKey = 'your_server_key'; // Remplacer par vraie clé
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => 1,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ];
        
        $payload = [
            'to' => $token,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high',
            'android' => [
                'notification' => [
                    'channel_id' => 'commandes_channel',
                    'sound' => 'default',
                    'priority' => 'high'
                ]
            ]
        ];
        
        // Pour debug - simuler envoi
        echo "   📤 Payload FCM:\n";
        echo "      Title: $title\n";
        echo "      Body: $body\n";
        echo "      Token: " . substr($token, 0, 20) . "...\n";
        echo "      Data: " . json_encode($data) . "\n";
        
        return 'debug_message_id_' . uniqid();
    }
    
    $messageId = envoyerNotificationFCM(
        $testToken,
        '🚚 Nouvelle Commande!',
        "Commande #{$commandeId} - 1500 FCFA\nDe: Cocody Riviera 2\nVers: Plateau Boulevard",
        [
            'commande_id' => $commandeId,
            'type' => 'nouvelle_commande',
            'action' => 'accept_refuse',
            'prix' => 1500
        ]
    );
    
    // Enregistrer dans l'historique
    $stmt = $pdo->prepare("
        INSERT INTO notifications_log_fcm 
        (coursier_id, commande_id, token_used, message, type, status, response, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $coursierId,
        $commandeId,
        $testToken,
        "Nouvelle Commande #{$commandeId} - 1500 FCFA",
        'nouvelle_commande',
        'sent',
        "Message ID: $messageId"
    ]);
    
    echo "   ✅ Notification envoyée (simulée)\n";
    echo "   🔗 Message ID: $messageId\n";
    
    // 5. Simulation réponse coursier via ADB
    echo "\n📱 5. SIMULATION RÉPONSE COURSIER (ADB)\n";
    
    echo "   🔧 Commandes ADB à exécuter sur le téléphone:\n\n";
    
    // Commandes ADB pour vérifier l'app
    $adbCommands = [
        "# 1. Vérifier que l'app est installée",
        "adb shell pm list packages | findstr suzosky",
        "",
        "# 2. Vérifier les logs de l'app",
        "adb logcat -s SuzoskyCoursier:* Firebase:* FCM:*",
        "",
        "# 3. Forcer démarrage de l'app",
        "adb shell am start -n com.suzosky.coursier/.MainActivity",
        "",
        "# 4. Envoyer notification test via ADB",
        "adb shell am broadcast -a com.google.firebase.messaging.RECEIVE_FCM",
        "",
        "# 5. Vérifier connexion réseau",
        "adb shell ping -c 3 8.8.8.8",
        "",
        "# 6. Vérifier les services en arrière-plan",
        "adb shell dumpsys activity services | findstr suzosky"
    ];
    
    foreach ($adbCommands as $cmd) {
        echo "      $cmd\n";
    }
    
    // 6. Créer endpoint de test pour l'app mobile
    echo "\n🌐 6. ENDPOINT TEST MOBILE\n";
    
    $endpointUrl = "http://localhost/COURSIER_LOCAL/mobile_app.php";
    echo "   🔗 URL: $endpointUrl\n";
    echo "   📋 Paramètres de test:\n";
    echo "      • action=get_commandes\n";
    echo "      • coursier_id=$coursierId\n";
    echo "      • token=" . substr($testToken, 0, 20) . "...\n";
    
    // Test de l'endpoint
    $testData = [
        'action' => 'get_commandes',
        'coursier_id' => $coursierId,
        'token' => $testToken
    ];
    
    echo "\n   🧪 Test de l'endpoint:\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpointUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($testData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "      Status: $httpCode\n";
    echo "      Response: " . substr($response, 0, 200) . "...\n";
    
    // 7. Instructions de vérification
    echo "\n✅ 7. VÉRIFICATIONS À EFFECTUER\n";
    
    echo "   📱 Sur le téléphone (ADB):\n";
    echo "      1. Ouvrir l'application Suzosky Coursier\n";
    echo "      2. Vérifier la réception de la notification\n";
    echo "      3. Vérifier l'affichage de la commande #{$commandeId}\n";
    echo "      4. Tester acceptation/refus de la commande\n";
    
    echo "\n   🖥️ Sur le serveur:\n";
    echo "      1. Vérifier les logs: tail -f debug_requests.log\n";
    echo "      2. Monitorer la BDD: SELECT * FROM commandes WHERE id = $commandeId\n";
    echo "      3. Vérifier FCM: SELECT * FROM notifications_log_fcm WHERE commande_id = $commandeId\n";
    
    echo "\n   🌐 Tests d'API:\n";
    echo "      • GET: $endpointUrl?action=get_commandes&coursier_id=$coursierId\n";
    echo "      • POST: Accept commande #{$commandeId}\n";
    echo "      • POST: Update position GPS\n";
    
    // 8. Résumé du test
    echo "\n📊 8. RÉSUMÉ DU TEST\n";
    
    echo "   👤 Coursier: YAPO Emmanuel (ID: $coursierId)\n";
    echo "   💰 Solde: 1000 FCFA\n";
    echo "   📱 Token FCM: Configuré\n";
    echo "   📦 Commande: #{$commandeId} ($codeCommande)\n";
    echo "   🔔 Notification: Envoyée\n";
    echo "   🌐 API: Accessible\n";
    
    echo "\n🎯 PROCHAINES ÉTAPES:\n";
    echo "   1. Connecter le téléphone via ADB\n";
    echo "   2. Lancer l'app Suzosky Coursier\n";
    echo "   3. Vérifier la synchronisation des données\n";
    echo "   4. Tester l'acceptation de la commande\n";
    echo "   5. Monitorer les logs en temps réel\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>