<?php
/**
 * DIAGNOSTIC COURSIER CM20250003
 * Investigation complète synchronisation mobile
 */

require_once 'config.php';

echo "🔍 DIAGNOSTIC COURSIER CM20250003\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Identifier le coursier CM20250003
    echo "\n👤 1. IDENTIFICATION COURSIER\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            id, matricule, nom, prenoms, email, telephone,
            COALESCE(solde_wallet, 0) as solde,
            statut_connexion,
            current_session_token,
            last_login_at,
            TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) as minutes_inactif
        FROM agents_suzosky 
        WHERE matricule = 'CM20250003' OR id = 3 OR nom LIKE '%CM20250003%'
    ");
    $stmt->execute();
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coursier) {
        // Chercher par d'autres critères
        echo "   🔍 Recherche alternative...\n";
        $stmt = $pdo->query("
            SELECT 
                id, matricule, nom, prenoms, email, telephone,
                COALESCE(solde_wallet, 0) as solde,
                statut_connexion,
                current_session_token,
                last_login_at
            FROM agents_suzosky 
            ORDER BY id
        ");
        $allCoursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   📋 Tous les coursiers disponibles:\n";
        foreach ($allCoursiers as $c) {
            $matricule = $c['matricule'] ?? 'N/A';
            echo "      • ID: {$c['id']} - {$c['nom']} {$c['prenoms']} (Matricule: $matricule)\n";
        }
        
        // Supposer que c'est l'ID 3 ou un autre
        $coursier = $allCoursiers[0] ?? null;
        if ($coursier) {
            echo "   ⚠️ Prise en compte du premier coursier pour diagnostic\n";
        }
    }
    
    if (!$coursier) {
        echo "   ❌ Aucun coursier trouvé!\n";
        exit(1);
    }
    
    $coursierId = $coursier['id'];
    echo "   ✅ Coursier identifié:\n";
    echo "      • ID: {$coursier['id']}\n";
    echo "      • Nom: {$coursier['nom']} {$coursier['prenoms']}\n";
    echo "      • Email: {$coursier['email']}\n";
    echo "      • Téléphone: {$coursier['telephone']}\n";
    echo "      • Matricule: " . ($coursier['matricule'] ?? 'N/A') . "\n";
    echo "      • Solde: {$coursier['solde']} FCFA\n";
    echo "      • Statut: {$coursier['statut_connexion']}\n";
    echo "      • Token session: " . (!empty($coursier['current_session_token']) ? '✅' : '❌') . "\n";
    echo "      • Dernière activité: " . ($coursier['last_login_at'] ?? 'Jamais') . "\n";
    
    // 2. Vérification des tokens FCM
    echo "\n📱 2. TOKENS FCM\n";
    
    $stmt = $pdo->prepare("
        SELECT id, token, device_type, is_active, created_at, updated_at
        FROM device_tokens 
        WHERE coursier_id = ?
        ORDER BY updated_at DESC
    ");
    $stmt->execute([$coursierId]);
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tokens)) {
        echo "   ❌ AUCUN TOKEN FCM TROUVÉ!\n";
        echo "   🚨 PROBLÈME CRITIQUE: Impossible d'envoyer des notifications\n";
    } else {
        echo "   📱 Tokens FCM trouvés: " . count($tokens) . "\n";
        foreach ($tokens as $token) {
            $status = $token['is_active'] ? '🟢 Actif' : '🔴 Inactif';
            echo "      • Token: " . substr($token['token'], 0, 30) . "...\n";
            echo "        Type: {$token['device_type']} | Statut: $status\n";
            echo "        Créé: {$token['created_at']} | MAJ: {$token['updated_at']}\n";
        }
    }
    
    // 3. Historique des notifications
    echo "\n🔔 3. HISTORIQUE NOTIFICATIONS\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            n.id, n.commande_id, n.message, n.status, n.created_at,
            SUBSTRING(n.token_used, 1, 20) as token_preview
        FROM notifications_log_fcm n
        WHERE n.coursier_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$coursierId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo "   ⚠️ Aucune notification dans l'historique\n";
    } else {
        echo "   📊 Dernières notifications (" . count($notifications) . "):\n";
        foreach ($notifications as $notif) {
            $commande = $notif['commande_id'] ? "Commande #{$notif['commande_id']}" : "Système";
            echo "      • $commande | {$notif['status']} | {$notif['created_at']}\n";
            echo "        Message: " . substr($notif['message'], 0, 60) . "...\n";
            echo "        Token: {$notif['token_preview']}...\n\n";
        }
    }
    
    // 4. Commandes récentes
    echo "\n📦 4. COMMANDES RÉCENTES\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.code_commande, c.statut, c.prix_total, 
            c.created_at, c.updated_at,
            c.adresse_depart, c.adresse_arrivee
        FROM commandes c
        WHERE c.coursier_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$coursierId]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($commandes)) {
        echo "   ℹ️ Aucune commande assignée récemment\n";
    } else {
        echo "   📋 Commandes récentes (" . count($commandes) . "):\n";
        foreach ($commandes as $cmd) {
            echo "      • #{$cmd['id']} | {$cmd['code_commande']} | {$cmd['statut']}\n";
            echo "        Prix: {$cmd['prix_total']} FCFA | Créé: {$cmd['created_at']}\n";
            echo "        {$cmd['adresse_depart']} → {$cmd['adresse_arrivee']}\n\n";
        }
    }
    
    // 5. Transactions de rechargement
    echo "\n💳 5. TRANSACTIONS RÉCENTES\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.type, t.montant, t.reference, t.description, 
            t.statut, t.date_creation
        FROM transactions_financieres t
        WHERE t.compte_id = ? AND t.compte_type = 'coursier'
        ORDER BY t.date_creation DESC
        LIMIT 5
    ");
    $stmt->execute([$coursierId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($transactions)) {
        echo "   ℹ️ Aucune transaction récente\n";
    } else {
        echo "   💰 Transactions récentes (" . count($transactions) . "):\n";
        foreach ($transactions as $trans) {
            $type = $trans['type'] === 'credit' ? '➕ Crédit' : '➖ Débit';
            echo "      • $type: {$trans['montant']} FCFA | {$trans['statut']}\n";
            echo "        Ref: {$trans['reference']} | {$trans['date_creation']}\n";
            echo "        Desc: {$trans['description']}\n\n";
        }
    }
    
    // 6. Diagnostic des problèmes
    echo "\n🚨 6. DIAGNOSTIC DES PROBLÈMES\n";
    
    $problemes = [];
    
    if (empty($tokens)) {
        $problemes[] = "❌ CRITIQUE: Aucun token FCM - Notifications impossibles";
    } else {
        $tokensActifs = array_filter($tokens, fn($t) => $t['is_active']);
        if (empty($tokensActifs)) {
            $problemes[] = "❌ CRITIQUE: Aucun token FCM actif";
        }
    }
    
    if ($coursier['statut_connexion'] !== 'en_ligne') {
        $problemes[] = "⚠️ Coursier hors ligne - Statut: {$coursier['statut_connexion']}";
    }
    
    if (empty($coursier['current_session_token'])) {
        $problemes[] = "⚠️ Aucun token de session - App probablement déconnectée";
    }
    
    if ($coursier['solde'] <= 0) {
        $problemes[] = "💰 Solde insuffisant: {$coursier['solde']} FCFA";
    }
    
    if (isset($coursier['minutes_inactif']) && $coursier['minutes_inactif'] > 30) {
        $problemes[] = "⏰ Inactif depuis {$coursier['minutes_inactif']} minutes";
    }
    
    if (empty($problemes)) {
        echo "   ✅ Aucun problème technique détecté\n";
        echo "   💡 Le problème pourrait être côté application mobile\n";
    } else {
        foreach ($problemes as $probleme) {
            echo "   $probleme\n";
        }
    }
    
    // 7. Solutions recommandées
    echo "\n💡 7. SOLUTIONS RECOMMANDÉES\n";
    
    if (empty($tokens) || empty($tokensActifs ?? [])) {
        echo "   🔧 SOLUTION FCM:\n";
        echo "      1. Créer un token FCM d'urgence\n";
        echo "      2. Vérifier configuration Firebase dans l'app\n";
        echo "      3. Redémarrer l'application mobile\n";
        
        // Créer token d'urgence
        echo "\n   🆘 Création token d'urgence...\n";
        $emergencyToken = 'debug_emergency_' . uniqid() . '_' . $coursierId;
        
        $stmt = $pdo->prepare("
            INSERT INTO device_tokens (coursier_id, token, device_type, is_active, created_at, updated_at)
            VALUES (?, ?, 'debug_device', 1, NOW(), NOW())
        ");
        
        if ($stmt->execute([$coursierId, $emergencyToken])) {
            echo "   ✅ Token d'urgence créé: " . substr($emergencyToken, 0, 30) . "...\n";
        } else {
            echo "   ❌ Erreur création token d'urgence\n";
        }
    }
    
    if ($coursier['statut_connexion'] !== 'en_ligne') {
        echo "   🔧 SOLUTION CONNEXION:\n";
        echo "      1. Forcer connexion en ligne\n";
        echo "      2. Mettre à jour last_login_at\n";
        
        $stmt = $pdo->prepare("
            UPDATE agents_suzosky 
            SET statut_connexion = 'en_ligne', last_login_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$coursierId])) {
            echo "   ✅ Statut forcé à 'en_ligne'\n";
        }
    }
    
    if (empty($coursier['current_session_token'])) {
        echo "   🔧 SOLUTION SESSION:\n";
        echo "      1. Générer nouveau token de session\n";
        
        $sessionToken = 'debug_session_' . uniqid();
        $stmt = $pdo->prepare("
            UPDATE agents_suzosky 
            SET current_session_token = ?
            WHERE id = ?
        ");
        
        if ($stmt->execute([$sessionToken, $coursierId])) {
            echo "   ✅ Token de session généré: " . substr($sessionToken, 0, 20) . "...\n";
        }
    }
    
    echo "\n✅ DIAGNOSTIC TERMINÉ\n";
    echo "📱 Vérifiez maintenant l'application mobile du coursier\n";
    echo "🔄 Redémarrez l'app si nécessaire\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>