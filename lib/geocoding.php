<?php
// lib/geocoding.php - Géocodage simple via Nominatim (OpenStreetMap)
// Usage: $coords = geocodeAddress('Adresse, Ville');
//         if ($coords) { $lat = $coords['lat']; $lng = $coords['lng']; }

function geocodeAddress(string $address, int $timeoutSeconds = 4): ?array {
    $addr = trim($address);
    if ($addr === '') { return null; }
    if (stripos($addr, 'Abidjan') === false) {
        $addr .= ", Abidjan, Côte d'Ivoire";
    }
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $addr,
        'format' => 'json',
        'limit' => 1,
        'addressdetails' => 0,
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Suzosky-Coursier/1.0 (contact@suzosky.com)\r\n",
            'timeout' => $timeoutSeconds,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) { return null; }
    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) { return null; }
    return [
        'lat' => (float)$data[0]['lat'],
        'lng' => (float)$data[0]['lon'],
        'provider' => 'nominatim',
    ];
}
