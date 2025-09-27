<?php
/**
 * SURVEILLANCE TEMPS RÉEL - COURSIERS ET COMMANDES
 * Monitoring automatique des connexions et attributions
 */

require_once 'config.php';

echo "🔍 SURVEILLANCE TEMPS RÉEL - SUZOSKY\n";
echo "=" . str_repeat("=", 50) . "\n";
echo "⏰ " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. État des coursiers connectés
    echo "👥 COURSIERS CONNECTÉS\n";
    echo str_repeat("-", 30) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            a.id, a.matricule, a.nom, a.prenoms,
            COALESCE(a.solde_wallet, 0) as solde,
            a.statut_connexion, a.last_login_at,
            TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) as inactif_min,
            COUNT(CASE WHEN c.statut = 'attribuee' THEN 1 END) as commandes_en_cours,
            COUNT(dt.id) as nb_tokens_fcm,
            COUNT(CASE WHEN dt.is_active = 1 THEN 1 END) as tokens_actifs
        FROM agents_suzosky a
        LEFT JOIN commandes c ON a.id = c.coursier_id AND c.statut = 'attribuee'
        LEFT JOIN device_tokens dt ON a.id = dt.coursier_id
        WHERE a.statut_connexion = 'en_ligne'
        GROUP BY a.id, a.matricule, a.nom, a.prenoms, a.solde_wallet, 
                 a.statut_connexion, a.last_login_at
        ORDER BY a.last_login_at DESC
    ");
    $coursiersEnLigne = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($coursiersEnLigne)) {
        echo "❌ AUCUN COURSIER CONNECTÉ!\n\n";
    } else {
        foreach ($coursiersEnLigne as $coursier) {
            $matricule = $coursier['matricule'] ?? 'N/A';
            $statusIcon = $coursier['inactif_min'] < 5 ? '🟢' : ($coursier['inactif_min'] < 30 ? '🟡' : '🔴');
            $tokenIcon = $coursier['tokens_actifs'] > 0 ? '📱' : '📵';
            $commandesIcon = $coursier['commandes_en_cours'] > 0 ? '📦' : '💤';
            
            echo "$statusIcon $tokenIcon $commandesIcon {$coursier['nom']} {$coursier['prenoms']} (M:{$matricule})\n";
            echo "   💰 Solde: {$coursier['solde']} FCFA\n";
            echo "   ⏰ Inactif: {$coursier['inactif_min']} min\n";
            echo "   📦 Commandes: {$coursier['commandes_en_cours']} en cours\n";
            echo "   📱 FCM: {$coursier['tokens_actifs']}/{$coursier['nb_tokens_fcm']} actifs\n\n";
        }
    }
    
    // 2. Commandes récentes
    echo "📦 COMMANDES RÉCENTES (5 dernières)\n";
    echo str_repeat("-", 30) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            c.id, c.code_commande, c.client_nom, c.prix_total, c.statut,
            c.created_at, c.updated_at,
            a.nom as coursier_nom, a.prenoms as coursier_prenoms, a.matricule,
            TIMESTAMPDIFF(MINUTE, c.created_at, NOW()) as age_minutes
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $commandesRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($commandesRecentes as $cmd) {
        $statusIcon = match($cmd['statut']) {
            'en_attente' => '⏳',
            'attribuee' => '🎯',
            'acceptee' => '✅',
            'en_cours' => '🚚',
            'livree' => '📍',
            default => '📋'
        };
        
        $coursierInfo = $cmd['coursier_nom'] 
            ? "{$cmd['coursier_nom']} {$cmd['coursier_prenoms']} (M:{$cmd['matricule']})"
            : 'Non attribuée';
            
        echo "$statusIcon #{$cmd['id']} - {$cmd['code_commande']} - {$cmd['prix_total']} FCFA\n";
        echo "   👤 Client: {$cmd['client_nom']}\n";
        echo "   🚚 Coursier: $coursierInfo\n";
        echo "   📊 Statut: {$cmd['statut']}\n";
        echo "   ⏰ Créée il y a {$cmd['age_minutes']} min\n\n";
    }
    
    // 3. Notifications FCM récentes
    echo "🔔 NOTIFICATIONS FCM RÉCENTES\n";
    echo str_repeat("-", 30) . "\n";
    
    $stmt = $pdo->query("
        SELECT 
            n.id, n.coursier_id, n.commande_id, n.message, n.type, n.status, n.created_at,
            a.nom, a.prenoms, a.matricule,
            TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) as age_minutes
        FROM notifications_log_fcm n
        LEFT JOIN agents_suzosky a ON n.coursier_id = a.id
        ORDER BY n.created_at DESC
        LIMIT 5
    ");
    $notificationsRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notificationsRecentes)) {
        echo "ℹ️ Aucune notification récente\n\n";
    } else {
        foreach ($notificationsRecentes as $notif) {
            $statusIcon = $notif['status'] === 'sent' ? '✅' : '❌';
            $coursierInfo = $notif['nom'] 
                ? "{$notif['nom']} {$notif['prenoms']} (M:{$notif['matricule']})"
                : "ID:{$notif['coursier_id']}";
                
            echo "$statusIcon #{$notif['id']} - {$notif['type']}\n";
            echo "   🚚 Coursier: $coursierInfo\n";
            echo "   📦 Commande: #{$notif['commande_id']}\n";
            echo "   💬 Message: " . substr($notif['message'], 0, 50) . "...\n";
            echo "   ⏰ Il y a {$notif['age_minutes']} min\n\n";
        }
    }
    
    // 4. Statistiques en temps réel
    echo "📊 STATISTIQUES TEMPS RÉEL\n";
    echo str_repeat("-", 30) . "\n";
    
    // Commandes par statut
    $stmt = $pdo->query("
        SELECT statut, COUNT(*) as count 
        FROM commandes 
        WHERE DATE(created_at) = CURDATE()
        GROUP BY statut
        ORDER BY count DESC
    ");
    $statsCommandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📦 Commandes aujourd'hui:\n";
    foreach ($statsCommandes as $stat) {
        echo "   • {$stat['statut']}: {$stat['count']}\n";
    }
    echo "\n";
    
    // Coursiers par statut
    $stmt = $pdo->query("
        SELECT 
            statut_connexion, 
            COUNT(*) as count,
            AVG(COALESCE(solde_wallet, 0)) as solde_moyen
        FROM agents_suzosky 
        GROUP BY statut_connexion
    ");
    $statsCoursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "👥 Coursiers par statut:\n";
    foreach ($statsCoursiers as $stat) {
        $solde = number_format($stat['solde_moyen'], 0);
        echo "   • {$stat['statut_connexion']}: {$stat['count']} (Solde moyen: {$solde} FCFA)\n";
    }
    echo "\n";
    
    // 5. Alertes et recommandations
    echo "⚠️ ALERTES ET RECOMMANDATIONS\n";
    echo str_repeat("-", 30) . "\n";
    
    $alertes = [];
    
    // Vérifier commandes en attente
    $stmt = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'en_attente'");
    $commandesEnAttente = $stmt->fetchColumn();
    
    if ($commandesEnAttente > 0) {
        $alertes[] = "🚨 $commandesEnAttente commandes en attente d'attribution";
    }
    
    // Vérifier coursiers sans tokens FCM
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT a.id) 
        FROM agents_suzosky a
        LEFT JOIN device_tokens dt ON a.id = dt.coursier_id AND dt.is_active = 1
        WHERE a.statut_connexion = 'en_ligne' AND dt.id IS NULL
    ");
    $coursiersSansToken = $stmt->fetchColumn();
    
    if ($coursiersSansToken > 0) {
        $alertes[] = "📱 $coursiersSansToken coursiers connectés sans token FCM";
    }
    
    // Vérifier commandes anciennes
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM commandes 
        WHERE statut = 'attribuee' 
        AND TIMESTAMPDIFF(HOUR, updated_at, NOW()) > 2
    ");
    $commandesAnciennes = $stmt->fetchColumn();
    
    if ($commandesAnciennes > 0) {
        $alertes[] = "⏰ $commandesAnciennes commandes attribuées depuis plus de 2h";
    }
    
    if (empty($alertes)) {
        echo "✅ Aucune alerte - Système opérationnel\n\n";
    } else {
        foreach ($alertes as $alerte) {
            echo "$alerte\n";
        }
        echo "\n";
    }
    
    // 6. Actions recommandées
    if (!empty($alertes)) {
        echo "💡 ACTIONS RECOMMANDÉES\n";
        echo str_repeat("-", 30) . "\n";
        
        if ($commandesEnAttente > 0) {
            echo "🔄 Relancer l'attribution automatique:\n";
            echo "   php attribution_intelligente.php\n\n";
        }
        
        if ($coursiersSansToken > 0) {
            echo "📱 Créer tokens FCM d'urgence:\n";
            echo "   php fix_device_tokens_structure.php\n\n";
        }
        
        if ($commandesAnciennes > 0) {
            echo "📞 Contacter les coursiers concernés\n";
            echo "   Vérifier état des livraisons en cours\n\n";
        }
    }
    
    // 7. URLs de monitoring
    echo "🔗 MONITORING EN TEMPS RÉEL\n";
    echo str_repeat("-", 30) . "\n";
    echo "🌐 API Mobile: http://localhost/COURSIER_LOCAL/mobile_sync_api.php\n";
    echo "💼 Admin Panel: http://localhost/COURSIER_LOCAL/admin.php\n";
    echo "💰 Finances: http://localhost/COURSIER_LOCAL/admin.php?section=finances\n";
    echo "📦 Commandes: http://localhost/COURSIER_LOCAL/admin.php?section=commandes\n\n";
    
    echo "✅ SURVEILLANCE TERMINÉE - " . date('H:i:s') . "\n";
    echo "🔄 Relancez ce script pour surveillance continue\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR SURVEILLANCE: " . $e->getMessage() . "\n";
    echo "📍 " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>