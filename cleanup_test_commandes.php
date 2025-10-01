<?php
/**
 * 🧹 NETTOYAGE DES COMMANDES DE TEST
 * Termine automatiquement toutes les commandes "nouvelle" de test
 */

require_once __DIR__ . '/config.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   🧹 NETTOYAGE DES COMMANDES DE TEST                     ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. Identifier les commandes "nouvelle" à nettoyer
    $stmt = $pdo->query("
        SELECT id, code_commande, created_at, adresse_depart, prix_estime
        FROM commandes
        WHERE statut = 'nouvelle'
        ORDER BY created_at ASC
    ");
    
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($commandes);
    
    echo "📊 Commandes 'nouvelle' trouvées: $total\n\n";
    
    if ($total === 0) {
        echo "✅ Aucune commande à nettoyer!\n";
        exit;
    }
    
    // Demander confirmation
    echo "⚠️  ATTENTION: Vous êtes sur le point de terminer $total commandes.\n";
    echo "Voulez-vous continuer? (oui/non): ";
    
    // En mode automatique pour le script
    $confirmation = 'oui';
    echo "$confirmation\n\n";
    
    if ($confirmation !== 'oui') {
        echo "❌ Opération annulée\n";
        exit;
    }
    
    // 2. Commencer le nettoyage
    echo "🚀 Début du nettoyage...\n";
    echo "─────────────────────────────────────────────────────────\n";
    
    $pdo->beginTransaction();
    
    $updated = 0;
    $errors = 0;
    
    foreach ($commandes as $cmd) {
        try {
            // Marquer comme livrée
            $updateStmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'livree', 
                    statut_paiement = 'paye',
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $updateStmt->execute([$cmd['id']]);
            
            // Créer une transaction si pas déjà existante
            $checkTransaction = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE commande_id = ?");
            $checkTransaction->execute([$cmd['id']]);
            
            if ($checkTransaction->fetchColumn() == 0) {
                $insertTransaction = $pdo->prepare("
                    INSERT INTO transactions (
                        commande_id, 
                        reference_transaction, 
                        montant, 
                        type_transaction,
                        methode_paiement, 
                        statut, 
                        created_at
                    ) VALUES (?, ?, ?, 'paiement', 'especes', 'success', NOW())
                ");
                
                $refTransaction = 'TRX-TEST-' . strtoupper(uniqid());
                $insertTransaction->execute([
                    $cmd['id'],
                    $refTransaction,
                    $cmd['prix_estime'] ?? 0
                ]);
            }
            
            $updated++;
            
            if ($updated % 10 === 0) {
                echo "✓ $updated/$total commandes terminées...\n";
            }
            
        } catch (Exception $e) {
            $errors++;
            echo "❌ Erreur sur commande #{$cmd['code_commande']}: {$e->getMessage()}\n";
        }
    }
    
    $pdo->commit();
    
    echo "\n╔════════════════════════════════════════════════════════════╗\n";
    echo "║   ✅ NETTOYAGE TERMINÉ                                   ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📊 RÉSULTATS:\n";
    echo "─────────────────────────────────────────────────────────\n";
    echo "Total commandes traitées: $total\n";
    echo "✅ Terminées avec succès: $updated\n";
    echo "❌ Erreurs: $errors\n\n";
    
    if ($updated > 0) {
        echo "🎉 Les commandes ont été marquées comme 'livree' et 'paye'\n";
        echo "💰 Des transactions ont été créées automatiquement\n\n";
    }
    
    // 3. Vérification finale
    $finalCheck = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut = 'nouvelle'");
    $remaining = $finalCheck->fetchColumn();
    
    echo "📋 Vérification finale:\n";
    echo "Commandes 'nouvelle' restantes: $remaining\n\n";
    
    if ($remaining == 0) {
        echo "✅ Parfait! Plus aucune commande 'nouvelle' en attente\n";
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
}
?>
