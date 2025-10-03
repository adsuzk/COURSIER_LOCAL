<?php
/**
 * diagnostic_fcm_full.php
 * Diagnostic complet côté serveur (prod ou local) pour vérifier:
 *  - Environnement et URLs (index à / vs /index.php)
 *  - Configuration FCM (compte de service / clé serveur) du projet coursier-suzosky
 *  - Schéma et état des tokens (device_tokens)
 *  - Disponibilité côté index (API get_coursier_availability.php)
 *  - Reachability des endpoints d’enregistrement/ping (sans écrire par défaut)
 *
 * Paramètres optionnels (GET):
 *  - coursier_id: entier, pour lister ses tokens récents
 *  - test_ping=1: exécute un ping d’écriture si vous fournissez aussi token
 *  - token: token FCM (utilisé uniquement si test_ping=1)
 *  - verbose=1: traces étendues
 *  - threshold: secondes fraicheur pour disponibilité (défaut 60)
 */

header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('UTC');

require_once __DIR__ . '/config.php';

function out($s='') { echo $s, "\n"; }
function kv($k, $v) { out(str_pad($k . ':', 34) . (is_scalar($v) ? $v : json_encode($v))); }
function sec_preview($s, $keep=4) { if ($s === null || $s === '') return '(vide)'; return substr($s,0,$keep) . '…'; }
function curl_head_or_get($url, $method='GET', $body=null, $headers=[]) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    if (strtoupper($method) === 'HEAD') curl_setopt($ch, CURLOPT_NOBODY, true);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err = curl_error($ch);
    curl_close($ch);
    $headers = substr($resp ?: '', 0, $hdrSize);
    $body = substr($resp ?: '', $hdrSize);
    return [$http, $headers, $body, $err];
}

out("===== DIAGNOSTIC COURSIIER/FCM (" . date('c') . ") =====");

// 0) PHP ENV
out("\n[0] PHP ENV");
kv('PHP version', PHP_VERSION);
kv('Extensions (subset)', implode(', ', array_intersect(['curl','openssl','pdo','pdo_mysql','json','mbstring'], get_loaded_extensions())));
kv('memory_limit', ini_get('memory_limit'));
kv('timezone', date_default_timezone_get());
kv('SERVER_NAME', $_SERVER['SERVER_NAME'] ?? '(n/a)');
kv('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'] ?? '(n/a)');
kv('SCRIPT_FILENAME', $_SERVER['SCRIPT_FILENAME'] ?? '(n/a)');

// 1) CONTEXTE ENVIRONNEMENT
out("\n[1] ENVIRONNEMENT");
$isProd = isProductionEnvironment();
$scheme = getRequestScheme();
$host = getServerHost();
$basePath = getAppBasePath();
$baseUrl = getAppBaseUrl();
kv('isProductionEnvironment', $isProd ? 'true' : 'false');
kv('HTTP_HOST', $host);
kv('REQUEST_SCHEME', $scheme);
kv('App Base Path', $basePath);
kv('App Base URL', $baseUrl);
kv('Filesystem index.php exists', file_exists(__DIR__ . '/index.php') ? 'yes' : 'no');
kv('Filesystem api/register exists', file_exists(__DIR__ . '/api/register_device_token_simple.php') ? 'yes' : 'no');
kv('Filesystem api/ping exists', file_exists(__DIR__ . '/api/ping_device_token.php') ? 'yes' : 'no');
kv('Filesystem api/availability exists', file_exists(__DIR__ . '/api/get_coursier_availability.php') ? 'yes' : 'no');

// 2) INDEX URL / vs /index.php
out("\n[2] INDEX / vs /index.php (HTTP test)");
$rootUrl = rtrim($baseUrl, '/') . '/';
$indexUrl = rtrim($baseUrl, '/') . '/index.php';
[$c1, $h1, $b1, $e1] = curl_head_or_get($rootUrl, 'GET');
[$c2, $h2, $b2, $e2] = curl_head_or_get($indexUrl, 'GET');
kv('GET / HTTP', $c1 . ($e1 ? " (err=$e1)" : ''));
kv('GET /index.php HTTP', $c2 . ($e2 ? " (err=$e2)" : ''));
kv('Body / preview', sec_preview(trim(strip_tags(substr($b1,0,120))), 40));
kv('Body /index.php preview', sec_preview(trim(strip_tags(substr($b2,0,120))), 40));
if ($c1 >= 200 && $c1 < 400 && $c2 >= 200 && $c2 < 400) {
    kv('Index mapping OK', 'oui (les deux répondent)');
} else {
    kv('Index mapping OK', 'non (vérifier la config/hébergement)');
}
// Similarité basique des bodies (normalisés)
$n1 = preg_replace('/\s+/', ' ', strip_tags($b1 ?? ''));
$n2 = preg_replace('/\s+/', ' ', strip_tags($b2 ?? ''));
$same = ($n1 !== '' && $n2 !== '' && substr(md5($n1),0,8) === substr(md5($n2),0,8));
kv('Index / et /index.php similaires', $same ? 'oui (hash~)' : 'non (contenu différent)');

