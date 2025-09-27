<?php
// Server-Sent Events stream for admin live data
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../lib/tracking_helpers.php';
$adminConf = $config['admin'] ?? [];
$expectedToken = $adminConf['api_token'] ?? null;
$hdrAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['Authorization'] ?? '');
$qpToken = $_GET['token'] ?? null;
if($expectedToken){
    $provided = null;
    if(preg_match('/Bearer\s+(.*)$/i', $hdrAuth, $m)) $provided = trim($m[1]);
    elseif($qpToken) $provided = $qpToken; // fallback query param for EventSource
    if(!$provided || !hash_equals($expectedToken, $provided)){
        http_response_code(401); echo 'unauthorized'; exit;
    }
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
// Allow CORS if needed
header('Access-Control-Allow-Origin: *');

$lastHash = null;
$pdo = getDBConnection();
function snapshot($pdo){
    $cols = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
    $hasDepLat = in_array('departure_lat', $cols, true);
    $hasDepLng = in_array('departure_lng', $cols, true);
    $hasModePaiement = in_array('mode_paiement', $cols, true);
    $hasPrixEstime = in_array('prix_estime', $cols, true);
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
    foreach($commandes as &$cmd){ $cmd['coursier_nom'] = trim(($cmd['nom'] ?? '').' '.($cmd['prenoms'] ?? '')); unset($cmd['nom'],$cmd['prenoms']); }
    $positions = [];
    try {
        $positions = $pdo->query("SELECT id_coursier, nom, prenoms, latitude, longitude, derniere_position FROM agents_suzosky WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND derniere_position >= DATE_SUB(NOW(), INTERVAL 2 HOUR)")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) { $positions = []; }
    if (empty($positions)) {
        try {
            $rows = tracking_select_latest_positions($pdo, 180);
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
        } catch(Exception $e) { $positions = []; }
    }
    $metrics = [ 'coursiers_actifs' => count($positions), 'commandes_actives' => count($commandes) ];
    // Alerts computation
    $alerts = [];
    $now = time();
    foreach($commandes as $c){
        // Delay pickup: statut nouvelle or en_cours without pickup_time threshold
        if(in_array($c['statut'], ['nouvelle','en_cours'])){
            if(!empty($c['updated_at'])){
                $ageMin = ($now - strtotime($c['updated_at']))/60;
                if($ageMin > 30){
                    $alerts[] = ['type'=>'pickup_delay','order_id'=>$c['id'],'severity'=>$ageMin>60?'critical':'warning','age_min'=>round($ageMin)];
                }
            }
        }
    }
    // Courier inactivity: last position older than threshold
    foreach($positions as $p){
        if(!empty($p['derniere_position'])){
            $idleMin = ($now - strtotime($p['derniere_position']))/60;
            if($idleMin > 15){
                $alerts[] = ['type'=>'inactivity','courier_id'=>$p['id_coursier'],'severity'=>$idleMin>30?'critical':'warning','idle_min'=>round($idleMin)];
            }
        }
    }
    return ['commandes'=>$commandes,'coursiers'=>$positions,'metrics'=>$metrics,'alerts'=>$alerts,'success'=>true];
}

while(true){
    $data = snapshot($pdo);
    $hash = md5(json_encode($data));
    if($hash !== $lastHash){
        echo "event: update\n";
        echo 'data: '.json_encode($data)."\n\n";
        @ob_flush(); flush();
        $lastHash = $hash;
    }
    // Heartbeat every 25s
    echo "event: ping\n"; echo 'data: {}' . "\n\n"; @ob_flush(); flush();
    sleep(5);
    if(connection_aborted()) break;
}
