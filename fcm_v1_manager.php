<?php
/**
 * GESTIONNAIRE FCM AVEC API HTTP V1 (SERVICE ACCOUNT)
 * Utilise coursier-suzosky-9851d39a25fa.json
 */

class FCMv1Manager {
    private $serviceAccountPath;
    private $projectId;
    private $accessToken;
    private $tokenExpiry;
    
    public function __construct() {
        $this->serviceAccountPath = __DIR__ . '/coursier-suzosky-9851d39a25fa.json';
        
        if (!file_exists($this->serviceAccountPath)) {
            throw new Exception("Fichier de compte de service introuvable: " . $this->serviceAccountPath);
        }
        
        $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
        $this->projectId = $serviceAccount['project_id'];
    }
    
    /**
     * Obtenir un access token OAuth2 depuis le service account
     */
    private function getAccessToken() {
        // Cache le token pendant 50 minutes (expire après 60min)
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }
        
        $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
        
        // Créer le JWT
        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        $payload = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600
        ];
        
        $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));
        
        $signature = '';
        openssl_sign(
            $base64UrlHeader . '.' . $base64UrlPayload,
            $signature,
            $serviceAccount['private_key'],
            OPENSSL_ALGO_SHA256
        );
        
        $base64UrlSignature = $this->base64UrlEncode($signature);
        $jwt = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
        
        // Échanger le JWT contre un access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Échec de l'obtention du token OAuth2: " . $response);
        }
        
        $tokenData = json_decode($response, true);
        $this->accessToken = $tokenData['access_token'];
        $this->tokenExpiry = time() + 3000; // 50 minutes
        
        return $this->accessToken;
    }
    
    /**
     * Encoder en base64 URL-safe
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Envoyer une notification FCM avec l'API HTTP v1
     */
    public function envoyerNotification($deviceToken, $titre, $corps, $donnees = []) {
        try {
            $accessToken = $this->getAccessToken();
            
            $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
            
            $message = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $titre,
                        'body' => $corps
                    ],
                    'data' => array_merge([
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'timestamp' => (string)time()
                    ], array_map('strval', $donnees)),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'nouvelles_commandes',
                            'priority' => 'max'
                        ]
                    ]
                ]
            ];
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($message),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => 'Notification envoyée avec succès (API v1)',
                    'response' => json_decode($response, true)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => "Erreur HTTP $httpCode",
                    'response' => $response,
                    'curl_error' => $curlError
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ];
        }
    }
    
    /**
     * Envoyer une notification à plusieurs appareils
     */
    public function envoyerNotificationMultiple($deviceTokens, $titre, $corps, $donnees = []) {
        $resultats = [];
        
        foreach ($deviceTokens as $token) {
            $resultats[] = [
                'token' => substr($token, 0, 20) . '...',
                'result' => $this->envoyerNotification($token, $titre, $corps, $donnees)
            ];
        }
        
        return $resultats;
    }
    
    /**
     * Test de connexion Firebase
     */
    public function testerConnexion() {
        try {
            $accessToken = $this->getAccessToken();
            
            return [
                'success' => true,
                'message' => '✅ Service Account valide!',
                'project_id' => $this->projectId,
                'token_obtenu' => substr($accessToken, 0, 50) . '...',
                'expiration' => date('Y-m-d H:i:s', $this->tokenExpiry)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '❌ Erreur: ' . $e->getMessage()
            ];
        }
    }
}

// Test si exécuté directement
if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $fcm = new FCMv1Manager();
        
        // Test de connexion
        echo "🔍 Test de connexion Firebase...\n\n";
        $testConnexion = $fcm->testerConnexion();
        print_r($testConnexion);
        
        // Test d'envoi de notification
        require_once 'config.php';
        $pdo = getDBConnection();
        
        $stmt = $pdo->query("
            SELECT dt.token, a.nom 
            FROM device_tokens dt
            JOIN agents_suzosky a ON dt.coursier_id = a.id
            WHERE dt.is_active = 1 
            LIMIT 1
        ");
        
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tokenData) {
            echo "\n\n📱 Test d'envoi de notification...\n\n";
            $result = $fcm->envoyerNotification(
                $tokenData['token'],
                '🎉 FCM v1 Activé!',
                'Le système de notifications moderne est maintenant opérationnel pour ' . $tokenData['nom'],
                [
                    'type' => 'test_fcm_v1',
                    'timestamp' => time()
                ]
            );
            
            print_r($result);
        } else {
            echo "\n\n⚠️ Aucun token de test disponible\n";
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], JSON_PRETTY_PRINT);
    }
}
?>