// 3) CONFIGURATION FCM (coursier-suzosky)
out("\n[3] FCM / Firebase");
$saPath = getenv('FIREBASE_SERVICE_ACCOUNT_FILE');
if (!$saPath) {
    $candidate = __DIR__ . '/data/firebase_service_account.json';
    if (file_exists($candidate)) $saPath = realpath($candidate);
    else {
        $matches = glob(__DIR__ . '/coursier-suzosky-firebase-adminsdk-*.json');
        if (!empty($matches)) $saPath = realpath($matches[0]);
    }
}
$projectId = null;
if ($saPath && file_exists($saPath)) {
    $saJson = json_decode(@file_get_contents($saPath), true);
    $projectId = $saJson['project_id'] ?? null;
}
$envKey = getenv('FCM_SERVER_KEY') ?: '';
$secretFile = __DIR__ . '/data/secret_fcm_key.txt';
$secretKey = '';
if (file_exists($secretFile)) $secretKey = trim(@file_get_contents($secretFile));
kv('Service account file', $saPath ?: '(absent)');
kv('Firebase project_id', $projectId ?: '(inconnu)');
kv('FCM_SERVER_KEY (env)', $envKey ? ('présente (' . strlen($envKey) . ' chars)') : '(absente)');
kv('secret_fcm_key.txt', $secretKey ? ('présente (' . strlen($secretKey) . ' chars)') : '(absente/ou vide)');
if ($projectId) kv('FCM envoi (préférence)', 'HTTP v1 (compte de service)');
elseif ($envKey || $secretKey) kv('FCM envoi (préférence)', 'Legacy key');
else kv('FCM envoi (préférence)', 'Aucun moyen détecté (à configurer)');

