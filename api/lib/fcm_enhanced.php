<?php
/**
 * fcm_enhanced.php
 * Améliorations récentes:
 *  - Auto-détection du fichier service account firebase: recherche d'abord
 *    FIREBASE_SERVICE_ACCOUNT_FILE (env), puis data/firebase_service_account.json,
 *    puis un fichier racine correspondant au motif coursier-suzosky-firebase-adminsdk-*.json
 *  - Permet d'activer le mode validation uniquement: export FCM_VALIDATE_ONLY=1
 *  - Forcer l'API legacy: créer data/force_legacy_fcm ou variable env FCM_FORCE_LEGACY=1
 *
 * Pour utiliser le fichier racine fourni (ex: coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json) sans config supplémentaire,
 * assurez-vous simplement qu'il est à la racine du projet. Le script l'utilisera automatiquement.
 */

// Envoi FCM avec journalisation. Utilise HTTP v1 si un compte de service Firebase est disponible,
// sinon bascule sur l'API Legacy via FCM_SERVER_KEY.
function fcm_send_with_log($tokens, $title, $body, $data = [], $coursier_id = null, $commande_id = null) {
    global $pdo;

    // Initialiser connexion DB si pas déjà fait
    if (!isset($pdo)) {
        require_once __DIR__ . "/../../config.php";
        $pdo = getDBConnection();
    }

    // Ensure dedicated log table exists to avoid conflicting legacy schemas
    try {
        // Créer la table avec le schéma aligné sur la documentation (commande_id)
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications_log_fcm (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coursier_id INT NULL,
            commande_id INT NULL,
            notification_type VARCHAR(64) NULL,
            title VARCHAR(255) NULL,
            message TEXT NULL,
            fcm_tokens_used TEXT NULL,
            fcm_response_code INT NULL,
            fcm_response TEXT NULL,
            success TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Migration douce: si une ancienne colonne order_id existe et commande_id n'existe pas, l'ajouter
        try { $pdo->exec("ALTER TABLE notifications_log_fcm ADD COLUMN commande_id INT NULL"); } catch (Throwable $e2) { /* peut déjà exister */ }
    } catch (Throwable $e) {
        // continue without blocking
    }
    $logTable = 'notifications_log_fcm';
    // Préparer une fonction de journalisation tolérante au schéma existant
    $logOnce = function($status, $tokensUsed = null, $respCode = null, $respText = null) use ($pdo, $logTable, $coursier_id, $commande_id, $title, $body) {
        try {
            // Récupérer les colonnes existantes
            $cols = [];
            try {
                $res = $pdo->query("SHOW COLUMNS FROM {$logTable}");
                foreach ($res as $row) { $cols[strtolower($row['Field'])] = $row['Field']; }
            } catch (Throwable $e) { /* ignore */ }

            // Si la table n'existe pas, la créer selon la documentation (schéma minimal)
            if (empty($cols)) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS {$logTable} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    coursier_id INT NULL,
                    commande_id INT NULL,
                    token_used TEXT NULL,
                    message TEXT NULL,
                    status VARCHAR(64) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $cols = [
                    'coursier_id' => 'coursier_id',
                    'commande_id' => 'commande_id',
                    'token_used'  => 'token_used',
                    'message'     => 'message',
                    'status'      => 'status',
                ];
            }

            // Construire INSERT dynamique en fonction des colonnes disponibles
            $fields = [];
            $placeholders = [];
            $values = [];
            $add = function($name, $value) use (&$fields, &$placeholders, &$values) {
                $fields[] = $name; $placeholders[] = ':' . $name; $values[':' . $name] = $value; };

            if (isset($cols['coursier_id'])) $add($cols['coursier_id'], $coursier_id);
            if (isset($cols['commande_id'])) $add($cols['commande_id'], $commande_id);
            // Support à la fois token_used et fcm_tokens_used
            $tokensStr = is_array($tokensUsed) ? json_encode($tokensUsed) : (string)$tokensUsed;
            if (isset($cols['token_used'])) $add($cols['token_used'], $tokensStr);
            if (isset($cols['fcm_tokens_used'])) $add($cols['fcm_tokens_used'], $tokensStr);
            // Support message et/ou title/message séparés
            if (isset($cols['message'])) $add($cols['message'], $body);
            if (isset($cols['title'])) $add($cols['title'], $title);
            // Statut et/ou success + codes
            if (isset($cols['status'])) $add($cols['status'], $status);
            if (isset($cols['success'])) $add($cols['success'], $status === 'sent' ? 1 : 0);
            if (isset($cols['fcm_response_code'])) $add($cols['fcm_response_code'], $respCode);
            if (isset($cols['fcm_response'])) $add($cols['fcm_response'], $respText);
            // Type
            if (isset($cols['notification_type'])) $add($cols['notification_type'], 'new_order');

            if ($fields) {
                $sql = 'INSERT INTO ' . $logTable . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
            }
        } catch (Throwable $e) { /* ne pas bloquer l'envoi si le log échoue */ }
    };
    // Permettre de forcer le mode legacy (variable d'env ou fichier drapeau)
    $forceLegacyFlag = (getenv('FCM_FORCE_LEGACY') === '1' || file_exists(__DIR__ . '/../../data/force_legacy_fcm'));

    $saPath = getenv('FIREBASE_SERVICE_ACCOUNT_FILE');
    if (!$saPath) {
        $candidate = __DIR__ . '/../../data/firebase_service_account.json';
        if (file_exists($candidate)) {
            $saPath = realpath($candidate);
        } else {
            // Nouveau: auto-détection d'un fichier service account à la racine du projet
            $rootPattern = glob(__DIR__ . '/../../coursier-suzosky-firebase-adminsdk-*.json');
            if (!empty($rootPattern)) {
                // Utiliser le premier trouvé
                $saPath = realpath($rootPattern[0]);
            }
        }
    }

    if (!$forceLegacyFlag && $saPath && file_exists($saPath)) {
        // HTTP v1 path
        $result = fcm_v1_send($tokens, $title, $body, $data, $saPath);
        $success = $result['success'] ?? false;
        $code = $result['code'] ?? null;
        $resp = $result['result'] ?? ($result['error'] ?? null);

        // Journalisation unifiée et tolérante
        $logOnce($success ? 'sent' : 'failed', $tokens, $code, is_string($resp) ? $resp : json_encode($resp));

        if ($pdo && isset($result['result']) && is_array($result['result'])) {
            // Nettoyer les tokens UNREGISTERED
            fcm_cleanup_unregistered_tokens($pdo, $result['result'], $coursier_id, $commande_id);
        }

        return $result + [ 'method' => 'http_v1' ];
    }

    // Legacy fallback
    $serverKey = getenv("FCM_SERVER_KEY");
    if (!$serverKey) {
        // Pas de méthode d'envoi disponible
        $logOnce('failed', $tokens, null, "FCM_SERVER_KEY manquante et aucun compte service détecté");
        return ["success" => false, "error" => "Aucun moyen FCM configuré (ni HTTP v1, ni legacy).", 'method' => 'none'];
    }

    $url = "https://fcm.googleapis.com/fcm/send";
    $payload = [
        "registration_ids" => array_values($tokens),
        "notification" => [
            "title" => $title,
            "body" => $body,
            "sound" => "default"
        ],
        "data" => $data,
        "priority" => "high"
    ];

    $headers = [
        "Authorization: key=" . $serverKey,
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        $logOnce('failed', $tokens, null, $error);
        return ["success" => false, "error" => $error, 'method' => 'legacy'];
    }
    curl_close($ch);
    $success = $httpCode >= 200 && $httpCode < 300;
    // Journaliser le résultat final (héritage)
    $logOnce($success ? 'sent' : 'failed', $tokens, $httpCode, $result);
    return ["success" => $success, "code" => $httpCode, "result" => $result, 'method' => 'legacy'];
}

