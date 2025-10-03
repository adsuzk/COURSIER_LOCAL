<?php
// SECTION ADMIN - ENVOI D'EMAILS (PHPMailer via lib/Mailer.php)
if (!function_exists('getPDO')) { require_once __DIR__ . '/../config.php'; }
require_once __DIR__ . '/../lib/Mailer.php';

// DB
try { $pdo = getPDO(); } catch (Throwable $e) {
    echo '<div class="alert" style="background:#dc3545;color:#fff;padding:12px;border-radius:6px">Erreur DB: ' . htmlspecialchars($e->getMessage()) . '</div>'; return;
}

$flash = function(string $msg, bool $ok=true) {
    $bg = $ok ? '#28a745' : '#dc3545';
    echo '<div style="background:' . $bg . ';color:#fff;padding:10px 14px;border-radius:8px;margin:10px 0">' . htmlspecialchars($msg) . '</div>';
};

// Handle POST send
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'send_mass') {
    $subject = trim($_POST['subject'] ?? '');
    $html    = trim($_POST['html'] ?? '');
    $limit   = max(1, min(1000, (int)($_POST['limit'] ?? 200)));
    $offset  = max(0, (int)($_POST['offset'] ?? 0));
    $test    = !empty($_POST['test_mode']);
    $testTo  = trim($_POST['test_email'] ?? '');

    if ($subject === '' || $html === '') {
        $flash('Sujet et contenu requis', false);
    } else {
        try {
            $mailer = new Mailer();
            if ($test) {
                if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) { $flash('Email de test invalide', false); }
                else {
                    $res = $mailer->sendHtml($testTo, 'Test', $subject, $html);
                    $flash($res['success'] ? 'Email de test envoyé' : ('Échec: ' . ($res['error'] ?? '')) , $res['success']);
                }
            } else {
                // Fetch recipients (clients_particuliers)
                $stmt = $pdo->prepare("SELECT email, prenoms, nom FROM clients_particuliers WHERE email IS NOT NULL AND email <> '' LIMIT ? OFFSET ?");
                $stmt->bindValue(1, $limit, PDO::PARAM_INT);
                $stmt->bindValue(2, $offset, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $ok=0;$ko=0; $logDir = __DIR__ . '/../EMAIL_SYSTEM/logs'; if (!is_dir($logDir)) { @mkdir($logDir,0755,true);} $logFile=$logDir.'/mass_mail_'.date('Y-m-d').'.log';
                foreach ($rows as $r) {
                    $to = trim($r['email']); if ($to==='') { $ko++; continue; }
                    $name = trim(($r['prenoms'] ?? '') . ' ' . ($r['nom'] ?? ''));
                    $res = $mailer->sendHtml($to, $name!==''?$name:'Client', $subject, $html);
                    $line = date('c') . ' | ' . ($res['success']?'SENT':'FAIL') . ' | ' . $to . ($res['success']?'':' | '.($res['error']??'')) . "\n";
                    @file_put_contents($logFile, $line, FILE_APPEND|LOCK_EX);
                    if ($res['success']) $ok++; else $ko++;
                }
                $flash("Envoi terminé: $ok succès, $ko échecs (limite $limit, offset $offset)", $ko===0);
            }
        } catch (Throwable $e) {
            $flash('Erreur: ' . $e->getMessage(), false);
        }
    }
}

// UI simple
?>
<div style="background:var(--glass-bg,#0f0f10);border:1px solid var(--glass-border,rgba(255,255,255,.08));border-radius:12px;padding:18px;margin:10px 0">
  <h2 style="margin:0 0 12px;color:#d4a853">📧 Emails de masse (PHPMailer)</h2>
  <?php
    // Alerte si SMTP non configuré (évite l'erreur "Could not instantiate mail function.")
    $smtpCfg = $config['smtp'] ?? [];
    $smtpHostConfigured = isset($smtpCfg['host']) && trim((string)$smtpCfg['host']) !== '';
    if (!$smtpHostConfigured) {
        echo '<div style="background:#6c757d;color:#fff;padding:10px 14px;border-radius:8px;margin:10px 0">'
           . 'Avertissement: aucun serveur SMTP n\'est configuré dans config.php (section smtp). '
           . 'Sur Windows/XAMPP, la fonction mail() échoue souvent ("Could not instantiate mail function"). '
           . 'Configurez SMTP_HOST, SMTP_USER, SMTP_PASS, etc. dans les variables d\'environnement ou dans config.php pour garantir l\'envoi.'
           . '</div>';
    }
  ?>
  <form method="post">
    <input type="hidden" name="action" value="send_mass" />
    <div style="margin:8px 0">
      <label>Sujet</label><br />
      <input name="subject" type="text" style="width:100%;padding:10px;border-radius:8px;border:1px solid #333;background:#111;color:#ddd" required />
    </div>
    <div style="margin:8px 0">
      <label>Contenu HTML</label><br />
      <textarea name="html" rows="10" style="width:100%;padding:10px;border-radius:8px;border:1px solid #333;background:#111;color:#ddd" placeholder="<h2>Titre</h2><p>Votre message…"></textarea>
      <small style="color:#999">Astuce: vous pouvez coller le HTML d'un modèle depuis EMAIL_SYSTEM/templates/ si besoin.</small>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin:8px 0">
      <div>
        <label>Limite</label><br />
        <input name="limit" type="number" value="200" min="1" max="1000" style="width:120px;padding:8px;border-radius:8px;border:1px solid #333;background:#111;color:#ddd" />
      </div>
      <div>
        <label>Offset</label><br />
        <input name="offset" type="number" value="0" min="0" style="width:120px;padding:8px;border-radius:8px;border:1px solid #333;background:#111;color:#ddd" />
      </div>
      <div style="display:flex;align-items:flex-end;gap:8px">
        <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="test_mode" value="1" /> Mode test</label>
        <input name="test_email" type="email" placeholder="email@test.com" style="padding:8px;border-radius:8px;border:1px solid #333;background:#111;color:#ddd" />
      </div>
    </div>
    <div style="margin-top:12px">
      <button type="submit" style="background:#d4a853;color:#111;border:none;border-radius:8px;padding:10px 16px;font-weight:600;cursor:pointer">Envoyer</button>
    </div>
  </form>
</div>