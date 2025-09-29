<?php
// Tests/e2e_fullstack_runner.php
// Suite E2E unique couvrant: FCM, API, assignation, notification, acceptation, timeline
// Utilisable via navigateur: http://localhost/COURSIER_LOCAL/Tests/e2e_fullstack_runner.php

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config.php';

function db(): PDO {
    if (function_exists('getDBConnection')) return getDBConnection();
    if (function_exists('getPDO')) return getPDO();
    // Fallback direct pour les tests
    try {
        return new PDO(
            "mysql:host=127.0.0.1;dbname=coursier_local;charset=utf8mb4",
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Throwable $e) {
        throw new RuntimeException('DB connection failed: ' . $e->getMessage());
    }
}

function baseUrl(string $path = ''): string {
    if (function_exists('routePath')) return routePath($path);
    if (function_exists('getAppBaseUrl')) return rtrim(getAppBaseUrl(), '/') . '/' . ltrim($path, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/COURSIER_LOCAL/Tests/e2e_fullstack_runner.php'), '/Tests');
    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

function http_get(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$code?:0,'body'=>$body,'error'=>$err];
}

function http_post_json(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data),
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code'=>$code?:0,'body'=>$body,'error'=>$err];
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        try {
            $safe = str_replace('`','``',$table);
            $res = $pdo->query("SHOW TABLES LIKE '".$safe."'");
            return $res && $res->fetchColumn() !== false;
        } catch (Throwable $e2) { return false; }
    }
}

function colExists(PDO $pdo, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $st->execute([$col]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) { return false; }
}

