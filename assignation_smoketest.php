<?php
// Test/assignation_smoketest.php - Crée une fausse commande minimale et déclenche l'assignation
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}
require_once __DIR__ . '/../config.php';

$out = [
    'ok' => false,
    'now' => date('c'),
    'env' => isProductionEnvironment() ? 'production' : 'development',
    'order' => null,
    'assign_call' => null,
    'error' => null,
];

try {
    $pdo = getDBConnection();
    $columns = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
    $hasStatut = in_array('statut', $columns, true);
    $hasLat = in_array('departure_lat', $columns, true);
    $hasLng = in_array('departure_lng', $columns, true);
    $hasCode = in_array('code_commande', $columns, true);
    $hasOrderNumber = in_array('order_number', $columns, true);
    $hasNumero = in_array('numero_commande', $columns, true);

    // Préparer INSERT simple
    $cols = ['adresse_depart','adresse_arrivee','telephone_expediteur','telephone_destinataire','description_colis','priorite','mode_paiement','prix_estime'];
    $vals = ['Test depart','Test destination','0101010101','0202020202','Test colis','normale','cash',1000];

    // Identifiants de commande si nécessaires
    $nowCode = 'SZK' . date('ymd') . mt_rand(100000, 999999);
    if ($hasCode) { $cols[] = 'code_commande'; $vals[] = $nowCode; }
    if ($hasOrderNumber) { $cols[] = 'order_number'; $vals[] = 'SZK' . date('Ymd') . substr($nowCode, -6); }
    if ($hasNumero && !$hasCode) { $cols[] = 'numero_commande'; $vals[] = 'SZK' . date('Ymd') . substr($nowCode, -6); }
    if ($hasStatut) { $cols[] = 'statut'; $vals[] = 'nouvelle'; }
    if ($hasLat) { $cols[] = 'departure_lat'; $vals[] = (float)($_GET['lat'] ?? 5.345); }
    if ($hasLng) { $cols[] = 'departure_lng'; $vals[] = (float)($_GET['lng'] ?? -4.022); }

    // client_id minimal si nécessaire
    if (in_array('client_id', $columns, true)) { $cols[] = 'client_id'; $vals[] = 1; }
    if (in_array('expediteur_id', $columns, true)) { $cols[] = 'expediteur_id'; $vals[] = 1; }
    if (in_array('destinataire_id', $columns, true)) { $cols[] = 'destinataire_id'; $vals[] = 1; }

    $sql = 'INSERT INTO commandes (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    $st = $pdo->prepare($sql);
    $st->execute($vals);
    $orderId = (int)$pdo->lastInsertId();
    $out['order'] = ['id' => $orderId];

    // Construire URL assignation
    $assignUrl = appUrl('api/assign_nearest_coursier.php');

    $payload = json_encode([
        'order_id' => $orderId,
        'departure_lat' => (float)($_GET['lat'] ?? 5.345),
        'departure_lng' => (float)($_GET['lng'] ?? -4.022)
    ]);
    $opts = [ 'http' => [ 'method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'ignore_errors' => true ] ];
    $ctx = stream_context_create($opts);
    $resp = @file_get_contents($assignUrl, false, $ctx);

    $out['assign_call'] = [
        'url' => $assignUrl,
        'response' => $resp,
        'headers' => isset($http_response_header) ? $http_response_header : null,
        'base_url_dbg' => appUrl(),
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    ];
    $out['ok'] = true;
} catch (Throwable $e) {
    http_response_code(500);
    $out['ok'] = false;
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
