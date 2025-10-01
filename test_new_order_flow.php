<?php
/**
 * SCRIPT DE TEST - SIMULATION NOUVELLE COMMANDE
 * Teste le nouveau flux: Notification → Diagnostic → Attribution
 */

require_once 'config.php';
require_once 'logger.php';

echo "=== TEST FLUX NOUVELLE COMMANDE ===\n\n";

$pdo = getDBConnection();

// Données de test
$testData = [
    'adresse_depart' => 'Champroux Stadium, Abidjan',
    'adresse_arrivee' => 'Sipim Atlantide PORT-BOUËT, Abidjan',
    'telephone_expediteur' => '0709876543',
    'telephone_destinataire' => '0798765432',
    'description_colis' => 'Test notification système',
    'priorite' => 'normale',
    'mode_paiement' => 'especes',
    'prix_estime' => 3500,
    'departure_lat' => 5.3599517,
    'departure_lng' => -4.0082563,
    'distance_estimee' => 8.5
];

echo "📋 DONNÉES DE TEST:\n";
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Simulation de l'API submit_order
echo "🔄 Simulation de soumission de commande...\n\n";

// Étape 1: Créer la commande
try {
    $fields = [
        'adresse_depart' => $testData['adresse_depart'],
        'adresse_arrivee' => $testData['adresse_arrivee'],
        'telephone_expediteur' => $testData['telephone_expediteur'],
        'telephone_destinataire' => $testData['telephone_destinataire'],
        'description_colis' => $testData['description_colis'],
        'priorite' => $testData['priorite'],
        'mode_paiement' => $testData['mode_paiement'],
        'prix_estime' => $testData['prix_estime'],
        'latitude_depart' => $testData['departure_lat'],
        'longitude_depart' => $testData['departure_lng'],
        'distance_estimee' => $testData['distance_estimee'],
        'dimensions' => null,
        'poids_estime' => null,
        'fragile' => 0,
        'created_at' => date('Y-m-d H:i:s'),
        'code_commande' => 'TEST' . date('ymdHis'),
        'order_number' => 'TESTSZK' . date('ymdHis'),
        'statut' => 'nouvelle'
    ];
    
    $sql = "INSERT INTO commandes (order_number, code_commande, adresse_depart, adresse_arrivee, telephone_expediteur, telephone_destinataire, description_colis, priorite, mode_paiement, prix_estime, latitude_depart, longitude_depart, distance_estimee, dimensions, poids_estime, fragile, statut, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $fields['order_number'],
        $fields['code_commande'],
        $fields['adresse_depart'],
        $fields['adresse_arrivee'],
        $fields['telephone_expediteur'],
        $fields['telephone_destinataire'],
        $fields['description_colis'],
        $fields['priorite'],
        $fields['mode_paiement'],
        $fields['prix_estime'],
        $fields['latitude_depart'],
        $fields['longitude_depart'],
        $fields['distance_estimee'],
        $fields['dimensions'],
        $fields['poids_estime'],
        $fields['fragile'],
        $fields['statut'],
        $fields['created_at']
    ]);
    $commande_id = $pdo->lastInsertId();
    
    echo "✅ Commande créée: #{$commande_id} - {$fields['code_commande']}\n\n";
    
    // Étape 2: Rechercher coursiers avec FCM
    echo "🔍 ÉTAPE 1: Recherche coursiers avec tokens FCM...\n";
    $stmtCoursiers = $pdo->query("
        SELECT a.id, a.nom, a.prenoms, a.matricule, a.statut_connexion,
               dt.token, a.last_login_at
        FROM agents_suzosky a
        INNER JOIN device_tokens dt ON dt.coursier_id = a.id AND dt.is_active = 1
        WHERE a.status = 'actif'
        ORDER BY 
            CASE WHEN a.statut_connexion = 'en_ligne' THEN 1 ELSE 2 END,
            a.last_login_at DESC
        LIMIT 5
    ");
    $coursiersDisponibles = $stmtCoursiers->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Coursiers trouvés: " . count($coursiersDisponibles) . "\n";
    foreach ($coursiersDisponibles as $idx => $c) {
        echo "  " . ($idx + 1) . ". {$c['nom']} {$c['prenoms']} (#{$c['id']}) - {$c['statut_connexion']}\n";
    }
    echo "\n";
    
    // Étape 3: Envoyer notifications
    if (count($coursiersDisponibles) > 0) {
        echo "📲 ÉTAPE 2: Envoi notifications FCM...\n";
        
        require_once 'fcm_manager.php';
        $fcm = new FCMManager();
        
        $title = "🚚 Nouvelle commande #{$fields['code_commande']}";
        $body = "De: {$fields['adresse_depart']}\nVers: {$fields['adresse_arrivee']}\nPrix: {$fields['prix_estime']} FCFA";
        
        $notifData = [
            'type' => 'new_order',
            'commande_id' => (string)$commande_id,
            'code_commande' => $fields['code_commande'],
            'adresse_depart' => $fields['adresse_depart'],
            'adresse_arrivee' => $fields['adresse_arrivee'],
            'prix_estime' => (string)$fields['prix_estime'],
            'priorite' => $fields['priorite'],
            'action' => 'open_order_details'
        ];
        
        $notificationsSent = [];
        foreach ($coursiersDisponibles as $coursier) {
            $fcmResult = $fcm->envoyerNotification($coursier['token'], $title, $body, $notifData);
            
            if ($fcmResult['success']) {
                echo "  ✅ Notification envoyée à {$coursier['nom']} {$coursier['prenoms']}\n";
                $notificationsSent[] = $coursier;
            } else {
                echo "  ❌ Échec pour {$coursier['nom']}: {$fcmResult['message']}\n";
            }
        }
        echo "\n";
        
        // Étape 4: Attribution
        if (count($notificationsSent) > 0) {
            echo "📦 ÉTAPE 4: Attribution de la commande...\n";
            $coursierAttribue = $coursiersDisponibles[0];
            
            $stmtAssign = $pdo->prepare("UPDATE commandes SET coursier_id = ?, statut = 'nouvelle', updated_at = NOW() WHERE id = ?");
            $stmtAssign->execute([$coursierAttribue['id'], $commande_id]);
            
            echo "  ✅ Commande attribuée à: {$coursierAttribue['nom']} {$coursierAttribue['prenoms']} (#{$coursierAttribue['id']})\n";
            echo "  📊 Notifications envoyées: " . count($notificationsSent) . "\n\n";
        } else {
            echo "  ⚠️ Aucune notification réussie, commande non attribuée\n\n";
        }
        
        // Étape 5: Diagnostic
        echo "📊 ÉTAPE 3: DIAGNOSTIC\n";
        $diagnostic = [
            'coursiers_disponibles' => count($coursiersDisponibles),
            'notifications_envoyees' => count($notificationsSent),
            'commande_attribuee' => count($notificationsSent) > 0,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
    } else {
        echo "⚠️ Aucun coursier avec token FCM trouvé\n\n";
    }
    
    echo "✅ TEST TERMINÉ\n";
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
