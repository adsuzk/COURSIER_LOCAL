<?php
require_once __DIR__ . '/config.php';

// Simuler ce que voit l'admin
$pdo = getPDO();

echo "=== CE QUE VOIT L'ADMIN (SECTION COMMANDES) ===\n\n";

// C'est ce que fait admin_commandes_enhanced.php
$stmt = $pdo->query("
    SELECT c.id, c.code_commande, c.order_number, c.statut, c.coursier_id, c.client_nom, 
           c.created_at, c.updated_at, c.prix_estime,
           co.nom as coursier_nom
    FROM commandes c
    LEFT JOIN coursiers co ON c.coursier_id = co.id
    WHERE c.statut NOT IN ('livree', 'annulee')
    ORDER BY c.created_at DESC
    LIMIT 20
");

$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($commandes)) {
    echo "Aucune commande non terminée visible dans l'admin.\n";
} else {
    foreach ($commandes as $cmd) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: " . $cmd['id'] . "\n";
        echo "CODE AFFICHÉ: " . ($cmd['code_commande'] ?: $cmd['order_number'] ?: 'N/A') . "\n";
        echo "  - code_commande: " . ($cmd['code_commande'] ?: 'NULL') . "\n";
        echo "  - order_number: " . ($cmd['order_number'] ?: 'NULL') . "\n";
        echo "Statut: " . $cmd['statut'] . "\n";
        echo "Coursier: " . ($cmd['coursier_nom'] ?: 'NON ASSIGNÉ') . " (ID: " . ($cmd['coursier_id'] ?: 'NULL') . ")\n";
        echo "Client: " . ($cmd['client_nom'] ?: 'N/A') . "\n";
        echo "Prix: " . ($cmd['prix_estime'] ?: '0') . " FCFA\n";
        echo "Créée: " . $cmd['created_at'] . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
}

echo "\n=== COMMANDES AVEC COURSIER ID 5 (ACTIVES) ===\n\n";
$stmt2 = $pdo->query("
    SELECT id, code_commande, order_number, statut, client_nom, created_at
    FROM commandes
    WHERE coursier_id = 5
    AND statut NOT IN ('livree', 'annulee')
    ORDER BY created_at DESC
");

$active5 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($active5 as $cmd) {
    echo "ID: " . $cmd['id'] . " | ";
    echo "Code: " . $cmd['code_commande'] . " | ";
    echo "Order#: " . $cmd['order_number'] . " | ";
    echo "Statut: " . $cmd['statut'] . "\n";
}
