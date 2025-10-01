<?php
// Test de commande complète end-to-end
require_once 'config.php';

// Simuler une session client
session_start();
$_SESSION['client_id'] = 999;
$_SESSION['client_email'] = 'test@client.com';
$_SESSION['client_nom'] = 'Test Client';
$_SESSION['client_telephone'] = '+225 07 08 09 10 11';

echo "=== TEST COMMANDE COMPLÈTE ===\n\n";

// 1. Vérifier l'état des coursiers
echo "1. ÉTAT DES COURSIERS\n";
$availabilityUrl = 'http://localhost/COURSIER_LOCAL/api/get_coursier_availability.php';
$availabilityResponse = file_get_contents($availabilityUrl);
$availability = json_decode($availabilityResponse, true);
echo "Disponibilité : " . ($availability['available'] ? 'OUI' : 'NON') . "\n";
echo "Coursiers actifs : " . $availability['active_count'] . "\n";
echo "Message : " . $availability['message'] . "\n\n";

if (!$availability['available']) {
    echo "❌ ARRÊT : Aucun coursier disponible pour le test\n";
    exit(1);
}

// 2. Créer une commande de test
echo "2. CRÉATION COMMANDE TEST\n";
$commandeData = [
    'type_service' => 'course',
    'lieu_depart' => 'Test Départ - Cocody',
    'lieu_destination' => 'Test Destination - Plateau',
    'nom_destinataire' => 'Test Destinataire',
    'telephone_destinataire' => '+225 01 02 03 04 05',
    'description' => 'Test de commande automatique - NE PAS LIVRER',
    'methode_paiement' => 'especes'
];

$submitUrl = 'http://localhost/COURSIER_LOCAL/api/submit_order.php';
$postData = http_build_query($commandeData);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                   "Content-Length: " . strlen($postData) . "\r\n",
        'content' => $postData
    ]
]);

$submitResponse = file_get_contents($submitUrl, false, $context);
$submitResult = json_decode($submitResponse, true);

echo "Réponse submit_order :\n";
print_r($submitResult);
echo "\n";

if (!$submitResult || !$submitResult['success']) {
    echo "❌ ERREUR : Échec création commande\n";
    if (isset($submitResult['error'])) {
        echo "Erreur : " . $submitResult['error'] . "\n";
    }
    exit(1);
}

$orderId = $submitResult['order_id'] ?? null;
if (!$orderId) {
    echo "❌ ERREUR : Pas d'ID de commande retourné\n";
    exit(1);
}

echo "✅ Commande créée avec ID : $orderId\n\n";

// 3. Vérifier l'état de la commande dans la BD
echo "3. VÉRIFICATION BASE DE DONNÉES\n";
try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
    $stmt->execute([$orderId]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        echo "❌ ERREUR : Commande non trouvée en BD\n";
        exit(1);
    }
    
    echo "Statut commande : " . ($commande['statut'] ?? 'N/A') . "\n";
    echo "Coursier assigné : " . ($commande['coursier_id'] ?? 'N/A') . "\n";
    echo "Date création : " . ($commande['date_commande'] ?? 'N/A') . "\n";
    echo "Lieu départ : " . ($commande['lieu_depart'] ?? 'N/A') . "\n";
    echo "Lieu destination : " . ($commande['lieu_destination'] ?? 'N/A') . "\n";
    echo "Téléphone destinataire : " . ($commande['telephone_destinataire'] ?? 'N/A') . "\n\n";
    
    // 4. Vérifier les tokens FCM des coursiers
    echo "4. TOKENS FCM DISPONIBLES\n";
    $stmt = $pdo->prepare("
        SELECT dt.coursier_id, dt.token, dt.is_active, dt.last_ping, dt.updated_at,
               a.nom, a.prenoms, a.statut_connexion
        FROM device_tokens dt
        LEFT JOIN agents_suzosky a ON dt.coursier_id = a.id
        WHERE dt.is_active = 1
        ORDER BY dt.last_ping DESC
    ");
    $stmt->execute();
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Tokens actifs trouvés : " . count($tokens) . "\n";
    foreach ($tokens as $token) {
        echo "- Coursier #{$token['coursier_id']} ({$token['nom']} {$token['prenoms']}) - Statut: {$token['statut_connexion']}\n";
        echo "  Token: " . substr($token['token'], 0, 30) . "...\n";
        echo "  Last ping: " . ($token['last_ping'] ?? 'N/A') . "\n";
        echo "  Updated: " . ($token['updated_at'] ?? 'N/A') . "\n\n";
    }
    
    // 5. Test d'envoi de notification FCM manuel
    echo "5. TEST NOTIFICATION FCM\n";
    if (count($tokens) > 0) {
        $targetToken = $tokens[0]['token'];
        $coursierId = $tokens[0]['coursier_id'];
        
        echo "Envoi notification test vers coursier #{$coursierId}...\n";
        
        // Inclure le helper FCM
        require_once 'lib/fcm_helper.php';
        
        $fcmHelper = new FCMHelper();
        $notificationData = [
            'title' => 'Nouvelle commande de test',
            'body' => 'Commande #' . $orderId . ' - Test automatique',
            'type' => 'new_order',
            'order_id' => (string)$orderId,
            'order_data' => json_encode($commande)
        ];
        
        $result = $fcmHelper->sendNotification($targetToken, $notificationData);
        
        echo "Résultat notification FCM :\n";
        print_r($result);
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR BD : " . $e->getMessage() . "\n";
}

echo "=== FIN TEST ===\n";
?>