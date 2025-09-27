<?php
// Simple HTML export (could be piped through wkhtmltopdf externally). For now outputs HTML with correct headers.
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
$orderId = isset($_GET['order_id'])?intval($_GET['order_id']):0;
if($orderId<=0){ http_response_code(400); echo 'order_id requis'; exit; }
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM commandes WHERE id=?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$order){ http_response_code(404); echo 'Commande introuvable'; exit; }
// Reuse timeline endpoint logic by internal include
ob_start();
$_GET['order_id']=$orderId;
include __DIR__.'/order_timeline.php';
$json = json_decode(ob_get_clean(), true);
$timeline = $json['timeline'] ?? [];
$positions = $json['positions'] ?? [];
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Rapport Commande #'.$orderId.'</title><style>body{font-family:Arial, sans-serif;margin:30px;}h1{font-size:20px;}table{border-collapse:collapse;width:100%;margin-bottom:25px;}td,th{border:1px solid #ccc;padding:6px 8px;font-size:12px;} .badge{display:inline-block;padding:4px 8px;border-radius:4px;background:#eee;margin-right:6px;font-size:11px;} .critical{background:#e53935;color:#fff;} .warning{background:#ffb300;color:#000;} .normal{background:#42a5f5;color:#fff;} </style></head><body>';
echo '<h1>Rapport Commande #'.$orderId.'</h1>';
echo '<h2>Détails</h2><table><tr><th>ID</th><th>Statut</th><th>Création</th><th>Pickup</th><th>Livraison</th></tr>';
echo '<tr><td>'.$order['id'].'</td><td>'.$order['statut'].'</td><td>'.($order['created_at']??'').'</td><td>'.($order['pickup_time']??'').'</td><td>'.($order['delivered_time']??'').'</td></tr></table>';
echo '<h2>Timeline</h2><table><tr><th>Événement</th><th>Horodatage</th><th>Severity</th></tr>';
foreach($timeline as $ev){ if(!isset($ev['event'])||$ev['event']==='current_status') continue; $sev=$ev['severity']??'normal'; echo '<tr><td>'.$ev['event'].'</td><td>'.($ev['timestamp']??'').'</td><td><span class="badge '.$sev.'">'.$sev.'</span></td></tr>'; }
echo '</table>';
echo '<h2>Positions (max 100)</h2><table><tr><th>Date</th><th>Latitude</th><th>Longitude</th></tr>';
foreach($positions as $p){ echo '<tr><td>'.$p['created_at'].'</td><td>'.$p['latitude'].'</td><td>'.$p['longitude'].'</td></tr>'; }
echo '</table>';
echo '</body></html>';