function latestTokenForCoursier(PDO $pdo, int $coursierId): ?string {
    if (!tableExists($pdo,'device_tokens')) return null;
    try {
        $st = $pdo->prepare('SELECT token FROM device_tokens WHERE (agent_id = ? OR coursier_id = ?) AND is_active = 1 ORDER BY updated_at DESC, last_used DESC LIMIT 1');
        $st->execute([$coursierId, $coursierId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row['token'] ?? null;
    } catch (Throwable $e) { return null; }
}

function ensureCommandesSchema(PDO $pdo): void {
    if (!tableExists($pdo, 'commandes')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS commandes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code_commande VARCHAR(50) UNIQUE,
            client_nom VARCHAR(100) NULL,
            client_telephone VARCHAR(30) NULL,
            lieu_depart VARCHAR(255) NULL,
            lieu_arrivee VARCHAR(255) NULL,
            coursier_id INT NULL,
            statut VARCHAR(32) DEFAULT 'nouvelle',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            assigned_at DATETIME NULL,
            picked_up_at DATETIME NULL,
            delivered_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        foreach (['code_commande','statut','created_at','updated_at'] as $c) {
            if (!colExists($pdo,'commandes',$c)) {
                try { $pdo->exec("ALTER TABLE commandes ADD COLUMN `{$c}` VARCHAR(64) NULL"); } catch (Throwable $e) {}
            }
        }
        foreach (['assigned_at','picked_up_at','delivered_at'] as $d) {
            if (!colExists($pdo,'commandes',$d)) {
                try { $pdo->exec("ALTER TABLE commandes ADD COLUMN `{$d}` DATETIME NULL"); } catch (Throwable $e) {}
            }
        }
    }
}

function createTestOrder(PDO $pdo, array $opts): array {
    $code = $opts['code_commande'] ?? ('TEST' . date('ymdHis') . rand(100,999));
    $clientNom = $opts['client_nom'] ?? 'Client Test E2E';
    $clientTel = $opts['client_telephone'] ?? '01020304';
    $from = $opts['adresse_depart'] ?? 'Plateau Test, Abidjan';
    $to   = $opts['adresse_arrivee'] ?? 'Cocody Test, Abidjan';
    
    // Utiliser la vraie structure de la table
    $stmt = $pdo->prepare("INSERT INTO commandes (
        code_commande, client_nom, client_telephone, 
        adresse_depart, adresse_arrivee, 
        adresse_retrait, adresse_livraison,
        prix_estime, statut, created_at, updated_at
    ) VALUES (?,?,?,?,?,?,?,1000,'nouvelle', NOW(), NOW())");
    $stmt->execute([$code,$clientNom,$clientTel,$from,$to,$from,$to]);
    $id = (int)$pdo->lastInsertId();
    return ['id'=>$id,'code_commande'=>$code,'from'=>$from,'to'=>$to];
}

function assignAutomatically(PDO $pdo): array {
    // Exécuter le script d'auto-assignation en interne et capturer la sortie
    $file = __DIR__ . '/../auto_assign_orders.php';
    if (!is_file($file)) return ['success'=>false,'output'=>'auto_assign_orders.php not found'];
    ob_start();
    try {
        include $file; // le script echo des logs
    } catch (Throwable $e) {
        $out = ob_get_clean();
        return ['success'=>false,'output'=> $out . "\n" . $e->getMessage()];
    }
    $out = ob_get_clean();
    $ok = (strpos($out, 'Attribution automatique') !== false) || (strpos($out,'✅ Notification envoyée') !== false) || (strpos($out,'terminée') !== false);
    return ['success'=>$ok,'output'=>$out];
}

function sendTestFCM(PDO $pdo, int $coursierId, ?string $token = null): array {
    // D'abord essayer d'obtenir un vrai token
    $tk = $token ?: latestTokenForCoursier($pdo, $coursierId);
    
    // Si pas de vrai token, utiliser la méthode directe pour test de configuration
    if (!$tk || strpos($tk, 'local_test_token') !== false || strpos($tk, 'emergency_token') !== false) {
        return sendDirectFCMTest($coursierId);
    }
    
    // Utiliser fcm_enhanced avec un vrai token
    require_once __DIR__ . '/../api/lib/fcm_enhanced.php';
    $res = fcm_send_with_log([$tk], '🔔 Test FCM Suzosky', 'Vérification sonnerie Suzosky', [
        'type'=>'test_notification','_data_only'=>false
    ], $coursierId, null);
    $ok = is_array($res) ? (bool)($res['success'] ?? false) : true; 
    return ['success'=>$ok,'message'=>'FCM déclenché (token réel)', 'detail'=>$res];
}

function sendDirectFCMTest(int $coursierId): array {
    try {
        $saPath = __DIR__ . '/../coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json';
        if (!file_exists($saPath)) {
            return ['success'=>false,'message'=>'Service Account non trouvé'];
        }
        
        $sa = json_decode(file_get_contents($saPath), true);
        $accessToken = generateAccessTokenForTest($sa);
        
        // Utiliser un topic pour test (pas besoin de token device)
        $message = [
            'topic' => 'coursier_' . $coursierId,
            'notification' => [
                'title' => '🔔 Test FCM Suzosky',
                'body' => 'Vérification sonnerie - Topic notification'
            ],
            'data' => [
                'type' => 'test_notification',
                'coursier_id' => (string)$coursierId,
                'timestamp' => (string)time()
            ],
            'android' => [
                'priority' => 'HIGH',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => 'suzosky_notifications'
                ]
            ]
        ];
        
        $payload = ['message' => $message];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$sa['project_id']}/messages:send");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success'=>true,'message'=>'FCM envoyé via topic','detail'=>['method'=>'topic','response'=>$result]];
        } else {
            return ['success'=>false,'message'=>'Erreur FCM HTTP ' . $httpCode,'detail'=>$result];
        }
        
    } catch (Exception $e) {
        return ['success'=>false,'message'=>'Erreur: ' . $e->getMessage()];
    }
}

