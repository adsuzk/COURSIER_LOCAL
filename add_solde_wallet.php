<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Vérification et ajout colonne solde_wallet:\n";

// Vérifier structure actuelle
$stmt = $pdo->query('DESCRIBE agents_suzosky');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasWallet = false;
echo "Structure actuelle agents_suzosky:\n";
foreach ($columns as $col) {
    echo "  • {$col['Field']} ({$col['Type']})\n";
    if ($col['Field'] === 'solde_wallet') $hasWallet = true;
}

if (!$hasWallet) {
    echo "\n➕ Ajout colonne solde_wallet...\n";
    try {
        $pdo->exec('ALTER TABLE agents_suzosky ADD COLUMN solde_wallet DECIMAL(10,2) DEFAULT 0.00 AFTER email');
        echo "✅ Colonne solde_wallet ajoutée!\n";
    } catch (Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n✅ Colonne solde_wallet existe déjà\n";
}

// Vérifier les soldes actuels
echo "\n📊 Soldes actuels:\n";
$stmt = $pdo->query("SELECT id, nom, prenoms, COALESCE(solde_wallet, 0) as solde FROM agents_suzosky");
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($agents as $agent) {
    echo "  • {$agent['nom']} {$agent['prenoms']}: {$agent['solde']} FCFA\n";
}
?>