<?php
require_once __DIR__ . '/../../config.php';
$adminConf = $config['admin'] ?? [];
$expectedToken = $adminConf['api_token'] ?? null;
$hdrAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['Authorization'] ?? '');
$qpToken = $_GET['token'] ?? null;
if($expectedToken){
    $provided = null;
    if(preg_match('/Bearer\\s+(.*)$/i',$hdrAuth,$m)) $provided = trim($m[1]);
    elseif($qpToken) $provided = $qpToken;
    if(!$provided || !hash_equals($expectedToken,$provided)){ http_response_code(401); echo 'unauthorized'; exit; }
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="orders_active_'.date('Ymd_His').'.csv"');
$pdo = getDBConnection();
$cols = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
$hasMode = in_array('mode_paiement',$cols,true);
$hasPrix = in_array('prix_estime',$cols,true);
$hasPickup = in_array('pickup_time',$cols,true);
$hasDelivered = in_array('delivered_time',$cols,true);
$out = fopen('php://output','w');
$header = ['id','statut'];
if($hasMode) $header[]='mode_paiement';
if($hasPrix) $header[]='prix_estime';
if($hasPickup) $header[]='pickup_time';
if($hasDelivered) $header[]='delivered_time';
$header[]='updated_at';
$header[]='coursier_id';
$header[]='coursier_nom';
fputcsv($out,$header,';');
$sql = "SELECT c.*, a.nom, a.prenoms FROM commandes c LEFT JOIN agents_suzosky a ON a.id_coursier=c.coursier_id WHERE c.statut IN ('nouvelle','en_cours','picked_up','livree') ORDER BY c.updated_at DESC LIMIT 1000";
foreach($pdo->query($sql) as $row){
    $line = [$row['id'],$row['statut']];
    if($hasMode) $line[]=$row['mode_paiement'];
    if($hasPrix) $line[]=$row['prix_estime'];
    if($hasPickup) $line[]=$row['pickup_time'];
    if($hasDelivered) $line[]=$row['delivered_time'];
    $line[]=$row['updated_at'];
    $line[]=$row['coursier_id'];
    $line[]=trim(($row['nom']??'').' '.($row['prenoms']??''));
    fputcsv($out,$line,';');
}
