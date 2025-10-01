<?php
require_once 'config.php';

$db = getDbConnection();

// Ajouter le champ cash_recupere s'il n'existe pas
try {
    $db->exec("ALTER TABLE commandes ADD COLUMN cash_recupere TINYINT(1) DEFAULT 0 COMMENT 'Argent récupéré par le coursier pour commandes en espèces'");
    echo "✅ Champ cash_recupere ajouté avec succès!" . PHP_EOL;
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Champ cash_recupere existe déjà" . PHP_EOL;
    } else {
        echo "❌ Erreur: " . $e->getMessage() . PHP_EOL;
    }
}

// Ajouter aussi le champ heure_debut s'il n'existe pas (pour "Commencer la livraison")
try {
    $db->exec("ALTER TABLE commandes ADD COLUMN heure_debut TIMESTAMP NULL COMMENT 'Heure de début de la livraison (après acceptation)'");
    echo "✅ Champ heure_debut ajouté avec succès!" . PHP_EOL;
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Champ heure_debut existe déjà" . PHP_EOL;
    } else {
        echo "❌ Erreur: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "=== État actuel des commandes coursier #5 ===" . PHP_EOL;
$stmt = $db->query("SELECT id, code_commande, statut, mode_paiement, heure_acceptation, heure_debut, heure_retrait, heure_livraison, cash_recupere FROM commandes WHERE coursier_id=5 ORDER BY id DESC LIMIT 3");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Code: {$row['code_commande']}" . PHP_EOL;
    echo "  Statut: {$row['statut']} | Paiement: {$row['mode_paiement']}" . PHP_EOL;
    echo "  Accepté: " . ($row['heure_acceptation'] ?? 'NULL') . PHP_EOL;
    echo "  Démarré: " . ($row['heure_debut'] ?? 'NULL') . PHP_EOL;
    echo "  Récupéré: " . ($row['heure_retrait'] ?? 'NULL') . PHP_EOL;
    echo "  Livré: " . ($row['heure_livraison'] ?? 'NULL') . PHP_EOL;
    echo "  Cash récupéré: " . ($row['cash_recupere'] ? 'OUI' : 'NON') . PHP_EOL;
    echo PHP_EOL;
}
