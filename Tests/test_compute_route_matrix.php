<?php
require_once __DIR__ . '/../lib/google_service_account_oauth.php';

// Usage: php Tests/test_compute_route_matrix.php /path/to/service-account.json
$jsonPath = $argv[1] ?? getenv('GOOGLE_MAPS_SERVICE_ACCOUNT_JSON');
if (!$jsonPath) {
    echo "Usage: php Tests/test_compute_route_matrix.php /path/to/service-account.json\n";
    exit(2);
}

try {
    echo "Using service account JSON: $jsonPath\n";
    $token = get_service_account_access_token($jsonPath, 'https://www.googleapis.com/auth/cloud-platform');
    echo "Got access token (length=" . strlen($token) . ") — calling computeRouteMatrix...\n";

    $json = json_decode(file_get_contents($jsonPath), true);
    $projectId = $json['project_id'] ?? null;
    // allow overriding project identifier with second CLI arg (useful to test project number)
    $overrideProject = $argv[2] ?? null;
    if ($overrideProject) {
        $projectId = $overrideProject;
    }
    // prefer project-scoped endpoint, fallback to simple v2 endpoint
    if ($projectId) {
        $matrixUrl = 'https://routes.googleapis.com/v2/projects/' . urlencode($projectId) . '/locations/global:computeRouteMatrix';
    } else {
        $matrixUrl = 'https://routes.googleapis.com/v2:computeRouteMatrix';
    }
    // Small test: origin + 2 destinations nearby Abidjan (adjust if needed)
    $body = [
        'origins' => [
            ['endpoint' => ['location' => ['latLng' => ['latitude' => 5.345, 'longitude' => -4.024]]]]
        ],
        'destinations' => [
            ['endpoint' => ['location' => ['latLng' => ['latitude' => 5.347, 'longitude' => -4.021]]]],
            ['endpoint' => ['location' => ['latLng' => ['latitude' => 5.336, 'longitude' => -4.012]]]]
        ],
        'travelMode' => 'DRIVE',
        'routingPreference' => 'TRAFFIC_AWARE'
    ];

    echo "Request URL: $matrixUrl\n";
    echo "Request body:\n" . json_encode($body, JSON_PRETTY_PRINT) . "\n\n";

    $ch = curl_init($matrixUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // include headers in output
    // verbose curl output to stderr for low-level diagnostics
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://stderr', 'w');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($resp === false) {
        echo "Matrix request failed: $err\n";
        exit(3);
    }

    $resp_headers = substr($resp, 0, $header_size);
    $resp_body = substr($resp, $header_size);
    echo "HTTP $code returned. Response headers:\n" . $resp_headers . "\n";
    echo "Response body (first 2000 chars):\n" . substr($resp_body,0,2000) . "\n\n";
    $data = json_decode($resp_body, true);
    // No API key fallback — we only use the service account JSON for server-side calls
    if (isset($data['originDestinationPairs'])) {
        echo "Response contains originDestinationPairs (" . count($data['originDestinationPairs']) . ")\n";
        $sample = array_slice($data['originDestinationPairs'], 0, 5);
        foreach ($sample as $p) {
            $i = $p['originIndex'] ?? '?';
            $j = $p['destinationIndex'] ?? '?';
            $d = $p['distanceMeters'] ?? ($p['distance'] ?? null);
            echo "  pair $i->$j distance=" . ($d ?? 'N/A') . "\n";
        }
        exit(0);
    } elseif (isset($data['rows'])) {
        echo "Response contains rows (" . count($data['rows']) . ")\n";
        $n = count($data['rows']);
        for ($i = 0; $i < $n; $i++) {
            $els = $data['rows'][$i]['elements'] ?? [];
            for ($j = 0; $j < count($els); $j++) {
                $d = $els[$j]['distance']['meters'] ?? ($els[$j]['distanceMeters'] ?? ($els[$j]['distance']['value'] ?? null));
                echo "  row $i -> col $j distance=" . ($d ?? 'N/A') . "\n";
            }
        }
        exit(0);
    } else {
        echo "Unknown response shape: " . substr($resp, 0, 800) . "\n";
        exit(4);
    }

    // If matrix returned 404/empty, also try computeRoutes (single route) to see if service responds
    echo "\n--- Now attempting computeRoutes (single route) ---\n";
    $computeUrl = $projectId ? ('https://routes.googleapis.com/v2/projects/' . urlencode($projectId) . '/locations/global:computeRoutes') : 'https://routes.googleapis.com/v2:computeRoutes';
    $computeBody = [
        'origin' => ['location' => ['latLng' => ['latitude' => 5.345, 'longitude' => -4.024]]],
        'destination' => ['location' => ['latLng' => ['latitude' => 5.347, 'longitude' => -4.021]]],
        'travelMode' => 'DRIVE',
        'routingPreference' => 'TRAFFIC_AWARE'
    ];

    $ch3 = curl_init($computeUrl);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HEADER, true);
    curl_setopt($ch3, CURLOPT_POST, true);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
    curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($computeBody));
    curl_setopt($ch3, CURLOPT_TIMEOUT, 10);
    $resp3 = curl_exec($ch3);
    $err3 = curl_error($ch3);
    $code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    $header_size3 = curl_getinfo($ch3, CURLINFO_HEADER_SIZE);
    curl_close($ch3);
    if ($resp3 === false) {
        echo "computeRoutes request failed: $err3\n";
    } else {
        $resp3_headers = substr($resp3, 0, $header_size3);
        $resp3_body = substr($resp3, $header_size3);
        echo "computeRoutes HTTP $code3 returned. Response headers:\n" . $resp3_headers . "\n";
        echo "computeRoutes response body (first 2000 chars):\n" . substr($resp3_body,0,2000) . "\n\n";
    }

    // Fallback computeRoutes with API key if token attempt failed/404
    if (($code3 === 404 || empty(trim($resp3))) && getenv('GOOGLE_MAPS_API_KEY')) {
        echo "Attempting computeRoutes fallback using API key...\n";
        $urlKey3 = $computeUrl . '?key=' . urlencode(getenv('GOOGLE_MAPS_API_KEY'));
        $ch4 = curl_init($urlKey3);
        curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch4, CURLOPT_HEADER, true);
        curl_setopt($ch4, CURLOPT_POST, true);
        curl_setopt($ch4, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch4, CURLOPT_POSTFIELDS, json_encode($computeBody));
        curl_setopt($ch4, CURLOPT_TIMEOUT, 10);
        $resp4 = curl_exec($ch4);
        $err4 = curl_error($ch4);
        $code4 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
        $header_size4 = curl_getinfo($ch4, CURLINFO_HEADER_SIZE);
        curl_close($ch4);
        if ($resp4 === false) {
            echo "computeRoutes fallback failed: $err4\n";
        } else {
            $resp4_headers = substr($resp4, 0, $header_size4);
            $resp4_body = substr($resp4, $header_size4);
            echo "computeRoutes fallback HTTP $code4 returned. Response headers:\n" . $resp4_headers . "\n";
            echo "computeRoutes fallback response body (first 2000 chars):\n" . substr($resp4_body,0,2000) . "\n\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