// 4) DEVICE TOKENS & DISPONIBILITÉ
out("\n[4] Device tokens & disponibilité");
$pdo = null; $errDb = null;
try { $pdo = getDBConnection(); } catch (Throwable $e) { $errDb = $e->getMessage(); }
if (!$pdo) {
    kv('DB connection', 'ERREUR: ' . $errDb);
} else {
    kv('DB connection', 'OK');
    try {
        $meta = $pdo->query("SELECT DATABASE() db, USER() user, @@hostname host, VERSION() ver, @@system_time_zone tzsys, @@time_zone tzg")->fetch(PDO::FETCH_ASSOC);
        if ($meta) {
            kv('DB database()', $meta['db'] ?? '(n/a)');
            kv('DB user()', $meta['user'] ?? '(n/a)');
            kv('DB @@hostname', $meta['host'] ?? '(n/a)');
            kv('DB version()', $meta['ver'] ?? '(n/a)');
            kv('DB timezone', ($meta['tzg'] ?? '(n/a)') . ' (sys=' . ($meta['tzsys'] ?? '(n/a)') . ')');
            $srvNow = $pdo->query('SELECT NOW() nowts')->fetchColumn();
            kv('DB NOW()', $srvNow ?: '(n/a)');
        }
    } catch (Throwable $e) {
        kv('DB metadata', 'ERREUR: '.$e->getMessage());
    }
    // Schéma minimal
    $hasTable = false;
    try {
        $st = $pdo->query("SHOW TABLES LIKE 'device_tokens'");
        $hasTable = $st && $st->fetchColumn();
    } catch (Throwable $e) { $hasTable = false; }
    kv('Table device_tokens', $hasTable ? 'présente' : 'absente');

    $threshold = 60;
    if (isset($_GET['threshold']) && is_numeric($_GET['threshold'])) $threshold = max(10, (int)$_GET['threshold']);
    if ($hasTable) {
        $hasLastPing = false; $hasIsActive = false;
        try { $hasLastPing = (bool)$pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'last_ping'")->fetchColumn(); } catch (Throwable $e) {}
        try { $hasIsActive = (bool)$pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'is_active'")->fetchColumn(); } catch (Throwable $e) {}
            // Lister colonnes existantes
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM device_tokens")->fetchAll(PDO::FETCH_COLUMN, 0);
                kv('Colonnes device_tokens', $cols ? implode(', ', $cols) : '(n/a)');
            } catch (Throwable $e) { kv('Colonnes device_tokens', 'ERREUR: '.$e->getMessage()); }
        $timeExpr = $hasLastPing ? "COALESCE(last_ping, updated_at)" : "updated_at";
        if ($hasIsActive) {
            $sql = "SELECT SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) active_count,
                           SUM(CASE WHEN is_active=1 AND TIMESTAMPDIFF(SECOND, {$timeExpr}, NOW()) <= :t THEN 1 ELSE 0 END) fresh_count,
                           MAX(CASE WHEN is_active=1 THEN {$timeExpr} END) last_active_at,
                           MAX({$timeExpr}) last_seen_at
                    FROM device_tokens";
        } else {
            $sql = "SELECT COUNT(*) active_count,
                           SUM(CASE WHEN TIMESTAMPDIFF(SECOND, {$timeExpr}, NOW()) <= :t THEN 1 ELSE 0 END) fresh_count,
                           MAX({$timeExpr}) last_active_at,
                           MAX({$timeExpr}) last_seen_at
                    FROM device_tokens";
        }
        $st = $pdo->prepare($sql);
        $st->execute([':t' => $threshold]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $active = (int)($row['active_count'] ?? 0);
        $fresh  = (int)($row['fresh_count'] ?? 0);
        $la = $row['last_active_at'] ?? $row['last_seen_at'] ?? null;
        // Calcul côté DB pour éviter les décalages de fuseau
        $since = null; $dbPhpDelta = null;
        try {
            $dbNow = $pdo->query('SELECT NOW()')->fetchColumn();
            if ($dbNow) {
                $phpNow = gmdate('Y-m-d H:i:s');
                $dbPhpDelta = strtotime($dbNow) - strtotime($phpNow);
                kv('DB-PHP time delta (s)', $dbPhpDelta);
            }
        } catch (Throwable $e) { /* ignore */ }
        if ($la) {
            try {
                $stmtDiff = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, :last, NOW())");
                $stmtDiff->execute([':last' => $la]);
                $since = (int)$stmtDiff->fetchColumn();
            } catch (Throwable $e) {
                $since = null;
            }
        }
        kv('active_count', $active);
        kv('fresh_count (<= '.$threshold.'s)', $fresh);
        kv('last_active_at', $la ?: '(n/a)');
        kv('seconds_since_last_active', $since !== null ? $since : '(n/a)');
        kv('availability (rule)', ($fresh>0 || $active>0) ? 'OUVERT' : 'FERMÉ');

        // TOP 5 récents (tous)
        try {
            $st2 = $pdo->query("SELECT coursier_id, is_active, LEFT(token,24) AS preview, platform, app_version, COALESCE(last_ping, updated_at) AS seen_at FROM device_tokens ORDER BY COALESCE(last_ping, updated_at) DESC LIMIT 5");
            $rows2 = $st2->fetchAll(PDO::FETCH_ASSOC);
            out('Top 5 tokens récents (tous):');
            foreach ($rows2 as $r2) out("- c={$r2['coursier_id']} active={$r2['is_active']} token={$r2['preview']}… platform={$r2['platform']} appv={$r2['app_version']} seen_at={$r2['seen_at']}");
            if (empty($rows2)) out('(aucun)');
        } catch (Throwable $e) { out('ERREUR Top 5: '.$e->getMessage()); }
    }
}

// 5) API REACHABILITY (GET only par défaut)
out("\n[5] API reachability");
$availUrl = rtrim($baseUrl, '/') . '/api/get_coursier_availability.php';
[$ac, $_, $abody, $ae] = curl_head_or_get($availUrl, 'GET');
kv('GET get_coursier_availability', $ac . ($ae ? " (err=$ae)" : ''));
if ($ac >= 200 && $ac < 300) {
    $trim = trim($abody);
    kv('availability JSON preview', sec_preview($trim, 80));
}
// register / ping endpoints (GET reachability)
$regUrl = rtrim($baseUrl, '/') . '/api/register_device_token_simple.php';
[$rc, $_h, $rbody, $re] = curl_head_or_get($regUrl, 'GET');
kv('GET register_device_token_simple', $rc . ($re ? " (err=$re)" : ''));
$pingUrlView = rtrim($baseUrl, '/') . '/api/ping_device_token.php';
[$pcv, $_h2, $pbodyv, $pev] = curl_head_or_get($pingUrlView, 'GET');
kv('GET ping_device_token', $pcv . ($pev ? " (err=$pev)" : ''));

// 5b) Logique exacte utilisée par l'index (FCMTokenSecurity)
out("\n[5b] Logique index (FCMTokenSecurity)");
try {
    require_once __DIR__ . '/fcm_token_security.php';
    if (class_exists('FCMTokenSecurity')) {
        $sec = new FCMTokenSecurity(['verbose' => false]);
        $res = $sec->canAcceptNewOrders();
        kv('can_accept_orders', isset($res['can_accept_orders']) && $res['can_accept_orders'] ? 'true' : 'false');
        kv('available_coursiers', $res['available_coursiers'] ?? '(n/a)');
        kv('fresh_coursiers', $res['fresh_coursiers'] ?? '(n/a)');
        kv('detection_mode', $res['detection_mode'] ?? '(n/a)');
        kv('threshold_seconds', $res['threshold_seconds'] ?? '(n/a)');
        kv('seconds_since_last_active (index view)', $res['seconds_since_last_active'] ?? '(n/a)');
        kv('last_active_at (index view)', $res['last_active_at'] ?? '(n/a)');
    } else {
        out('FCMTokenSecurity introuvable (classe)');
    }
} catch (Throwable $e) {
    out('Erreur FCMTokenSecurity: ' . $e->getMessage());
}

// 6) DIAGNOSTIC LOGS (dernières lignes)
out("\n[6] Logs (dernieres entrées)");
$logDir = __DIR__ . '/diagnostic_logs';
kv('diagnostic_logs writable', (is_dir($logDir) ? (is_writable($logDir) ? 'oui' : 'non (dir existe mais non inscriptible)') : (@mkdir($logDir, 0775, true) ? 'créé' : 'non (échec creation)')));
foreach (['token_reg.log','token_ping.log','db_connection_errors.log'] as $lf) {
    $p = $logDir . '/' . $lf;
    if (file_exists($p)) {
        $lines = @file($p) ?: [];
        $tail = array_slice($lines, -5);
        out("- $lf:");
        foreach ($tail as $ln) out("  " . rtrim($ln));
    } else {
        out("- $lf: (absent)");
    }
}

// 7) TOKENS PAR COURSIIER (option)
if ($pdo) {
    $cid = isset($_GET['coursier_id']) ? (int)$_GET['coursier_id'] : 0;
    if ($cid > 0) {
        out("\n[7] Tokens pour coursier_id=".$cid);
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token TEXT NOT NULL, token_hash CHAR(64), is_active TINYINT(1) DEFAULT 1, platform VARCHAR(32), app_version VARCHAR(64), updated_at DATETIME, last_ping DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Throwable $e) {}
        try {
            $st = $pdo->prepare("SELECT id, is_active, platform, app_version, LEFT(token,24) AS preview, updated_at, last_ping FROM device_tokens WHERE coursier_id = ? ORDER BY COALESCE(last_ping, updated_at) DESC LIMIT 5");
            $st->execute([$cid]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) out("- id={$r['id']} active={$r['is_active']} platform={$r['platform']} appv={$r['app_version']} token={$r['preview']}… last_ping={$r['last_ping']} updated={$r['updated_at']}");
            if (empty($rows)) out('(aucun token)');
        } catch (Throwable $e) { out('ERREUR lecture tokens: '.$e->getMessage()); }
    }
}

