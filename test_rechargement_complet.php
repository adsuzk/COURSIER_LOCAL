<?php
/**
 * TEST RECHARGEMENT WORKFLOW COMPLET
 * Simulation d'un rechargement admin avec FCM
 */

require_once 'config.php';
require_once 'fcm_manager.php';

echo "=== TEST WORKFLOW RECHARGEMENT COMPLET ===\n\n";

$coursier_id = 5; // ZALLE Ismael
$montant = 100;   // 100 FCFA de test
$motif = "Test workflow FCM synchronisation mobile";

try {
    $pdo = getPDO();
    $pdo->beginTransaction();
    
    echo "1. Vérification coursier...\n";
    $stmt = $pdo->prepare("SELECT id, nom, prenoms, email, solde_wallet FROM agents_suzosky WHERE id = ?");
    $stmt->execute([$coursier_id]);
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coursier) {
        throw new Exception("Coursier introuvable");
    }
    
    echo "   Coursier: {$coursier['nom']} {$coursier['prenoms']}\n";
    echo "   Solde actuel: {$coursier['solde_wallet']} FCFA\n\n";
    
    $ancienSolde = $coursier['solde_wallet'] ?? 0;
    $nouveauSolde = $ancienSolde + $montant;
    
    echo "2. Mise à jour solde agents_suzosky...\n";
    $stmt = $pdo->prepare("UPDATE agents_suzosky SET solde_wallet = ? WHERE id = ?");
    $stmt->execute([$nouveauSolde, $coursier_id]);
    echo "   ✅ Solde mis à jour: {$ancienSolde} → {$nouveauSolde} FCFA\n\n";
    
    echo "3. Enregistrement dans table recharges...\n";
    $stmt = $pdo->prepare("
        INSERT INTO recharges (
            coursier_id, montant, currency, status, created_at, updated_at, details
        ) VALUES (?, ?, ?, ?, NOW(), NOW(), ?)
    ");
    
    $details = json_encode([
        'type' => 'rechargement_admin_direct_test',
        'admin_user' => 'test_system',
        'motif' => $motif,
        'ancien_solde' => $ancienSolde,
        'nouveau_solde' => $nouveauSolde,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->execute([$coursier_id, $montant, 'FCFA', 'success', $details]);
    echo "   ✅ Transaction enregistrée dans recharges\n\n";
    
    echo "4. Récupération tokens FCM actifs...\n";
    $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
    $stmt->execute([$coursier_id]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "   Tokens trouvés: " . count($tokens) . "\n\n";
    
    echo "5. Envoi notifications FCM...\n";
    $fcm = new FCMManager();
    $notificationsSent = 0;
    
    foreach ($tokens as $token) {
        echo "   Envoi vers token: " . substr($token, 0, 20) . "...\n";
        
        $title = '💰 Compte Rechargé!';
        $body = "Votre compte a été crédité de {$montant} FCFA\nNouveau solde: {$nouveauSolde} FCFA\nMotif: {$motif}";
        
        $data = [
            'type' => 'wallet_recharge',
            'montant' => (string)$montant,
            'nouveau_solde' => (string)$nouveauSolde,
            'motif' => $motif,
            'action' => 'refresh_wallet'
        ];
        
        $result = $fcm->envoyerNotification($token, $title, $body, $data);
        
        echo "     Résultat: " . ($result['success'] ? '✅ ENVOYÉ' : '❌ ÉCHEC') . "\n";
        if (!$result['success']) {
            echo "     Erreur: {$result['message']}\n";
        }
        
        // Log détaillé de la notification FCM
        $stmt = $pdo->prepare("
            INSERT INTO notifications_log_fcm 
            (coursier_id, token_used, message, type, status, response_data, created_at)
            VALUES (?, ?, ?, 'wallet_recharge', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $coursier_id, 
            $token, 
            $body,
            $result['success'] ? 'sent' : 'failed',
            json_encode($result)
        ]);
        
        if ($result['success']) {
            $notificationsSent++;
        }
    }
    
    $pdo->commit();
    
    echo "\n=== RÉSUMÉ ===\n";
    echo "✅ Rechargement: {$montant} FCFA\n";
    echo "✅ Nouveau solde: {$nouveauSolde} FCFA\n";
    echo "✅ Notifications envoyées: {$notificationsSent}/" . count($tokens) . "\n";
    echo "✅ Transaction ID dans recharges: " . $pdo->lastInsertId() . "\n\n";
    
    echo "L'app mobile devrait maintenant recevoir la notification et mettre à jour le solde automatiquement!\n";
    
} catch (Exception $e) {
    $pdo->rollback();
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}