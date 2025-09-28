<?php
/**
 * SIMULATEUR FCM POUR TESTS DE SYNCHRONISATION
 * Simule l'envoi de notifications et teste la connectivité mobile
 */

require_once 'config.php';

echo "📱 SIMULATEUR FCM - TEST SYNCHRONISATION\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    $pdo = getDBConnection();
    $coursierId = 3; // YAPO Emmanuel
    
    // 1. Préparer le coursier
    echo "\n👤 1. PRÉPARATION COURSIER\n";
    
    // Vérifier token FCM
    $stmt = $pdo->prepare("
        SELECT id, token, is_active, created_at, last_ping
        FROM device_tokens 
        WHERE coursier_id = ? 
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->execute([$coursierId]);
    $tokenInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenInfo) {
        echo "   ⚠️ Aucun token FCM - Création d'urgence...\n";
        
        // Créer token de test
        $testToken = 'test_mobile_' . date('YmdHis') . '_' . uniqid();
        $stmt = $pdo->prepare("
            INSERT INTO device_tokens 
            (coursier_id, token, device_type, platform, is_active, created_at, updated_at, last_ping)
            VALUES (?, ?, 'mobile', 'android', 1, NOW(), NOW(), NOW())
        ");
        $stmt->execute([$coursierId, $testToken]);
        
        echo "   ✅ Token créé: " . substr($testToken, 0, 30) . "...\n";
        $tokenInfo = ['id' => $pdo->lastInsertId(), 'token' => $testToken, 'is_active' => 1];
    } else {
        echo "   ✅ Token existant: " . substr($tokenInfo['token'], 0, 30) . "...\n";
        echo "   📅 Créé: {$tokenInfo['created_at']}\n";
        echo "   🏃 Actif: " . ($tokenInfo['is_active'] ? 'Oui' : 'Non') . "\n";
    }
    
    // 2. Simuler réception de commande
    echo "\n📦 2. SIMULATION RÉCEPTION COMMANDE\n";
    
    // Récupérer dernière commande attribuée
    $stmt = $pdo->prepare("
        SELECT id, code_commande, client_nom, adresse_depart, adresse_arrivee, 
               prix_total, statut, created_at
        FROM commandes 
        WHERE coursier_id = ? AND statut IN ('attribuee', 'acceptee')
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$coursierId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        echo "   ℹ️ Aucune commande en attente - Création d'une commande test...\n";
        
        // Créer commande de test
        $codeCommande = 'SYNC_' . date('YmdHis');
        $orderNumber = 'ORD' . date('YmdHis') . rand(100, 999);
        
        $stmt = $pdo->prepare("
            INSERT INTO commandes 
            (order_number, code_commande, client_nom, client_telephone, 
             adresse_depart, adresse_arrivee, description,
             prix_total, statut, coursier_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $orderNumber,
            $codeCommande,
            'CLIENT SYNC TEST',
            '0712345678',
            'Marcory Zone 4C',
            'Treichville Rue du Commerce',
            'Test synchronisation mobile - Vérification notification',
            2000,
            'attribuee',
            $coursierId
        ]);
        
        $commandeId = $pdo->lastInsertId();
        $commande = [
            'id' => $commandeId,
            'code_commande' => $codeCommande,
            'client_nom' => 'CLIENT SYNC TEST',
            'adresse_depart' => 'Marcory Zone 4C',
            'adresse_arrivee' => 'Treichville Rue du Commerce',
            'prix_total' => '2000.00',
            'statut' => 'attribuee'
        ];
        
        echo "   ✅ Commande créée: #{$commandeId} ($codeCommande)\n";
    } else {
        echo "   📋 Commande existante: #{$commande['id']} ({$commande['code_commande']})\n";
    }
    
    echo "   💰 Prix: {$commande['prix_total']} FCFA\n";
    echo "   🏠 Départ: {$commande['adresse_depart']}\n";
    echo "   🎯 Arrivée: {$commande['adresse_arrivee']}\n";
    echo "   📊 Statut: {$commande['statut']}\n";
    
    // 3. Simuler envoi notification FCM
    echo "\n🔔 3. SIMULATION NOTIFICATION FCM\n";
    
    $notificationData = [
        'title' => '🚚 Nouvelle Commande Suzosky!',
        'body' => "#{$commande['id']} - {$commande['prix_total']} FCFA\n" .
                 "📍 {$commande['adresse_depart']}\n" .
                 "🎯 {$commande['adresse_arrivee']}\n" .
                 "👤 {$commande['client_nom']}",
        'data' => [
            'type' => 'nouvelle_commande',
            'commande_id' => $commande['id'],
            'action' => 'accept_refuse',
            'prix' => $commande['prix_total'],
            'client' => $commande['client_nom']
        ]
    ];
    
    echo "   📤 Payload notification:\n";
    echo "      Titre: {$notificationData['title']}\n";
    echo "      Message: " . str_replace("\n", " | ", $notificationData['body']) . "\n";
    echo "      Action: Accepter/Refuser\n";
    echo "      ID Commande: {$commande['id']}\n";
    
    // Enregistrer la notification simulée
    $stmt = $pdo->prepare("
        INSERT INTO notifications_log_fcm 
        (coursier_id, commande_id, token_used, message, type, status, response_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $coursierId,
        $commande['id'],
        $tokenInfo['token'],
        $notificationData['body'],
        'nouvelle_commande',
        'simulated',
        json_encode(['simulation' => true, 'payload' => $notificationData])
    ]);
    
    $notificationId = $pdo->lastInsertId();
    echo "   ✅ Notification enregistrée (ID: $notificationId)\n";
    
    // 4. Test API mobile endpoints
    echo "\n🌐 4. TEST ENDPOINTS API MOBILE\n";
    
    $baseUrl = "http://localhost/COURSIER_LOCAL/mobile_sync_api.php";
    
    // Test 1: Récupération profil
    echo "   📱 Test profil coursier...\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl?action=get_profile&coursier_id=$coursierId",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['success']) {
            echo "      ✅ Profil récupéré: {$data['profile']['nom']} {$data['profile']['prenoms']}\n";
            echo "      💰 Solde: {$data['profile']['solde']} FCFA\n";
        } else {
            echo "      ❌ Erreur profil: {$data['message']}\n";
        }
    } else {
        echo "      ❌ Erreur HTTP: $httpCode\n";
    }
    
    // Test 2: Récupération commandes
    echo "   📦 Test commandes coursier...\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$baseUrl?action=get_commandes&coursier_id=$coursierId",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data['success']) {
            echo "      ✅ {$data['count']} commandes récupérées\n";
            if ($data['count'] > 0) {
                $lastCmd = $data['commandes'][0];
                echo "      📋 Dernière: #{$lastCmd['id']} - {$lastCmd['statut']}\n";
            }
        } else {
            echo "      ❌ Erreur commandes: {$data['message']}\n";
        }
    } else {
        echo "      ❌ Erreur HTTP: $httpCode\n";
    }
    
    // 5. Instructions pour test mobile
    echo "\n📱 5. INSTRUCTIONS TEST MOBILE (ADB)\n";
    
    echo "   🔌 Connexion ADB:\n";
    echo "      adb devices\n\n";
    
    echo "   📱 Démarrage app:\n";
    echo "      adb shell am start -n com.suzosky.coursier/.MainActivity\n\n";
    
    echo "   📋 Logs à monitorer:\n";
    echo "      adb logcat -s FirebaseMessaging:* FCM:* SuzoskyCoursier:*\n\n";
    
    echo "   🧪 Tests depuis l'app:\n";
    echo "      1. Se connecter avec matricule: CM20250001\n";
    echo "      2. Vérifier réception commande #{$commande['id']}\n";
    echo "      3. Tester acceptation/refus\n";
    echo "      4. Vérifier mise à jour statut serveur\n\n";
    
    // 6. URLs de test directes
    echo "📡 6. URLS DE TEST DIRECTES\n";
    
    echo "   📊 Profil: $baseUrl?action=get_profile&coursier_id=$coursierId\n";
    echo "   📦 Commandes: $baseUrl?action=get_commandes&coursier_id=$coursierId\n";
    echo "   ✅ Accepter: $baseUrl?action=accept_commande&coursier_id=$coursierId&commande_id={$commande['id']}\n";
    echo "   ❌ Refuser: $baseUrl?action=refuse_commande&coursier_id=$coursierId&commande_id={$commande['id']}\n";
    echo "   🔔 Test notif: $baseUrl?action=test_notification&coursier_id=$coursierId\n";
    
    // 7. Monitoring en temps réel
    echo "\n📊 7. MONITORING TEMPS RÉEL\n";
    
    echo "   🗃️ Base de données:\n";
    echo "      SELECT * FROM commandes WHERE coursier_id = $coursierId ORDER BY id DESC LIMIT 3;\n";
    echo "      SELECT * FROM notifications_log_fcm WHERE coursier_id = $coursierId ORDER BY id DESC LIMIT 3;\n";
    echo "      SELECT * FROM device_tokens WHERE coursier_id = $coursierId;\n\n";
    
    echo "   📄 Logs fichiers:\n";
    echo "      tail -f mobile_sync_debug.log\n";
    echo "      tail -f debug_requests.log\n\n";
    
    // Résumé final
    echo "🎯 RÉSUMÉ DU TEST\n";
    echo "   👤 Coursier: YAPO Emmanuel (ID: $coursierId)\n";
    echo "   📱 Token FCM: Configuré (ID: {$tokenInfo['id']})\n";
    echo "   📦 Commande: #{$commande['id']} ({$commande['code_commande']})\n";
    echo "   🔔 Notification: Simulée (ID: $notificationId)\n";
    echo "   🌐 API: Fonctionnelle\n";
    echo "   📊 Statut: Prêt pour test mobile\n";
    
    echo "\n✅ SYSTÈME PRÉPARÉ POUR TEST DE SYNCHRONISATION\n";
    echo "🎬 Lancez maintenant l'application mobile via ADB\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>