// 8) TEST PING (écriture) – uniquement si demandé
$doPing = isset($_GET['test_ping']) && $_GET['test_ping'] == '1';
if ($doPing) {
    $cid = isset($_GET['coursier_id']) ? (int)$_GET['coursier_id'] : 0;
    $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
    out("\n[8] TEST PING (écriture) vers api/ping_device_token.php");
    if ($cid <= 0 || $token === '') {
        out('Paramètres requis: coursier_id et token');
    } else {
        $pingUrl = rtrim($baseUrl, '/') . '/api/ping_device_token.php';
        $form = http_build_query([
            'coursier_id' => $cid,
            'token' => $token,
            'platform' => 'android',
            'app_version' => 'diagnostic'
        ]);
        [$pc, $ph, $pb, $pe] = curl_head_or_get($pingUrl, 'POST', $form, ['Content-Type: application/x-www-form-urlencoded']);
        kv('POST ping_device_token', $pc . ($pe ? " (err=$pe)" : ''));
        kv('Ping response preview', sec_preview(trim($pb), 80));
    }
}

// 9) RÉSUMÉ
out("\n[9] RÉSUMÉ");
out("- Environnement: " . ($isProd ? 'PROD' : 'DEV') . " (host=$host, basePath=$basePath)");
out("- Index URLs: / -> HTTP $c1; /index.php -> HTTP $c2");
if ($projectId) out("- FCM: Compte de service OK (projet=$projectId)");
elseif ($envKey || $secretKey) out("- FCM: Clé legacy présente (assurez-vous qu’elle correspond au même projet Firebase)");
else out("- FCM: AUCUNE config trouvée (ni service account, ni clé)");
if (isset($active)) out("- Tokens: active=$active, fresh($threshold s)=$fresh, disponibilité=" . (($fresh>0 || $active>0) ? 'OUVERT' : 'FERMÉ'));
out("- API availability: HTTP $ac");
out("\nCopiez-collez l’intégralité de ce rapport ici pour analyse.");

?>
