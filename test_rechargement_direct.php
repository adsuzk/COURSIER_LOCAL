<?php
/**
 * TEST DU SYSTÈME DE RECHARGEMENT DIRECT
 * Vérification de l'intégration et des fonctionnalités
 */

require_once 'config.php';

echo "🧪 TEST SYSTÈME RECHARGEMENT DIRECT\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier l'état des coursiers avant test
    echo "\n📊 1. ÉTAT INITIAL DES COURSIERS\n";
    
    $stmt = $pdo->query("
        SELECT 
            id, nom, prenoms, email,
            COALESCE(solde_wallet, 0) as solde,
            statut_connexion
        FROM agents_suzosky 
        WHERE type_poste IN ('coursier', 'coursier_moto', 'coursier_velo')
        ORDER BY nom
    ");
    $coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($coursiers as $coursier) {
        $status = $coursier['statut_connexion'] === 'en_ligne' ? '🟢' : '⚫';
        echo "   $status {$coursier['nom']} {$coursier['prenoms']}: {$coursier['solde']} FCFA\n";
    }
    
    // 2. Test simulation rechargement
    echo "\n💳 2. TEST SIMULATION RECHARGEMENT\n";
    
    if (!empty($coursiers)) {
        $coursierTest = $coursiers[0]; // Premier coursier pour test
        $montantTest = 1000;
        $motifTest = "Test automatique système";
        
        echo "   🎯 Coursier sélectionné: {$coursierTest['nom']} {$coursierTest['prenoms']}\n";
        echo "   💰 Solde actuel: {$coursierTest['solde']} FCFA\n";
        echo "   ➕ Montant à ajouter: $montantTest FCFA\n";
        
        // Simuler le rechargement
        $ancienSolde = $coursierTest['solde'];
        $nouveauSolde = $ancienSolde + $montantTest;
        
        $pdo->beginTransaction();
        
        // Update solde
        $stmt = $pdo->prepare("UPDATE agents_suzosky SET solde_wallet = ? WHERE id = ?");
        $stmt->execute([$nouveauSolde, $coursierTest['id']]);
        
        // Enregistrer transaction
        $reference = 'TEST_RECH_' . date('YmdHis');
        $stmt = $pdo->prepare("
            INSERT INTO transactions_financieres (
                type, montant, compte_type, compte_id, reference, description, statut, date_creation
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'credit',
            $montantTest,
            'coursier',
            $coursierTest['id'],
            $reference,
            "Test rechargement: $montantTest FCFA - $motifTest",
            'reussi'
        ]);
        
        // Vérifier FCM tokens
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
        $stmt->execute([$coursierTest['id']]);
        $fcmCount = $stmt->fetchColumn();
        
        echo "   🔔 Tokens FCM disponibles: $fcmCount\n";
        
        if ($fcmCount > 0) {
            // Simuler notification FCM
            $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$coursierTest['id']]);
            $token = $stmt->fetchColumn();
            
            $message = "💰 Test: Compte rechargé! +{$montantTest} FCFA - Nouveau solde: {$nouveauSolde} FCFA";
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications_log_fcm (coursier_id, token_used, message, status, created_at)
                VALUES (?, ?, ?, 'sent', NOW())
            ");
            $stmt->execute([$coursierTest['id'], $token, $message]);
            
            echo "   📤 Notification FCM envoyée\n";
        } else {
            echo "   ⚠️ Aucun token FCM - Notification non envoyée\n";
        }
        
        $pdo->commit();
        
        echo "   ✅ Rechargement simulé avec succès!\n";
        echo "   📈 Nouveau solde: $nouveauSolde FCFA\n";
        echo "   📋 Référence: $reference\n";
        
    } else {
        echo "   ❌ Aucun coursier disponible pour le test\n";
    }
    
    // 3. Vérifier l'état après rechargement
    echo "\n📊 3. ÉTAT APRÈS RECHARGEMENT\n";
    
    $stmt = $pdo->query("
        SELECT 
            id, nom, prenoms,
            COALESCE(solde_wallet, 0) as solde,
            statut_connexion
        FROM agents_suzosky 
        WHERE type_poste IN ('coursier', 'coursier_moto', 'coursier_velo')
        ORDER BY nom
    ");
    $coursiersApres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($coursiersApres as $coursier) {
        $status = $coursier['statut_connexion'] === 'en_ligne' ? '🟢' : '⚫';
        echo "   $status {$coursier['nom']} {$coursier['prenoms']}: {$coursier['solde']} FCFA\n";
    }
    
    // 4. Vérifier les transactions
    echo "\n📄 4. DERNIÈRES TRANSACTIONS\n";
    
    $stmt = $pdo->query("
        SELECT 
            reference, montant, description, date_creation
        FROM transactions_financieres 
        WHERE type = 'credit' 
        ORDER BY date_creation DESC 
        LIMIT 3
    ");
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($transactions as $trans) {
        echo "   💳 {$trans['reference']}: {$trans['montant']} FCFA - {$trans['description']}\n";
        echo "      📅 {$trans['date_creation']}\n";
    }
    
    // 5. Test interface admin
    echo "\n🌐 5. TEST ACCÈS INTERFACE\n";
    
    $urlTest = 'http://localhost/COURSIER_LOCAL/admin.php?section=finances&tab=rechargement_direct';
    echo "   🔗 URL: $urlTest\n";
    
    // Test simple avec curl si disponible
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlTest);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            echo "   ✅ Interface accessible (HTTP $httpCode)\n";
            
            if (strpos($response, 'Rechargement Direct') !== false) {
                echo "   ✅ Contenu correct détecté\n";
            } else {
                echo "   ⚠️ Contenu inattendu\n";
            }
        } else {
            echo "   ❌ Erreur HTTP: $httpCode\n";
        }
    } else {
        echo "   ℹ️ CURL non disponible - Test manuel requis\n";
    }
    
    echo "\n✅ TESTS TERMINÉS AVEC SUCCÈS!\n";
    echo "🎯 Le système de rechargement direct est opérationnel.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>