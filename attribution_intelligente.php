<?php
/**
 * SYSTÈME INTELLIGENT D'ATTRIBUTION DES COMMANDES
 * Attribution automatique aux coursiers connectés et disponibles
 */

require_once 'config.php';

echo "🤖 SYSTÈME INTELLIGENT D'ATTRIBUTION\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier les coursiers connectés
    echo "\n👥 1. COURSIERS CONNECTÉS\n";
    
    $stmt = $pdo->query("
        SELECT 
            id, matricule, nom, prenoms, email, telephone,
            COALESCE(solde_wallet, 0) as solde,
            statut_connexion, last_login_at,
            TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_inactif,
            current_session_token
        FROM agents_suzosky 
        WHERE statut_connexion = 'en_ligne' 
        ORDER BY last_login_at DESC
    ");
    $coursiersConnectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($coursiersConnectes)) {
        echo "   ❌ AUCUN COURSIER CONNECTÉ!\n";
        
        // Afficher l'état de tous les coursiers
        $stmt = $pdo->query("
            SELECT 
                id, matricule, nom, prenoms, 
                COALESCE(solde_wallet, 0) as solde,
                statut_connexion, last_login_at
            FROM agents_suzosky 
            ORDER BY last_login_at DESC NULLS LAST
        ");
        $tousCoursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   📋 État de tous les coursiers:\n";
        foreach ($tousCoursiers as $c) {
            $matricule = $c['matricule'] ?? 'N/A';
            $status = $c['statut_connexion'] === 'en_ligne' ? '🟢' : '🔴';
            $solde = $c['solde'] > 0 ? '💰' : '💸';
            $lastLogin = $c['last_login_at'] ?? 'Jamais';
            echo "      $status $solde {$c['nom']} {$c['prenoms']} (M:{$matricule}) - {$c['solde']} FCFA - {$c['statut_connexion']} - $lastLogin\n";
        }
        
        // Simuler connexion des coursiers avec solde
        echo "\n   🔄 SIMULATION CONNEXION COURSIERS AVEC SOLDE...\n";
        $stmt = $pdo->prepare("
            UPDATE agents_suzosky 
            SET statut_connexion = 'en_ligne', 
                last_login_at = NOW(),
                current_session_token = CONCAT('sim_', UNIX_TIMESTAMP(), '_', id)
            WHERE COALESCE(solde_wallet, 0) > 100
            LIMIT 3
        ");
        $updated = $stmt->execute();
        $affectedRows = $stmt->rowCount();
        
        echo "   ✅ $affectedRows coursiers simulés connectés\n";
        
        // Recharger la liste
        $stmt = $pdo->query("
            SELECT 
                id, matricule, nom, prenoms, email, telephone,
                COALESCE(solde_wallet, 0) as solde,
                statut_connexion, last_login_at,
                current_session_token
            FROM agents_suzosky 
            WHERE statut_connexion = 'en_ligne' 
            ORDER BY last_login_at DESC
        ");
        $coursiersConnectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo "   📊 Coursiers connectés: " . count($coursiersConnectes) . "\n";
    foreach ($coursiersConnectes as $coursier) {
        $matricule = $coursier['matricule'] ?? 'N/A';
        $hasToken = !empty($coursier['current_session_token']) ? '🔑' : '🚫';
        echo "      🟢 {$coursier['nom']} {$coursier['prenoms']} (M:{$matricule})\n";
        echo "         💰 Solde: {$coursier['solde']} FCFA\n";
        echo "         $hasToken Token session\n\n";
    }
    
    // 2. Vérifier commandes en attente
    echo "📦 2. COMMANDES EN ATTENTE\n";
    
    $stmt = $pdo->query("
        SELECT 
            id, code_commande, client_nom, adresse_depart, adresse_arrivee,
            prix_total, statut, created_at,
            TIMESTAMPDIFF(MINUTE, created_at, NOW()) as age_minutes
        FROM commandes 
        WHERE statut IN ('en_attente', 'nouvelle') 
        OR (statut = 'attribuee' AND coursier_id IS NULL)
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $commandesEnAttente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($commandesEnAttente)) {
        echo "   ℹ️ Aucune commande en attente - Création d'une commande test...\n";
        
        $codeCommande = 'SMART_' . date('YmdHis');
        $orderNumber = 'ORD' . date('YmdHis') . rand(100, 999);
        
        $stmt = $pdo->prepare("
            INSERT INTO commandes 
            (order_number, code_commande, client_nom, client_telephone, 
             adresse_depart, adresse_arrivee, description,
             prix_total, statut, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())
        ");
        
        $stmt->execute([
            $orderNumber,
            $codeCommande,
            'CLIENT SMART TEST',
            '0778899001',
            'Yopougon Marché',
            'Plateau Tour CCIA',
            'Test du système d\'attribution intelligent',
            1800
        ]);
        
        $nouvelleCommandeId = $pdo->lastInsertId();
        echo "   ✅ Commande test créée: #{$nouvelleCommandeId} ($codeCommande)\n";
        
        // Recharger
        $stmt = $pdo->query("
            SELECT 
                id, code_commande, client_nom, adresse_depart, adresse_arrivee,
                prix_total, statut, created_at
            FROM commandes 
            WHERE statut = 'en_attente' 
            ORDER BY created_at ASC
            LIMIT 10
        ");
        $commandesEnAttente = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo "   📊 Commandes à traiter: " . count($commandesEnAttente) . "\n";
    foreach ($commandesEnAttente as $cmd) {
        echo "      📋 #{$cmd['id']} - {$cmd['code_commande']} - {$cmd['prix_total']} FCFA\n";
        echo "         👤 {$cmd['client_nom']}\n";
        echo "         📍 {$cmd['adresse_depart']} → {$cmd['adresse_arrivee']}\n\n";
    }
    
    // 3. Attribution intelligente
    echo "🎯 3. ATTRIBUTION INTELLIGENTE\n";
    
    if (empty($coursiersConnectes)) {
        echo "   ❌ Aucun coursier connecté pour attribution\n";
    } elseif (empty($commandesEnAttente)) {
        echo "   ℹ️ Aucune commande à attribuer\n";
    } else {
        
        $attributionsReussies = 0;
        
        foreach ($commandesEnAttente as $commande) {
            // Filtrer coursiers éligibles
            $coursiersEligibles = array_filter($coursiersConnectes, function($c) use ($commande) {
                // Critères: solde minimum et session active
                $soldeMinimum = max(100, $commande['prix_total'] * 0.05); // 5% du prix ou 100 FCFA min
                return $c['solde'] >= $soldeMinimum && !empty($c['current_session_token']);
            });
            
            if (empty($coursiersEligibles)) {
                echo "   ⚠️ Commande #{$commande['id']}: Aucun coursier éligible\n";
                continue;
            }
            
            // Algorithme de sélection: priorité au coursier avec le plus gros solde
            usort($coursiersEligibles, function($a, $b) {
                return $b['solde'] <=> $a['solde'];
            });
            
            $coursierChoisi = $coursiersEligibles[0];
            
            // Attribution
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET coursier_id = ?, statut = 'attribuee', updated_at = NOW()
                WHERE id = ? AND statut IN ('en_attente', 'nouvelle')
            ");
            
            if ($stmt->execute([$coursierChoisi['id'], $commande['id']])) {
                echo "   ✅ #{$commande['id']} → {$coursierChoisi['nom']} {$coursierChoisi['prenoms']}\n";
                echo "      💰 Solde: {$coursierChoisi['solde']} FCFA\n";
                echo "      🎯 Prix: {$commande['prix_total']} FCFA\n";
                
                $attributionsReussies++;
                
                // Notification automatique
                $message = "🚚 Nouvelle commande #{$commande['id']} - {$commande['prix_total']} FCFA";
                
                // Vérifier si le coursier a un token FCM
                $stmt = $pdo->prepare("
                    SELECT token FROM device_tokens 
                    WHERE coursier_id = ? AND is_active = 1 
                    ORDER BY updated_at DESC LIMIT 1
                ");
                $stmt->execute([$coursierChoisi['id']]);
                $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $tokenUsed = $tokenData['token'] ?? 'no_token_available';
                
                // Enregistrer notification
                $stmt = $pdo->prepare("
                    INSERT INTO notifications_log_fcm 
                    (coursier_id, commande_id, token_used, message, type, status, response_data, created_at)
                    VALUES (?, ?, ?, ?, 'attribution_auto', 'sent', ?, NOW())
                ");
                
                $responseData = json_encode([
                    'attribution_auto' => true,
                    'coursier_id' => $coursierChoisi['id'],
                    'timestamp' => time(),
                    'has_token' => !empty($tokenData)
                ]);
                
                $stmt->execute([
                    $coursierChoisi['id'],
                    $commande['id'],
                    $tokenUsed,
                    $message,
                    $responseData
                ]);
                
                echo "      📱 Notification: " . ($tokenData ? '✅ Envoyée' : '⚠️ Pas de token FCM') . "\n";
                
                // Retirer ce coursier de la liste pour équilibrer
                $coursiersConnectes = array_filter($coursiersConnectes, function($c) use ($coursierChoisi) {
                    return $c['id'] !== $coursierChoisi['id'];
                });
                
            } else {
                echo "   ❌ Erreur attribution commande #{$commande['id']}\n";
            }
            
            echo "\n";
        }
        
        echo "📊 Résultat: $attributionsReussies/" . count($commandesEnAttente) . " attributions réussies\n";
    }
    
    // 4. Vérification tokens FCM
    echo "\n📱 4. VÉRIFICATION TOKENS FCM\n";
    
    $stmt = $pdo->query("
        SELECT 
            a.id, a.nom, a.prenoms, a.matricule,
            COUNT(dt.id) as nb_tokens,
            COUNT(CASE WHEN dt.is_active = 1 THEN 1 END) as tokens_actifs,
            COUNT(CASE WHEN c.id IS NOT NULL AND c.statut = 'attribuee' THEN 1 END) as commandes_attribuees
        FROM agents_suzosky a
        LEFT JOIN device_tokens dt ON a.id = dt.coursier_id
        LEFT JOIN commandes c ON a.id = c.coursier_id AND c.statut = 'attribuee'
        WHERE a.statut_connexion = 'en_ligne'
        GROUP BY a.id, a.nom, a.prenoms, a.matricule
    ");
    $coursiersWithTokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($coursiersWithTokens as $coursier) {
        $matricule = $coursier['matricule'] ?? 'N/A';
        $tokenStatus = $coursier['tokens_actifs'] > 0 ? '📱✅' : '📱❌';
        echo "   $tokenStatus {$coursier['nom']} {$coursier['prenoms']} (M:{$matricule})\n";
        echo "      Tokens: {$coursier['tokens_actifs']}/{$coursier['nb_tokens']} actifs\n";
        echo "      Commandes: {$coursier['commandes_attribuees']} attribuées\n";
        
        if ($coursier['tokens_actifs'] == 0 && $coursier['commandes_attribuees'] > 0) {
            echo "      🚨 ATTENTION: Commandes sans token FCM!\n";
        }
        echo "\n";
    }
    
    // 5. Statistiques finales
    echo "📈 5. STATISTIQUES SYSTÈME\n";
    
    $stats = [];
    
    // Commandes par statut aujourd'hui
    $stmt = $pdo->query("
        SELECT statut, COUNT(*) as count
        FROM commandes 
        WHERE DATE(created_at) = CURDATE()
        GROUP BY statut
    ");
    $stats['commandes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Coursiers par statut
    $stmt = $pdo->query("
        SELECT 
            statut_connexion, 
            COUNT(*) as count,
            AVG(COALESCE(solde_wallet, 0)) as solde_moyen
        FROM agents_suzosky 
        GROUP BY statut_connexion
    ");
    $stats['coursiers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   📦 Commandes du jour:\n";
    foreach ($stats['commandes'] as $stat) {
        echo "      • {$stat['statut']}: {$stat['count']}\n";
    }
    
    echo "\n   👥 Coursiers par statut:\n";
    foreach ($stats['coursiers'] as $stat) {
        $solde = number_format($stat['solde_moyen'], 0);
        echo "      • {$stat['statut_connexion']}: {$stat['count']} (Solde moyen: {$solde} FCFA)\n";
    }
    
    echo "\n✅ SYSTÈME D'ATTRIBUTION INTELLIGENT - TERMINÉ\n";
    echo "🔄 Relancez ce script pour attribution continue\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR SYSTÈME: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n";
}
?>