// HTTP v1: envoi avec compte de service (JWT → access_token → messages:send)
function fcm_v1_send(array $tokens, string $title, string $body, array $data, string $serviceAccountPath): array {
    $sa = json_decode(@file_get_contents($serviceAccountPath), true);
    if (!is_array($sa) || empty($sa['client_email']) || empty($sa['private_key']) || empty($sa['project_id'])) {
        return ['success' => false, 'error' => 'Service account JSON invalide'];
    }

    $accessToken = fcm_v1_get_access_token($sa);
    if (!$accessToken) {
        return ['success' => false, 'error' => 'Impossible d\'obtenir un access_token'];
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . $sa['project_id'] . '/messages:send';
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    // Permettre l'envoi "data-only" (nécessaire pour que onMessageReceived soit appelé quand l'app est en arrière-plan)
    // Activer en ajoutant _data_only => true dans $data. On retire ensuite cette clé interne.
    $useDataOnly = false;
    if (isset($data['_data_only'])) {
        $useDataOnly = (bool)$data['_data_only'];
        unset($data['_data_only']);
    }

    $results = [];
    $allOk = true;
    foreach ($tokens as $t) {
        $message = [
            'token' => $t,
            // Même en data-only on inclut les données title/body pour affichage local côté app.
            'data' => array_map('strval', $data + [
                'title' => $title,
                'body'  => $body
            ]),
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'sound' => 'default'
                ]
            ]
        ];
        if (!$useDataOnly) {
            // Mode classique: on laisse aussi le bloc notification (affichage système direct si app background)
            $message['notification'] = [ 'title' => $title, 'body' => $body ];
        } else {
            // En data-only, signaler qu'on veut que l'app réagisse; content_available pour iOS éventuel
            $message['apns'] = [ 'headers' => [ 'apns-priority' => '10' ], 'payload' => [ 'aps' => [ 'content-available' => 1 ] ] ];
        }
        $payload = [ 'message' => $message ];
        if (getenv('FCM_VALIDATE_ONLY') === '1') {
            $payload['validate_only'] = true; // Flag attendu au niveau racine, pas dans message
        }
        // Nettoyer les null (sécurité)
        $payload['message'] = array_filter($payload['message'], function($v) { return $v !== null; });

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) {
            $results[] = [
                'token' => substr($t,0,12).'…',
                'token_raw' => $t,
                'code' => null,
                'error' => curl_error($ch)
            ];
            $allOk = false;
        } else {
            $decoded = json_decode($resp, true);
            $errorMeta = fcm_extract_error_meta($decoded);
            $entry = [
                'token' => substr($t,0,12).'…',
                'token_raw' => $t,
                'code' => $http,
                'result' => $resp
            ];
            if (!empty($errorMeta['name'])) {
                $entry['message_name'] = $errorMeta['name'];
            }
            if (!empty($errorMeta['errorCode'])) {
                $entry['errorCode'] = $errorMeta['errorCode'];
            }
            if (!empty($errorMeta['errorStatus'])) {
                $entry['errorStatus'] = $errorMeta['errorStatus'];
            }
            if (!empty($errorMeta['errorMessage'])) {
                $entry['errorMessage'] = $errorMeta['errorMessage'];
            }
            $results[] = $entry;
            if ($http < 200 || $http >= 300) $allOk = false;
        }
        curl_close($ch);
    }

    return [ 'success' => $allOk, 'code' => null, 'result' => $results ];
}

