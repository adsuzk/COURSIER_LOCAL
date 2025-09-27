<?php
// logout.php - Détruit la session et redirige vers l'accueil
session_start();
// Vider toutes les variables de session
session_unset();
// Détruire la session
session_destroy();
// Supprimer le cookie de session pour tout le domaine
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', time() - 42000, '/');
}
// Redirection vers la page d'accueil
header('Location: index.php');
exit;
