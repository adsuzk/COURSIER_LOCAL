<?php
// create_test_orders.php - Créer des commandes de test pour l'historique
require_once 'config.php';

try {
    $pdo = getDBConnection();
    echo "✅ Connexion à la base de données réussie\n";
    
    // Vérifier si la table commandes existe
    $tables = $pdo->query("SHOW TABLES LIKE 'commandes'")->fetchAll();
    
    if (empty($tables)) {
        echo "⚠️ Table commandes n'existe pas. Création...\n";
        
        // Créer la table commandes
        $sql = "
        CREATE TABLE commandes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero_commande VARCHAR(50) UNIQUE NOT NULL,
            client_id INT NOT NULL,
            adresse_depart VARCHAR(500) NOT NULL,
            adresse_arrivee VARCHAR(500) NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            statut ENUM('en_attente', 'en_cours', 'livree', 'annulee') DEFAULT 'en_attente',
            date_commande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_livraison TIMESTAMP NULL,
            notes TEXT,
            INDEX idx_client_id (client_id),
            INDEX idx_numero_commande (numero_commande),
            INDEX idx_statut (statut),
            FOREIGN KEY (client_id) REFERENCES clients_particuliers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($sql);
        echo "✅ Table commandes créée avec succès\n";
    } else {
        echo "✅ Table commandes existe déjà\n";
    }
    
    // Trouver l'utilisateur test
    $testUser = $pdo->prepare("SELECT id FROM clients_particuliers WHERE email = ?");
    $testUser->execute(['test@suzosky.com']);
    $user = $testUser->fetch();
    
    if (!$user) {
        echo "❌ Utilisateur test non trouvé. Veuillez d'abord exécuter test_auth.php\n";
        exit;
    }
    
    $clientId = $user['id'];
    echo "✅ Utilisateur test trouvé (ID: $clientId)\n";
    
    // Supprimer les anciennes commandes de test
    $pdo->prepare("DELETE FROM commandes WHERE client_id = ?")->execute([$clientId]);
    echo "🗑️ Anciennes commandes supprimées\n";
    
    // Créer de nouvelles commandes de test avec plus de variété
    $testOrders = [
        ['CMD2024001', 'Cocody Angré 7ème tranche, Abidjan', 'Plateau Centre commercial, Abidjan', 2500, 'livree', -5],
        ['CMD2024002', 'Yopougon Selmer, Abidjan', 'Adjamé Bracodi marché, Abidjan', 1800, 'livree', -3],
        ['CMD2024003', 'Marcory Zone 4, Abidjan', 'Treichville Arras boulevard, Abidjan', 3200, 'livree', -7],
        ['CMD2024004', 'Koumassi Grand marché, Abidjan', 'Port-Bouët Vridi canal, Abidjan', 2800, 'en_cours', 0],
        ['CMD2024005', 'Bingerville Centre-ville', 'Cocody Riviera Golf, Abidjan', 4500, 'nouvelle', 0],
        ['CMD2024006', 'Abobo PK18 carrefour', 'Yopougon Gesco, Abidjan', 2200, 'livree', -1],
        ['CMD2024007', 'Attécoubé Santé publique', 'Plateau Immeuble SCIAM, Abidjan', 3800, 'livree', -2],
        ['CMD2024008', 'Cocody Danga Nord', 'Marcory Biafra marché, Abidjan', 2900, 'annulee', -4]
    ];
    
    echo "📦 Création de " . count($testOrders) . " commandes de test...\n";
    
    foreach ($testOrders as $index => $order) {
        $stmt = $pdo->prepare("
            INSERT INTO commandes (numero_commande, client_id, adresse_depart, adresse_arrivee, prix_estime, statut, date_creation, telephone_expediteur, telephone_destinataire) 
            VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY), '+225 01 23 45 67 89', '+225 07 07 07 07 07')
        ");
        
        $stmt->execute([
            $order[0],          // numero_commande
            $clientId,          // client_id
            $order[1],          // adresse_depart
            $order[2],          // adresse_arrivee
            $order[3],          // prix_estime
            $order[4],          // statut
            abs($order[5])      // jours en arrière
        ]);
        
        echo "   ✅ " . $order[0] . " - " . $order[3] . " FCFA (" . $order[4] . ")\n";
    }
    
    // Afficher les statistiques
    $stats = $pdo->prepare("
        SELECT 
            statut,
            COUNT(*) as nombre,
            SUM(prix_estime) as total_montant
        FROM commandes 
        WHERE client_id = ? 
        GROUP BY statut
    ");
    $stats->execute([$clientId]);
    
    echo "\n📊 Statistiques des commandes créées:\n";
    $totalCommandes = 0;
    $totalMontant = 0;
    
    while ($stat = $stats->fetch()) {
        $totalCommandes += $stat['nombre'];
        $totalMontant += $stat['total_montant'];
        echo "   " . ucfirst($stat['statut']) . ": " . $stat['nombre'] . " commande(s) - " . number_format($stat['total_montant'], 0, ',', ' ') . " FCFA\n";
    }
    
    echo "\n🎯 Total: $totalCommandes commandes pour " . number_format($totalMontant, 0, ',', ' ') . " FCFA\n";
    echo "\n✅ Commandes de test créées avec succès !\n";
    echo "🔗 Vous pouvez maintenant tester l'historique dans le modal Mon Compte\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    echo "📝 Trace: " . $e->getTraceAsString() . "\n";
}
?>
