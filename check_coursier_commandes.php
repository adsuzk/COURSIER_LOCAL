<?php
require_once __DIR__ . '/config.php';

$pdo = getDBConnection();

// Vérifier les commandes du coursier 5
$stmt = $pdo->prepare("
    SELECT id, code_commande, statut, coursier_id, created_at, updated_at
    FROM commandes 
    WHERE coursier_id = 5 
    AND statut IN ('nouvelle', 'attribuee', 'acceptee', 'en_cours', 'recuperee')
    ORDER BY created_at DESC
");
$stmt->execute();

echo "COMMANDES DU COURSIER 5:\n";
echo "─────────────────────────────────────────────────────\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
        "ID: %d | Code: %s | Statut: %s | Créée: %s\n",
        $row['id'],
        $row['code_commande'],
        $row['statut'],
        $row['created_at']
    );
}
?>
