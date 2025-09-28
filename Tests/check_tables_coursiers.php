<?php
require_once 'config.php';
$pdo = getDBConnection();

echo "Tables disponibles avec coursier:\n";
$stmt = $pdo->query('SHOW TABLES LIKE "%coursier%"');
while ($table = $stmt->fetchColumn()) {
    echo "  • $table\n";
}

echo "\nContenu table coursiers:\n";
try {
    $stmt = $pdo->query('SELECT id, nom, email FROM coursiers LIMIT 5');
    while ($row = $stmt->fetch()) {
        echo "  • ID: {$row['id']} - {$row['nom']} ({$row['email']})\n";
    }
} catch (Exception $e) {
    echo "  Erreur: " . $e->getMessage() . "\n";
}

echo "\nContenu table agents_suzosky:\n";
try {
    $stmt = $pdo->query('SELECT id, nom, prenoms, email FROM agents_suzosky LIMIT 5');
    while ($row = $stmt->fetch()) {
        echo "  • ID: {$row['id']} - {$row['nom']} {$row['prenoms']} ({$row['email']})\n";
    }
} catch (Exception $e) {
    echo "  Erreur: " . $e->getMessage() . "\n";
}
?>