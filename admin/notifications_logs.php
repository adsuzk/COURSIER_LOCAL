<?php
// Simple viewer for notifications_log (protected by admin token session)
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/util.php';

function isAdminAuthenticated(): bool {
    return !empty($_SESSION['admin_ok']) && $_SESSION['admin_ok'] === true;
}

if (!isAdminAuthenticated()) {
    header('Location: notifications_admin.php');
    exit;
}

try {
  $pdo = getDBConnection();
  // Prefer the dedicated FCM log table, fallback to legacy table if needed
  $hasFcm = false;
  try { $pdo->query("SELECT 1 FROM notifications_log_fcm LIMIT 1"); $hasFcm = true; } catch (Throwable $e) { $hasFcm = false; }
  if ($hasFcm) {
    $stmt = $pdo->prepare("SELECT * FROM notifications_log_fcm ORDER BY id DESC LIMIT 200");
  } else {
    // Best-effort legacy support: create minimal table if absent
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS notifications_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coursier_id INT NULL,
        order_id INT NULL,
        notification_type VARCHAR(64) NULL,
        title VARCHAR(255) NULL,
        message TEXT NULL,
        fcm_tokens_used TEXT NULL,
        fcm_response_code INT NULL,
        fcm_response TEXT NULL,
        success TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
    $stmt = $pdo->prepare("SELECT * FROM notifications_log ORDER BY id DESC LIMIT 200");
  }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $rows = [];
    $err = $e->getMessage();
}

// h() fourni par lib/util.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Logs Notifications</title>
  <style>
    body{ font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin:20px; background:#0b1220; color:#e6edf3; }
    table{ width:100%; border-collapse: collapse; }
    th, td{ border:1px solid #1f2a44; padding:8px; font-size:13px; vertical-align: top; }
    th{ background:#111a2b; position:sticky; top:0; }
    a{ color:#58a6ff; }
    .ok{ color:#36d399; }
    .ko{ color:#f87171; }
  </style>
  <meta name="robots" content="noindex,nofollow" />
</head>
<body>
  <h1>Logs des notifications</h1>
  <div style="margin-bottom:12px;"><a href="notifications_admin.php">← Retour admin</a></div>
  <?php if (!empty($err)): ?><div style="color:#f87171;">Erreur: <?php echo h($err); ?></div><?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Date</th>
        <th>Coursier</th>
        <th>Order</th>
        <th>Type</th>
        <th>Titre</th>
        <th>Message</th>
        <th>Tokens</th>
        <th>HTTP</th>
        <th>Résultat FCM</th>
        <th>Succès</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo (int)$r['id']; ?></td>
          <td><?php echo h($r['created_at']); ?></td>
          <td><?php echo h($r['coursier_id']); ?></td>
          <td><?php echo h($r['order_id']); ?></td>
          <td><?php echo h($r['notification_type']); ?></td>
          <td><?php echo h($r['title']); ?></td>
          <td><?php echo h($r['message']); ?></td>
          <td style="max-width:260px; word-break: break-all;"><code><?php echo h($r['fcm_tokens_used']); ?></code></td>
          <td><?php echo h($r['fcm_response_code']); ?></td>
          <td style="max-width:340px; word-break: break-all;"><code><?php echo h($r['fcm_response']); ?></code></td>
          <td><?php echo !empty($r['success']) ? '<span class="ok">oui</span>' : '<span class="ko">non</span>'; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
<script>if (window.top !== window.self) { try{ top.location = location.href; }catch(e){} }</script>
</html>
