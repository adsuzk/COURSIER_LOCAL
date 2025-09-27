<?php
// sections_index/header.php - Fragment d'en-tête (navigation)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

$logoUrl          = routePath('assets/logo-suzosky.svg');
$applicationsUrl  = routePath('applications.php');
$recrutementUrl   = routePath('recrutement.php');
$cguUrl           = routePath('cgu.html');
$businessUrl      = routePath('business.php');
$logoutUrl        = routePath('logout.php');

$isLoggedIn = !empty($_SESSION['client_id']);
$userLabel  = trim((string)($_SESSION['client_nom'] ?? ''));
if ($userLabel === '') {
    $userLabel = trim((string)(($_SESSION['client_prenoms'] ?? '') . ' ' . ($_SESSION['client_nom'] ?? '')));
}
if ($userLabel === '') {
    $userLabel = 'Mon compte';
}
?>
<header class="site-header" id="accueil">
    <nav class="main-nav">
        <div class="nav-container">
            <div class="nav-logo">
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Conciergerie Privée Suzosky" class="logo-img">
                <div class="logo-text">
                    <span class="brand-name">SUZOSKY</span>
                    <span class="brand-tagline">CONCIERGERIE PRIVÉE</span>
                </div>
            </div>
            <div class="nav-menu">
                <a href="#accueil" class="nav-link active">Accueil</a>
                <a href="#services" class="nav-link">Services</a>
                <a href="<?= htmlspecialchars($applicationsUrl, ENT_QUOTES, 'UTF-8') ?>" class="nav-link">Applications</a>
                <a href="<?= htmlspecialchars($recrutementUrl, ENT_QUOTES, 'UTF-8') ?>" class="nav-link">Recrutement</a>
                <a href="<?= htmlspecialchars($cguUrl, ENT_QUOTES, 'UTF-8') ?>" class="nav-link">CGU</a>
                <a href="#contact" class="nav-link">Contact</a>
                <div class="nav-auth" id="navAuth">
                    <?php if ($isLoggedIn): ?>
                        <div id="userNav" class="auth-state">
                            <a href="#" class="nav-login" onclick="openAccountModal(); return false;">
                                <span class="user-name"><?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                            <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>" class="nav-logout">Déconnexion</a>
                        </div>
                    <?php else: ?>
                        <div id="guestNav" class="auth-state">
                            <a href="#" id="openConnexionLink" class="nav-login">Connexion Particulier</a>
                            <a href="<?= htmlspecialchars($businessUrl, ENT_QUOTES, 'UTF-8') ?>" class="nav-login">Espace Business</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mobile-menu-toggle" role="button" aria-controls="mobileMenu" aria-expanded="false" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </div>
        </div>

        <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
            <div class="mobile-menu-content">
                <div class="mobile-menu-header">
                    <div class="mobile-logo">
                        <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Suzosky" class="mobile-logo-img">
                        <span class="mobile-brand">SUZOSKY</span>
                    </div>
                    <div class="mobile-menu-close" onclick="toggleMobileMenu()">
                        <i class="fas fa-times"></i>
                    </div>
                </div>
                <div class="mobile-menu-links">
                    <a href="#accueil" class="mobile-nav-link" onclick="toggleMobileMenu()">🏠 Accueil</a>
                    <a href="#services" class="mobile-nav-link" onclick="toggleMobileMenu()">⚡ Services</a>
                    <a href="<?= htmlspecialchars($applicationsUrl, ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link" onclick="toggleMobileMenu()">📱 Applications</a>
                    <a href="<?= htmlspecialchars($recrutementUrl, ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link" onclick="toggleMobileMenu()">👥 Recrutement</a>
                    <a href="<?= htmlspecialchars($cguUrl, ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav-link" onclick="toggleMobileMenu()">📋 CGU</a>
                    <a href="#contact" class="mobile-nav-link" onclick="toggleMobileMenu()">📞 Contact</a>
                </div>
                <div class="mobile-menu-auth" id="mobileNavAuth">
                    <?php if ($isLoggedIn): ?>
                        <div class="auth-state">
                            <a href="#" class="btn-primary full-width" onclick="openAccountModal(); toggleMobileMenu(); return false;">
                                <?= htmlspecialchars($userLabel, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                            <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>" class="nav-logout">Déconnexion</a>
                        </div>
                    <?php else: ?>
                        <div class="auth-state">
                            <a href="#" class="btn-primary full-width" onclick="toggleMobileMenu(); var link = document.getElementById('openConnexionLink'); if (link) { link.click(); } return false;">Connexion Particulier</a>
                            <a href="<?= htmlspecialchars($businessUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-secondary full-width">Espace Business</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
