<?php
// Test simple existence du compte test@test.com
require_once 'config.php';

echo "=== VÉRIFICATION COMPTE test@test.com ===\n";

try {
    $pdo = getDBConnection();
    echo "✅ Connexion DB réussie\n";
    
    // Vérifier structure table clients
    $result = $pdo->query("SHOW COLUMNS FROM clients");
    $columns = $result->fetchAll(PDO::FETCH_COLUMN);
    echo "📋 Colonnes disponibles: " . implode(', ', $columns) . "\n";
    
    // Rechercher le compte test@test.com
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute(['test@test.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ COMPTE TROUVÉ: test@test.com\n";
        echo "   ID: " . $user['id'] . "\n";
        if (isset($user['nom'])) echo "   Nom: " . $user['nom'] . "\n";
        if (isset($user['password'])) {
            echo "   Password stocké: " . substr($user['password'], 0, 20) . "...\n";
            echo "   Longueur: " . strlen($user['password']) . " caractères\n";
        }
        if (isset($user['statut'])) echo "   Statut: " . $user['statut'] . "\n";
        if (isset($user['date_creation'])) echo "   Date création: " . $user['date_creation'] . "\n";
    } else {
        echo "❌ COMPTE NON TROUVÉ: test@test.com\n";
        
        // Vérifier s'il y a des comptes similaires
        $stmt = $pdo->prepare("SELECT email FROM clients WHERE email LIKE '%test%' LIMIT 5");
        $stmt->execute();
        $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($similar) {
            echo "📧 Comptes similaires trouvés:\n";
            foreach ($similar as $email) {
                echo "   - $email\n";
            }
        }
        
        // Compter total des clients
        $count = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        echo "👥 Total clients dans la base: $count\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}