function generateAccessTokenForTest($serviceAccount) {
    // JWT generation pour FCM
    $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
    $now = time();
    $payload = json_encode([
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = '';
    openssl_sign($base64Header . '.' . $base64Payload, $signature, $serviceAccount['private_key'], 'sha256WithRSAEncryption');
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    
    // Échanger JWT contre access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return $data['access_token'] ?? null;
}

function markActiveOrderViaAPI(int $coursierId, int $orderId): array {
    $url = baseUrl('api/set_active_order.php');
    return http_post_json($url, [
        'coursier_id'=>$coursierId,
        'commande_id'=>$orderId,
        'active'=>true
    ]);
}

function updateCommandeStatus(PDO $pdo, int $orderId, string $statut): void {
    $valid = ['nouvelle','assignee','acceptee','en_cours','picked_up','livree'];
    if (!in_array($statut, $valid, true)) throw new InvalidArgumentException('Statut invalide');
    $extra = '';
    if ($statut === 'assignee') $extra = ', assigned_at = NOW()';
    if ($statut === 'picked_up' || $statut === 'en_cours') $extra = ', picked_up_at = NOW()';
    if ($statut === 'livree') $extra = ', delivered_at = NOW()';
    $st = $pdo->prepare("UPDATE commandes SET statut = ?, updated_at = NOW() {$extra} WHERE id = ?");
    $st->execute([$statut, $orderId]);
}

function fetchTimeline(int $orderId): array {
    $url = baseUrl('api/timeline_sync.php?order_id=' . urlencode((string)$orderId));
    $resp = http_get($url);
    $data = json_decode($resp['body'] ?? '', true);
    return ['http'=>$resp,'json'=>$data];
}

function preflight(PDO $pdo): array {
    $issues = [];
    // Check Service Account or FCM_SERVER_KEY
    $sa = glob(__DIR__ . '/../coursier-suzosky-firebase-adminsdk-*.json');
    $hasSA = $sa && count($sa) > 0;
    $hasKey = getenv('FCM_SERVER_KEY') ? true : false;
    if (!$hasSA && !$hasKey) {
        $issues[] = 'FCM non configuré (ni Service Account, ni FCM_SERVER_KEY)';
    } else if ($hasSA) {
        $issues[] = '✓ Firebase Service Account détecté: ' . basename($sa[0]);
    }
    
    // Check for real FCM tokens
    try {
        $realTokens = $pdo->query("SELECT COUNT(*) as count FROM device_tokens WHERE is_active = 1 AND token NOT LIKE 'emergency_%' AND token NOT LIKE 'debug_%' AND token NOT LIKE 'local_test_%'")->fetchColumn();
        if ($realTokens == 0) {
            $issues[] = '⚠ Aucun vrai token FCM actif détecté - utilisation des tokens test';
        } else {
            $issues[] = "✓ {$realTokens} token(s) FCM réel(s) actif(s)";
        }
    } catch(Exception $e) {
        $issues[] = 'Erreur vérification tokens: ' . $e->getMessage();
    }
    // Check tables
    $tables = ['commandes','device_tokens','agents_suzosky'];
    foreach ($tables as $t) {
        if (!tableExists($pdo,$t)) $issues[] = "Table manquante: {$t} (sera créée si besoin)";
    }
    // Check coursiers actifs
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM agents_suzosky WHERE status='actif'");
        $activeCount = (int)$stmt->fetchColumn();
        if ($activeCount === 0) {
            $issues[] = 'Aucun coursier actif trouvé dans agents_suzosky';
        }
    } catch (Throwable $e) {
        $issues[] = 'Erreur vérification coursiers: ' . $e->getMessage();
    }
    return ['hasSA'=>$hasSA,'hasKey'=>$hasKey,'issues'=>$issues,'active_coursiers'=>$activeCount ?? 0];
}

function now(): string { return date('Y-m-d H:i:s'); }

// Controller
$coursierId = isset($_POST['coursier_id']) ? (int)$_POST['coursier_id'] : 0;
$providedToken = isset($_POST['token']) ? trim((string)$_POST['token']) : '';
$run = isset($_POST['run_all']);

$steps = [];

try {
    $pdo = db();
    $pf = preflight($pdo);
    $steps[] = ['name'=>'Préflight FCM/DB','status'=> empty($pf['issues']) ? 'PASS':'WARN', 'detail'=>$pf];

    if ($run) {
        if ($coursierId <= 0) throw new RuntimeException('Veuillez fournir coursier_id');

        // 1) Vérifier/enregistrer token FCM
        $tokenToUse = $providedToken;
        if ($providedToken !== '') {
            $url = baseUrl('api/register_device_token_simple.php');
            $resp = http_post_json($url, [
                'coursier_id'=>$coursierId,
                'token'=>$providedToken,
                'platform'=>'android',
                'app_version'=>'E2E_TEST'
            ]);
            $steps[] = ['name'=>'Enregistrement token FCM','status'=>($resp['code']>=200&&$resp['code']<300)?'PASS':'FAIL','detail'=>$resp];
        } else {
            // Chercher un token existant
            $existingToken = latestTokenForCoursier($pdo, $coursierId);
            if ($existingToken) {
                $tokenToUse = $existingToken;
                $steps[] = ['name'=>'Token FCM existant trouvé','status'=>'PASS','detail'=>['token_preview'=>substr($existingToken,0,20).'...']];
            } else {
                $steps[] = ['name'=>'Aucun token FCM','status'=>'WARN','detail'=>'Aucun token trouvé pour ce coursier - notifications impossibles'];
            }
        }

        // 2) Créer commande
        $order = createTestOrder($pdo, []);
        $steps[] = ['name'=>'Création commande','status'=> $order['id']? 'PASS':'FAIL', 'detail'=>$order];

        // 3) Assignation auto (+ FCM)
        $assign = assignAutomatically($pdo);
        $steps[] = ['name'=>'Assignation automatique','status'=> $assign['success']? 'PASS':'WARN', 'detail'=>$assign];

        // 4) FCM test explicite (pour sonnerie)
        if ($tokenToUse) {
            $fcm = sendTestFCM($pdo, $coursierId, $tokenToUse);
            $steps[] = ['name'=>'Envoi test FCM (son Suzosky)','status'=> $fcm['success']? 'PASS':'WARN', 'detail'=>$fcm];
        } else {
            $steps[] = ['name'=>'Envoi test FCM (son Suzosky)','status'=>'SKIP','detail'=>'Aucun token disponible'];
        }

        // 5) Récupérer l’ID exact de la commande (peut avoir été mise à jour par assign)
        $st = $pdo->prepare('SELECT id, coursier_id, statut FROM commandes WHERE id = ?');
        $st->execute([$order['id']]);
        $orderRow = $st->fetch(PDO::FETCH_ASSOC) ?: [];

        // 6) Marquer active et passer en acceptee
        $activeResp = markActiveOrderViaAPI($coursierId, (int)$order['id']);
        $steps[] = ['name'=>'Activation commande (API set_active_order)','status'=>($activeResp['code']>=200&&$activeResp['code']<300)?'PASS':'FAIL','detail'=>$activeResp];
        updateCommandeStatus($pdo, (int)$order['id'], 'acceptee');
        $steps[] = ['name'=>'Statut -> acceptee','status'=>'PASS','detail'=>['order_id'=>$order['id'],'at'=>now()]];

        // 7) Timeline après acceptation
        $tl1 = fetchTimeline((int)$order['id']);
        $tl1ok = (bool)($tl1['json']['success'] ?? false);
        $steps[] = ['name'=>'Timeline après acceptation','status'=>$tl1ok?'PASS':'FAIL','detail'=>$tl1];

        // 8) picked_up -> en_cours
        updateCommandeStatus($pdo, (int)$order['id'], 'picked_up');
        updateCommandeStatus($pdo, (int)$order['id'], 'en_cours');
        $steps[] = ['name'=>'Statut -> picked_up puis en_cours','status'=>'PASS','detail'=>['order_id'=>$order['id'],'at'=>now()]];

        $tl2 = fetchTimeline((int)$order['id']);
        $tl2ok = (bool)($tl2['json']['success'] ?? false) && in_array($tl2['json']['data']['statut'] ?? '', ['en_cours','livree'], true);
        $steps[] = ['name'=>'Timeline en cours','status'=>$tl2ok?'PASS':'FAIL','detail'=>$tl2];

        // 9) livree
        updateCommandeStatus($pdo, (int)$order['id'], 'livree');
        $steps[] = ['name'=>'Statut -> livree','status'=>'PASS','detail'=>['order_id'=>$order['id'],'at'=>now()]];
        $tl3 = fetchTimeline((int)$order['id']);
        $tl3ok = (bool)($tl3['json']['success'] ?? false) && (($tl3['json']['data']['statut'] ?? '') === 'livree');
        $steps[] = ['name'=>'Timeline livrée','status'=>$tl3ok?'PASS':'FAIL','detail'=>$tl3];
    }
} catch (Throwable $e) {
    $steps[] = ['name'=>'FATAL','status'=>'FAIL','detail'=>['message'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]];
}

function h($v){ return htmlspecialchars(is_scalar($v)? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); }

?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Suite E2E Suzosky (FCM, API, Assignation, Timeline)</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu; background:#0f1221; color:#f0f3ff; margin:0; padding:24px}
.container{max-width:1100px; margin:0 auto}
h1{color:#D4A853; margin:0 0 12px}
small.muted{color:#99a}
.card{background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:16px; margin:12px 0}
label{display:block; margin:8px 0 4px}
input[type="text"],input[type="number"]{width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.2); background:rgba(0,0,0,0.25); color:#fff}
button{background:#D4A853; color:#10131f; border:none; padding:10px 16px; border-radius:8px; font-weight:600; cursor:pointer}
.badge{display:inline-block; padding:3px 8px; border-radius:999px; font-size:12px; font-weight:700}
.badge.PASS{background:#14b8a6; color:#00110f}
.badge.WARN{background:#f59e0b; color:#160b00}
.badge.FAIL{background:#ef4444; color:#2b0000}
pre{white-space:pre-wrap; word-break:break-word; background:rgba(0,0,0,0.3); padding:10px; border-radius:8px}
.step{display:flex; align-items:flex-start; gap:10px; margin:8px 0}
.step .title{min-width:230px}
.meta{color:#ccd; font-size:13px}
</style>
</head>
<body>
<div class="container">
  <h1>Suite de tests E2E Suzosky</h1>
  <div class="meta">FCM • API • Assignation • Notification • Acceptation • Timeline</div>

  <form method="post" class="card">
    <div style="display:grid; grid-template-columns:1fr 2fr; gap:16px; align-items:end">
      <div>
        <label for="coursier_id">ID du coursier connecté</label>
        <input type="number" id="coursier_id" name="coursier_id" value="<?= h($coursierId ?: '') ?>" placeholder="ex: 3" required />
      </div>
      <div>
        <label for="token">Token FCM (optionnel, si vous voulez forcer l'enregistrement)</label>
        <input type="text" id="token" name="token" value="<?= h($providedToken ?: '') ?>" placeholder="FCM token de l'app coursier" />
      </div>
    </div>
    <div style="margin-top:12px">
      <button type="submit" name="run_all" value="1">Lancer le test complet</button>
    </div>
    <div class="meta" style="margin-top:8px">Astuce: laissez "Token FCM" vide si l'app est déjà connectée; le test lira le dernier token actif depuis la base.</div>
  </form>

  <div class="card">
    <h3>Résultats</h3>
    <?php if (empty($steps)): ?>
      <small class="muted">Remplissez les champs et cliquez sur "Lancer le test complet".</small>
    <?php else: ?>
      <?php foreach ($steps as $s): ?>
        <div class="step">
          <div class="title"><span class="badge <?= h($s['status']) ?>"><?= h($s['status']) ?></span> <?= h($s['name']) ?></div>
          <div style="flex:1">
            <pre><?php echo h($s['detail']); ?></pre>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Notes importantes</h3>
    <ul>
      <li>Le son de la notification (sonnerie Suzosky) est défini par le canal de notification Android. Assurez-vous qu'il est configuré dans l'app et activé sur le téléphone.</li>
      <li>En environnement local, la sécurité FCM sur index est en mode "fallback" si le fichier Scripts/Scripts cron/fcm_token_security.php est absent. Cela n'empêche pas ces tests.</li>
      <li>La timeline se base sur l'API <code>api/timeline_sync.php</code> qui lit la table <code>commandes</code>. Ce test met à jour directement cette table pour simuler les transitions.</li>
    </ul>
  </div>
</div>
</body>
</html>
