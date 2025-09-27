<?php
require_once __DIR__ . '/config.php';

echo "=== RECHERCHE TOUTES TABLES POUR CM20250003 ===\n";

try {
    $pdo = getDBConnection();
    
    // Obtenir toutes les tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables disponibles:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\n=== RECHERCHE DU COURSIER ===\n";
    
    // Tables qui pourraient contenir des coursiers
    $target_tables = ['coursiers', 'agents_coursiers', 'utilisateurs', 'users', 'agents'];
    
    foreach ($target_tables as $table) {
        if (in_array($table, $tables)) {
            echo "\n--- TABLE: $table ---\n";
            
            // Obtenir la structure de la table
            $struct_stmt = $pdo->query("DESCRIBE $table");
            $columns = $struct_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Colonnes:\n";
            foreach ($columns as $col) {
                echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
            }
            
            // Rechercher CM20250003 dans cette table
            $search_fields = ['matricule', 'nom', 'prenoms', 'telephone', 'email', 'username', 'login'];
            
            foreach ($search_fields as $field) {
                if (in_array($field, array_column($columns, 'Field'))) {
                    try {
                        $search_stmt = $pdo->prepare("SELECT * FROM $table WHERE $field LIKE ?");
                        $search_stmt->execute(['%CM20250003%']);
                        $results = $search_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if ($results) {
                            echo "✅ TROUVÉ dans $table.$field:\n";
                            foreach ($results as $result) {
                                echo "  Enregistrement trouvé:\n";
                                foreach ($result as $key => $value) {
                                    echo "    $key: $value\n";
                                }
                                echo "  ---\n";
                            }
                        }
                    } catch (Exception $e) {
                        // Ignorer les erreurs de colonnes inexistantes
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>