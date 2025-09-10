<?php
require_once __DIR__ . '/functions.php';

// Gestion de la connexion
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Vérification simple (à remplacer par votre système d'auth)
    if ($username === 'admin' && $password === 'suzosky2024') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
    header('Location: admin.php?section=dashboard');
        exit;
    } else {
        renderLoginForm('Identifiants incorrects');
        exit;
    }
}

// Gestion de la déconnexion
if (($_GET['section'] ?? '') === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Vérification de l'authentification
if (!checkAdminAuth()) {
    renderLoginForm();
    exit;
}

renderHeader();
$section = $_GET['section'] ?? 'dashboard';
switch ($section) {
    case 'agents': include __DIR__ . '/agents.php'; break;
    case 'chat': include __DIR__ . '/chat.php'; break;
    case 'clients': include __DIR__ . '/clients.php'; break;
    case 'recrutement': include __DIR__ . '/recrutement.php'; break;
    case 'commandes': include __DIR__ . '/commandes.php'; break;
    default: include __DIR__ . '/dashboard.php';
}
renderFooter();
