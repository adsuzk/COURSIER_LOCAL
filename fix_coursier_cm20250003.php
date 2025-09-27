<?php
require_once __DIR__ . '/config.php';

echo "=== RECHERCHE DANS AGENTS_SUZOSKY ===\n";

try {
    $pdo = getDBConnection();
    
    // Vérifier la structure de agents_suzosky
    $stmt = $pdo->query("DESCRIBE agents_suzosky");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Structure table agents_suzosky:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
    // Rechercher CM20250003
    echo "\n=== RECHERCHE CM20250003 ===\n";
    
    $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE matricule = ? OR telephone = ?");
    $stmt->execute(['CM20250003', 'CM20250003']);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($agent) {
        echo "✅ COURSIER TROUVÉ!\n";
        foreach ($agent as $key => $value) {
            if ($key === 'password' || $key === 'plain_password') {
                echo "  $key: " . ($value ? '[HASH/PASSWORD PRÉSENT]' : '[VIDE]') . "\n";
            } else {
                echo "  $key: $value\n";
            }
        }
        
        // Test du mot de passe KOrxl
        echo "\n=== TEST MOT DE PASSE KOrxl ===\n";
        $test_password = 'KOrxl';
        
        $password_ok = false;
        
        // Test avec password_verify (hash moderne)
        if (!empty($agent['password'])) {
            $password_ok = password_verify($test_password, $agent['password']);
            if ($password_ok) {
                echo "✅ Password vérifié avec password_verify()\n";
            }
        }
        
        // Test avec plain_password (texte clair)
        if (!$password_ok && !empty($agent['plain_password'])) {
            $password_ok = hash_equals($agent['plain_password'], $test_password);
            if ($password_ok) {
                echo "✅ Password vérifié avec plain_password\n";
            }
        }
        
        if (!$password_ok) {
            echo "❌ MOT DE PASSE INCORRECT!\n";
            echo "  Password hash: " . ($agent['password'] ?? 'NULL') . "\n";
            echo "  Plain password: " . ($agent['plain_password'] ?? 'NULL') . "\n";
            echo "  Test avec 'KOrxl': ÉCHEC\n";
            
            // Correction automatique
            echo "\n=== CORRECTION AUTOMATIQUE ===\n";
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            
            $update_stmt = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = ? WHERE id = ?");
            if ($update_stmt->execute([$new_hash, $test_password, $agent['id']])) {
                echo "✅ MOT DE PASSE CORRIGÉ!\n";
                echo "  Nouveau hash: $new_hash\n";
                echo "  Plain password: $test_password (pour compatibilité)\n";
            } else {
                echo "❌ ERREUR lors de la correction\n";
            }
        }
        
    } else {
        echo "❌ COURSIER CM20250003 INTROUVABLE!\n";
        echo "Vérification de tous les agents...\n";
        
        $stmt = $pdo->query("SELECT id, matricule, nom, prenoms, telephone, statut FROM agents_suzosky LIMIT 20");
        $all_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nTous les agents (premiers 20):\n";
        foreach ($all_agents as $ag) {
            echo "  ID:{$ag['id']} | {$ag['matricule']} | {$ag['nom']} {$ag['prenoms']} | {$ag['telephone']} | {$ag['statut']}\n";
        }
        
        echo "\n=== CRÉATION DU COURSIER CM20250003 ===\n";
        // Créer le coursier manquant
        $insert_stmt = $pdo->prepare("
            INSERT INTO agents_suzosky (
                matricule, nom, prenoms, telephone, email, 
                password, plain_password, statut, type_agent, 
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'actif', 'coursier', NOW(), NOW())
        ");
        
        $new_hash = password_hash('KOrxl', PASSWORD_DEFAULT);
        
        if ($insert_stmt->execute([
            'CM20250003',
            'Coursier',
            'Test',
            '+225 07 XX XX XX XX', // Vous devrez mettre le vrai numéro
            'cm20250003@suzosky.com',
            $new_hash,
            'KOrxl'
        ])) {
            echo "✅ COURSIER CM20250003 CRÉÉ AVEC SUCCÈS!\n";
            echo "  Matricule: CM20250003\n";
            echo "  Mot de passe: KOrxl\n";
            echo "  Status: actif\n";
        } else {
            echo "❌ ERREUR lors de la création\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>