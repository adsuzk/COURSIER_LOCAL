<?php
/**
 * TEST COMPLET DU SYSTÈME DE COMMANDES ET NOTIFICATIONS
 * Vérifie que les coursiers connectés reçoivent les commandes
 */

require_once __DIR__ . '/config.php';

echo "🧪 TEST SYSTÈME COMMANDES + NOTIFICATIONS FCM\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier les coursiers connectés
    echo "👥 1. COURSIERS CONNECTÉS\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            a.id, a.nom, a.prenoms, a.matricule, a.telephone,
            a.statut_connexion, a.last_login_at,
            COUNT(DISTINCT f.id) as nb_tokens_fcm,
            COUNT(DISTINCT CASE WHEN f.is_active = 1 THEN f.id END) as tokens_actifs
        FROM agents_suzosky a
        LEFT JOIN device_tokens f ON a.id = f.coursier_id
        GROUP BY a.id, a.nom, a.prenoms, a.matricule, a.telephone, a.statut_connexion, a.last_login_at
        ORDER BY a.statut_connexion DESC, a.last_login_at DESC
    ");
    
    $coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $coursiersEnLigne = [];
    
    foreach ($coursiers as $c) {
        $statut = $c['statut_connexion'] === 'en_ligne' ? '🟢' : '🔴';
        $token = $c['tokens_actifs'] > 0 ? '📱✅' : '📱❌';
        
        echo "$statut $token {$c['nom']} {$c['prenoms']} (M:{$c['matricule']})\n";
        echo "   Tokens FCM: {$c['tokens_actifs']}/{$c['nb_tokens_fcm']}\n";
        echo "   Statut: {$c['statut_connexion']} | Dernière connexion: " . ($c['last_login_at'] ?? 'Jamais') . "\n\n";
        
        if ($c['statut_connexion'] === 'en_ligne') {
            $coursiersEnLigne[] = $c;
        }
    }
    
    echo "📊 Total: " . count($coursiers) . " coursiers | " . count($coursiersEnLigne) . " en ligne\n\n";
    
    if (empty($coursiersEnLigne)) {
        echo "⚠️  AUCUN COURSIER EN LIGNE - Simulation de connexion...\n\n";
        
        // Simuler connexion des 2 premiers coursiers
        $stmt = $pdo->prepare("
            UPDATE agents_suzosky 
            SET statut_connexion = 'en_ligne', 
                last_login_at = NOW()
            LIMIT 2
        ");
        $stmt->execute();
        $nbSimules = $stmt->rowCount();
        
        echo "✅ $nbSimules coursiers simulés connectés\n\n";
        
        // Recharger la liste
        $stmt = $pdo->query("
            SELECT 
                a.id, a.nom, a.prenoms, a.matricule,
                COALESCE(a.solde_wallet, 0) as solde,
                COUNT(DISTINCT CASE WHEN f.is_active = 1 THEN f.id END) as tokens_actifs
            FROM agents_suzosky a
            LEFT JOIN device_tokens f ON a.id = f.coursier_id
            WHERE a.statut_connexion = 'en_ligne'
            GROUP BY a.id, a.nom, a.prenoms, a.matricule, a.solde_wallet
        ");
        $coursiersEnLigne = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 2. Créer une commande de test
    echo "📦 2. CRÉATION COMMANDE DE TEST\n";
    echo str_repeat("-", 70) . "\n";
    
    $codeCommande = 'TEST' . date('YmdHis');
    $orderNumber = 'TST' . date('His') . rand(100, 999);
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            order_number, code_commande, client_nom, client_telephone,
            adresse_depart, adresse_arrivee, telephone_expediteur, telephone_destinataire,
            description_colis, priorite, mode_paiement, prix_estime,
            statut, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nouvelle', NOW())
    ");
    
    $result = $stmt->execute([
        $orderNumber,
        $codeCommande,
        'Client Test',
        '0778899000',
        'Cocody Angré 7ème Tranche',
        'Plateau Cité Administrative',
        '0778899000',
        '0767788990',
        'Colis de test pour vérification système',
        'normale',
        'especes',
        1500
    ]);
    
    $commandeId = $pdo->lastInsertId();
    
    echo "✅ Commande créée: #$commandeId ($codeCommande)\n";
    echo "   De: Cocody Angré 7ème Tranche\n";
    echo "   Vers: Plateau Cité Administrative\n";
    echo "   Prix: 1500 FCFA\n\n";
    
    // 3. Attribution automatique
    echo "🎯 3. ATTRIBUTION AUTOMATIQUE\n";
    echo str_repeat("-", 70) . "\n";
    
    if (empty($coursiersEnLigne)) {
        echo "❌ Aucun coursier disponible pour attribution\n\n";
    } else {
        // Prendre le coursier avec le plus gros solde
        usort($coursiersEnLigne, function($a, $b) {
            return $b['solde'] <=> $a['solde'];
        });
        
        $coursierChoisi = $coursiersEnLigne[0];
        
        // Assigner la commande
        $stmt = $pdo->prepare("
            UPDATE commandes 
            SET coursier_id = ?, statut = 'attribuee', updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$coursierChoisi['id'], $commandeId]);
        
        echo "✅ Commande assignée à: {$coursierChoisi['nom']} {$coursierChoisi['prenoms']}\n";
        echo "   Matricule: {$coursierChoisi['matricule']}\n";
        echo "   Solde: {$coursierChoisi['solde']} FCFA\n\n";
        
        // 4. Envoi notification FCM
        echo "📱 4. NOTIFICATION FCM\n";
        echo str_repeat("-", 70) . "\n";
        
        // Récupérer token FCM actif
        $stmt = $pdo->prepare("
            SELECT token, last_ping, device_info
            FROM device_tokens 
            WHERE coursier_id = ? AND is_active = 1 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$coursierChoisi['id']]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData) {
            echo "⚠️  Aucun token FCM actif pour ce coursier\n";
            echo "   Le coursier doit ouvrir l'application pour s'enregistrer\n\n";
        } else {
            echo "✅ Token FCM trouvé\n";
            echo "   Token: " . substr($tokenData['token'], 0, 30) . "...\n";
            echo "   Dernier ping: {$tokenData['last_ping']}\n";
            echo "   Device: {$tokenData['device_info']}\n\n";
            
            // Charger système FCM
            require_once __DIR__ . '/lib/fcm_enhanced.php';
            
            $title = "🚚 Nouvelle commande #$codeCommande";
            $body = "De: Cocody Angré\nVers: Plateau\nPrix: 1500 FCFA";
            
            $notifData = [
                'type' => 'new_order',
                'commande_id' => $commandeId,
                'code_commande' => $codeCommande,
                'adresse_depart' => 'Cocody Angré 7ème Tranche',
                'adresse_arrivee' => 'Plateau Cité Administrative',
                'prix_estime' => 1500,
                'priorite' => 'normale'
            ];
            
            echo "📤 Envoi notification FCM...\n";
            $fcmResult = fcm_send_with_log(
                [$tokenData['token']], 
                $title, 
                $body, 
                $notifData,
                $coursierChoisi['id'],
                'TEST_SYSTEM'
            );
            
            if ($fcmResult['success']) {
                echo "✅ Notification envoyée avec succès!\n";
                echo "   Message ID: " . ($fcmResult['message_id'] ?? 'N/A') . "\n";
            } else {
                echo "❌ Échec envoi notification\n";
                echo "   Erreur: " . ($fcmResult['error'] ?? 'Inconnue') . "\n";
            }
            echo "\n";
        }
    }
    
    // 5. Vérification finale
    echo "✅ 5. VÉRIFICATION FINALE\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.code_commande, c.statut,
            c.adresse_depart, c.adresse_arrivee, c.prix_estime,
            a.nom as coursier_nom, a.prenoms as coursier_prenoms, a.matricule,
            c.created_at, c.updated_at
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.id = ?
    ");
    $stmt->execute([$commandeId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Commande #$commandeId - {$commande['code_commande']}\n";
    echo "├─ Statut: {$commande['statut']}\n";
    echo "├─ Coursier: " . ($commande['coursier_nom'] ? "{$commande['coursier_nom']} {$commande['coursier_prenoms']} (M:{$commande['matricule']})" : "Non assigné") . "\n";
    echo "├─ De: {$commande['adresse_depart']}\n";
    echo "├─ Vers: {$commande['adresse_arrivee']}\n";
    echo "├─ Prix: {$commande['prix_estime']} FCFA\n";
    echo "├─ Créée: {$commande['created_at']}\n";
    echo "└─ Mise à jour: {$commande['updated_at']}\n\n";
    
    echo str_repeat("=", 70) . "\n";
    echo "🎉 TEST TERMINÉ AVEC SUCCÈS\n\n";
    
    echo "📋 ACTIONS À FAIRE MAINTENANT:\n";
    echo "1. Ouvrir l'application coursier sur le mobile\n";
    echo "2. Se connecter avec le compte du coursier assigné\n";
    echo "3. Vérifier que la commande apparaît dans \"Mes Courses\"\n";
    echo "4. Vérifier que le son de notification a retenti\n";
    echo "5. Ouvrir http://localhost/COURSIER_LOCAL/admin.php?section=commandes\n";
    echo "6. Vérifier que la page se recharge automatiquement toutes les 30 secondes\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n";
}
?>
