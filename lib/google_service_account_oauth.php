<?php
/**
 * Helper: exchange service account JSON for an access_token (JWT flow)
 * Usage: require_once __DIR__ . '/google_service_account_oauth.php';
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_service_account_access_token($jsonPath, $scope = 'https://www.googleapis.com/auth/cloud-platform') {
    if (!file_exists($jsonPath)) {
        throw new Exception('Service account JSON not found: ' . $jsonPath);
    }
    $sa = json_decode(file_get_contents($jsonPath), true);
    if (!$sa || !isset($sa['client_email']) || !isset($sa['private_key'])) {
        throw new Exception('Invalid service account JSON');
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss' => $sa['client_email'],
        'scope' => $scope,
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ];

    $jwtHeader = base64url_encode(json_encode($header));
    $jwtClaims = base64url_encode(json_encode($claims));
    $unsigned = $jwtHeader . '.' . $jwtClaims;

    $pkey = openssl_pkey_get_private($sa['private_key']);
    if (!$pkey) {
        throw new Exception('Unable to parse private key from service account JSON');
    }
    $sig = '';
    $ok = openssl_sign($unsigned, $sig, $pkey, OPENSSL_ALGO_SHA256);
    openssl_free_key($pkey);
    if (!$ok) {
        throw new Exception('Failed to sign JWT');
    }
    $jwt = $unsigned . '.' . base64url_encode($sig);

    $post = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false) {
        throw new Exception('Token request failed: ' . $err);
    }
    $data = json_decode($resp, true);
    if (empty($data['access_token'])) {
        throw new Exception('No access_token in token response: HTTP ' . intval($code) . ' resp=' . substr($resp,0,400));
    }
    return $data['access_token'];
}

