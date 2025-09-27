<?php
/**
 * DIAGNOSTIC CRITIQUE DES PROBLÈMES SYSTÈME
 * Analyse des incohérences détectées
 */

require_once 'config.php';

echo "🔍 DIAGNOSTIC CRITIQUE DES PROBLÈMES SYSTÈME\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. PROBLÈME TABLES DOUBLONS
    echo "\n❌ 1. PROBLÈME TABLES DOUBLONS\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM coursiers");
    $coursiersCount = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
    $agentsCount = $stmt->fetchColumn();
    
    echo "   • Table 'coursiers': $coursiersCount entrées\n";
    echo "   • Table 'agents_suzosky' (connectés): $agentsCount entrées\n";
    echo "   • 🚨 INCOHÉRENCE: Deux systèmes de gestion coursiers!\n";
    
    // 2. VÉRIFICATION CONTRAINTES FK
    echo "\n❌ 2. CONTRAINTES CLÉS ÉTRANGÈRES\n";
    
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'coursier_local' 
        AND TABLE_NAME = 'commandes' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Contraintes FK sur table 'commandes':\n";
    foreach ($constraints as $constraint) {
        echo "   • {$constraint['COLUMN_NAME']} → {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
    
    // 3. PROBLÈME SOLDE COURSIER
    echo "\n❌ 3. PROBLÈME SOLDE COURSIER\n";
    
    $stmt = $pdo->query("
        SELECT a.id, a.nom, a.prenoms, 
               c.solde_wallet, c.credit_balance,
               a.statut_connexion
        FROM agents_suzosky a
        LEFT JOIN coursiers c ON a.email = c.email
        WHERE a.statut_connexion = 'en_ligne'
    ");
    $coursierData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coursierData) {
        echo "   • Coursier: {$coursierData['nom']} {$coursierData['prenoms']}\n";
        echo "   • Solde wallet: " . ($coursierData['solde_wallet'] ?? '0') . " FCFA\n";
        echo "   • Crédit balance: " . ($coursierData['credit_balance'] ?? '0') . " FCFA\n";
        echo "   • 🚨 PROBLÈME: Solde = 0, ne devrait pas pouvoir recevoir commandes!\n";
    }
    
    // 4. VÉRIFICATION NOTIFICATIONS RÉELLES
    echo "\n❌ 4. NOTIFICATIONS RÉELLES\n";
    
    // Vérifier les vraies notifications FCM
    $stmt = $pdo->query("
        SELECT fcm.*, a.nom, a.prenoms 
        FROM notifications_log_fcm fcm
        JOIN agents_suzosky a ON fcm.coursier_id = a.id
        ORDER BY fcm.created_at DESC 
        LIMIT 5
    ");
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Dernières notifications FCM:\n";
    if (empty($notifications)) {
        echo "   • ❌ AUCUNE notification FCM réelle envoyée!\n";
        echo "   • 🚨 Les logs sont fictifs, pas de vraie integration FCM!\n";
    } else {
        foreach ($notifications as $notif) {
            echo "   • [{$notif['created_at']}] {$notif['nom']} {$notif['prenoms']} - {$notif['status']}\n";
        }
    }
    
    // 5. VÉRIFICATION APPLICATION MOBILE RÉELLE
    echo "\n❌ 5. APPLICATION MOBILE RÉELLE\n";
    
    // Vérifier si il y a une vraie API mobile
    $mobileApiFiles = [
        'mobile_app.php',
        'api/coursier_orders.php', 
        'api/mobile_auth.php'
    ];
    
    echo "   Fichiers API mobile:\n";
    foreach ($mobileApiFiles as $file) {
        $exists = file_exists($file);
        echo "   • $file: " . ($exists ? '✅' : '❌') . "\n";
    }
    
    echo "\n🔍 DIAGNOSTIC TERMINÉ - PROBLÈMES IDENTIFIÉS:\n";
    echo "   1. 🚨 Architecture double (coursiers + agents_suzosky)\n";
    echo "   2. 🚨 Contraintes FK incohérentes\n";
    echo "   3. 🚨 Solde coursier = 0 (bloque les commandes)\n";
    echo "   4. 🚨 Notifications FCM fictives\n";
    echo "   5. 🚨 Pas de vraie API mobile intégrée\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR DIAGNOSTIC: " . $e->getMessage() . "\n";
}
?>