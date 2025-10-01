<?php
require_once 'config.php';
$db = getDbConnection();

// Créer une nouvelle commande en espèces pour coursier #5
$sql = "INSERT INTO commandes (
    order_number, 
    code_commande, 
    client_nom, 
    client_telephone, 
    adresse_retrait, 
    latitude_retrait, 
    longitude_retrait, 
    adresse_livraison, 
    latitude_livraison, 
    longitude_livraison, 
    description_colis, 
    coursier_id, 
    statut, 
    priorite, 
    prix_base, 
    prix_total, 
    mode_paiement, 
    created_at
) VALUES (
    'ORD" . time() . "',
    'SZ" . date('ymdHis') . strtoupper(substr(md5(uniqid()), 0, 3)) . "',
    'Client Test Cash',
    '0757575757',
    'Cocody Angré 8ème Tranche',
    5.3600,
    -3.9800,
    'Marcory Zone 4',
    5.3200,
    -4.0100,
    'Test bouton Cash récupéré - Livraison espèces',
    5,
    'acceptee',
    'normale',
    2500,
    2500,
    'especes',
    NOW()
)";

$db->exec($sql);
$lastId = $db->lastInsertId();

echo "✅ Commande #{$lastId} créée avec succès!" . PHP_EOL;
echo "📦 Mode de paiement: ESPÈCES" . PHP_EOL;
echo "💰 Montant: 2500 FCFA" . PHP_EOL;
echo "🚚 Statut initial: acceptee" . PHP_EOL;
echo PHP_EOL;
echo "🎯 Flow à tester:" . PHP_EOL;
echo "  1. Cliquer '🚀 Commencer la livraison' → statut = en_cours" . PHP_EOL;
echo "  2. Cliquer '📦 J'ai récupéré le colis' → statut = recuperee" . PHP_EOL;
echo "  3. Cliquer '🏁 Marquer comme livrée' → statut = livree" . PHP_EOL;
echo "  4. Cliquer '💵 J'ai récupéré le cash' → cash_recupere = 1" . PHP_EOL;
echo PHP_EOL;

// Afficher toutes les commandes du coursier
$stmt = $db->query("SELECT id, code_commande, statut, mode_paiement, cash_recupere FROM commandes WHERE coursier_id=5 ORDER BY id DESC LIMIT 5");
echo "=== Commandes coursier #5 ===" . PHP_EOL;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Code: {$row['code_commande']} | Statut: {$row['statut']} | Paiement: {$row['mode_paiement']} | Cash: " . ($row['cash_recupere'] ? 'OUI' : 'NON') . PHP_EOL;
}
