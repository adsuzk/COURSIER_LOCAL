<?php
// admin/diagnostic_telemetry.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/version_helpers.php';
header('Content-Type: text/html; charset=utf-8');

$err = null; $stats = []; $devices = [];

// Charger la version courante (source de vérité commune)
$vcfg = vu_load_versions_config();
vu_overlay_with_latest_upload($vcfg, false);
$latest = $vcfg['current_version'] ?? [];
$latestCode = (int)($latest['version_code'] ?? 0);
$latestName = $latest['version_name'] ?? '';
$latestUrl  = $latest['apk_url'] ?? '';
$latestSize = (int)($latest['apk_size'] ?? 0);
$latestDate = $latest['release_date'] ?? '';
$minSupported = (int)($latest['min_supported_version'] ?? 1);
$isForce = !empty($latest['force_update']);

try {
    $pdo = getPDO();
    // Tables existence quick check
    $tables = ['app_devices','app_crashes','app_events','app_sessions','app_versions','app_notifications'];
    $missing = [];
    foreach ($tables as $t) {
        try {
            $pdo->query("SELECT 1 FROM {$t} LIMIT 1");
        } catch (Throwable $e) {
            $missing[] = $t;
        }
    }
    if (!empty($missing)) {
        $err = 'Tables manquantes: ' . implode(', ', $missing);
    }

    // Counts
    $stats['devices']  = (int)$pdo->query("SELECT COUNT(*) FROM app_devices")->fetchColumn();
    $stats['crashes']  = (int)$pdo->query("SELECT COUNT(*) FROM app_crashes")->fetchColumn();
    $stats['events']   = (int)$pdo->query("SELECT COUNT(*) FROM app_events")->fetchColumn();
    $stats['sessions'] = (int)$pdo->query("SELECT COUNT(*) FROM app_sessions")->fetchColumn();

    // Last devices
    $stmt = $pdo->query("SELECT device_id, device_brand, device_model, android_version, app_version_name, app_version_code, last_seen FROM app_devices ORDER BY last_seen DESC LIMIT 20");
    $devices = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

} catch (Throwable $e) {
    $err = 'db_connect_failed: ' . $e->getMessage();
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8" />
<title>Diagnostic Télémétrie</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu;color:#eee;background:#121212;margin:0;padding:2rem}
.card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;margin-bottom:1rem;overflow:hidden}
.card-header{background:rgba(212,168,83,.12);padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,.1);color:#d4a853;font-weight:700}
.card-body{padding:1rem}
.btn{background:#2d2d2d;border:1px solid rgba(255,255,255,.2);color:#fff;padding:.5rem .75rem;border-radius:6px;cursor:pointer}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:.5rem;border-bottom:1px solid rgba(255,255,255,.08)}
.alert{padding:1rem;border-radius:8px}
.alert-error{background:#8e2a2a}
.alert-ok{background:#1f6f3a}
small{color:#bbb}
</style>
</head>
<body>
  <h1 style="margin-top:0;color:#d4a853;">Diagnostic Télémétrie</h1>
  <?php if ($err): ?>
    <div class="alert alert-error">⚠️ <?php echo htmlspecialchars($err); ?></div>
  <?php else: ?>
    <div class="card">
      <div class="card-header">Résumé</div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
          <div class="card"><div class="card-body"><b>Appareils</b><div style="font-size:1.5rem;"><?php echo (int)$stats['devices']; ?></div></div></div>
          <div class="card"><div class="card-body"><b>Crashes</b><div style="font-size:1.5rem;"><?php echo (int)$stats['crashes']; ?></div></div></div>
          <div class="card"><div class="card-body"><b>Événements</b><div style="font-size:1.5rem;"><?php echo (int)$stats['events']; ?></div></div></div>
          <div class="card"><div class="card-body"><b>Sessions</b><div style="font-size:1.5rem;"><?php echo (int)$stats['sessions']; ?></div></div></div>
        </div>
      </div>
    </div>

      <div class="card">
        <div class="card-header">Dernière version disponible</div>
        <div class="card-body">
          <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;">
            <div><b>Version</b><div style="font-size:1.1rem;"><?php echo htmlspecialchars($latestName); ?> (code <?php echo (int)$latestCode; ?>)</div></div>
            <div><b>Obligation</b><div><?php echo $isForce ? 'Mise à jour obligatoire' : 'Optionnelle'; ?><?php if ($minSupported>0): ?> <small>(min support: <?php echo (int)$minSupported; ?>)</small><?php endif; ?></div></div>
            <?php if ($latestDate): ?><div><b>Publiée</b><div><small><?php echo htmlspecialchars($latestDate); ?></small></div></div><?php endif; ?>
            <?php if ($latestUrl): ?><div><a class="btn" href="<?php echo htmlspecialchars($latestUrl); ?>" download>Télécharger l’APK</a></div><?php endif; ?>
            <?php if ($latestSize): ?><div><small><?php echo number_format($latestSize/1048576, 2); ?> Mo</small></div><?php endif; ?>
          </div>
        </div>
      </div>

    <div class="card">
      <div class="card-header">Derniers appareils vus</div>
      <div class="card-body">
        <?php if (empty($devices)): ?>
          <div class="alert">Aucun appareil encore présent. Utilisez le test ci-dessous.</div>
        <?php else: ?>
          <table class="table">
            <thead><tr>
              <th>Device</th><th>Modèle</th><th>Android</th><th>App</th><th>Vu</th>
            </tr></thead>
            <tbody>
              <?php foreach ($devices as $d): ?>
                <tr>
                  <td style="font-family:monospace;"><?php echo htmlspecialchars($d['device_id']); ?></td>
                  <td><?php echo htmlspecialchars(trim(($d['device_brand']??'').' '.($d['device_model']??''))); ?></td>
                  <td><?php echo htmlspecialchars($d['android_version']??''); ?></td>
                  <td><?php echo htmlspecialchars(($d['app_version_name']??'').' ('.(int)($d['app_version_code']??0).')'); ?></td>
                  <td><small><?php echo htmlspecialchars($d['last_seen']??''); ?></small></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Test rapide de connectivité / upsert</div>
      <div class="card-body">
        <p>Ces tests appellent <code>/api/app_updates.php</code> pour simuler un appareil. « À jour » utilise la version courante (update_available=false attendu). « Obsolète » utilise une version inférieure (update_available=true attendu).</p>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
          <button class="btn" id="btnTestLatest">Simuler appareil à jour (code <?php echo (int)$latestCode; ?>)</button>
          <button class="btn" id="btnTestOutdated">Simuler appareil obsolète (code <?php echo max(0, $latestCode-1); ?>)</button>
          <button class="btn" id="btnReload">Rafraîchir la liste</button>
        </div>
        <div id="out" style="margin-top:1rem;white-space:pre-wrap;background:rgba(255,255,255,.05);padding:.5rem;border-radius:6px;"></div>
      </div>
    </div>
  <?php endif; ?>

<script>
const out = document.getElementById('out');
function logOut(t){ out.textContent = (out.textContent? out.textContent + "\n\n" : '') + t; }
async function callUpdates(deviceId, versionCode, versionName){
  try {
    logOut('Appel en cours… device_id=' + deviceId + ', code=' + versionCode);
    const url = `/api/app_updates.php?device_id=${encodeURIComponent(deviceId)}&version_code=${encodeURIComponent(versionCode)}&device_model=DiagPhone&device_brand=Diag&android_version=14&version_name=${encodeURIComponent(versionName)}`;
    const r = await fetch(url, {cache:'no-store'});
    const t = await r.text();
    logOut(t);
  } catch (e) {
    logOut('Erreur: ' + e.message);
  }
}

document.getElementById('btnTestLatest')?.addEventListener('click', ()=>{
  callUpdates('TEST_UP_TO_DATE', <?php echo (int)$latestCode; ?>, <?php echo json_encode($latestName ?: '1.0'); ?>);
});
document.getElementById('btnTestOutdated')?.addEventListener('click', ()=>{
  const outdated = Math.max(0, <?php echo (int)$latestCode; ?> - 1);
  callUpdates('TEST_OUTDATED', outdated, <?php echo json_encode($latestName ?: '1.0'); ?>);
});
document.getElementById('btnReload')?.addEventListener('click', ()=>location.reload());
</script>
</body>
</html>
