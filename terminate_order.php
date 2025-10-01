<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

// 1. Afficher toutes les commandes non terminées
echo "=== TOUTES LES COMMANDES NON TERMINÉES ===\n\n";
$stmt = $pdo->query("
    SELECT id, code_commande, order_number, statut, coursier_id, client_nom, created_at 
    FROM commandes 
    WHERE statut NOT IN ('livree', 'annulee')
    ORDER BY created_at DESC
");

$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($commandes)) {
    echo "Aucune commande non terminée.\n\n";
} else {
    foreach ($commandes as $cmd) {
        echo "ID: " . $cmd['id'] . "\n";
        echo "Code: " . $cmd['code_commande'] . "\n";
        echo "Order#: " . $cmd['order_number'] . "\n";
        echo "Statut: " . $cmd['statut'] . "\n";
        echo "Coursier: " . ($cmd['coursier_id'] ?? 'NON ASSIGNÉ') . "\n";
        echo "Client: " . $cmd['client_nom'] . "\n";
        echo "Créée: " . $cmd['created_at'] . "\n";
        echo str_repeat("-", 60) . "\n";
    }
}

// 2. Si une commande ID est fournie, la terminer
if ($argc > 1 && is_numeric($argv[1])) {
    $orderId = (int)$argv[1];
    
    echo "\n=== TERMINER LA COMMANDE ID: $orderId ===\n";
    
    // Récupérer les détails de la commande
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo "ERREUR: Commande introuvable!\n";
        exit(1);
    }
    
    echo "Commande trouvée:\n";
    echo "  - Code: " . $order['code_commande'] . "\n";
    echo "  - Order#: " . $order['order_number'] . "\n";
    echo "  - Statut actuel: " . $order['statut'] . "\n";
    echo "  - Coursier ID: " . ($order['coursier_id'] ?? 'NULL') . "\n";
    
    // Mettre à jour le statut
    echo "\nMise à jour du statut à 'livree'...\n";
    
    $updateStmt = $pdo->prepare("
        UPDATE commandes 
        SET statut = 'livree', 
            statut_paiement = 'paye',
            heure_livraison = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $updateStmt->execute([$orderId]);
    
    if ($result) {
        echo "✓ Commande terminée avec succès!\n";
        
        // Si un coursier était assigné, créer la transaction
        if ($order['coursier_id']) {
            echo "\nCréation de la transaction pour le coursier...\n";
            
            $montant = $order['prix_estime'] ?? $order['prix_total'] ?? 0;
            
            // Vérifier si une transaction existe déjà
            $checkTrans = $pdo->prepare("
                SELECT id FROM coursier_transactions 
                WHERE order_id = ? AND coursier_id = ?
            ");
            $checkTrans->execute([$orderId, $order['coursier_id']]);
            
            if (!$checkTrans->fetch()) {
                $insertTrans = $pdo->prepare("
                    INSERT INTO coursier_transactions 
                    (coursier_id, order_id, montant, type, description, created_at) 
                    VALUES (?, ?, ?, 'gain', ?, NOW())
                ");
                
                $description = "Livraison commande " . $order['code_commande'];
                $insertTrans->execute([
                    $order['coursier_id'],
                    $orderId,
                    $montant,
                    $description
                ]);
                
                echo "✓ Transaction créée (Montant: $montant FCFA)\n";
                
                // Mettre à jour le solde du coursier
                $updateBalance = $pdo->prepare("
                    UPDATE coursiers 
                    SET solde = solde + ?
                    WHERE id = ?
                ");
                $updateBalance->execute([$montant, $order['coursier_id']]);
                
                echo "✓ Solde coursier mis à jour\n";
            } else {
                echo "⚠ Transaction déjà existante\n";
            }
        }
        
        // Vérifier le résultat
        $verifyStmt = $pdo->prepare("SELECT statut, statut_paiement, heure_livraison FROM commandes WHERE id = ?");
        $verifyStmt->execute([$orderId]);
        $updated = $verifyStmt->fetch(PDO::FETCH_ASSOC);
        
        echo "\nÉtat final:\n";
        echo "  - Statut: " . $updated['statut'] . "\n";
        echo "  - Paiement: " . $updated['statut_paiement'] . "\n";
        echo "  - Heure livraison: " . $updated['heure_livraison'] . "\n";
        
    } else {
        echo "✗ Erreur lors de la mise à jour!\n";
        exit(1);
    }
}

echo "\n=== UTILISATION ===\n";
echo "Pour terminer une commande: php " . basename(__FILE__) . " <ID_COMMANDE>\n";
echo "Exemple: php " . basename(__FILE__) . " 144\n";
