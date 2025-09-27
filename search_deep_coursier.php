<?php
require_once __DIR__ . '/config.php';

echo "=== RECHERCHE APPROFONDIE CM20250003 ===\n";

try {
    $pdo = getDBConnection();
    
    // Recherche dans la table coursiers
    echo "=== TABLE COURSIERS ===\n";
    
    // Recherche par tous les champs texte possibles
    $fields = ['nom', 'telephone', 'email', 'adresse', 'numero_permis', 'cni'];
    
    foreach ($fields as $field) {
        $stmt = $pdo->prepare("SELECT id, nom, telephone, email, statut, password_hash, password_plain FROM coursiers WHERE $field LIKE ?");
        $stmt->execute(['%CM20250003%']);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($results) {
            echo "✅ Trouvé via $field:\n";
            foreach ($results as $result) {
                foreach ($result as $key => $value) {
                    echo "  $key: $value\n";
                }
                echo "  ---\n";
            }
        }
    }
    
    // Recherche approximative par nom ou téléphone
    echo "\n=== RECHERCHE APPROXIMATIVE ===\n";
    $stmt = $pdo->query("SELECT id, nom, telephone, email, statut, password_hash, password_plain, derniere_connexion FROM coursiers WHERE nom LIKE '%CM%' OR telephone LIKE '%20250003%'");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($results) {
        echo "Résultats approximatifs:\n";
        foreach ($results as $result) {
            echo "ID: {$result['id']} | Nom: {$result['nom']} | Tél: {$result['telephone']} | Statut: {$result['statut']}\n";
            echo "  Password hash: {$result['password_hash']}\n";
            echo "  Password plain: {$result['password_plain']}\n";
            echo "  Dernière connexion: {$result['derniere_connexion']}\n";
            echo "  ---\n";
        }
    }
    
    // Liste de tous les coursiers actifs
    echo "\n=== TOUS LES COURSIERS ACTIFS ===\n";
    $stmt = $pdo->query("SELECT id, nom, telephone, email, statut FROM coursiers WHERE statut = 'actif' LIMIT 20");
    $all_coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_coursiers as $coursier) {
        echo "ID: {$coursier['id']} | {$coursier['nom']} | {$coursier['telephone']} | {$coursier['email']}\n";
    }
    
    // Vérification si il pourrait y avoir une autre table
    echo "\n=== AUTRES TABLES POSSIBLES ===\n";
    
    // Vérifier agents_unified
    $stmt = $pdo->query("SELECT * FROM agents_unified LIMIT 5");
    $unified_sample = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($unified_sample) {
        echo "Exemple agents_unified:\n";
        foreach ($unified_sample[0] as $key => $value) {
            echo "  $key: $value\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>