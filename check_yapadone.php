<?php
// Vérification compte yapadone@gmail.com
require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // D'abord vérifier la structure
    $cols = $pdo->query("SHOW COLUMNS FROM clients")->fetchAll(PDO::FETCH_COLUMN);
    echo "Colonnes disponibles: " . implode(', ', $cols) . "\n\n";
    
    // Rechercher yapadone@gmail.com avec toutes les colonnes
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute(['yapadone@gmail.com']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "COMPTE TROUVE: yapadone@gmail.com\n";
        echo "ID: {$user['id']}\n";
        if (isset($user['nom'])) echo "Nom: {$user['nom']}\n";
        if (isset($user['password'])) echo "Password: " . substr($user['password'], 0, 10) . "...\n";
    } else {
        echo "COMPTE yapadone@gmail.com NON TROUVE\n";
        
        // Chercher des comptes similaires
        $stmt = $pdo->prepare("SELECT email FROM clients WHERE email LIKE ? LIMIT 5");
        $stmt->execute(['%yapadone%']);
        $similar = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($similar) {
            echo "Comptes similaires: " . implode(', ', $similar) . "\n";
        } else {
            // Chercher des emails Gmail
            $stmt = $pdo->prepare("SELECT email FROM clients WHERE email LIKE '%gmail%' LIMIT 3");
            $stmt->execute();
            $gmail_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if ($gmail_users) {
                echo "Quelques comptes Gmail existants: " . implode(', ', $gmail_users) . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERREUR: " . $e->getMessage() . "\n";
}
?>