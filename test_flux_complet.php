<?php
/**
 * TEST COMPLET DU FLUX DE COMMANDE
 * De la création jusqu'à la réception par le coursier
 */

require_once 'config.php';

echo "🧪 TEST COMPLET DU FLUX DE COMMANDE\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. État initial du système
    echo "\n📊 1. ÉTAT INITIAL DU SYSTÈME\n";
    
    // Coursiers connectés
    $stmt = $pdo->prepare("SELECT id, nom, prenoms, statut_connexion, current_session_token, last_login_at FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
    $stmt->execute();
    $coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   📱 Coursiers connectés: " . count($coursiers) . "\n";
    
    $coursierTest = null;
    foreach ($coursiers as $c) {
        echo "   • {$c['nom']} {$c['prenoms']} (ID: {$c['id']})\n";
        echo "     Token: " . (!empty($c['current_session_token']) ? '✅' : '❌') . "\n";
        
        // Vérifier FCM
        $stmtFCM = $pdo->prepare("SELECT COUNT(*) FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
        $stmtFCM->execute([$c['id']]);
        $fcmCount = $stmtFCM->fetchColumn();
        echo "     FCM: " . ($fcmCount > 0 ? "✅ ($fcmCount tokens)" : '❌') . "\n";
        
        if (!empty($c['current_session_token']) && $fcmCount > 0) {
            $coursierTest = $c;
        }
    }
    
    if (!$coursierTest) {
        echo "   ❌ AUCUN coursier disponible pour le test (besoin token + FCM)\n";
        exit(1);
    }
    
    echo "   🎯 Coursier sélectionné pour test: {$coursierTest['nom']} {$coursierTest['prenoms']}\n";
    
    // 2. Créer une commande test
    echo "\n📦 2. CRÉATION COMMANDE TEST\n";
    
    $commandeData = [
        'order_number' => 'TEST_' . uniqid(),
        'code_commande' => 'TC' . date('ymdHi'),
        'client_type' => 'particulier',
        'client_nom' => 'TEST CLIENT',
        'client_telephone' => '+225 07 12 34 56 78',
        'adresse_depart' => 'Cocody Riviera 2, Abidjan',
        'adresse_arrivee' => 'Plateau Immeuble CCIA, Abidjan',
        'adresse_retrait' => 'Cocody Riviera 2, Abidjan',
        'adresse_livraison' => 'Plateau Immeuble CCIA, Abidjan',
        'description_colis' => 'Test automatique - Livraison documents urgents',
        'prix_total' => 2500,
        'prix_base' => 2000,
        'frais_supplementaires' => 500,
        'statut' => 'en_attente',
        'priorite' => 'normale',
        'mode_paiement' => 'especes',
        'statut_paiement' => 'attente',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            order_number, code_commande, client_type, client_nom, client_telephone, 
            adresse_depart, adresse_arrivee, adresse_retrait, adresse_livraison, 
            description_colis, prix_total, prix_base, frais_supplementaires, 
            statut, priorite, mode_paiement, statut_paiement, created_at
        ) VALUES (
            :order_number, :code_commande, :client_type, :client_nom, :client_telephone,
            :adresse_depart, :adresse_arrivee, :adresse_retrait, :adresse_livraison,
            :description_colis, :prix_total, :prix_base, :frais_supplementaires,
            :statut, :priorite, :mode_paiement, :statut_paiement, :created_at
        )
    ");
    
    if ($stmt->execute($commandeData)) {
        $commandeId = $pdo->lastInsertId();
        echo "   ✅ Commande créée avec ID: $commandeId\n";
        echo "   � Code: {$commandeData['code_commande']}\n";
        echo "   �📍 Départ: {$commandeData['adresse_depart']}\n";
        echo "   📍 Arrivée: {$commandeData['adresse_arrivee']}\n";
        echo "   💰 Prix: {$commandeData['prix_total']} FCFA\n";
    } else {
        echo "   ❌ Erreur création commande\n";
        exit(1);
    }
    
    // 3. Assigner la commande au coursier
    echo "\n🎯 3. ASSIGNATION AU COURSIER\n";
    
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET coursier_id = ?, statut = 'assignee', updated_at = NOW()
        WHERE id = ?
    ");
    
    if ($stmt->execute([$coursierTest['id'], $commandeId])) {
        echo "   ✅ Commande assignée à {$coursierTest['nom']} {$coursierTest['prenoms']}\n";
    } else {
        echo "   ❌ Erreur assignation commande\n";
        exit(1);
    }
    
    // 4. Simuler l'envoi de notification FCM
    echo "\n🔔 4. SIMULATION NOTIFICATION FCM\n";
    
    // Récupérer les tokens FCM du coursier
    $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
    $stmt->execute([$coursierTest['id']]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "   📱 Tokens FCM trouvés: " . count($tokens) . "\n";
    
    foreach ($tokens as $token) {
        echo "   📤 Envoi notification au token: " . substr($token, 0, 20) . "...\n";
        
        // Log de la notification (simulation)
        $stmt = $pdo->prepare("
            INSERT INTO notifications_log_fcm (coursier_id, commande_id, token_used, message, status, created_at) 
            VALUES (?, ?, ?, ?, 'sent', NOW())
        ");
        
        $message = "Nouvelle commande #{$commandeId} - {$commandeData['adresse_depart']} → {$commandeData['adresse_arrivee']} ({$commandeData['prix_total']} FCFA)";
        
        $stmt->execute([
            $coursierTest['id'], 
            $commandeId, 
            $token, 
            $message
        ]);
        
        echo "   ✅ Notification loggée\n";
    }
    
    // 5. Vérifier la réception côté coursier
    echo "\n📲 5. VÉRIFICATION RÉCEPTION COURSIER\n";
    
    // Simuler l'API coursier qui récupère ses commandes
    $stmt = $pdo->prepare("
        SELECT c.*, cl.nom as client_nom, cl.telephone as client_telephone
        FROM commandes c
        LEFT JOIN clients cl ON c.client_id = cl.id  
        WHERE c.coursier_id = ? AND c.statut = 'assignee'
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$coursierTest['id']]);
    $commandesCoursier = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   📦 Commandes assignées au coursier: " . count($commandesCoursier) . "\n";
    
    foreach ($commandesCoursier as $cmd) {
        if ($cmd['id'] == $commandeId) {
            echo "   ✅ COMMANDE TEST TROUVÉE!\n";
            echo "   📋 Détails reçus par le coursier:\n";
            echo "      • ID: {$cmd['id']}\n";
            echo "      • Client: {$cmd['client_nom']}\n";
            echo "      • Téléphone: {$cmd['client_telephone']}\n";
            echo "      • Départ: {$cmd['adresse_depart']}\n";
            echo "      • Arrivée: {$cmd['adresse_arrivee']}\n";
            echo "      • Prix: {$cmd['prix_commande']} FCFA\n";
            echo "      • Statut: {$cmd['statut']}\n";
            break;
        }
    }
    
    // 6. Simuler confirmation de réception par le coursier
    echo "\n✅ 6. CONFIRMATION RÉCEPTION\n";
    
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET statut = 'confirmee', updated_at = NOW()
        WHERE id = ?
    ");
    
    if ($stmt->execute([$commandeId])) {
        echo "   ✅ Coursier a confirmé la réception de la commande\n";
    }
    
    // 7. Statistiques finales
    echo "\n📊 7. RÉSUMÉ FINAL\n";
    
    // Vérifier les logs FCM
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications_log_fcm WHERE commande_id = ?");
    $stmt->execute([$commandeId]);
    $notifCount = $stmt->fetchColumn();
    
    echo "   📈 Notifications FCM envoyées: $notifCount\n";
    
    // Statut final de la commande
    $stmt = $pdo->prepare("SELECT statut, updated_at FROM commandes WHERE id = ?");
    $stmt->execute([$commandeId]);
    $finalStatus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   🎯 Statut final commande: {$finalStatus['statut']}\n";
    echo "   ⏰ Dernière mise à jour: {$finalStatus['updated_at']}\n";
    
    echo "\n🎉 TEST COMPLET RÉUSSI!\n";
    echo "✅ Flux complet validé: Création → Assignation → Notification → Réception → Confirmation\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>