function fcm_extract_error_meta(?array $decoded): array {
    $meta = [
        'name' => null,
        'errorCode' => null,
        'errorStatus' => null,
        'errorMessage' => null,
    ];

    if (!is_array($decoded)) {
        return $meta;
    }

    if (!empty($decoded['name'])) {
        $meta['name'] = $decoded['name'];
    }

    if (!empty($decoded['error']) && is_array($decoded['error'])) {
        $meta['errorStatus'] = $decoded['error']['status'] ?? null;
        $meta['errorMessage'] = $decoded['error']['message'] ?? null;
        if (!empty($decoded['error']['details']) && is_array($decoded['error']['details'])) {
            foreach ($decoded['error']['details'] as $detail) {
                if (!is_array($detail)) continue;
                if (($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError' && !empty($detail['errorCode'])) {
                    $meta['errorCode'] = $detail['errorCode'];
                    break;
                }
            }
        }
        if (!$meta['errorCode'] && !empty($meta['errorStatus'])) {
            $meta['errorCode'] = $meta['errorStatus'];
        }
    }

    return $meta;
}

function fcm_cleanup_unregistered_tokens(PDO $pdo, array $results, ?int $coursierId, ?int $orderId): void {
    $tokensToDelete = [];
    foreach ($results as $entry) {
        if (!is_array($entry)) continue;
        $code = $entry['code'] ?? null;
        $errorCode = $entry['errorCode'] ?? null;
        $tokenRaw = $entry['token_raw'] ?? null;
        if ($code === 404 && $errorCode === 'UNREGISTERED' && $tokenRaw) {
            $tokensToDelete[] = $tokenRaw;
        }
    }

    if (empty($tokensToDelete)) {
        return;
    }

    $tokensToDelete = array_unique($tokensToDelete);
    try {
        $stmt = $pdo->prepare('DELETE FROM device_tokens WHERE token = ?');
    } catch (Throwable $e) {
        return;
    }

    $loggerLoaded = false;
    foreach ($tokensToDelete as $token) {
        try {
            $stmt->execute([$token]);
        } catch (Throwable $e) {
            continue;
        }

        if (!$loggerLoaded) {
            $loggerPath = __DIR__ . '/../../logger.php';
            if (file_exists($loggerPath)) {
                require_once $loggerPath;
            }
            $loggerLoaded = true;
        }

        if (function_exists('logInfo')) {
            logInfo('FCM token supprimé après réponse UNREGISTERED', [
                'token_prefix' => substr($token, 0, 12) . '…',
                'coursier_id' => $coursierId,
                'order_id' => $orderId
            ], 'FCM');
        }
    }
}

function fcm_v1_get_access_token(array $sa): ?string {
    $now = time();
    $header = base64_url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
    // Permettre d'ajouter cloud-platform pour diagnostics IAM si demandé
    if (getenv('FCM_EXTRA_SCOPE_CLOUD') === '1') {
        $scopes[] = 'https://www.googleapis.com/auth/cloud-platform';
    }
    $claimSet = [
        'iss' => $sa['client_email'],
        'scope' => implode(' ', $scopes),
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ];
    $payload = base64_url_encode(json_encode($claimSet));
    $toSign = $header . '.' . $payload;

    $privateKey = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) return null;
    $signature = '';
    $ok = openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok) return null;
    $jwt = $toSign . '.' . base64_url_encode($signature);

    // Exchange JWT for access token
    $post = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $http < 200 || $http >= 300) return null;
    $j = json_decode($resp, true);
    return $j['access_token'] ?? null;
}

function base64_url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Fonction de compatibilité
function fcm_send($tokens, $title, $body, $data = []) {
    return fcm_send_with_log($tokens, $title, $body, $data);
}
?>