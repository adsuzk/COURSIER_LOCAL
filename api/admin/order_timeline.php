<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../lib/tracking_helpers.php';

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
// Simple Bearer token auth (admin)
$adminConf = $config['admin'] ?? [];
$expectedToken = $adminConf['api_token'] ?? null;
$hdrAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['Authorization'] ?? '');
if($expectedToken){
    if(preg_match('/Bearer\s+(.*)$/i', $hdrAuth, $m)){
        $provided = trim($m[1]);
        if(hash_equals($expectedToken, $provided) === false){
            http_response_code(401); echo json_encode(['success'=>false,'error'=>'unauthorized']); exit;
        }
    } else {
        http_response_code(401); echo json_encode(['success'=>false,'error'=>'missing bearer token']); exit;
    }
}
if($orderId <= 0){ echo json_encode(['success'=>false,'error'=>'order_id requis']); exit; }

try {
    $pdo = getDBConnection();
    $cols = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
    $hasPickupTime = in_array('pickup_time', $cols, true);
    $hasDeliveredTime = in_array('delivered_time', $cols, true);
    $hasCreatedAt = in_array('created_at', $cols, true);
    $hasUpdatedAt = in_array('updated_at', $cols, true);
    $hasModePaiement = in_array('mode_paiement', $cols, true);
    $hasPrix = in_array('prix_estime', $cols, true);
    $hasDepartureLat = in_array('departure_lat', $cols, true);
    $hasDepartureLng = in_array('departure_lng', $cols, true);
    $hasCashCollected = in_array('cash_collected', $cols, true);
    $hasCashAmount = in_array('cash_amount', $cols, true);

    $select = "SELECT id, statut, " .
        ($hasModePaiement?"mode_paiement,":"'' as mode_paiement,") .
        ($hasPrix?"prix_estime AS prix_estime,":"0 AS prix_estime,") .
        ($hasPickupTime?"pickup_time,":"NULL AS pickup_time,") .
        ($hasDeliveredTime?"delivered_time,":"NULL AS delivered_time,") .
        ($hasCreatedAt?"created_at,":"NULL AS created_at,") .
        ($hasUpdatedAt?"updated_at,":"NULL AS updated_at,") .
        ($hasDepartureLat?"departure_lat,":"NULL AS departure_lat,") .
        ($hasDepartureLng?"departure_lng,":"NULL AS departure_lng,") .
        ($hasCashCollected?"cash_collected,":"0 AS cash_collected,") .
        ($hasCashAmount?"cash_amount":"NULL AS cash_amount") .
        " FROM commandes WHERE id = ?";
    $stmt = $pdo->prepare($select); $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$order){ echo json_encode(['success'=>false,'error'=>'Commande introuvable']); exit; }

    // Construire timeline logique à partir des dates présentes avec classification
    $timeline = [];
    $push = function($label,$ts,$key=null) use (&$timeline){ if($ts) $timeline[] = ['event'=>$label,'timestamp'=>$ts,'key'=>$key]; };
    $push('created', $order['created_at'] ?? null, 'created_at');
    $push('pickup', $order['pickup_time'] ?? null, 'pickup_time');
    $push('delivered', $order['delivered_time'] ?? null, 'delivered_time');
    $timeline[] = ['event'=>'current_status','value'=>$order['statut']];

    // Ajouter severité / code couleur basé sur délais vs seuils (en minutes)
    $thresholds = [
        'creation_to_pickup_warning' => 15,
        'creation_to_pickup_critical' => 30,
        'pickup_to_delivered_warning' => 30,
        'pickup_to_delivered_critical' => 60
    ];
    $createdAt = $order['created_at'] ?? null;
    $pickupAt = $order['pickup_time'] ?? null;
    $deliveredAt = $order['delivered_time'] ?? null;
    $creationToPickup = ($createdAt && $pickupAt) ? (strtotime($pickupAt)-strtotime($createdAt))/60 : null; // minutes
    $pickupToDelivered = ($pickupAt && $deliveredAt) ? (strtotime($deliveredAt)-strtotime($pickupAt))/60 : null;
    foreach($timeline as &$ev){
        $ev['severity'] = 'normal';
        $ev['color'] = '#42a5f5';
        if($ev['event']==='pickup' && $creationToPickup!==null){
            if($creationToPickup >= $thresholds['creation_to_pickup_critical']){ $ev['severity']='critical'; $ev['color']='#e53935'; }
            elseif($creationToPickup >= $thresholds['creation_to_pickup_warning']){ $ev['severity']='warning'; $ev['color']='#ffb300'; }
        }
        if($ev['event']==='delivered' && $pickupToDelivered!==null){
            if($pickupToDelivered >= $thresholds['pickup_to_delivered_critical']){ $ev['severity']='critical'; $ev['color']='#e53935'; }
            elseif($pickupToDelivered >= $thresholds['pickup_to_delivered_warning']){ $ev['severity']='warning'; $ev['color']='#ffb300'; }
        }
    }

    // Récupérer positions historiques éventuelles du coursier depuis tracking table si coursier_id lié
    $courseurId = null;
    try {
        $r = $pdo->prepare("SELECT coursier_id FROM commandes WHERE id = ?");
        $r->execute([$orderId]);
        $courseurId = $r->fetchColumn();
    } catch(Exception $e) {}

    $positions = [];
    if($courseurId){
        try {
            $positions = tracking_select_positions_for_courier($pdo, (int)$courseurId, 100);
        } catch(Exception $e) { $positions = []; }
    }

    // Compute durations (seconds) if timestamps present
    $durations = [
        'creation_to_pickup' => null,
        'pickup_to_delivered' => null,
        'total' => null
    ];
    $createdAt = $order['created_at'] ?? null;
    $pickupAt = $order['pickup_time'] ?? null;
    $deliveredAt = $order['delivered_time'] ?? null;
    if($createdAt && $pickupAt){
        $durations['creation_to_pickup'] = strtotime($pickupAt) - strtotime($createdAt);
    }
    if($pickupAt && $deliveredAt){
        $durations['pickup_to_delivered'] = strtotime($deliveredAt) - strtotime($pickupAt);
    }
    if($createdAt && $deliveredAt){
        $durations['total'] = strtotime($deliveredAt) - strtotime($createdAt);
    }

    // Speeds (km/h) between successive tracking points (if any) using haversine
    $speeds = [];
    if(count($positions) > 1){
        for($i = count($positions)-1; $i > 0; $i--){
            $p1 = $positions[$i]; // oldest in window (because DESC order)
            $p2 = $positions[$i-1];
            if(!isset($p1['latitude'],$p1['longitude'],$p2['latitude'],$p2['longitude'])) continue;
            $lat1 = deg2rad((float)$p1['latitude']);
            $lat2 = deg2rad((float)$p2['latitude']);
            $dLat = $lat2 - $lat1;
            $dLon = deg2rad((float)$p2['longitude'] - (float)$p1['longitude']);
            $a = sin($dLat/2)**2 + cos($lat1)*cos($lat2)*sin($dLon/2)**2;
            $c = 2*atan2(sqrt($a), sqrt(1-$a));
            $distanceKm = 6371*$c; // Earth radius km
            $t1 = strtotime($p1['created_at']);
            $t2 = strtotime($p2['created_at']);
            if($t1 && $t2 && $t2 > $t1){
                $hours = ($t2 - $t1)/3600.0;
                if($hours > 0){
                    $speeds[] = round($distanceKm / $hours, 2);
                }
            }
        }
    }

    echo json_encode([
        'success'=>true,
        'order'=>$order,
        'timeline'=>$timeline,
        'positions'=>$positions,
        'durations'=>$durations,
        'speeds'=>$speeds
    ]);
} catch(Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
