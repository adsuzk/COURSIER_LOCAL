<?php
require_once(__DIR__ . '/config.php');

echo "=== STRUCTURE TABLE agents_suzosky ===\n";

try {
    $pdo = getDBConnection();
    $stmt = $pdo->query('DESCRIBE agents_suzosky');
    
    while($row = $stmt->fetch()) {
        echo sprintf("%-20s | %-15s | %-5s | %-5s | %s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key'], 
            $row['Default'] ?? 'NULL'
        );
    }
    
    echo "\n=== DONNÉES CM20250003 ===\n";
    
    // Test avec différents noms de colonnes possibles
    $possible_cols = ['identifiant', 'id_coursier', 'code_coursier', 'username', 'login'];
    
    foreach($possible_cols as $col) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE $col = 'CM20250003' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            if($result) {
                echo "✅ Trouvé avec colonne '$col':\n";
                print_r($result);
                break;
            }
        } catch(Exception $e) {
            // Colonne n'existe pas
        }
    }
    
} catch(Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}
?>