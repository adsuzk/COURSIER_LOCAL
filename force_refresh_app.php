<?php
// Force refresh des données de l'app en envoyant une notification FCM
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use Kreait\Firebase\Factory;

try {
    $pdo = getDBConnection();
    
    // Récupérer le token FCM du coursier #5
    $stmt = $pdo->prepare("SELECT fcm_token FROM fcm_tokens WHERE coursier_id = 5 AND is_active = 1 ORDER BY last_ping DESC LIMIT 1");
    $stmt->execute();
    $token = $stmt->fetchColumn();
    
    if (!$token) {
        die("❌ Aucun token FCM trouvé pour le coursier #5\n");
    }
    
    echo "📱 Token FCM: " . substr($token, 0, 50) . "...\n";
    
    // Initialiser Firebase
    $serviceAccountPath = __DIR__ . '/coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json';
    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $messaging = $factory->createMessaging();
    
    // Créer la notification
    $message = [
        'token' => $token,
        'data' => [
            'type' => 'refresh_data',
            'action' => 'reload_orders',
            'timestamp' => time()
        ],
        'notification' => [
            'title' => '🔄 Mise à jour',
            'body' => 'Rechargement des données...'
        ],
        'android' => [
            'priority' => 'high'
        ]
    ];
    
    $result = $messaging->send($message);
    echo "✅ Notification envoyée avec succès!\n";
    echo "Message ID: $result\n";
    
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
