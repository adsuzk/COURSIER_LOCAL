<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

echo "=== ANALYSE DES COMMANDES ===\n\n";

// 1. Identifier les commandes de test
echo "--- COMMANDES DE TEST (à supprimer) ---\n";
$testOrders = $pdo->query("
    SELECT id, code_commande, order_number, statut, coursier_id, created_at
    FROM commandes
    WHERE code_commande LIKE 'T%' 
       OR code_commande LIKE 'TEST%'
       OR order_number LIKE 'TEST-%'
       OR order_number LIKE 'TST%'
    ORDER BY created_at DESC
");

$testCount = 0;
$testIds = [];
while ($test = $testOrders->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $test['id'] . " | Code: " . $test['code_commande'] . " | Order#: " . $test['order_number'] . " | Statut: " . $test['statut'] . "\n";
    $testIds[] = $test['id'];
    $testCount++;
}

echo "\nTotal commandes de test: $testCount\n\n";

// 2. Identifier les vraies commandes (depuis index)
echo "--- VRAIES COMMANDES (depuis l'index) ---\n";
$realOrders = $pdo->query("
    SELECT id, code_commande, order_number, statut, coursier_id, client_nom, created_at
    FROM commandes
    WHERE (code_commande LIKE 'SZ%' OR code_commande LIKE 'SZK%')
    AND statut NOT IN ('livree', 'annulee')
    ORDER BY created_at DESC
    LIMIT 10
");

$realCount = 0;
while ($real = $realOrders->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $real['id'] . " | Code: " . $real['code_commande'] . " | Order#: " . $real['order_number'] . " | Statut: " . $real['statut'] . " | Client: " . $real['client_nom'] . "\n";
    $realCount++;
}

echo "\nTotal vraies commandes actives: $realCount\n\n";

// 3. Option de nettoyage
if ($argc > 1 && $argv[1] === 'clean') {
    echo "=== NETTOYAGE DES COMMANDES DE TEST ===\n\n";
    
    if (empty($testIds)) {
        echo "Aucune commande de test à supprimer.\n";
    } else {
        // D'abord, supprimer les transactions liées (si la table existe)
        $placeholders = implode(',', array_fill(0, count($testIds), '?'));
        
        // Vérifier si la table coursier_transactions existe
        $tables = $pdo->query("SHOW TABLES LIKE 'coursier_transactions'")->fetchAll();
        if (!empty($tables)) {
            echo "Suppression des transactions liées...\n";
            $deleteTrans = $pdo->prepare("DELETE FROM coursier_transactions WHERE order_id IN ($placeholders)");
            $deleteTrans->execute($testIds);
            $transDeleted = $deleteTrans->rowCount();
            echo "✓ $transDeleted transactions supprimées\n";
        } else {
            echo "⚠ Table coursier_transactions inexistante (ignorée)\n";
        }
        
        // Ensuite, supprimer les commandes
        echo "Suppression des commandes de test...\n";
        $deleteOrders = $pdo->prepare("DELETE FROM commandes WHERE id IN ($placeholders)");
        $deleteOrders->execute($testIds);
        $ordersDeleted = $deleteOrders->rowCount();
        echo "✓ $ordersDeleted commandes de test supprimées\n";
        
        // Réinitialiser le total_commandes du coursier si nécessaire
        echo "Réinitialisation du compteur de commandes du coursier...\n";
        $pdo->exec("
            UPDATE coursiers c
            SET total_commandes = (
                SELECT COUNT(*) 
                FROM commandes 
                WHERE coursier_id = c.id 
                AND statut = 'livree'
            )
        ");
        echo "✓ Compteurs mis à jour\n";
        
        echo "\n✅ NETTOYAGE TERMINÉ !\n";
    }
} else {
    echo "=== POUR NETTOYER LES COMMANDES DE TEST ===\n";
    echo "Exécutez: php " . basename(__FILE__) . " clean\n";
}
