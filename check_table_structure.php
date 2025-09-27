<?php
require_once __DIR__ . '/config.php';

echo "=== STRUCTURE TABLE AGENTS ===\n";

try {
    $pdo = getDBConnection();
    
    // Obtenir la structure de la table agents
    $stmt = $pdo->query("DESCRIBE agents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes disponibles dans la table 'agents':\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")" . ($column['Key'] ? " [" . $column['Key'] . "]" : "") . "\n";
    }
    
    echo "\n=== RECHERCHE COURSIER CM20250003 ===\n";
    
    // Chercher le coursier par différents champs possibles
    $possible_fields = ['matricule', 'nom', 'prenoms', 'telephone', 'email', 'id'];
    
    foreach ($possible_fields as $field) {
        if (in_array($field, array_column($columns, 'Field'))) {
            echo "Recherche par $field contenant 'CM20250003'...\n";
            $stmt = $pdo->prepare("SELECT * FROM agents WHERE $field LIKE ?");
            $stmt->execute(['%CM20250003%']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($results) {
                echo "✅ TROUVÉ dans $field:\n";
                foreach ($results as $result) {
                    echo "  ID: " . $result['id'] . "\n";
                    echo "  Nom: " . ($result['nom'] ?? 'N/A') . " " . ($result['prenoms'] ?? 'N/A') . "\n";
                    echo "  Téléphone: " . ($result['telephone'] ?? 'N/A') . "\n";
                    echo "  Hash mot de passe: " . ($result['mot_de_passe'] ?? 'N/A') . "\n";
                    echo "  ---\n";
                }
            }
        }
    }
    
    // Si pas trouvé, lister tous les agents pour debug
    echo "\n=== LISTE DE TOUS LES AGENTS (premiers 10) ===\n";
    $stmt = $pdo->query("SELECT * FROM agents LIMIT 10");
    $all_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_agents as $agent) {
        echo "ID: " . $agent['id'] . " | ";
        echo "Nom: " . ($agent['nom'] ?? 'N/A') . " " . ($agent['prenoms'] ?? 'N/A') . " | ";
        echo "Tél: " . ($agent['telephone'] ?? 'N/A') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>