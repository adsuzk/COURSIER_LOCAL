<?php
// status_fcm.php — Page unique de diagnostic et d'action FCM
// Objectif: voir l'état (ENV, DB, clé FCM), le dernier token détecté, et déclencher un test de sonnerie.

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config.php';

$errors = [];
$notices = [];
$actions = [];

// Helpers simples
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function looks_like_real_fcm_token(string $t): bool {
    if ($t === '' || stripos($t, 'TEST_') === 0) return false;
    // Les tokens FCM V1 sont longs (>140), base64/urlsafe; on valide grossièrement par la longueur
    return strlen($t) >= 120; // seuil prudent
}

// Récupération DB
try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    $errors[] = 'Connexion DB impossible: ' . $e->getMessage();
    $pdo = null;
}

// Lire la clé FCM (via env_override: FCM_SERVER_KEY)
$fcmKey = getenv('FCM_SERVER_KEY') ?: '';
$fcmKeyInfo = 'Non définie';
if ($fcmKey) {
    $fcmKeyInfo = substr($fcmKey, 0, 8) . '… (' . strlen($fcmKey) . ' chars)';
}

// Détection compte de service pour liens directs
$saFile = getenv('FIREBASE_SERVICE_ACCOUNT_FILE');
if (!$saFile) {
  $cand = __DIR__ . '/data/firebase_service_account.json';
  if (is_file($cand)) $saFile = $cand;
}
$projectId = '';
$saEmail = '';
if ($saFile && is_file($saFile)) {
  $saJson = json_decode((string)@file_get_contents($saFile), true);
  if (is_array($saJson)) {
    $projectId = (string)($saJson['project_id'] ?? '');
    $saEmail = (string)($saJson['client_email'] ?? '');
  }
}

// Actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';
// Actions sans DB: import JSON compte de service et clé legacy
if ($action === 'upload_sa') {
  try {
    if (!isset($_FILES['sa_json']) || ($_FILES['sa_json']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Aucun fichier JSON reçu');
    }
    $tmp = $_FILES['sa_json']['tmp_name'] ?? '';
    $raw = $tmp ? @file_get_contents($tmp) : '';
    $j = json_decode((string)$raw, true);
    if (!is_array($j) || empty($j['type']) || empty($j['client_email']) || empty($j['private_key']) || empty($j['project_id'])) {
      throw new RuntimeException('Fichier JSON invalide (client_email/private_key/project_id requis)');
    }
    $dest = __DIR__ . '/data/firebase_service_account.json';
    if (!is_dir(dirname($dest))) { @mkdir(dirname($dest), 0775, true); }
    if (@file_put_contents($dest, $raw) === false) {
      throw new RuntimeException('Impossible d\'écrire le fichier sur disque');
    }
    putenv('FIREBASE_SERVICE_ACCOUNT_FILE=' . $dest);
    $notices[] = 'Compte de service importé — HTTP v1 activé.';
    // refresh variables locales
    $saFile = $dest;
    $projectId = (string)($j['project_id'] ?? '');
    $saEmail = (string)($j['client_email'] ?? '');
  } catch (Throwable $e) {
    $errors[] = 'Échec upload compte de service: ' . $e->getMessage();
  }
} elseif ($action === 'save_legacy') {
  try {
    $key = trim((string)($_POST['legacy_key'] ?? ''));
    if ($key === '') throw new RuntimeException('Clé vide');
    $dest = __DIR__ . '/data/secret_fcm_key.txt';
    if (!is_dir(dirname($dest))) { @mkdir(dirname($dest), 0775, true); }
    if (@file_put_contents($dest, $key) === false) {
      throw new RuntimeException('Écriture impossible');
    }
    putenv('FCM_SERVER_KEY=' . $key);
    $fcmKey = $key; $fcmKeyInfo = substr($fcmKey, 0, 8) . '… (' . strlen($fcmKey) . ' chars)';
    $notices[] = 'Clé serveur (legacy) enregistrée';
  } catch (Throwable $e) {
    $errors[] = 'Échec enregistrement clé legacy: ' . $e->getMessage();
  }
}
if ($action && $pdo) {
    switch ($action) {
        case 'clean_tokens':
            try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
          id INT AUTO_INCREMENT PRIMARY KEY,
          coursier_id INT NOT NULL,
          token TEXT NOT NULL,
          token_hash CHAR(64) NOT NULL,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_token_hash (token_hash),
          KEY idx_coursier (coursier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $pdo->exec("ALTER TABLE device_tokens MODIFY token TEXT NOT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER token"); } catch (Throwable $e) {}
        try { $pdo->exec("UPDATE device_tokens SET token_hash = SHA2(token,256) WHERE token_hash IS NULL OR token_hash = ''"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE device_tokens MODIFY token_hash CHAR(64) NOT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE device_tokens ADD UNIQUE KEY uniq_token_hash (token_hash)"); } catch (Throwable $e) {}
                $stmt = $pdo->prepare('DELETE FROM device_tokens WHERE coursier_id = 7');
                $stmt->execute();
                $notices[] = 'Tokens supprimés pour coursier_id=6';
            } catch (Throwable $e) {
                $errors[] = 'Erreur suppression tokens: ' . $e->getMessage();
            }
            break;
        case 'send_test':
            try {
                $stmt = $pdo->prepare('SELECT token FROM device_tokens WHERE coursier_id = 7 ORDER BY updated_at DESC LIMIT 1');
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $errors[] = "Aucun token pour coursier_id=6";
                } else {
                    $token = $row['token'];
                    require_once __DIR__ . '/api/lib/fcm_enhanced.php';
                    $res = fcm_send_with_log([
                        $token
                    ], '🔔 Test FCM', 'Essai de sonnerie immédiat', [
                        'type' => 'new_order',
                        'sound' => 'default',
                        'order_id' => 'TEST_' . time(),
                    ], 6, 'STATUS_PAGE_TEST');
                    $actions['send_test'] = $res;
                    if (!empty($res['success'])) $notices[] = 'Notification test envoyée (voir téléphone)';
                }
            } catch (Throwable $e) {
                $errors[] = 'Erreur envoi test FCM: ' . $e->getMessage();
            }
            break;
    case 'send_test_data_only':
      try {
        $stmt = $pdo->prepare('SELECT token FROM device_tokens WHERE coursier_id = 7 ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
          $errors[] = "Aucun token pour coursier_id=7";
        } else {
          $token = $row['token'];
          require_once __DIR__ . '/api/lib/fcm_enhanced.php';
          $res = fcm_send_with_log([
            $token
          ], '🔔 Test Data-Only', 'Forcer passage onMessageReceived', [
            'type' => 'new_order',
            'sound' => 'default',
            '_data_only' => true,
            'order_id' => 'TESTDATA_' . time(),
          ], 7, 'STATUS_PAGE_TESTDATA');
          $actions['send_test_data_only'] = $res;
          if (!empty($res['success'])) $notices[] = 'Notification data-only envoyée (voir téléphone)';
        }
      } catch (Throwable $e) {
        $errors[] = 'Erreur envoi test data-only FCM: ' . $e->getMessage();
      }
      break;
        case 'create_order':
            try {
                // Création d'une commande minimale assignée à CM20250001 (id=7)
                $pdo->exec("CREATE TABLE IF NOT EXISTS commandes (
                    id INT AUTO_INCREMENT PRIMARY KEY
                ) ENGINE=InnoDB"); // au cas où, ne définit pas le schéma complet

                // On tente un insert compatible avec le schéma courant: utiliser colonnes usuelles si présentes
                $now = date('Y-m-d H:i:s');
                // Détecter colonnes
                $cols = [];
                $q = $pdo->query('SHOW COLUMNS FROM commandes');
                foreach ($q as $c) { $cols[$c['Field']] = true; }

                $fields = [];
                $params = [];
                $add = function($name, $val) use (&$fields, &$params, $cols) {
                    if (isset($cols[$name])) { $fields[$name] = $val; }
                };

                $add('numero_commande', 'TEST-' . time());
                $add('statut', 'assignée');
                $add('etat', 'en_attente');
                $add('coursier_id', 7);
                $add('created_at', $now);
                $add('updated_at', $now);
                $add('adresse_retrait', 'Point A (test)');
                $add('adresse_livraison', 'Point B (test)');
                $add('prix_total', 0);
                $add('moyen_paiement', 'especes');

                if (empty($fields)) throw new RuntimeException('Schéma commandes inconnu, impossible d\'insérer un test');

                $sql = 'INSERT INTO commandes (' . implode(',', array_keys($fields)) . ') VALUES ('
                    . implode(',', array_fill(0, count($fields), '?')) . ')';
                $st = $pdo->prepare($sql);
                $st->execute(array_values($fields));
                $newId = (int)$pdo->lastInsertId();
                $notices[] = 'Commande test créée ID=' . $newId . ' pour l’agent CM20250001';
            } catch (Throwable $e) {
                $errors[] = 'Erreur création commande: ' . $e->getMessage();
            }
            break;
    }
}

// Lire dernier token
$latest = null;
if ($pdo) {
    try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (
      id INT AUTO_INCREMENT PRIMARY KEY,
      coursier_id INT NOT NULL,
      token TEXT NOT NULL,
      token_hash CHAR(64) NOT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_token_hash (token_hash),
      KEY idx_coursier (coursier_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token TEXT NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER token"); } catch (Throwable $e) {}
    try { $pdo->exec("UPDATE device_tokens SET token_hash = SHA2(token,256) WHERE token_hash IS NULL OR token_hash = ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens MODIFY token_hash CHAR(64) NOT NULL"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE device_tokens ADD UNIQUE KEY uniq_token_hash (token_hash)"); } catch (Throwable $e) {}

  $stmt = $pdo->prepare('SELECT token, updated_at FROM device_tokens WHERE coursier_id = 7 ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute();
        $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $errors[] = 'Erreur lecture token: ' . $e->getMessage();
    }
}

// Début rendu HTML simple
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Statut FCM — Coursier</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 20px; }
    .ok { color: #0a7a0a; }
    .warn { color: #a06a00; }
    .err { color: #b00020; }
    .card { border: 1px solid #ddd; border-radius: 8px; padding: 16px; margin: 10px 0; }
    .row { display: flex; gap: 16px; flex-wrap: wrap; }
    .row .card { flex: 1 1 300px; }
    code { background: #f6f8fa; padding: 2px 4px; border-radius: 4px; }
    .btns a { display: inline-block; margin-right: 8px; padding: 8px 12px; border-radius: 6px; text-decoration: none; border: 1px solid #ccc; }
    .btns a.primary { background: #0a7a0a; color: white; border-color: #0a7a0a; }
    .btns a.warn { background: #a06a00; color: white; border-color: #a06a00; }
    .btns a.danger { background: #b00020; color: white; border-color: #b00020; }
    pre { white-space: pre-wrap; word-wrap: break-word; background: #f9fafb; padding: 8px; border-radius: 6px; }
  </style>
  <meta http-equiv="refresh" content="15">
  <!-- Auto-refresh léger pour suivre l'arrivée d'un token -->
  <link rel="icon" href="data:,">
  <script>
    // Confirmation actions destructrices
  function confirmClean() { return confirm('Supprimer tous les tokens pour CM20250001 ?'); }
  </script>
  <?php /* cache-buster pour script/service worker éventuels */ ?>
</head>
<body>
  <h1>Statut FCM — Coursier</h1>

  <?php if ($errors): ?>
    <div class="card err">
      <strong>Erreurs:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($notices): ?>
    <div class="card ok">
      <strong>Infos:</strong>
      <ul>
        <?php foreach ($notices as $n): ?>
          <li><?= h($n) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
          <a class="primary" style="background:#004c92;border-color:#004c92" href="?action=send_test_data_only">🔧 Test data-only</a>
  <?php endif; ?>

  <div class="row">
    <div class="card">
      <h3>Environnement</h3>
      <p>ENV: <strong><?= isProductionEnvironment() ? 'production' : 'development' ?></strong></p>
      <p>Base URL: <code><?= h(getAppBaseUrl()) ?></code></p>
      <p>Clé FCM: <?= $fcmKey ? '<span class="ok">Détectée</span> — <code>' . h($fcmKeyInfo) . '</code>' : '<span class="err">Manquante</span>' ?></p>
      <?php if ($fcmKey && stripos($fcmKey, 'PLACEHOLDER_') === 0): ?>
        <p class="warn">La clé FCM semble factice (PLACEHOLDER). Remplacez le contenu de <code>data/secret_fcm_key.txt</code> par la VRAIE clé serveur Firebase (Project settings > Cloud Messaging > Server key).</p>
      <?php endif; ?>
      <?php 
        $sa = getenv('FIREBASE_SERVICE_ACCOUNT_FILE');
        $saStatus = $sa && file_exists($sa) ? '<span class="ok">Compte de service détecté</span>' : '<span class="warn">Compte de service absent</span>';
      ?>
      <p>HTTP v1: <?= $saStatus ?> <?= $sa && file_exists($sa) ? '(fichier: <code>'.h(basename($sa)).'</code>)' : '' ?></p>
      <?php if (!$sa || !file_exists($sa)): ?>
        <p style="margin-top:6px;">Pour activer l'API FCM HTTP v1 (recommandé):
          <br>- Firebase Console > Project Settings > Service accounts > Generate new private key
          <br>- Sauvegardez le JSON dans <code>data/firebase_service_account.json</code>
          <br>- Rechargez cette page
        </p>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Configuration FCM (rapide)</h3>
      <h4>1) Importer le compte de service (HTTP v1)</h4>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_sa" />
        <input type="file" name="sa_json" accept="application/json,.json" />
        <button type="submit">Téléverser le JSON</button>
      </form>
      <?php if ($projectId): ?>
        <p style="margin-top:8px;">Projet détecté: <code><?= h($projectId) ?></code><?php if ($saEmail): ?> — Compte: <code><?= h($saEmail) ?></code><?php endif; ?></p>
        <ul>
          <li><a target="_blank" href="https://console.cloud.google.com/iam-admin/serviceaccounts?project=<?= h(urlencode($projectId)) ?>&hl=fr">Comptes de service (liste)</a></li>
          <?php if ($saEmail): ?>
          <li><a target="_blank" href="https://console.cloud.google.com/iam-admin/serviceaccounts/details/<?= h(urlencode($saEmail)) ?>/keys?project=<?= h(urlencode($projectId)) ?>&hl=fr">Clés du compte (<?= h($saEmail) ?>)</a></li>
          <?php endif; ?>
          <li><a target="_blank" href="https://console.cloud.google.com/apis/library/fcm.googleapis.com?project=<?= h(urlencode($projectId)) ?>&hl=fr">Activer l'API FCM (HTTP v1)</a></li>
          <li><a target="_blank" href="https://console.firebase.google.com/project/<?= h(urlencode($projectId)) ?>/settings/cloudmessaging?hl=fr">Paramètres Cloud Messaging (legacy)</a></li>
        </ul>
      <?php endif; ?>

      <h4>2) Clé serveur (legacy) — option de secours</h4>
      <form method="post">
        <input type="hidden" name="action" value="save_legacy" />
        <textarea name="legacy_key" rows="2" style="width:100%;" placeholder="Collez la clé serveur (legacy) ici..."></textarea>
        <div style="margin-top:8px;"><button type="submit">Enregistrer la clé legacy</button></div>
      </form>
      <p style="margin-top:6px; font-size:90%;">Retrouvez la clé sur la page Cloud Messaging du projet Firebase. Évitez les clés commençant par <code>AIza</code> (ce ne sont pas des clés serveur).</p>
    </div>

    <div class="card">
  <h3>Token agent CM20250001 (ID=7)</h3>
      <?php if ($latest): ?>
        <p>Détecté: <strong><?= h(substr($latest['token'], 0, 48)) ?>…</strong></p>
        <p>Longueur: <?= strlen($latest['token']) ?> — Mise à jour: <?= h($latest['updated_at']) ?></p>
        <p>Qualité: <?= looks_like_real_fcm_token($latest['token']) ? '<span class="ok">Semble RÉEL</span>' : '<span class="warn">Semble non réel/simulé</span>' ?></p>
        <div class="btns">
          <a class="primary" href="?action=send_test">🔔 Envoyer un test (sonnerie)</a>
          <a class="danger" href="?action=clean_tokens" onclick="return confirmClean()">🗑️ Vider tokens</a>
        </div>
      <?php else: ?>
        <p class="err">Aucun token enregistré pour CM20250001.</p>
        <ol>
          <li>Installez/lancez l'app coursier en mode debug.</li>
          <li>Assurez-vous d'avoir le VRAI <code>google-services.json</code> côté Android.</li>
      <li>Assurez-vous d'avoir le VRAI <code>google-services.json</code> côté Android (fichier canonique : <code>CoursierAppV7/app/google-services.json</code>).</li>
          <li>Autorisez la permission Notifications sur le téléphone.</li>
          <li>Connectez-vous avec <strong>CM20250001 / g4mKU</strong> pour enregistrer le token.</li>
          <li>Revenez ici: la page se rafraîchit toutes les 15s.</li>
        </ol>
        <hr />
        <h4>Coller un token manuellement</h4>
        <form action="test_fcm_direct.php" method="post" target="_blank">
          <input type="hidden" name="coursier_id" value="7" />
          <textarea name="token" rows="4" style="width:100%;" placeholder="Collez ici un vrai token FCM..."></textarea>
          <div style="margin-top:8px;">
            <button type="submit">Enregistrer ce token</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3>Actions de test</h3>
      <div class="btns">
  <a class="warn" href="?action=create_order">➕ Créer une commande test (assignée à CM20250001)</a>
        <a href="check_token_realtime.php" target="_blank">🧪 Vue CLI (check_token_realtime.php)</a>
        <a href="monitor_fcm_realtime.php" target="_blank">📡 Monitor CLI (monitor_fcm_realtime.php)</a>
      </div>
      <p style="margin-top:8px;">Utilisez <code>test_fcm_notification.php</code> pour tester un envoi multi-tokens.</p>
    </div>
  </div>

  <?php
  // Historique des 15 derniers envois
  if ($pdo) {
      try {
          $hist = $pdo->query("SELECT id, created_at, title, notification_type, success, fcm_response_code, LEFT(fcm_response,160) AS snippet FROM notifications_log_fcm ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) { $hist = []; }
  }
  if (!empty($hist)): ?>
    <div class="card">
      <h3>Historique envois récents (15)</h3>
      <table style="border-collapse:collapse;width:100%;font-size:13px;">
        <thead>
          <tr style="text-align:left;border-bottom:1px solid #ccc;">
            <th>ID</th><th>Date</th><th>Titre</th><th>Type</th><th>Succès</th><th>HTTP</th><th>Extrait</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($hist as $r): ?>
          <tr style="border-top:1px solid #eee;">
            <td><?= (int)$r['id'] ?></td>
            <td><?= h($r['created_at']) ?></td>
            <td><?= h(mb_strimwidth($r['title'] ?? '',0,30,'…','UTF-8')) ?></td>
            <td><?= h($r['notification_type']) ?></td>
            <td><?= $r['success'] ? '<span class="ok">OK</span>' : '<span class="err">KO</span>' ?></td>
            <td><?= h($r['fcm_response_code']) ?></td>
            <td><code><?= h(mb_strimwidth($r['snippet'] ?? '',0,80,'…','UTF-8')) ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($pdo):
    // Santé globale: % succès sur 24h
    try {
        $m = $pdo->query("SELECT COUNT(*) total, SUM(success=1) ok FROM notifications_log_fcm WHERE created_at > NOW() - INTERVAL 24 HOUR")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { $m = null; }
    if ($m && $m['total']>0):
      $pct = round(100 * ($m['ok']/$m['total']),1);
  ?>
    <div class="card">
      <h3>Santé (24h)</h3>
      <p>Total: <strong><?= (int)$m['total'] ?></strong> — Succès: <strong><?= (int)$m['ok'] ?></strong> — Taux: <strong><?= $pct ?>%</strong></p>
      <p style="font-size:12px;">Objectif ≥ 99%. En cas de chute, vérifier IAM / expirations token.</p>
    </div>
  <?php endif; endif; ?>

  <?php if (!empty($actions)): ?>
    <div class="card">
      <h3>Résultats des actions</h3>
      <pre><?= h(json_encode($actions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
  <?php endif; ?>

  <div class="card">
    <h3>Aide rapide</h3>
    <ul>
      <li>Remplacez <code>data/secret_fcm_key.txt</code> par la VRAIE clé serveur Firebase (commence souvent par AAAA...)</li>
      <li>Assurez-vous que l'app Android utilise le <em>vrai</em> <code>google-services.json</code> du même projet Firebase.</li>
      <li>Une fois un token RÉEL détecté ici, cliquez sur « Envoyer un test »: le téléphone doit sonner même en arrière-plan.</li>
    </ul>
  </div>

</body>
</html>
