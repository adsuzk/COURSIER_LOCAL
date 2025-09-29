<?php
/**
 * TEST FCM DIRECT SENDER
 * Teste l'envoi direct de notifications FCM avec le vrai token
 */

require_once 'config.php';

// Headers API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $pdo = getDBConnection();
    $action = $_REQUEST['action'] ?? '';
    
    switch ($action) {
        
        case 'test_direct_fcm':
            // Test d'envoi FCM direct
            $coursierId = intval($_REQUEST['coursier_id'] ?? 5);
            $message = $_REQUEST['message'] ?? '🧪 Test notification FCM';
            
            // Récupérer le token réel
            $stmt = $pdo->prepare("
                SELECT token FROM device_tokens 
                WHERE (coursier_id = ? OR agent_id = ?) AND is_active = 1 
                ORDER BY updated_at DESC LIMIT 1
            ");
            $stmt->execute([$coursierId, $coursierId]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                echo json_encode([
                    'success' => false,
                    'message' => "Aucun token FCM actif trouvé pour coursier $coursierId"
                ]);
                exit;
            }
            
            $token = $tokenData['token'];
            
            // Envoyer notification FCM avec la nouvelle API
            $fcmResult = sendFCMNotificationV1($token, $message, [
                'type' => 'test_direct',
                'coursier_id' => $coursierId,
                'timestamp' => time()
            ]);
            
            // Logger dans la base
            $stmt = $pdo->prepare("
                INSERT INTO notifications_log_fcm 
                (coursier_id, token_used, message, type, status, response_data, created_at)
                VALUES (?, ?, ?, 'test_direct', ?, ?, NOW())
            ");
            
            $status = $fcmResult['success'] ? 'sent' : 'failed';
            $responseData = json_encode($fcmResult);
            
            $stmt->execute([$coursierId, $token, $message, $status, $responseData]);
            
            echo json_encode([
                'success' => $fcmResult['success'],
                'message' => $fcmResult['success'] ? 'Notification envoyée avec succès' : $fcmResult['error'],
                'fcm_status' => $status,
                'fcm_response' => $fcmResult,
                'token_used' => substr($token, 0, 20) . '...',
                'log_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'create_test_order':
            // Créer une commande test et envoyer notification
            $coursierId = intval($_REQUEST['coursier_id'] ?? 5);
            
            // Créer commande
            $orderCode = 'TEST_' . date('YmdHis');
            $stmt = $pdo->prepare("
                INSERT INTO commandes 
                (code_commande, client_nom, client_telephone, adresse_depart, adresse_arrivee, 
                 description, prix_total, statut, coursier_id, created_at)
                VALUES (?, 'Client Test', '0123456789', 'Test Départ', 'Test Arrivée', 
                        'Commande de test FCM', 25.00, 'attribuee', ?, NOW())
            ");
            $stmt->execute([$orderCode, $coursierId]);
            $orderId = $pdo->lastInsertId();
            
            // Envoyer notification
            $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE (coursier_id = ? OR agent_id = ?) AND is_active = 1 ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([$coursierId, $coursierId]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $notificationSent = false;
            if ($tokenData) {
                $notifResult = sendFCMNotificationV1(
                    $tokenData['token'], 
                    "📦 Nouvelle commande: $orderCode", 
                    [
                        'type' => 'new_order',
                        'order_id' => $orderId,
                        'order_code' => $orderCode,
                        'coursier_id' => $coursierId
                    ]
                );
                
                $notificationSent = $notifResult['success'];
                
                // Logger
                $stmt = $pdo->prepare("INSERT INTO notifications_log_fcm (coursier_id, token_used, message, type, status, response_data, created_at) VALUES (?, ?, ?, 'new_order', ?, ?, NOW())");
                $stmt->execute([$coursierId, $tokenData['token'], "📦 Nouvelle commande: $orderCode", $notificationSent ? 'sent' : 'failed', json_encode($notifResult)]);
            }
            
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'order_code' => $orderCode,
                'notification_sent' => $notificationSent,
                'message' => "Commande $orderCode créée" . ($notificationSent ? ' et notification envoyée' : ' mais notification échouée')
            ]);
            break;
            
        case 'get_fcm_logs':
            // Récupérer logs FCM récents
            $stmt = $pdo->prepare("
                SELECT id, coursier_id, LEFT(token_used, 20) as token_preview, message, type, status, created_at
                FROM notifications_log_fcm 
                ORDER BY created_at DESC LIMIT 10
            ");
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'logs' => $logs,
                'count' => count($logs)
            ]);
            break;
            
        case 'get_recent_orders':
            // Récupérer commandes récentes
            $stmt = $pdo->prepare("
                SELECT id, code_commande, coursier_id, statut, created_at
                FROM commandes 
                ORDER BY created_at DESC LIMIT 5
            ");
            $stmt->execute();
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'orders' => $orders
            ]);
            break;
            
        case 'test_firebase_config':
            // Tester configuration Firebase
            $serviceAccountFile = 'coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json';
            $projectId = 'coursier-suzosky';
            
            $serviceAccountExists = file_exists($serviceAccountFile);
            $config = null;
            
            if ($serviceAccountExists) {
                $config = json_decode(file_get_contents($serviceAccountFile), true);
                $projectId = $config['project_id'] ?? $projectId;
            }
            
            echo json_encode([
                'success' => $serviceAccountExists,
                'service_account' => $serviceAccountExists,
                'project_id' => $projectId,
                'message' => $serviceAccountExists ? 'Configuration Firebase OK' : 'Service account manquant',
                'config_file' => $serviceAccountFile
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue',
                'available_actions' => ['test_direct_fcm', 'create_test_order', 'get_fcm_logs', 'get_recent_orders', 'test_firebase_config']
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}

/**
 * Envoyer notification FCM avec la nouvelle API v1
 */
function sendFCMNotificationV1($token, $message, $data = []) {
    try {
        // Charger service account
        $serviceAccountFile = 'coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json';
        if (!file_exists($serviceAccountFile)) {
            return ['success' => false, 'error' => 'Service account Firebase manquant'];
        }
        
        $serviceAccount = json_decode(file_get_contents($serviceAccountFile), true);
        $projectId = $serviceAccount['project_id'];
        
        // Obtenir access token OAuth2
        $accessToken = getOAuth2AccessToken($serviceAccount);
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Impossible d\'obtenir access token'];
        }
        
        // Construire payload FCM v1
        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => '🚚 Suzosky Coursier',
                    'body' => $message
                ],
                'data' => array_merge($data, [
                    'timestamp' => (string)time(),
                    'sound' => 'suzosky_notification.mp3'
                ]),
                'android' => [
                    'notification' => [
                        'channel_id' => 'commandes_channel',
                        'sound' => 'suzosky_notification.mp3',
                        'priority' => 'high'
                    ]
                ]
            ]
        ];
        
        // Envoyer via API v1
        $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true, 'response' => json_decode($response, true)];
        } else {
            return ['success' => false, 'error' => "HTTP $httpCode: $response"];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Obtenir access token OAuth2 pour Firebase
 */
function getOAuth2AccessToken($serviceAccount) {
    try {
        $now = time();
        $expiry = $now + 3600; // 1 heure
        
        // JWT Header
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        
        // JWT Payload
        $payload = json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $expiry
        ]);
        
        // Encoder en base64url
        $headerEncoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        
        // Créer signature
        $signature = '';
        $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
        openssl_sign($headerEncoded . '.' . $payloadEncoded, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        
        // JWT complet
        $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
        
        // Échanger JWT contre access token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://oauth2.googleapis.com/token',
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
        
    } catch (Exception $e) {
        error_log('OAuth2 Error: ' . $e->getMessage());
        return null;
    }
}
?>