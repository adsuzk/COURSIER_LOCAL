<?php
/**
 * FCM Helper
 * Centralise l'envoi FCM v1 et l'obtention d'access token OAuth2 via service account
 */

function getFirebaseServiceAccountFile()
{
    $candidates = [
        __DIR__ . '/../coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json',
        __DIR__ . '/../coursier-suzosky-a70fc74a6a8a.json',
        __DIR__ . '/coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json'
    ];

    foreach ($candidates as $p) {
        if (file_exists($p)) {
            return $p;
        }
    }

    return null;
}

function getServiceAccountArray()
{
    $file = getFirebaseServiceAccountFile();
    if (!$file) return null;
    $contents = file_get_contents($file);
    if (!$contents) return null;
    return json_decode($contents, true);
}

function getOAuth2AccessTokenFromServiceAccount($serviceAccount)
{
    try {
        $now = time();
        $expiry = $now + 3600; // 1 hour

        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $expiry
        ]);

        $headerEncoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

        $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
        if (!$privateKey) return null;

        $signature = '';
        openssl_sign($headerEncoded . '.' . $payloadEncoded, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;

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

function sendFCMNotificationV1($token, $message, $data = [], $options = [])
{
    try {
        $serviceAccount = getServiceAccountArray();
        if (!$serviceAccount) {
            return ['success' => false, 'error' => 'Service account Firebase manquant'];
        }

        $projectId = $serviceAccount['project_id'] ?? null;
        if (!$projectId) return ['success' => false, 'error' => 'project_id introuvable dans service account'];

        $accessToken = getOAuth2AccessTokenFromServiceAccount($serviceAccount);
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Impossible d\'obtenir access token'];
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $options['title'] ?? '🚚 Suzosky Coursier',
                    'body' => $message
                ],
                'data' => array_merge($data, [
                    'timestamp' => (string)time()
                ]),
                'android' => [
                    'notification' => [
                        'channel_id' => $options['channel_id'] ?? 'commandes_channel',
                        'sound' => $options['sound'] ?? 'suzosky_notification.mp3',
                        'priority' => 'high'
                    ]
                ]
            ]
        ];

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
            return ['success' => false, 'error' => "HTTP $httpCode: $response", 'http_code' => $httpCode, 'raw' => $response];
        }

    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function isPermanentFcmError($errorString)
{
    $err = strtoupper($errorString);
    $permanentIndicators = ['NOT_REGISTERED', 'UNREGISTERED', 'INVALID_ARGUMENT', 'INVALID_REGISTRATION', 'UNAVAILABLE', 'NOT_FOUND'];
    foreach ($permanentIndicators as $k) {
        if (strpos($err, $k) !== false) return true;
    }
    // also check common phrases
    $phrases = ['registration token not registered', 'not registered', 'Invalid registration'];
    foreach ($phrases as $p) {
        if (stripos($errorString, $p) !== false) return true;
    }
    return false;
}

?>
