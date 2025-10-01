<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

echo "=== COMMANDES EN COURS ===\n\n";

$stmt = $pdo->query("
    SELECT id, code_commande, order_number, statut, coursier_id, client_nom, 
           pickup_address, delivery_address, created_at 
    FROM commandes 
    WHERE statut IN ('en_cours', 'acceptee', 'en_route', 'prise_en_charge')
    ORDER BY created_at DESC 
    LIMIT 10
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    echo "Aucune commande en cours trouvée.\n";
} else {
    foreach ($rows as $row) {
        echo "ID: " . $row['id'] . "\n";
        echo "Code Commande: " . ($row['code_commande'] ?? 'NULL') . "\n";
        echo "Order Number: " . ($row['order_number'] ?? 'NULL') . "\n";
        echo "Statut: " . $row['statut'] . "\n";
        echo "Coursier ID: " . ($row['coursier_id'] ?? 'NULL') . "\n";
        echo "Client: " . $row['client_nom'] . "\n";
        echo "Pickup: " . $row['pickup_address'] . "\n";
        echo "Delivery: " . $row['delivery_address'] . "\n";
        echo "Créée le: " . $row['created_at'] . "\n";
        echo str_repeat("-", 50) . "\n";
    }
}

echo "\n=== DERNIÈRES COMMANDES (toutes) ===\n\n";

$stmt2 = $pdo->query("
    SELECT id, code_commande, order_number, statut, coursier_id, client_nom, created_at 
    FROM commandes 
    ORDER BY created_at DESC 
    LIMIT 5
");

$rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows2 as $row) {
    echo "ID: " . $row['id'] . " | Code: " . ($row['code_commande'] ?? 'NULL') . " | Order#: " . ($row['order_number'] ?? 'NULL') . " | Statut: " . $row['statut'] . " | Coursier: " . ($row['coursier_id'] ?? 'NULL') . "\n";
}
