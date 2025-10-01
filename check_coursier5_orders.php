<?php
require_once __DIR__ . '/config.php';
$pdo = getPDO();

echo "=== COMMANDES DU COURSIER ID 5 ===\n\n";

$stmt = $pdo->prepare("
    SELECT id, code_commande, order_number, statut, coursier_id, client_nom, created_at, updated_at
    FROM commandes 
    WHERE coursier_id = 5 
    ORDER BY created_at DESC 
    LIMIT 20
");

$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "Aucune commande trouvée pour le coursier ID 5\n";
} else {
    foreach ($rows as $r) {
        echo "ID: " . $r['id'] . "\n";
        echo "Code: " . $r['code_commande'] . "\n";
        echo "Order#: " . $r['order_number'] . "\n";
        echo "Statut: " . $r['statut'] . "\n";
        echo "Client: " . $r['client_nom'] . "\n";
        echo "Créée: " . $r['created_at'] . "\n";
        echo "MAJ: " . $r['updated_at'] . "\n";
        echo str_repeat("-", 60) . "\n";
    }
}

echo "\n=== COMMANDES ACTIVES (NON TERMINÉES) ===\n\n";

$stmt2 = $pdo->prepare("
    SELECT id, code_commande, order_number, statut, client_nom, created_at
    FROM commandes 
    WHERE coursier_id = 5 
    AND statut NOT IN ('livree', 'annulee')
    ORDER BY created_at DESC
");

$stmt2->execute();
$active = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($active)) {
    echo "Aucune commande active pour le coursier ID 5\n";
} else {
    foreach ($active as $a) {
        echo "⚠ ID: " . $a['id'] . " | Code: " . $a['code_commande'] . " | Order#: " . $a['order_number'] . " | Statut: " . $a['statut'] . "\n";
    }
}
