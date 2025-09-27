<?php
// Admin UI: FCM setup, token registration, test push (protected by admin token)
session_start();
// Si inclus via admin.php (section=notifications), on rend en inline sans re-déclarer le HTML global
$renderInline = isset($_GET['section']) && $_GET['section'] === 'notifications';
if (!$renderInline) header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/lib/fcm_enhanced.php';
require_once __DIR__ . '/../lib/util.php';

function isAdminAuthenticated(): bool {
    return !empty($_SESSION['admin_ok']) && $_SESSION['admin_ok'] === true;
}

function requireDb(): PDO {
    return getDBConnection();
}

$errors = [];
$messages = [];

// Handle login with admin token
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $token = trim($_POST['admin_token'] ?? '');
    $expected = (getenv('ADMIN_API_TOKEN') ?: ($GLOBALS['config']['admin']['api_token'] ?? ''));
    if ($token !== '' && $expected !== '' && hash_equals($expected, $token)) {
        $_SESSION['admin_ok'] = true;
        $messages[] = 'Authentification réussie.';
    } else {
        $errors[] = 'Token admin invalide.';
    }
}

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: notifications_admin.php');
    exit;
}

// Only proceed with admin actions if authenticated
if (isAdminAuthenticated()) {
    // Save FCM server key
    if (isset($_POST['action']) && $_POST['action'] === 'save_fcm_key') {
        try {
            $key = trim($_POST['fcm_key'] ?? '');
            if ($key === '') throw new Exception('Clé FCM vide.');
            $dataDir = __DIR__ . '/../data';
            if (!is_dir($dataDir)) @mkdir($dataDir, 0775, true);
            $keyFile = $dataDir . '/secret_fcm_key.txt';
            if (@file_put_contents($keyFile, $key) === false) throw new Exception('Impossible d\'écrire la clé (droits dossier ?).');
            // Charge immédiatement la clé dans l'env courant
            putenv('FCM_SERVER_KEY=' . $key);
            $messages[] = 'Clé FCM enregistrée avec succès.';
        } catch (Throwable $e) {
            $errors[] = 'Erreur enregistrement clé FCM: ' . $e->getMessage();
        }
    }

    // Register a device token manually
    if (isset($_POST['action']) && $_POST['action'] === 'register_token') {
        try {
            $pdo = requireDb();
            $coursierId = intval($_POST['coursier_id'] ?? 0);
            $token = trim($_POST['token'] ?? '');
            if ($coursierId <= 0 || $token === '') throw new Exception('Paramètres invalides');
            $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $pdo->prepare("INSERT INTO device_tokens (coursier_id, token) VALUES (?, ?) ON DUPLICATE KEY UPDATE coursier_id = VALUES(coursier_id), updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$coursierId, $token]);
            $messages[] = 'Token enregistré pour le coursier #' . $coursierId;
        } catch (Throwable $e) {
            $errors[] = 'Erreur enregistrement token: ' . $e->getMessage();
        }
    }

    // Send a test push to a coursier
    if (isset($_POST['action']) && $_POST['action'] === 'send_test_push') {
        try {
            $pdo = requireDb();
            $coursierId = intval($_POST['coursier_id'] ?? 0);
            $title = trim($_POST['title'] ?? 'Test Suzosky');
            $body  = trim($_POST['body']  ?? 'Notification de test');
            $orderId = intval($_POST['order_id'] ?? 0) ?: null;
            if ($coursierId <= 0) throw new Exception('Coursier invalide');
            $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmtTok = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC");
            $stmtTok->execute([$coursierId]);
            $tokens = array_column($stmtTok->fetchAll(PDO::FETCH_ASSOC), 'token');
            if (empty($tokens)) throw new Exception('Aucun token enregistré pour ce coursier');
            $res = fcm_send_with_log($tokens, $title, $body, ['type' => 'admin_test', 'order_id' => $orderId], $coursierId, $orderId);
            $ok = !empty($res['success']);
            $messages[] = 'Envoi FCM ' . ($ok ? 'réussi' : 'échoué') . ' (code: ' . ($res['code'] ?? 'n/a') . ')';
        } catch (Throwable $e) {
            $errors[] = 'Erreur envoi push: ' . $e->getMessage();
        }
    }
}

// Helpers for display
$isProd = isProductionEnvironment();
$fcmConfigured = getenv('FCM_SERVER_KEY') ? true : false;

// h() fourni par lib/util.php

?>
<?php if (!$renderInline): ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Administration Notifications</title>
  <style>
    body{ font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin:20px; background:#0b1220; color:#e6edf3; }
    .card{ background:#111a2b; border:1px solid #1f2a44; border-radius:12px; padding:16px; margin-bottom:16px; }
    .row{ display:flex; gap:16px; flex-wrap:wrap; }
    .col{ flex:1 1 360px; }
    input[type=text], textarea, input[type=number]{ width:100%; padding:10px; border-radius:8px; border:1px solid #2b3a5b; background:#0c162d; color:#e6edf3; }
    button{ background:#1f6feb; color:white; border:none; padding:10px 14px; border-radius:8px; cursor:pointer; }
    button:hover{ background:#316dca; }
    .ok{ color:#36d399; }
    .ko{ color:#f87171; }
    a{ color:#58a6ff; }
  </style>
  <meta name="robots" content="noindex,nofollow" />
  <meta http-equiv="Cache-Control" content="no-store" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <link rel="icon" href="data:," />
  <script>if (location.protocol !== 'https:') { /* can enforce https if desired */ }</script>
  <script>if (location.pathname.toLowerCase().includes('/test/')) location.href = '../admin/notifications_admin.php';</script>
  <script>if (window.top !== window.self) { try{ top.location = location.href; }catch(e){} }</script>
  <script>if (history.length > 50) history.go(0);</script>
  <!-- Basic content security policy -->
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';" />
  <!-- No caching -->
</head>
<body>
  <h1>Administration Notifications</h1>
<?php else: ?>
  <div class="container" style="padding: 16px;">
    <h2 style="margin-bottom:12px;">Administration Notifications</h2>
<?php endif; ?>

  <?php if ($errors): ?>
    <div class="card" style="border-color:#7f1d1d; background:#1f0b0b;">
      <strong>Erreurs</strong>
      <ul><?php foreach ($errors as $e) echo '<li>'.h($e).'</li>'; ?></ul>
    </div>
  <?php endif; ?>
  <?php if ($messages): ?>
    <div class="card" style="border-color:#0f5132; background:#0b2e1f;">
      <strong>Messages</strong>
      <ul><?php foreach ($messages as $m) echo '<li>'.h($m).'</li>'; ?></ul>
    </div>
  <?php endif; ?>

  <?php if (!isAdminAuthenticated()): ?>
    <div class="card" style="max-width:520px;">
      <h3>Connexion admin</h3>
      <form method="post">
        <input type="hidden" name="action" value="login" />
        <label>Token admin</label>
        <input type="text" name="admin_token" placeholder="Entrer le token admin" />
        <div style="margin-top:12px;"><button type="submit">Se connecter</button></div>
      </form>
      <p style="opacity:0.7; font-size:13px;">Le token est défini dans `config.php` (ADMIN_API_TOKEN).</p>
    </div>
  <?php else: ?>
    <form method="post" style="margin-bottom:16px;">
      <input type="hidden" name="action" value="logout" />
      <button type="submit">Se déconnecter</button>
    </form>

    <div class="row">
      <div class="col">
        <div class="card">
          <h3>Statut</h3>
          <ul>
            <li>Environnement: <?php echo $isProd? '<span class="ok">production</span>' : '<span class="ko">développement</span>'; ?></li>
            <li>Clé FCM configurée: <?php echo $fcmConfigured? '<span class="ok">oui</span>' : '<span class="ko">non</span>'; ?></li>
          </ul>
          <div><a href="notifications_logs.php">Voir les logs des notifications</a></div>
        </div>
      </div>
      <div class="col">
        <div class="card">
          <h3>Configurer la clé FCM</h3>
          <form method="post">
            <input type="hidden" name="action" value="save_fcm_key" />
            <label>Clé serveur FCM</label>
            <textarea name="fcm_key" rows="3" placeholder="AAAA..." spellcheck="false"></textarea>
            <div style="margin-top:12px;"><button type="submit">Enregistrer</button></div>
            <p style="opacity:0.7; font-size:13px;">La clé est stockée dans `data/secret_fcm_key.txt` et chargée comme variable d'environnement.</p>
          </form>
        </div>
      </div>
    </div>

    <div class="row">
      <div class="col">
        <div class="card">
          <h3>Enregistrer un token (manuel)</h3>
          <form method="post">
            <input type="hidden" name="action" value="register_token" />
            <label>Coursier ID</label>
            <input type="number" name="coursier_id" placeholder="ex: 5" />
            <label>Token FCM</label>
            <textarea name="token" rows="2" placeholder="collez ici le token FCM du téléphone" spellcheck="false"></textarea>
            <div style="margin-top:12px;"><button type="submit">Enregistrer le token</button></div>
          </form>
        </div>
      </div>
      <div class="col">
        <div class="card">
          <h3>Envoyer un push de test</h3>
          <form method="post">
            <input type="hidden" name="action" value="send_test_push" />
            <label>Coursier ID</label>
            <input type="number" name="coursier_id" placeholder="ex: 5" />
            <label>Titre</label>
            <input type="text" name="title" placeholder="Nouvelle commande" />
            <label>Message</label>
            <input type="text" name="body" placeholder="Une course vous a été attribuée" />
            <label>Order ID (optionnel)</label>
            <input type="number" name="order_id" placeholder="ex: 123" />
            <div style="margin-top:12px;"><button type="submit">Envoyer</button></div>
          </form>
        </div>
      </div>
    </div>

  <?php endif; ?>
  <div style="margin-top:24px; opacity:0.6; font-size:12px;">Admin Notifications Suzosky</div>
<?php if ($renderInline): ?>
  </div>
<?php else: ?>
</body>
</html>
<?php endif; ?>
