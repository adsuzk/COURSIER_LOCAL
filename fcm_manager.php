<?php
/**
 * SYSTÈME FCM FIREBASE RÉEL
 * Configuration et envoi de notifications Firebase Cloud Messaging
 */

require_once 'config.php';
require_once __DIR__ . '/api/lib/fcm_enhanced.php';

class FCMManager {
    
    private $serverKey;
    private $projectId;
    private $serviceAccountPath;
    
    public function __construct() {
        // Détection projet et secrets
        $this->projectId = 'coursier-suzosky';
        $this->serviceAccountPath = $this->detectServiceAccount();
        $this->serverKey = $this->getServerKey();
    }
    
    public function getServerKey() {
        // Essayer de lire depuis un fichier secret si la variable d'env est absente
        $envKey = getenv('FCM_SERVER_KEY');
        if (!$envKey) {
            $secretPath = __DIR__ . '/data/secret_fcm_key.txt';
            if (is_file($secretPath)) {
                $content = trim(@file_get_contents($secretPath));
                if ($content !== '') {
                    $envKey = $content;
                    // l'injecter pour les autres bibliothèques aussi
                    putenv('FCM_SERVER_KEY=' . $envKey);
                }
            }
        }
        // Confirmer le projectId si un compte de service existe
        if ($this->serviceAccountPath && is_file($this->serviceAccountPath)) {
            $sa = json_decode(@file_get_contents($this->serviceAccountPath), true);
            if (!empty($sa['project_id'])) $this->projectId = $sa['project_id'];
        }
        return $envKey ?: 'LEGACY_KEY_NOT_CONFIGURED';
    }

    private function detectServiceAccount(): ?string {
        // 1) variable d'environnement
        $sa = getenv('FIREBASE_SERVICE_ACCOUNT_FILE');
        if ($sa && is_file($sa)) return realpath($sa);
        // 2) data/firebase_service_account.json
        $candidate = __DIR__ . '/data/firebase_service_account.json';
        if (is_file($candidate)) return realpath($candidate);
        // 3) racine: coursier-suzosky-firebase-adminsdk-*.json
        $glob = glob(__DIR__ . '/coursier-suzosky-firebase-adminsdk-*.json');
        if (!empty($glob)) return realpath($glob[0]);
        return null;
    }
    
    public function envoyerNotification($token, $title, $body, $data = []) {
        // Utiliser l'API moderne HTTP v1 si un compte de service est disponible
        if ($this->serviceAccountPath && is_file($this->serviceAccountPath)) {
            $res = fcm_v1_send([$token], $title, $body, $data, $this->serviceAccountPath);
            $ok = $res['success'] ?? false;
            $code = $res['code'] ?? null;
            $result = [
                'success' => $ok,
                'http_code' => $code,
                'response' => json_encode($res['result'] ?? []),
                'message' => $ok ? 'Notification envoyée (HTTP v1)' : 'Échec FCM HTTP v1'
            ];
            return $result;
        }
        
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
        
        // Si la clé n'est pas configurée, retourner une erreur explicite (ne pas simuler succès)
        if ($this->serverKey === 'LEGACY_KEY_NOT_CONFIGURED') {
            return [
                'success' => false,
                'message' => 'FCM non configuré: aucune clé serveur et aucun compte de service',
                'http_code' => 0,
                'response' => null,
                'payload' => $payload
            ];
        }
        
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

    // Aide: envoi d'une notification de nouvelle commande au dernier token actif du coursier
    public function notifierNouvelleCommande(int $coursierId, int $commandeId) {
        global $pdo;
        if (!isset($pdo)) { $pdo = getDBConnection(); }
        $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE (coursier_id = ? OR agent_id = ?) AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([$coursierId, $coursierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['success' => false, 'message' => 'Aucun token FCM actif'];
        }
        // Récupérer quelques infos de commande si possible
        $title = '🚚 Nouvelle Commande!';
        $body = 'Une course vous a été attribuée';
        try {
            $s = $pdo->prepare("SELECT adresse_depart, adresse_arrivee, prix_total FROM commandes WHERE id = ?");
            $s->execute([$commandeId]);
            if ($cmd = $s->fetch(PDO::FETCH_ASSOC)) {
                $body = "#{$commandeId} - " . ($cmd['prix_total'] ?? '') . " FCFA\n" . ($cmd['adresse_depart'] ?? '') . " → " . ($cmd['adresse_arrivee'] ?? '');
            }
        } catch (Throwable $e) { /* best effort */ }
        return $this->envoyerNotification($row['token'], $title, $body, [
            'type' => 'nouvelle_commande', 'commande_id' => (string)$commandeId
        ]);
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