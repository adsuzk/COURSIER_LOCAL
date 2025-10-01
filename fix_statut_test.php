<?php
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Changer le statut des commandes test en "nouvelle"
    $stmt = $pdo->prepare("UPDATE commandes SET statut = 'nouvelle' WHERE coursier_id = 5 AND id IN (134, 135)");
    $stmt->execute();
    
    echo "✅ Commandes #134 et #135 mises à jour avec statut 'nouvelle'\n";
    
    // Vérifier le résultat
    $stmt = $pdo->prepare("SELECT id, code_commande, statut FROM commandes WHERE coursier_id = 5 AND id IN (134, 135)");
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        echo "Commande #{$row['id']} - {$row['code_commande']} - Statut: {$row['statut']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>