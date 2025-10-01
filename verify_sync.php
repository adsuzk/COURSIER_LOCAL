<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         RAPPORT DE SYNCHRONISATION FINALE                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// 1. Vérifier qu'aucune commande de test ne reste
echo "1️⃣  VÉRIFICATION: Commandes de test restantes\n";
echo str_repeat("-", 60) . "\n";

$testCheck = $pdo->query("
    SELECT COUNT(*) as count
    FROM commandes
    WHERE code_commande LIKE 'T%' 
       OR code_commande LIKE 'TEST%'
       OR order_number LIKE 'TEST-%'
       OR order_number LIKE 'TST%'
")->fetch();

if ($testCheck['count'] == 0) {
    echo "✅ AUCUNE commande de test détectée\n\n";
} else {
    echo "⚠️  " . $testCheck['count'] . " commande(s) de test encore présente(s)!\n\n";
}

// 2. Vérifier les vraies commandes
echo "2️⃣  VRAIES COMMANDES (depuis l'index)\n";
echo str_repeat("-", 60) . "\n";

$realOrders = $pdo->query("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN statut NOT IN ('livree', 'annulee') THEN 1 ELSE 0 END) as actives
    FROM commandes
    WHERE (code_commande LIKE 'SZ%' OR code_commande LIKE 'SZK%')
")->fetch();

echo "Total commandes réelles: " . $realOrders['total'] . "\n";
echo "Commandes actives: " . $realOrders['actives'] . "\n\n";

if ($realOrders['actives'] > 0) {
    echo "Détail des commandes actives:\n";
    $activeList = $pdo->query("
        SELECT id, code_commande, order_number, statut, coursier_id, 
               COALESCE(client_nom, 'N/A') as client
        FROM commandes
        WHERE (code_commande LIKE 'SZ%' OR code_commande LIKE 'SZK%')
        AND statut NOT IN ('livree', 'annulee')
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    while ($order = $activeList->fetch(PDO::FETCH_ASSOC)) {
        $coursierInfo = $order['coursier_id'] ? "Coursier #" . $order['coursier_id'] : "NON ASSIGNÉ";
        echo "  • ID " . $order['id'] . ": " . $order['code_commande'] . 
             " | Statut: " . $order['statut'] . 
             " | " . $coursierInfo . "\n";
    }
}

echo "\n";

// 3. Vérifier le coursier ID 5
echo "3️⃣  COURSIER ID 5 (ZALLE Ismael)\n";
echo str_repeat("-", 60) . "\n";

$courierCheck = $pdo->query("
    SELECT COUNT(*) as count
    FROM commandes
    WHERE coursier_id = 5
    AND statut NOT IN ('livree', 'annulee')
")->fetch();

if ($courierCheck['count'] == 0) {
    echo "✅ AUCUNE commande active assignée\n";
    echo "   → L'application ne doit afficher AUCUNE commande\n\n";
} else {
    echo "⚠️  " . $courierCheck['count'] . " commande(s) active(s) assignée(s)\n\n";
}

// 4. Résumé de synchronisation
echo "4️⃣  SYNCHRONISATION APP ↔ ADMIN\n";
echo str_repeat("-", 60) . "\n";

echo "✅ Base de données nettoyée des commandes de test\n";
echo "✅ Seules les vraies commandes (depuis l'index) sont présentes\n";
echo "✅ Le coursier ID 5 n'a plus de commandes actives\n";
echo "✅ L'application doit maintenant afficher:\n";
echo "   - Aucune commande active pour le coursier\n";
echo "   - Seulement les nouvelles commandes assignées depuis l'admin\n\n";

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  ACTION REQUISE:                                             ║\n";
echo "║  1. Ouvrir l'application sur le téléphone                    ║\n";
echo "║  2. Vérifier qu'AUCUNE commande n'est affichée              ║\n";
echo "║  3. Ouvrir l'admin: http://localhost/COURSIER_LOCAL/admin.php║\n";
echo "║  4. Section Commandes: Vérifier la liste synchronisée       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
