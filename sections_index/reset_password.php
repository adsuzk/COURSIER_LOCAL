<?php
// sections_index/reset_password.php – Formulaire de réinitialisation de mot de passe
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe</title>
    <link rel="stylesheet" href="<?= (function_exists('routePath') ? routePath('assets/css/style.css') : dirname($_SERVER['SCRIPT_NAME']) . '/assets/css/style.css') ?>">
</head>
<body>
    <div class="reset-password-container">
        <h2>🔑 Réinitialisation du mot de passe</h2>
        <?php if (!$token): ?>
            <p>Jeton manquant ou invalide. Veuillez vérifier le lien reçu par email.</p>
        <?php else: ?>
            <form id="resetPasswordForm" method="post" action="<?= (function_exists('routePath') ? rtrim(routePath(''), '/') : rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')) ?>/api/index.php?action=particulier_do_reset_password">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
                <div class="form-group">
                    <label for="newPassword">Nouveau mot de passe (5 caractères)</label>
                    <input type="password" id="newPassword" name="password" required minlength="5" maxlength="5">
                </div>
                <div class="form-group">
                    <label for="confirmPassword">Confirmer le mot de passe</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required minlength="5" maxlength="5">
                </div>
                <button type="submit" class="btn-primary full-width">Valider</button>
            </form>
            <div id="resetMessage"></div>
            <script src="<?= (function_exists('routePath') ? routePath('assets/js/reset_password.js') : dirname($_SERVER['SCRIPT_NAME']) . '/assets/js/reset_password.js') ?>"></script>
        <?php endif; ?>
    </div>
</body>
</html>
