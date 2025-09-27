<?php
require_once 'config.php';

$pdo = getDBConnection();
$stmt = $pdo->query("
    SELECT id, nom, prenoms, statut_connexion, current_session_token, date_creation 
    FROM agents_suzosky 
    WHERE statut_connexion = 'en_ligne'
");

echo "COURSIERS MARQUÉS 'en_ligne' DANS LA BASE:\n";
while($row = $stmt->fetch()) {
    echo "ID: {$row['id']}, {$row['nom']} {$row['prenoms']}\n";
    echo "   Session: " . ($row['current_session_token'] ? 'OUI' : 'NON') . "\n";
    echo "   Date création: {$row['date_creation']}\n\n";
}
?>