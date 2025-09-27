<?php
// directions_proxy.php — Proxy sécurisé pour l'API Google Directions
// Usage (GET):
//   directions_proxy.php?origin=lat,lng&destination=lat,lng&mode=driving&language=fr&region=ci
// Optionnel:
//   waypoints=lat1,lng1|lat2,lng2   alternatives=true|false   avoid=tolls|highways|ferries
// La clé API est lue via variable d'environnement GOOGLE_DIRECTIONS_API_KEY
// ou via le fichier data/secret_google_directions_key.txt

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Optionnel si appelé depuis appli native

function readGoogleDirectionsKey(): ?string {
    // 1) ENV var prioritaire
    $key = getenv('GOOGLE_DIRECTIONS_API_KEY');
    if (!empty($key)) return $key;
    // 2) Fichier secret (non versionné)
    $file = __DIR__ . '/../data/secret_google_directions_key.txt';
    if (file_exists($file)) {
        $content = trim((string)@file_get_contents($file));
        if ($content !== '') return $content;
    }
    return null;
}

function respondError(int $code, string $message, array $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge([
        'ok' => false,
        'error' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Validation rapide "lat,lng"
function parseLatLng(string $pair): ?array {
    $parts = explode(',', $pair);
    if (count($parts) !== 2) return null;
    $lat = filter_var(trim($parts[0]), FILTER_VALIDATE_FLOAT);
    $lng = filter_var(trim($parts[1]), FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lng === false) return null;
    // bornes
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) return null;
    return [$lat, $lng];
}

try {
    $key = readGoogleDirectionsKey();
    if (!$key) {
        respondError(500, 'Server directions key missing. Define GOOGLE_DIRECTIONS_API_KEY or data/secret_google_directions_key.txt');
    }

    $origin = isset($_GET['origin']) ? trim($_GET['origin']) : '';
    $destination = isset($_GET['destination']) ? trim($_GET['destination']) : '';
    if ($origin === '' || $destination === '') {
        respondError(400, 'Missing required parameters: origin and destination (lat,lng)');
    }
    if (!parseLatLng($origin) || !parseLatLng($destination)) {
        respondError(400, 'Invalid lat,lng format for origin or destination');
    }

    $mode = $_GET['mode'] ?? 'driving';
    $allowedModes = ['driving','walking','transit','bicycling','two_wheeler'];
    if (!in_array($mode, $allowedModes, true)) $mode = 'driving';

    $language = $_GET['language'] ?? 'fr';
    $region = $_GET['region'] ?? 'ci';
    $waypoints = isset($_GET['waypoints']) ? trim((string)$_GET['waypoints']) : '';
    $alternatives = isset($_GET['alternatives']) ? (($_GET['alternatives'] === 'true') ? 'true' : 'false') : 'false';
    $avoid = isset($_GET['avoid']) ? trim((string)$_GET['avoid']) : '';

    $qs = [
        'origin' => $origin,
        'destination' => $destination,
        'mode' => $mode,
        'language' => $language,
        'region' => $region,
        'alternatives' => $alternatives,
        'key' => $key,
    ];
    if ($waypoints !== '') $qs['waypoints'] = $waypoints;
    if ($avoid !== '') $qs['avoid'] = $avoid;

    $url = 'https://maps.googleapis.com/maps/api/directions/json?' . http_build_query($qs);

    // Appel cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: SuzoskyCoursier/1.0'
        ],
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        respondError(502, 'Directions upstream error', ['details' => $err]);
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        respondError(502, 'Directions upstream HTTP ' . $httpCode);
    }
    $json = json_decode($resp, true);
    if (!is_array($json)) {
        respondError(502, 'Invalid JSON from Directions');
    }

    // Forward quasi tel quel mais sans exposer la clé (déjà seulement en entrée)
    echo json_encode([
        'ok' => true,
        'directions' => $json,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    respondError(500, 'Unexpected server error', ['exception' => $e->getMessage()]);
}
