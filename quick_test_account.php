<?php
// Test ultra simple pour le compte test@test.com
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Rechercher test@test.com
    $stmt = $pdo->prepare("SELECT id, nom, email FROM clients WHERE email = ?");
    $stmt->execute(['test@test.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✅ COMPTE TROUVÉ: test@test.com (ID: {$user['id']})\n";
    } else {
        echo "❌ COMPTE test@test.com NON TROUVÉ\n";
        
        // Chercher des comptes similaires
        $stmt = $pdo->prepare("SELECT email FROM clients WHERE email LIKE '%test%' LIMIT 3");
        $stmt->execute();
        $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($similar) {
            echo "📧 Comptes similaires: " . implode(', ', $similar) . "\n";
        } else {
            echo "📧 Aucun compte avec 'test' trouvé\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>