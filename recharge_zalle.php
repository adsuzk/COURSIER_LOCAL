<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Rechargement ZALLE Ismael...\n";

$pdo->beginTransaction();

// Recharger ZALLE Ismael
$stmt = $pdo->prepare('UPDATE agents_suzosky SET solde_wallet = 5000 WHERE nom = "ZALLE" AND prenoms = "Ismael"');
$stmt->execute();

// Enregistrer transaction
$stmt = $pdo->prepare('
    INSERT INTO transactions_financieres (
        type, montant, compte_type, compte_id, reference, description, statut, date_creation
    ) VALUES ("credit", 5000, "coursier", 5, "ADMIN_MANUAL_ZALLE", "Rechargement manuel pour tests", "reussi", NOW())
');
$stmt->execute();

$pdo->commit();

echo "✅ Rechargement effectué: 5000 FCFA\n";

// Vérifier
$stmt = $pdo->query('SELECT nom, prenoms, COALESCE(solde_wallet, 0) as solde FROM agents_suzosky WHERE type_poste IN ("coursier", "coursier_moto")');
$coursiers = $stmt->fetchAll();

echo "\n📊 SOLDES ACTUELS:\n";
foreach ($coursiers as $c) {
    echo "   • {$c['nom']} {$c['prenoms']}: {$c['solde']} FCFA\n";
}
?>