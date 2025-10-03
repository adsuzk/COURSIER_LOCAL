<?php
// Test de connexion MySQL
echo "=== Test de connexion MySQL ===\n";

try {
    $conn = new PDO('mysql:host=127.0.0.1;dbname=coursier_local', 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connexion MySQL OK\n";
    echo "✓ Base de données 'coursier_local' accessible\n";
    
    // Tester une requête simple
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Nombre de tables: " . count($tables) . "\n";
    
} catch(PDOException $e) {
    echo "✗ Erreur MySQL: " . $e->getMessage() . "\n";
}

echo "\n=== Test accès web ===\n";
echo "Si vous voyez ce message, Apache fonctionne correctement.\n";
?>
