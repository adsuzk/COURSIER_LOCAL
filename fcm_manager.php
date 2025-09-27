<?php
/**
 * SYSTÈME FCM FIREBASE RÉEL
 * Configuration et envoi de notifications Firebase Cloud Messaging
 */

require_once 'config.php';

class FCMManager {
    
    private $serverKey;
    private $projectId;
    
    public function __construct() {
        // Configuration Firebase - À REMPLACER par vos vraies clés
        $this->serverKey = $this->getServerKey();
        $this->projectId = 'coursier-suzosky'; // Votre project ID Firebase
    }
    
    public function getServerKey() {
        // Essayer de lire depuis le fichier de config Firebase
        $firebaseConfig = 'coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json';
        
        if (file_exists($firebaseConfig)) {
            $config = json_decode(file_get_contents($firebaseConfig), true);
            if (isset($config['project_id'])) {
                $this->projectId = $config['project_id'];
            }
        }
        
        // Pour les tests, utiliser une clé de développement
        // REMPLACER PAR VOTRE VRAIE SERVER KEY FIREBASE
        return 'AAAA1234567890:APA91bHsampleKeyForTesting1234567890abcdefghijklmnopqrstuvwxyz';
    }
    
    public function envoyerNotification($token, $title, $body, $data = []) {
        $url = 'https://fcm.googleapis.com/fcm/send';
        
        // Construction du payload
        $notification = [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => 1,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'icon' => 'ic_notification',
            'color' => '#D4A853' // Couleur Suzosky
        ];
        
        $payload = [
            'to' => $token,
            'notification' => $notification,
            'data' => array_merge($data, [
                'timestamp' => time(),
                'server' => 'suzosky_prod'
            ]),
            'priority' => 'high',
            'android' => [
                'notification' => [
                    'channel_id' => 'commandes_channel',
                    'sound' => 'default',
                    'priority' => 'high',
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
                    'default_light_settings' => true
                ]
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $body
                        ],
                        'sound' => 'default',
                        'badge' => 1
                    ]
                ]
            ]
        ];
        
        // Headers
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];
        
        // Envoi via cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $result = [
            'success' => false,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
            'payload' => $payload
        ];
        
        if ($error) {
            $result['message'] = 'Erreur cURL: ' . $error;
        } elseif ($httpCode !== 200) {
            $result['message'] = 'Erreur HTTP: ' . $httpCode;
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['success']) && $responseData['success'] > 0) {
                $result['success'] = true;
                $result['message'] = 'Notification envoyée';
                $result['message_id'] = $responseData['results'][0]['message_id'] ?? null;
            } else {
                $result['message'] = 'Échec FCM: ' . ($responseData['results'][0]['error'] ?? 'Erreur inconnue');
            }
        }
        
        return $result;
    }
    
    public function envoyerNotificationCommande($coursierId, $commande) {
        global $pdo;
        
        // Récupérer token FCM actif
        $stmt = $pdo->prepare("
            SELECT token FROM device_tokens 
            WHERE coursier_id = ? AND is_active = 1 
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([$coursierId]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Aucun token FCM actif'];
        }
        
        $title = '🚚 Nouvelle Commande!';
        $body = "Commande #{$commande['id']} - {$commande['prix_total']} FCFA\n" .
                "De: {$commande['adresse_depart']}\n" .
                "Vers: {$commande['adresse_arrivee']}";
        
        $data = [
            'type' => 'nouvelle_commande',
            'commande_id' => (string)$commande['id'],
            'action' => 'accept_refuse',
            'prix' => (string)$commande['prix_total'],
            'adresse_depart' => $commande['adresse_depart'],
            'adresse_arrivee' => $commande['adresse_arrivee']
        ];
        
        $result = $this->envoyerNotification($tokenData['token'], $title, $body, $data);
        
        // Enregistrer dans les logs
        $stmt = $pdo->prepare("
            INSERT INTO notifications_log_fcm 
            (coursier_id, commande_id, token_used, message, type, status, response_data, created_at)
            VALUES (?, ?, ?, ?, 'nouvelle_commande', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $coursierId,
            $commande['id'],
            $tokenData['token'],
            $body,
            $result['success'] ? 'sent' : 'failed',
            json_encode($result)
        ]);
        
        return $result;
    }
}

// Test du système FCM
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    
    echo "🔥 TEST SYSTÈME FCM FIREBASE\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    try {
        $fcm = new FCMManager();
        $pdo = getDBConnection();
        
        // Test 1: Notification simple
        echo "\n📱 1. TEST NOTIFICATION SIMPLE\n";
        
        // Récupérer token de test
        $stmt = $pdo->prepare("
            SELECT token FROM device_tokens 
            WHERE coursier_id = 3 AND is_active = 1 
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute();
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            $result = $fcm->envoyerNotification(
                $tokenData['token'],
                '🧪 Test Firebase',
                'Ceci est un test de notification Firebase Cloud Messaging',
                ['test' => 'true', 'timestamp' => time()]
            );
            
            echo "   Résultat: " . ($result['success'] ? '✅ Succès' : '❌ Échec') . "\n";
            echo "   Message: " . $result['message'] . "\n";
            echo "   HTTP Code: " . $result['http_code'] . "\n";
            
            if (!$result['success']) {
                echo "   Erreur: " . ($result['error'] ?: 'Voir response') . "\n";
                echo "   Response: " . substr($result['response'], 0, 200) . "...\n";
            }
        } else {
            echo "   ❌ Aucun token FCM disponible\n";
        }
        
        // Test 2: Notification de commande
        echo "\n📦 2. TEST NOTIFICATION COMMANDE\n";
        
        // Récupérer dernière commande
        $stmt = $pdo->prepare("
            SELECT id, code_commande, prix_total, adresse_depart, adresse_arrivee
            FROM commandes 
            WHERE coursier_id = 3 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute();
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commande && $tokenData) {
            $result = $fcm->envoyerNotificationCommande(3, $commande);
            
            echo "   Commande: #{$commande['id']} - {$commande['code_commande']}\n";
            echo "   Résultat: " . ($result['success'] ? '✅ Succès' : '❌ Échec') . "\n";
            echo "   Message: " . $result['message'] . "\n";
            
            if (isset($result['message_id'])) {
                echo "   Message ID: {$result['message_id']}\n";
            }
        } else {
            echo "   ⚠️ Aucune commande ou token disponible\n";
        }
        
        // Configuration à vérifier
        echo "\n🔧 3. CONFIGURATION À VÉRIFIER\n";
        echo "   📋 Points importants:\n";
        echo "   • Server Key Firebase: " . (strlen($fcm->getServerKey()) > 50 ? '✅ Configurée' : '❌ À configurer') . "\n";
        echo "   • Project ID: {$fcm->projectId}\n";
        echo "   • Fichier config: " . (file_exists('coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json') ? '✅' : '❌') . "\n";
        
        echo "\n💡 PROCHAINES ÉTAPES:\n";
        echo "   1. Configurer la vraie Server Key Firebase\n";
        echo "   2. Vérifier configuration dans l'app mobile\n";
        echo "   3. Tester depuis l'app mobile avec ADB\n";
        echo "   4. Monitorer les logs FCM en temps réel\n";
        
    } catch (Exception $e) {
        echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    }
}
?>