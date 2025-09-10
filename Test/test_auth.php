<?php
// test_auth.php - Script de test pour l'authentification
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();
    echo "✅ Connexion à la base de données réussie\n";
    
    // Vérifier si la table clients_particuliers existe
    $tables = $pdo->query("SHOW TABLES LIKE 'clients_particuliers'")->fetchAll();
    
    if (empty($tables)) {
        echo "⚠️ Table clients_particuliers n'existe pas. Création...\n";
        
        // Créer la table clients_particuliers
        $sql = "
        CREATE TABLE clients_particuliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nom VARCHAR(100) NOT NULL,
            prenoms VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE,
            telephone VARCHAR(20) NOT NULL UNIQUE,
            password VARCHAR(255) NULL,
            statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_telephone (telephone),
            INDEX idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql);
        echo "✅ Table clients_particuliers créée avec succès\n";
    } else {
        echo "✅ Table clients_particuliers existe déjà\n";
        
        // Vérifier si le champ password existe
        $columns = $pdo->query("SHOW COLUMNS FROM clients_particuliers LIKE 'password'")->fetchAll();
        if (empty($columns)) {
            echo "⚠️ Champ password manquant. Ajout...\n";
            $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN password VARCHAR(255) NULL AFTER email");
            echo "✅ Champ password ajouté\n";
        }
    }
    
    // Créer un utilisateur de test si il n'existe pas
    $testUser = $pdo->prepare("SELECT id FROM clients_particuliers WHERE email = ?");
    $testUser->execute(['test@suzosky.com']);
    
    if (!$testUser->fetch()) {
        echo "⚠️ Utilisateur de test n'existe pas. Création...\n";
        
        $hashedPassword = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO clients_particuliers (nom, prenoms, email, telephone, password, statut) 
            VALUES (?, ?, ?, ?, ?, 'actif')
        ");
        $stmt->execute(['Test', 'Utilisateur', 'test@suzosky.com', '+225 01 23 45 67 89', $hashedPassword]);
        
        echo "✅ Utilisateur de test créé:\n";
        echo "   Email: test@suzosky.com\n";
        echo "   Téléphone: +225 01 23 45 67 89\n";
        echo "   Mot de passe: test123\n";
    } else {
        echo "✅ Utilisateur de test existe déjà\n";
    }
    
    // Afficher quelques statistiques
    $count = $pdo->query("SELECT COUNT(*) FROM clients_particuliers")->fetchColumn();
    echo "\n📊 Statistiques:\n";
    echo "   Total clients: $count\n";
    
    echo "\n🎯 Test terminé avec succès !\n";
    echo "🔗 Vous pouvez maintenant tester la connexion avec:\n";
    echo "   - Email: test@suzosky.com\n";
    echo "   - Mot de passe: test123\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "📝 Trace: " . $e->getTraceAsString() . "\n";
}
?>
