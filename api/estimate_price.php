<?php
// api/estimate_price.php — Estimation distance/durée + prix aligné sur l'index (admin finances)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once __DIR__ . '/../config.php';

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDBConnection();

    // Inputs: origin/destination as addresses or lat,lng
    $origin = $_GET['origin'] ?? $_POST['origin'] ?? '';
    $destination = $_GET['destination'] ?? $_POST['destination'] ?? '';
    $oLat = isset($_GET['origin_lat']) ? $_GET['origin_lat'] : ($_POST['origin_lat'] ?? null);
    $oLng = isset($_GET['origin_lng']) ? $_GET['origin_lng'] : ($_POST['origin_lng'] ?? null);
    $dLat = isset($_GET['destination_lat']) ? $_GET['destination_lat'] : ($_POST['destination_lat'] ?? null);
    $dLng = isset($_GET['destination_lng']) ? $_GET['destination_lng'] : ($_POST['destination_lng'] ?? null);

    if ((empty($origin) || empty($destination)) && (empty($oLat) || empty($oLng) || empty($dLat) || empty($dLng))) {
        respond(['success' => false, 'message' => 'Paramètres manquants: origin/destination ou lat/lng requis'], 400);
    }

    // Charger paramètres tarification (admin.php finances)
    $params = [];
    $st = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) { $params[$row['parametre']] = (float)$row['valeur']; }
    $baseFare = isset($params['frais_base']) ? (int)$params['frais_base'] : 500; // FCFA
    $pricePerKm = isset($params['prix_kilometre']) ? (int)$params['prix_kilometre'] : 100; // FCFA/km

    // Appeler le proxy Directions local si possible
    $distanceMeters = null; $durationSeconds = null; $distanceText = null; $durationText = null;
    try {
        $qs = [];
        if (!empty($origin) && !empty($destination)) {
            $qs['origin'] = $origin;
            $qs['destination'] = $destination;
        } else {
            $qs['origin'] = $oLat . ',' . $oLng;
            $qs['destination'] = $dLat . ',' . $dLng;
        }
        $qs['mode'] = 'driving';
        $qs['language'] = 'fr';
        $qs['region'] = 'ci';
        $url = appUrl('api/directions_proxy.php?' . http_build_query($qs));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) throw new Exception($err);
        $json = json_decode($resp, true);
        if ($http >= 200 && $http < 300 && isset($json['directions']['routes'][0]['legs'][0])) {
            $leg = $json['directions']['routes'][0]['legs'][0];
            $distanceMeters = (int)($leg['distance']['value'] ?? 0);
            $durationSeconds = (int)($leg['duration']['value'] ?? 0);
            $distanceText = $leg['distance']['text'] ?? null;
            $durationText = $leg['duration']['text'] ?? null;
        }
    } catch (Throwable $e) {
        // Fallback handled below
    }

    if (!$distanceMeters || $distanceMeters <= 0) {
        // Fallback: distance approx 15km, 35min for Abidjan
        $distanceMeters = 15000; $durationSeconds = 35 * 60;
        $distanceText = '15 km'; $durationText = '35 min';
    }

    $distanceKm = round($distanceMeters / 1000, 2);

    // Barèmes d'urgence (mêmes que js_route_calculation.php)
    $multipliers = [
        'normale' => ['base' => 1.0, 'perKm' => 1.0, 'name' => 'Standard'],
        'urgente' => ['base' => 1.4, 'perKm' => 1.3, 'name' => 'Urgente'],
        'express' => ['base' => 1.8, 'perKm' => 1.6, 'name' => 'Express'],
    ];

    $calcs = [];
    foreach ($multipliers as $key => $m) {
        $bf = max($baseFare, (int)round($baseFare * $m['base']));
        $pk = max($pricePerKm, (int)round($pricePerKm * $m['perKm']));
        $distanceCost = (int)round(max(0, $distanceKm) * $pk);
        $total = max($bf, $bf + $distanceCost);
        $calcs[$key] = [
            'name' => $m['name'],
            'baseFare' => $bf,
            'perKmRate' => $pk,
            'distanceKm' => $distanceKm,
            'distanceCost' => $distanceCost,
            'totalPrice' => $total,
        ];
    }

    respond([
        'success' => true,
        'distance' => ['text' => $distanceText, 'value' => $distanceMeters],
        'duration' => ['text' => $durationText, 'value' => $durationSeconds],
        'calculations' => $calcs,
    ]);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => $e->getMessage()], 500);
}
