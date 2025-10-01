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
        'mot_de_passe' => password_hash('test123', PASSWORD_DEFAULT)
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
            INSERT INTO clients (nom, email, telephone, mot_de_passe, date_inscription) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $clientData['nom'],
            $clientData['email'], 
            $clientData['telephone'],
            $clientData['mot_de_passe']
        ]);
        $clientId = $pdo->lastInsertId();
        echo "✅ Nouveau client créé ID: $clientId\n";
    }
    
    echo "\n=== COMMANDE DIRECTE EN BD ===\n";
    
    // Créer une commande directement en BD pour contourner l'API
    $commandeData = [
        'client_id' => $clientId,
        'type_service' => 'course',
        'lieu_depart' => 'Test Départ - Cocody, Abidjan',
        'lieu_destination' => 'Test Destination - Plateau, Abidjan',
        'nom_destinataire' => 'Test Destinataire',
        'telephone_destinataire' => '+225 01 02 03 04 05',
        'description' => 'Test de commande automatique - NE PAS LIVRER',
        'methode_paiement' => 'especes',
        'prix_estimatif' => 2000,
        'date_commande' => date('Y-m-d H:i:s'),
        'statut' => 'en_attente'
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            client_id, type_service, lieu_depart, lieu_destination, 
            nom_destinataire, telephone_destinataire, description, 
            methode_paiement, prix_estimatif, date_commande, statut
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $commandeData['client_id'],
        $commandeData['type_service'],
        $commandeData['lieu_depart'],
        $commandeData['lieu_destination'],
        $commandeData['nom_destinataire'],
        $commandeData['telephone_destinataire'],
        $commandeData['description'],
        $commandeData['methode_paiement'],
        $commandeData['prix_estimatif'],
        $commandeData['date_commande'],
        $commandeData['statut']
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
    require_once 'lib/fcm_helper.php';
    
    $fcmHelper = new FCMHelper();
    $notificationData = [
        'title' => 'Nouvelle commande !',
        'body' => "Commande #{$orderId} - {$commandeData['lieu_depart']} → {$commandeData['lieu_destination']}",
        'type' => 'new_order',
        'order_id' => (string)$orderId,
        'pickup_location' => $commandeData['lieu_depart'],
        'delivery_location' => $commandeData['lieu_destination'],
        'client_name' => 'Client Test',
        'client_phone' => '+225 07 08 09 10 11',
        'recipient_name' => $commandeData['nom_destinataire'],
        'recipient_phone' => $commandeData['telephone_destinataire'],
        'description' => $commandeData['description'],
        'estimated_price' => (string)$commandeData['prix_estimatif']
    ];
    
    echo "Envoi notification vers token: " . substr($coursier['token'], 0, 30) . "...\n";
    $result = $fcmHelper->sendNotification($coursier['token'], $notificationData);
    
    if ($result['success']) {
        echo "✅ Notification envoyée avec succès\n";
        if (isset($result['response'])) {
            echo "Réponse FCM: " . json_encode($result['response']) . "\n";
        }
    } else {
        echo "❌ Échec envoi notification\n";
        echo "Erreur: " . ($result['error'] ?? 'Inconnue') . "\n";
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