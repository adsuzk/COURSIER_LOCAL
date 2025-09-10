<?php
// sections index/forgot_password.php – formulaire mot de passe oublié Particulier
?>
<h2>🔑 Mot de passe oublié</h2>
<p class="subtitle">Entrez votre email ou téléphone pour réinitialiser</p>

<form id="forgotForm" method="post" action="api/index.php?action=particulier_reset_password">
  <div class="form-group">
    <label for="forgotEmail">📧 Email ou téléphone</label>
    <input type="text" id="forgotEmail" name="login" required placeholder="Email ou téléphone" class="smart-phone-input">
    <small class="input-help">Commencez par un chiffre pour le téléphone</small>
  </div>
  <button type="submit" class="btn-primary full-width">Envoyer le lien de réinitialisation</button>
</form>
<p class="auth-switch">
  <a href="#" id="backToLoginLink">← Retour à la connexion</a>
</p>
