<?php
// Créer un client de test et tester la commande
require_once 'config.php';

echo "=== CRÉATION CLIENT TEST ===\n\n";

try {
    $pdo = getDBConnection();
    
    // Créer un client de test
    $clientData = [
        'nom' => 'Client Test',
        'email' => 'test.client@suzosky.com',
        'telephone' => '+225 07 08 09 10 11',
        'password_hash' => password_hash('test123', PASSWORD_DEFAULT)
    ];
    
    // Vérifier si le client existe déjà
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? OR telephone = ?");
    $stmt->execute([$clientData['email'], $clientData['telephone']]);
    $existingClient = $stmt->fetch();
    
    if ($existingClient) {
        $clientId = $existingClient['id'];
        echo "✅ Client existant trouvé ID: $clientId\n";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO clients (nom, email, telephone, password_hash, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $clientData['nom'],
            $clientData['email'], 
            $clientData['telephone'],
            $clientData['password_hash']
        ]);
        $clientId = $pdo->lastInsertId();
        echo "✅ Nouveau client créé ID: $clientId\n";
    }
    
    echo "\n=== COMMANDE DIRECTE EN BD ===\n";
    
    // Créer une commande directement en BD pour contourner l'API
    $commandeData = [
        'order_number' => 'TEST-' . date('YmdHis'),
        'code_commande' => 'T' . strtoupper(substr(uniqid(), -6)),
        'client_type' => 'particulier',
        'client_id' => $clientId,
        'client_nom' => 'Client Test',
        'client_telephone' => '+225 07 08 09 10 11',
        'adresse_retrait' => 'Test Départ - Cocody, Abidjan',
        'adresse_livraison' => 'Test Destination - Plateau, Abidjan',
        'adresse_depart' => 'Test Départ - Cocody, Abidjan',
        'adresse_arrivee' => 'Test Destination - Plateau, Abidjan',
        'telephone_expediteur' => '+225 07 08 09 10 11',
        'telephone_destinataire' => '+225 01 02 03 04 05',
        'description_colis' => 'Test de commande automatique - NE PAS LIVRER',
        'mode_paiement' => 'especes',
        'prix_base' => 2000,
        'prix_total' => 2000,
        'prix_estime' => 2000,
        'statut' => 'en_attente',
        'statut_paiement' => 'attente',
        'priorite' => 'normale'
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            order_number, code_commande, client_type, client_id, client_nom, client_telephone,
            adresse_retrait, adresse_livraison, adresse_depart, adresse_arrivee,
            telephone_expediteur, telephone_destinataire, description_colis,
            mode_paiement, prix_base, prix_total, prix_estime, 
            statut, statut_paiement, priorite, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $commandeData['order_number'],
        $commandeData['code_commande'],
        $commandeData['client_type'],
        $commandeData['client_id'],
        $commandeData['client_nom'],
        $commandeData['client_telephone'],
        $commandeData['adresse_retrait'],
        $commandeData['adresse_livraison'],
        $commandeData['adresse_depart'],
        $commandeData['adresse_arrivee'],
        $commandeData['telephone_expediteur'],
        $commandeData['telephone_destinataire'],
        $commandeData['description_colis'],
        $commandeData['mode_paiement'],
        $commandeData['prix_base'],
        $commandeData['prix_total'],
        $commandeData['prix_estime'],
        $commandeData['statut'],
        $commandeData['statut_paiement'],
        $commandeData['priorite']
    ]);
    
    $orderId = $pdo->lastInsertId();
    echo "✅ Commande créée ID: $orderId\n\n";
    
    // Maintenant tester le système d'attribution
    echo "=== TEST ATTRIBUTION AUTOMATIQUE ===\n";
    
    // Vérifier les coursiers disponibles
    $stmt = $pdo->prepare("
        SELECT dt.coursier_id, dt.token, a.nom, a.prenoms 
        FROM device_tokens dt
        LEFT JOIN agents_suzosky a ON dt.coursier_id = a.id
        WHERE dt.is_active = 1 
        ORDER BY dt.last_ping DESC
        LIMIT 1
    ");
    $stmt->execute();
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coursier) {
        echo "❌ Aucun coursier disponible\n";
        exit(1);
    }
    
    echo "Coursier disponible: #{$coursier['coursier_id']} - {$coursier['nom']} {$coursier['prenoms']}\n";
    echo "Token: " . substr($coursier['token'], 0, 30) . "...\n\n";
    
    // Test d'attribution manuelle
    echo "=== ATTRIBUTION MANUELLE ===\n";
    $stmt = $pdo->prepare("UPDATE commandes SET coursier_id = ?, statut = 'attribuee' WHERE id = ?");
    $stmt->execute([$coursier['coursier_id'], $orderId]);
    echo "✅ Commande attribuée au coursier #{$coursier['coursier_id']}\n\n";
    
    // Test de notification FCM
    echo "=== TEST NOTIFICATION FCM ===\n";
    require_once 'api/lib/fcm_enhanced.php';
    
    // Préparer les données de notification
    $notificationData = [
        'type' => 'new_order',
        'order_id' => $orderId,
        'order_number' => $commandeData['order_number'],
        'code_commande' => $commandeData['code_commande'],
        'client_nom' => $commandeData['client_nom'],
        'client_telephone' => $commandeData['client_telephone'],
        'adresse_depart' => $commandeData['adresse_depart'],
        'adresse_arrivee' => $commandeData['adresse_arrivee'],
        'description_colis' => $commandeData['description_colis'],
        'prix_total' => $commandeData['prix_total'],
        'mode_paiement' => $commandeData['mode_paiement']
    ];
    
    $title = "🔔 Nouvelle commande #{$commandeData['code_commande']}";
    $body = "Course de {$commandeData['adresse_depart']} vers {$commandeData['adresse_arrivee']} - {$commandeData['prix_total']} FCFA";
    
    echo "Envoi notification vers token: " . substr($coursier['token'], 0, 30) . "...\n";
    echo "Titre: $title\n";
    echo "Message: $body\n";
    
    // Envoyer la notification
    $result = fcm_send_with_log(
        [$coursier['token']], 
        $title, 
        $body, 
        $notificationData, 
        $coursier['coursier_id'], 
        $orderId
    );
    
    if ($result && is_array($result)) {
        echo "✅ Notification envoyée avec succès\n";
        echo "Réponse FCM: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ Échec envoi notification\n";
        echo "Résultat: " . json_encode($result) . "\n";
    }
    
    echo "\n=== VÉRIFICATION APP ANDROID ===\n";
    echo "👁️ Vérifiez maintenant votre app Android (coursier #{$coursier['coursier_id']}):\n";
    echo "1. Une notification doit apparaître\n";
    echo "2. L'app doit afficher une fenêtre Accepter/Refuser\n";
    echo "3. Les informations de la commande doivent être visibles\n\n";
    
    echo "=== DONNÉES COMMANDE POUR L'APP ===\n";
    echo "ID Commande: $orderId\n";
    echo "Départ: {$commandeData['lieu_depart']}\n";
    echo "Destination: {$commandeData['lieu_destination']}\n";
    echo "Client: Client Test (+225 07 08 09 10 11)\n";
    echo "Destinataire: {$commandeData['nom_destinataire']} ({$commandeData['telephone_destinataire']})\n";
    echo "Prix estimé: {$commandeData['prix_estimatif']} FCFA\n";
    echo "Description: {$commandeData['description']}\n\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== TEST TERMINÉ ===\n";
echo "Maintenant vérifiez votre appareil Android pour voir si la notification est arrivée !\n";
?>