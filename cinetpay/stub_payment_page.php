<?php
require_once __DIR__ . '/../config.php';
if (isProductionEnvironment()) { http_response_code(404); exit('Not allowed'); }
$tid = $_GET['tid'] ?? 'LOCAL';
$amount = $_GET['amount'] ?? '0';
$callback = appUrl('api/cinetpay_callback.php');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Stub Paiement Local - CinetPay</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#0f172a;color:#e2e8f0}
.card{background:#111827;border:1px solid #1f2937;border-radius:12px;padding:28px;max-width:520px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
.btn{display:inline-block;padding:10px 16px;border-radius:8px;text-decoration:none}
.btn-success{background:#16a34a;color:#fff}
.btn-fail{background:#dc2626;color:#fff}
small{color:#94a3b8}
</style>
</head>
<body>
  <div class="card">
    <h1>Simulation Paiement</h1>
    <p>Transaction: <strong><?= htmlspecialchars($tid) ?></strong></p>
    <p>Montant: <strong><?= htmlspecialchars($amount) ?> XOF</strong></p>
    <p><small>Cette page simule l'interface CinetPay pour les tests en local.</small></p>
    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn btn-success" href="<?= $callback ?>?cpm_site_id=LOCAL&cpm_trans_id=<?= urlencode($tid) ?>&cpm_amount=<?= urlencode($amount) ?>&cpm_currency=XOF&cpm_result=00&cpm_trans_date=<?= urlencode(date('Y-m-d H:i:s')) ?>">Simuler Succès</a>
      <a class="btn btn-fail" href="<?= $callback ?>?cpm_site_id=LOCAL&cpm_trans_id=<?= urlencode($tid) ?>&cpm_amount=<?= urlencode($amount) ?>&cpm_currency=XOF&cpm_result=99&cpm_trans_date=<?= urlencode(date('Y-m-d H:i:s')) ?>">Simuler Échec</a>
    </div>
  </div>
</body>
</html>
