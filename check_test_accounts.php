<?php
require_once __DIR__ . '/config.php';

echo "=== Vérification des comptes de test disponibles ===\n\n";

try {
    $pdo = getDBConnection();
    
    // Lister tous les clients disponibles
    $stmt = $pdo->query("SELECT id, nom, prenoms, email, telephone, statut FROM clients_particuliers ORDER BY id");
    $clients = $stmt->fetchAll();
    
    echo "Comptes clients disponibles:\n";
    echo "==========================\n";
    
    foreach ($clients as $client) {
        echo "ID: " . $client['id'] . "\n";
        echo "Nom: " . $client['nom'] . " " . $client['prenoms'] . "\n";
        echo "Email: " . $client['email'] . "\n";
        echo "Téléphone: " . $client['telephone'] . "\n";
        echo "Statut: " . $client['statut'] . "\n";
        echo "--------------------\n";
    }
    
    echo "\n=== Comptes de test recommandés ===\n";
    echo "Pour TEST001 (test123): Ce compte n'existe pas dans la base\n";
    echo "Utilisez plutôt:\n";
    echo "- Email: test@test.com\n";
    echo "- Mot de passe: abcde\n\n";
    
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>