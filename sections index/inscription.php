<?php
// sections index/inscription.php – formulaire d'inscription Particulier
?>
<h2>📝 Créer un compte</h2>
<p class="subtitle">Rejoignez Suzosky Coursier</p>

<form id="registerForm" method="post">
  <input type="hidden" name="action" value="register">
  <div class="form-group">
    <label for="registerNom">👤 Nom</label>
    <input type="text" id="registerNom" name="nom" required placeholder="Votre nom">
  </div>
  <div class="form-group">
    <label for="registerPrenoms">👤 Prénoms</label>
    <input type="text" id="registerPrenoms" name="prenoms" required placeholder="Vos prénoms">
  </div>
  <div class="form-group">
    <label for="registerEmail">📧 Email (optionnel)</label>
    <input type="email" id="registerEmail" name="email" placeholder="votre@email.com">
  </div>
  <div class="form-group">
    <label for="registerTelephone">📱 Téléphone</label>
    <input type="tel" id="registerTelephone" name="telephone" required placeholder="Numéro de téléphone" class="smart-phone-input">
  </div>
  <div class="form-group">
    <label for="registerPassword">🔒 Mot de passe (5 caractères)</label>
    <input type="password" id="registerPassword" name="password" required placeholder="Choisir un mot de passe" minlength="5" maxlength="5">
  </div>
  <div class="form-group">
    <label for="confirmPassword">🔒 Confirmer le mot de passe</label>
    <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Confirmez votre mot de passe" minlength="5" maxlength="5">
  </div>
  <button type="submit" class="btn-primary full-width">Créer mon compte</button>
</form>
<p class="auth-switch">
  <a href="#" id="backToLoginLink">← Retour à la connexion</a>
</p>
