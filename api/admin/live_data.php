<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../lib/tracking_helpers.php';
// Bearer token admin auth
$adminConf = $config['admin'] ?? [];
$expectedToken = $adminConf['api_token'] ?? null;
$hdrAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['Authorization'] ?? '');
if($expectedToken){
    if(preg_match('/Bearer\s+(.*)$/i', $hdrAuth, $m)){
        $provided = trim($m[1]);
        if(hash_equals($expectedToken, $provided) === false){
            http_response_code(401); echo json_encode(['success'=>false,'error'=>'unauthorized']); exit;
        }
    } else { http_response_code(401); echo json_encode(['success'=>false,'error'=>'missing bearer token']); exit; }
}

try {
    $pdo = getDBConnection();

    // Assurer colonnes optionnelles utilisées dans markers
    $cols = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
    $hasDepLat = in_array('departure_lat', $cols, true);
    $hasDepLng = in_array('departure_lng', $cols, true);
    $hasModePaiement = in_array('mode_paiement', $cols, true);
    $hasPrixEstime = in_array('prix_estime', $cols, true);

    // Commandes actives (limiter volume)
    $sql = "SELECT c.id, c.statut, ".
        ($hasModePaiement ? 'c.mode_paiement,' : "'' as mode_paiement,") .
        ($hasPrixEstime ? 'c.prix_estime as tarif,' : "0 as tarif,") .
        ($hasDepLat ? 'c.departure_lat,' : 'NULL as departure_lat,') .
        ($hasDepLng ? 'c.departure_lng,' : 'NULL as departure_lng,') .
        "c.coursier_id, c.updated_at, a.nom, a.prenoms
         FROM commandes c LEFT JOIN agents_suzosky a ON a.id_coursier = c.coursier_id
         WHERE c.statut IN ('nouvelle','en_cours','picked_up')
         ORDER BY c.updated_at DESC LIMIT 100";
    $commandes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    foreach($commandes as &$cmd){
        $cmd['coursier_nom'] = trim(($cmd['nom'] ?? '').' '.($cmd['prenoms'] ?? '')); unset($cmd['nom'],$cmd['prenoms']);
    }

    // Positions coursiers: prefer agents_suzosky if fresh, else fallback to tracking_coursiers helper
    $positions = [];
    try {
        $positions = $pdo->query("SELECT id_coursier, nom, prenoms, latitude, longitude, derniere_position FROM agents_suzosky WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND derniere_position >= DATE_SUB(NOW(), INTERVAL 2 HOUR)")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $positions = []; }
    if (empty($positions)) {
        try {
            $rows = tracking_select_latest_positions($pdo, 180);
            // Join with agents table to get names if available
            $ids = array_column($rows, 'coursier_id');
            $mapNames = [];
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("SELECT id_coursier, nom, prenoms FROM agents_suzosky WHERE id_coursier IN ($in)");
                $st->execute($ids);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) { $mapNames[$a['id_coursier']] = $a; }
            }
            $positions = array_map(function($r) use ($mapNames){
                $n = $mapNames[$r['coursier_id']] ?? ['nom'=>null,'prenoms'=>null];
                return [
                    'id_coursier' => (int)$r['coursier_id'],
                    'nom' => $n['nom'],
                    'prenoms' => $n['prenoms'],
                    'latitude' => $r['latitude'],
                    'longitude' => $r['longitude'],
                    'derniere_position' => $r['derniere_position'] ?? null,
                ];
            }, $rows);
        } catch (Exception $e) { $positions = []; }
    }

    // Métriques rapides
    $metrics = [
        'coursiers_actifs' => count($positions),
        'commandes_actives' => count($commandes)
    ];

    echo json_encode([
        'success' => true,
        'commandes' => $commandes,
        'coursiers' => $positions,
        'metrics' => $metrics
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
