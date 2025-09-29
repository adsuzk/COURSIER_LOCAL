<?php
// check_user_account.php - Vérifier l'existence du compte test@test.com
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    // D'abord vérifier la structure de la table
    echo "📋 STRUCTURE TABLE CLIENTS:\n";
    $stmt = $pdo->query("DESCRIBE clients");
    $columns = [];
    while ($col = $stmt->fetch()) {
        $columns[] = $col['Field'];
        echo "- " . $col['Field'] . ": " . $col['Type'] . ($col['Null'] === 'NO' ? ' (OBLIGATOIRE)' : '') . "\n";
    }
    echo "\n";
    
    // Construire la requête avec les colonnes disponibles
    $select_fields = ['id', 'email'];
    $optional_fields = ['nom', 'prenom', 'telephone', 'created_at', 'date_creation', 'password', 'mot_de_passe'];
    
    foreach ($optional_fields as $field) {
        if (in_array($field, $columns)) {
            $select_fields[] = $field;
        }
    }
    
    $sql = "SELECT " . implode(', ', $select_fields) . " FROM clients WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['test@test.com']);
    $user = $stmt->fetch();
    
    header('Content-Type: text/plain; charset=utf-8');
    
    if ($user) {
        echo "✅ COMPTE TROUVÉ\n";
        echo "==================\n";
        foreach ($user as $key => $value) {
            if (!is_numeric($key)) {
                echo ucfirst($key) . ": " . ($value ?: 'Non défini') . "\n";
            }
        }
        
        // Tester la connexion avec un mot de passe courant
        $password_field = isset($user['password']) ? $user['password'] : (isset($user['mot_de_passe']) ? $user['mot_de_passe'] : '');
        if ($password_field) {
            $test_passwords = ['test', 'password', '123456', 'test123', 'admin'];
            echo "\nTest mots de passe courants:\n";
            foreach ($test_passwords as $pass) {
                if (password_verify($pass, $password_field)) {
                    echo "🔑 MOT DE PASSE TROUVÉ: '$pass'\n";
                    break;
                } else {
                    echo "❌ '$pass' - incorrect\n";
                }
            }
        } else {
            echo "\n⚠️ Aucun champ mot de passe trouvé dans la table\n";
        }
    } else {
        echo "❌ COMPTE NON TROUVÉ\n";
        echo "====================\n";
        echo "L'email test@test.com n'existe pas dans la base.\n\n";
        
        // Vérifier s'il y a d'autres comptes
        $stmt = $pdo->query("SELECT COUNT(*) as total, MAX(created_at) as derniere_creation FROM clients");
        $stats = $stmt->fetch();
        echo "Total clients: " . $stats['total'] . "\n";
        echo "Dernier client: " . ($stats['derniere_creation'] ?: 'Aucun') . "\n";
        
        // Montrer quelques comptes existants
        $stmt = $pdo->query("SELECT id, email, nom, created_at FROM clients ORDER BY created_at DESC LIMIT 5");
        echo "\nDerniers comptes créés:\n";
        while ($row = $stmt->fetch()) {
            echo "- ID " . $row['id'] . ": " . $row['email'] . " (" . ($row['nom'] ?: 'Sans nom') . ") - " . ($row['created_at'] ?: 'Date inconnue') . "\n";
        }
    }
    
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8', true, 500);
    echo "❌ ERREUR DB: " . $e->getMessage() . "\n";
    echo "Vérifiez que MySQL fonctionne et que la base coursier_lws_20250928 existe.\n";
}