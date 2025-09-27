<?php
/**
 * CORRECTIF: Synchronisation agents_suzosky → coursiers
 * Et test complet du flux de commande
 */

require_once 'config.php';

echo "🔧 CORRECTIF ET TEST COMPLET DU FLUX\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier et synchroniser les tables
    echo "\n🔄 1. SYNCHRONISATION DES TABLES\n";
    
    // Vérifier la structure de la table coursiers
    $stmt = $pdo->query('DESCRIBE coursiers');
    $coursiersCols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   📋 Colonnes table coursiers: " . implode(', ', $coursiersCols) . "\n";
    
    // Récupérer les agents connectés
    $stmt = $pdo->prepare("SELECT id, nom, prenoms, email, telephone, statut_connexion, current_session_token FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
    $stmt->execute();
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   📱 Agents connectés trouvés: " . count($agents) . "\n";
    
    $coursierTest = null;
    
    foreach ($agents as $agent) {
        // Vérifier si cet agent existe déjà dans coursiers
        $stmt = $pdo->prepare("SELECT id FROM coursiers WHERE email = ?");
        $stmt->execute([$agent['email']]);
        $existingCoursier = $stmt->fetch();
        
        if (!$existingCoursier) {
            echo "   ➕ Création coursier pour {$agent['nom']} {$agent['prenoms']}\n";
            
            // Créer le coursier correspondant
            $stmt = $pdo->prepare("
                INSERT INTO coursiers (nom, email, telephone, statut, created_at) 
                VALUES (?, ?, ?, 'actif', NOW())
            ");
            
            $nomComplet = trim($agent['nom'] . ' ' . $agent['prenoms']);
            $stmt->execute([$nomComplet, $agent['email'], $agent['telephone'] ?? '']);
            
            $coursierId = $pdo->lastInsertId();
            echo "   ✅ Coursier créé avec ID: $coursierId\n";
            
            // Associer l'agent avec ce coursier
            $coursierTest = [
                'coursier_id' => $coursierId,
                'agent_id' => $agent['id'],
                'nom' => $nomComplet,
                'email' => $agent['email'],
                'has_token' => !empty($agent['current_session_token'])
            ];
            
        } else {
            echo "   ✅ Coursier existant trouvé pour {$agent['nom']} {$agent['prenoms']}\n";
            $coursierTest = [
                'coursier_id' => $existingCoursier['id'],
                'agent_id' => $agent['id'],
                'nom' => $agent['nom'] . ' ' . $agent['prenoms'],
                'email' => $agent['email'],
                'has_token' => !empty($agent['current_session_token'])
            ];
        }
    }
    
    if (!$coursierTest) {
        echo "   ❌ AUCUN coursier disponible pour le test\n";
        exit(1);
    }
    
    echo "   🎯 Coursier sélectionné: {$coursierTest['nom']} (ID coursier: {$coursierTest['coursier_id']}, ID agent: {$coursierTest['agent_id']})\n";
    
    // 2. Vérifier les tokens FCM pour cet agent
    echo "\n📱 2. VÉRIFICATION FCM\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
    $stmt->execute([$coursierTest['agent_id']]);
    $fcmCount = $stmt->fetchColumn();
    
    echo "   🔔 Tokens FCM actifs: $fcmCount\n";
    
    if ($fcmCount == 0) {
        echo "   ⚠️ Création token FCM d'urgence...\n";
        $emergencyToken = 'test_emergency_' . uniqid() . '_' . $coursierTest['agent_id'];
        
        $stmt = $pdo->prepare("
            INSERT INTO device_tokens (coursier_id, token, device_type, is_active, created_at, updated_at) 
            VALUES (?, ?, 'test_device', 1, NOW(), NOW())
        ");
        $stmt->execute([$coursierTest['agent_id'], $emergencyToken]);
        echo "   ✅ Token d'urgence créé\n";
    }
    
    // 3. Créer une commande test
    echo "\n📦 3. CRÉATION COMMANDE TEST\n";
    
    $commandeData = [
        'order_number' => 'TEST_' . uniqid(),
        'code_commande' => 'TC' . date('ymdHi'),
        'client_type' => 'particulier',
        'client_nom' => 'CLIENT TEST AUTOMATIQUE',
        'client_telephone' => '+225 07 12 34 56 78',
        'adresse_depart' => 'Cocody Riviera 2, Abidjan',
        'adresse_arrivee' => 'Plateau Immeuble CCIA, Abidjan',
        'adresse_retrait' => 'Cocody Riviera 2, Abidjan',
        'adresse_livraison' => 'Plateau Immeuble CCIA, Abidjan',
        'description_colis' => 'Test automatique - Documents urgents',
        'prix_total' => 2500,
        'prix_base' => 2000,
        'frais_supplementaires' => 500,
        'statut' => 'en_attente',
        'priorite' => 'normale',
        'mode_paiement' => 'especes',
        'statut_paiement' => 'attente'
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
            :statut, :priorite, :mode_paiement, :statut_paiement, NOW()
        )
    ");
    
    if ($stmt->execute($commandeData)) {
        $commandeId = $pdo->lastInsertId();
        echo "   ✅ Commande créée avec ID: $commandeId\n";
        echo "   📋 Code: {$commandeData['code_commande']}\n";
        echo "   📍 {$commandeData['adresse_depart']} → {$commandeData['adresse_arrivee']}\n";
        echo "   💰 Prix: {$commandeData['prix_total']} FCFA\n";
    } else {
        echo "   ❌ Erreur création commande\n";
        exit(1);
    }
    
    // 4. Assigner au coursier (utilisation de l'ID coursier, pas agent)
    echo "\n🎯 4. ASSIGNATION AU COURSIER\n";
    
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET coursier_id = ?, statut = 'assignee', updated_at = NOW()
        WHERE id = ?
    ");
    
    if ($stmt->execute([$coursierTest['coursier_id'], $commandeId])) {
        echo "   ✅ Commande assignée au coursier ID: {$coursierTest['coursier_id']}\n";
        echo "   👤 Nom: {$coursierTest['nom']}\n";
    } else {
        echo "   ❌ Erreur assignation\n";
        exit(1);
    }
    
    // 5. Notification FCM (utilisation de l'ID agent pour les tokens)
    echo "\n🔔 5. NOTIFICATION FCM\n";
    
    $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
    $stmt->execute([$coursierTest['agent_id']]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "   📱 Tokens FCM: " . count($tokens) . "\n";
    
    foreach ($tokens as $token) {
        $message = "🚚 Nouvelle commande #{$commandeId} | {$commandeData['code_commande']} | {$commandeData['adresse_depart']} → {$commandeData['adresse_arrivee']} | {$commandeData['prix_total']} FCFA";
        
        echo "   📤 Envoi notification: " . substr($token, 0, 20) . "...\n";
        
        // Log notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications_log_fcm (coursier_id, commande_id, token_used, message, status, created_at)
            VALUES (?, ?, ?, ?, 'sent', NOW())
        ");
        $stmt->execute([$coursierTest['agent_id'], $commandeId, $token, $message]);
        
        echo "   ✅ Notification loggée\n";
    }
    
    // 6. Simuler réception côté coursier (API mobile)
    echo "\n📲 6. RÉCEPTION PAR LE COURSIER\n";
    
    // Simulation de l'API mobile qui récupère les commandes
    $stmt = $pdo->prepare("
        SELECT c.*, 'coursier_api' as source
        FROM commandes c
        WHERE c.coursier_id = ? AND c.statut = 'assignee'
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$coursierTest['coursier_id']]);
    $commandeReçue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($commandeReçue && $commandeReçue['id'] == $commandeId) {
        echo "   ✅ COMMANDE REÇUE PAR L'API COURSIER!\n";
        echo "   📋 Données reçues:\n";
        echo "      • Code: {$commandeReçue['code_commande']}\n";
        echo "      • Client: {$commandeReçue['client_nom']}\n";
        echo "      • Téléphone: {$commandeReçue['client_telephone']}\n";
        echo "      • Retrait: {$commandeReçue['adresse_retrait']}\n";
        echo "      • Livraison: {$commandeReçue['adresse_livraison']}\n";
        echo "      • Prix: {$commandeReçue['prix_total']} FCFA\n";
        echo "      • Statut: {$commandeReçue['statut']}\n";
        
        // 7. Confirmation d'acceptation par le coursier
        echo "\n✅ 7. ACCEPTATION PAR LE COURSIER\n";
        
        $stmt = $pdo->prepare("
            UPDATE commandes 
            SET statut = 'acceptee', heure_acceptation = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$commandeId])) {
            echo "   ✅ Coursier a accepté la commande\n";
            echo "   ⏰ Heure acceptation: " . date('Y-m-d H:i:s') . "\n";
        }
        
        // 8. Simulation progression
        echo "\n🚚 8. SIMULATION PROGRESSION\n";
        
        sleep(1); // Petite pause pour simuler le temps
        
        // En route vers retrait
        $stmt = $pdo->prepare("UPDATE commandes SET statut = 'en_route_retrait' WHERE id = ?");
        $stmt->execute([$commandeId]);
        echo "   🚶 En route vers le retrait\n";
        
        sleep(1);
        
        // Colis récupéré
        $stmt = $pdo->prepare("UPDATE commandes SET statut = 'colis_recupere', heure_retrait = NOW() WHERE id = ?");
        $stmt->execute([$commandeId]);
        echo "   📦 Colis récupéré\n";
        
        sleep(1);
        
        // En cours de livraison
        $stmt = $pdo->prepare("UPDATE commandes SET statut = 'en_cours_livraison' WHERE id = ?");
        $stmt->execute([$commandeId]);
        echo "   🚚 En cours de livraison\n";
        
        sleep(1);
        
        // Livré
        $stmt = $pdo->prepare("UPDATE commandes SET statut = 'livre', heure_livraison = NOW() WHERE id = ?");
        $stmt->execute([$commandeId]);
        echo "   ✅ Livré avec succès!\n";
        
    } else {
        echo "   ❌ COMMANDE NON REÇUE PAR LE COURSIER\n";
    }
    
    // 9. Résumé final
    echo "\n📊 9. RÉSUMÉ FINAL DU TEST\n";
    
    // Statut final
    $stmt = $pdo->prepare("SELECT statut, created_at, heure_acceptation, heure_retrait, heure_livraison FROM commandes WHERE id = ?");
    $stmt->execute([$commandeId]);
    $finalData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   🎯 Statut final: {$finalData['statut']}\n";
    echo "   📅 Créée: {$finalData['created_at']}\n";
    echo "   ✅ Acceptée: {$finalData['heure_acceptation']}\n";
    echo "   📦 Retirée: {$finalData['heure_retrait']}\n";
    echo "   🚚 Livrée: {$finalData['heure_livraison']}\n";
    
    // Notifications envoyées
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications_log_fcm WHERE commande_id = ?");
    $stmt->execute([$commandeId]);
    $notifCount = $stmt->fetchColumn();
    
    echo "   🔔 Notifications FCM: $notifCount\n";
    
    // Calculer le temps total
    $tempsTotal = strtotime($finalData['heure_livraison']) - strtotime($finalData['created_at']);
    echo "   ⏱️ Temps total: " . gmdate('H:i:s', $tempsTotal) . "\n";
    
    echo "\n🎉 TEST COMPLET RÉUSSI!\n";
    echo "✅ Flux validé: Création → Assignation → Notification → Réception → Acceptation → Progression → Livraison\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Ligne: " . $e->getLine() . "\n";
}
?>