<?php
// test_password.php - Test rapide de vérification de mot de passe

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $email = 'test@test.com';
    $password = '12345';
    
    // Rechercher l'utilisateur
    $stmt = $pdo->prepare("SELECT id, nom, prenoms, email, telephone, password FROM clients_particuliers WHERE email = ?");
    $stmt->execute([$email]);
    $client = $stmt->fetch();
    
    if (!$client) {
        echo "❌ Utilisateur non trouvé\n";
        exit;
    }
    
    echo "✅ Utilisateur trouvé: " . $client['email'] . "\n";
    echo "🔑 Hash en base: " . substr($client['password'], 0, 30) . "...\n";
    
    // Vérifier le mot de passe
    if (password_verify($password, $client['password'])) {
        echo "✅ Mot de passe correct!\n";
    } else {
        echo "❌ Mot de passe incorrect\n";
        
        // Test manual de hash
        $testHash = password_hash($password, PASSWORD_DEFAULT);
        echo "🧪 Test hash généré: " . substr($testHash, 0, 30) . "...\n";
        
        if (password_verify($password, $testHash)) {
            echo "✅ Test hash fonctionne\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>