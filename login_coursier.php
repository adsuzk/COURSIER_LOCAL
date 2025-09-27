<?php
// Page de connexion coursier (V7 compatible, sans changer l'app)
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion Coursier</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:#0f1115; color:#fff; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .card { width: 100%; max-width: 420px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.3); }
    h1 { margin:0 0 12px; font-size: 20px; }
    label { display:block; font-size: 14px; margin:12px 0 6px; opacity:.9; }
    input { width:100%; padding:12px; border-radius:10px; border:1px solid rgba(255,255,255,.15); background:#0c0e12; color:#fff; }
    button { width:100%; padding:12px; margin-top:16px; background:#D4A853; color:#121212; border:none; border-radius:10px; font-weight:700; cursor:pointer; }
    .hint { font-size:12px; opacity:.7; margin-top:10px; }
    .error { background: rgba(255, 80, 80, .15); color:#ff8080; border:1px solid rgba(255,80,80,.3); padding:10px; border-radius:10px; margin:10px 0 0; display:none; }
    .ok { background: rgba(80, 255, 120, .15); color:#aaffc1; border:1px solid rgba(80,255,120,.3); padding:10px; border-radius:10px; margin:10px 0 0; display:none; }
  </style>
  <script>
    async function login(evt){
      evt.preventDefault();
      const id = document.getElementById('identifier').value.trim();
      const pwd = document.getElementById('password').value.trim();
      const err = document.getElementById('err');
      const ok = document.getElementById('ok');
      const btn = evt.submitter || document.querySelector('button[type="submit"]');
      err.style.display = 'none'; ok.style.display='none';
      if(!id || !pwd){ err.textContent = 'Matricule/téléphone et mot de passe requis'; err.style.display='block'; return; }
      try {
        document.body.style.cursor = 'progress';
        if (btn) { btn.disabled = true; btn.textContent = 'Connexion…'; }
        const res = await fetch('api/agent_auth.php', {
          method: 'POST',
          headers: { 'Content-Type':'application/json' },
          credentials: 'include',
          body: JSON.stringify({ action:'login', identifier:id, password:pwd })
        });
        let text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch(parseErr) {
          console.error('Réponse non-JSON:', text);
          throw new Error('Réponse serveur invalide');
        }
        if(json.success){
          ok.textContent = json.message || 'Connexion réussie'; ok.style.display='block';
          setTimeout(()=>{ window.location.href = 'coursier.php'; }, 400);
        } else {
          err.textContent = json.error || json.message || 'Identifiants incorrects'; err.style.display='block';
        }
      } catch(e){
        err.textContent = (e && e.message) ? e.message : 'Erreur réseau. Réessayez.'; err.style.display='block';
      } finally {
        document.body.style.cursor = 'default';
        if (btn) { btn.disabled = false; btn.textContent = 'Se connecter'; }
      }
    }
  </script>
  </head>
<body>
  <form class="card" onsubmit="login(event)">
    <h1>Connexion Coursier</h1>
    <label for="identifier">Matricule ou Téléphone (+225…)</label>
    <input id="identifier" placeholder="CM2025xxxx ou +225XXXXXXXXXX" autocomplete="username" />
    <label for="password">Mot de passe (5 caractères)</label>
    <input id="password" type="password" minlength="5" maxlength="5" autocomplete="current-password" />
    <button type="submit">Se connecter</button>
    <div id="ok" class="ok"></div>
    <div id="err" class="error"></div>
    <div class="hint">Besoin d’aide ? Demandez à l’admin de régénérer votre mot de passe.</div>
  </form>
</body>
</html>