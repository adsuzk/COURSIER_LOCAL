<?php
// Page publique des applications Suzosky
// Récupère les dernières versions depuis la structure admin et les affiche proprement

// Auto-détection et récupération des métadonnées
include __DIR__ . '/admin/auto_detect_apk.php';
require_once __DIR__ . '/config.php';

// 1) Charger les données d'applications
$applications = require __DIR__ . '/applis.php';

// 2) Récupérer les métadonnées des dernières APK uploadées
$uploadDir = __DIR__ . '/admin/uploads';
$latestMetaFile = $uploadDir . '/latest_apk.json';
$uploadedApks = ['current' => null, 'previous' => null];

if (file_exists($latestMetaFile)) {
    $uploadData = json_decode(file_get_contents($latestMetaFile), true);
    if (is_array($uploadData)) {
        $uploadedApks['current'] = $uploadData;
        if (isset($uploadData['previous']) && is_array($uploadData['previous'])) {
            $uploadedApks['previous'] = $uploadData['previous'];
        }
    }
}

$baseUrl = function_exists('getAppBaseUrl') ? getAppBaseUrl() : ((function () {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $basePath = $scriptDir === '' ? '/' : '/' . $scriptDir . '/';
    return $scheme . '://' . $host . $basePath;
})());
$assetUrl = function ($path) use ($baseUrl) {
    $prefix = rtrim($baseUrl, '/');
    return $prefix . '/' . ltrim($path, '/');
};
$pageUrl = function_exists('appUrl') ? appUrl('applications') : rtrim($baseUrl, '/') . '/applications';

// 3) Séparer les applications par type (coursiers/clients)
$appliCoursiers = [];
$appliClients = [];

foreach ($applications as $app) {
    if (stripos($app['nom'], 'coursier') !== false || stripos($app['description'], 'livraison') !== false) {
        $appliCoursiers[] = $app;
    } else {
        $appliClients[] = $app;
    }
}

// 4) Enrichir les données coursiers avec les métadonnées uploadées
if (!empty($uploadedApks['current']) && !empty($appliCoursiers)) {
    $appliCoursiers[0]['version'] = "v{$uploadedApks['current']['version_name']} (build {$uploadedApks['current']['version_code']})";
    $downloadPath = 'admin/download_apk.php?file=' . urlencode($uploadedApks['current']['file']);
    $appliCoursiers[0]['lien'] = function_exists('routePath') ? routePath($downloadPath) : '/' . ltrim($downloadPath, '/');
    $appliCoursiers[0]['taille'] = isset($uploadedApks['current']['apk_size']) ? number_format($uploadedApks['current']['apk_size']/1024/1024, 2) . ' MB' : 'N/A';
    $appliCoursiers[0]['date_maj'] = isset($uploadedApks['current']['uploaded_at']) ? date('d/m/Y', strtotime($uploadedApks['current']['uploaded_at'])) : date('d/m/Y');
    
    if (!empty($uploadedApks['previous'])) {
        $previousPath = 'admin/download_apk.php?file=' . urlencode($uploadedApks['previous']['file']);
        $appliCoursiers[0]['lien_precedent'] = function_exists('routePath') ? routePath($previousPath) : '/' . ltrim($previousPath, '/');
        $appliCoursiers[0]['version_precedente'] = "v{$uploadedApks['previous']['version_name']} (build {$uploadedApks['previous']['version_code']})";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO optimisé -->
    <title>📱 Applications Suzosky | Télécharger Apps Coursier & Client | Côte d'Ivoire</title>
    <meta name="description" content="Téléchargez les applications officielles Suzosky : App Coursier pour livreurs, App Client pour commandes. Dernières versions Android disponibles.">
    <meta name="keywords" content="application suzosky, app coursier abidjan, télécharger apk suzosky, application livraison côte d'ivoire, app mobile suzosky">
    
    <!-- Open Graph -->
    <meta property="og:title" content="📱 Applications Suzosky | Télécharger Apps Officielles">
    <meta property="og:description" content="Téléchargez les applications officielles Suzosky : App Coursier pour livreurs, App Client pour commandes.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($pageUrl, ENT_QUOTES); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($assetUrl('assets/og-image-apps.jpg'), ENT_QUOTES); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icon-32.png">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* Styles Suzosky */
        :root {
            --primary-gold: #D4A853;
            --primary-dark: #1A1A2E;
            --secondary-blue: #16213E;
            --accent-blue: #0F3460;
            --accent-red: #E94560;
            --glass-bg: rgba(255,255,255,0.08);
            --glass-border: rgba(255,255,255,0.2);
            --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --overlay-dark: rgba(26, 26, 46, 0.9);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--gradient-dark);
            color: #fff;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header simplifié */
        .app-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-img {
            height: 50px;
            width: auto;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-gold);
            letter-spacing: -0.02em;
        }

        .brand-tagline {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.7);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .back-link {
            color: var(--primary-gold);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #fff;
            transform: translateX(-3px);
        }

        /* Contenu principal */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 1rem;
        }

        .page-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title h1 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-gold);
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(212, 168, 83, 0.3);
        }

        .page-title p {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.8);
            max-width: 600px;
            margin: 0 auto;
        }

        /* Navigation par type */
        .app-type-nav {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3rem;
        }

        .app-type-btn {
            padding: 1rem 2rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(20px);
        }

        .app-type-btn.active,
        .app-type-btn:hover {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--glass-shadow);
        }

        /* Sections d'applications */
        .app-section {
            display: none;
        }

        .app-section.active {
            display: block;
        }

        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Grille d'applications */
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .app-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            backdrop-filter: blur(20px);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .app-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glass-shadow);
            border-color: var(--primary-gold);
        }

        .app-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-gold);
        }

        .app-header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .app-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: #fff;
            padding: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .app-details h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 0.5rem;
        }

        .app-version {
            background: rgba(212, 168, 83, 0.2);
            color: var(--primary-gold);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .app-description {
            color: rgba(255,255,255,0.8);
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .app-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .meta-value {
            color: #fff;
            font-weight: 600;
        }

        .app-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .download-btn {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 168, 83, 0.4);
        }

        .previous-btn {
            background: var(--glass-bg);
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border: 1px solid var(--glass-border);
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .previous-btn:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        .no-apps-message {
            text-align: center;
            padding: 4rem 2rem;
            color: rgba(255,255,255,0.6);
            background: var(--glass-bg);
            border-radius: 20px;
            border: 1px dashed var(--glass-border);
        }

        .no-apps-message i {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
            color: var(--primary-gold);
            opacity: 0.5;
        }

        /* Footer */
        .app-footer {
            background: var(--overlay-dark);
            text-align: center;
            padding: 2rem;
            margin-top: 4rem;
            border-top: 1px solid var(--glass-border);
        }

        .app-footer p {
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 1rem;
            }

            .page-title h1 {
                font-size: 2rem;
            }

            .app-type-nav {
                flex-direction: column;
                align-items: center;
            }

            .apps-grid {
                grid-template-columns: 1fr;
            }

            .app-card {
                padding: 1.5rem;
            }

            .app-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header simplifié -->
    <header class="app-header">
        <div class="header-container">
            <div class="logo-section">
                <img src="assets/favicon.svg" alt="Suzosky" class="logo-img">
                <div class="brand-text">
                    <span class="brand-name">SUZOSKY</span>
                    <span class="brand-tagline">APPLICATIONS</span>
                </div>
            </div>
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Retour à l'accueil
            </a>
        </div>
    </header>

    <!-- Contenu principal -->
    <main class="main-content">
        <div class="page-title">
            <h1><i class="fas fa-mobile-alt"></i> Nos Applications</h1>
            <p>Téléchargez les applications officielles Suzosky pour profiter de tous nos services en mobilité</p>
        </div>

        <!-- Navigation par type -->
        <nav class="app-type-nav">
            <a href="#" class="app-type-btn active" onclick="showAppType('clients')" id="btn-clients">
                <i class="fas fa-users"></i>
                Applications Clients
            </a>
            <a href="#" class="app-type-btn" onclick="showAppType('coursiers')" id="btn-coursiers">
                <i class="fas fa-motorcycle"></i>
                Applications Coursiers
            </a>
        </nav>

        <!-- Section Applications Coursiers -->
        <section id="section-coursiers" class="app-section">
            <h2 class="section-title">
                <i class="fas fa-motorcycle"></i>
                Applications pour Coursiers
            </h2>
            
            <?php if (!empty($appliCoursiers)): ?>
                <div class="apps-grid">
                    <?php foreach ($appliCoursiers as $app): ?>
                    <div class="app-card">
                        <div class="app-header-info">
                            <img src="<?= htmlspecialchars($app['icon'] ?? 'assets/favicon.svg') ?>" alt="<?= htmlspecialchars($app['nom']) ?>" class="app-icon">
                            <div class="app-details">
                                <h3><?= htmlspecialchars($app['nom']) ?></h3>
                                <span class="app-version"><?= htmlspecialchars($app['version'] ?? 'v1.0.0') ?></span>
                            </div>
                        </div>
                        
                        <div class="app-description">
                            <?= htmlspecialchars($app['description']) ?>
                        </div>
                        
                        <div class="app-meta">
                            <div class="meta-item">
                                <span class="meta-label">Plateformes</span>
                                <span class="meta-value"><?= htmlspecialchars(implode(', ', $app['plateformes'] ?? ['Android'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Taille</span>
                                <span class="meta-value"><?= htmlspecialchars($app['taille'] ?? 'N/A') ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Dernière MAJ</span>
                                <span class="meta-value"><?= htmlspecialchars($app['date_maj'] ?? $app['date'] ?? date('d/m/Y')) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Statut</span>
                                <span class="meta-value" style="color: var(--success-color);">✅ Production</span>
                            </div>
                        </div>
                        
                        <div class="app-actions">
                            <?php if (!empty($app['lien'])): ?>
                                <a href="<?= htmlspecialchars($app['lien']) ?>" class="download-btn" download>
                                    <i class="fas fa-download"></i>
                                    Télécharger la dernière version
                                </a>
                            <?php else: ?>
                                <div class="download-btn" style="opacity: 0.5; cursor: not-allowed;">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Bientôt disponible
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($app['lien_precedent'])): ?>
                                <a href="<?= htmlspecialchars($app['lien_precedent']) ?>" class="previous-btn" download>
                                    <i class="fas fa-history"></i>
                                    Version précédente (<?= htmlspecialchars($app['version_precedente']) ?>)
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-apps-message">
                    <i class="fas fa-motorcycle"></i>
                    <h3>Aucune application coursier disponible</h3>
                    <p>Les applications pour coursiers seront bientôt disponibles au téléchargement.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Section Applications Clients -->
        <section id="section-clients" class="app-section active">
            <h2 class="section-title">
                <i class="fas fa-users"></i>
                Applications pour Clients
            </h2>
            
            <?php if (!empty($appliClients)): ?>
                <div class="apps-grid">
                    <?php foreach ($appliClients as $app): ?>
                    <div class="app-card">
                        <div class="app-header-info">
                            <img src="<?= htmlspecialchars($app['icon'] ?? 'assets/favicon.svg') ?>" alt="<?= htmlspecialchars($app['nom']) ?>" class="app-icon">
                            <div class="app-details">
                                <h3><?= htmlspecialchars($app['nom']) ?></h3>
                                <span class="app-version"><?= htmlspecialchars($app['version'] ?? 'v1.0.0') ?></span>
                            </div>
                        </div>
                        
                        <div class="app-description">
                            <?= htmlspecialchars($app['description']) ?>
                        </div>
                        
                        <div class="app-meta">
                            <div class="meta-item">
                                <span class="meta-label">Plateformes</span>
                                <span class="meta-value"><?= htmlspecialchars(implode(', ', $app['plateformes'] ?? ['Android'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Version</span>
                                <span class="meta-value"><?= htmlspecialchars($app['version'] ?? 'v1.0.0') ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Date</span>
                                <span class="meta-value"><?= htmlspecialchars($app['date'] ?? date('d/m/Y')) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Statut</span>
                                <span class="meta-value" style="color: var(--warning-color);">🚧 En développement</span>
                            </div>
                        </div>
                        
                        <div class="app-actions">
                            <?php if (!empty($app['lien'])): ?>
                                <a href="<?= htmlspecialchars($app['lien']) ?>" class="download-btn" download>
                                    <i class="fas fa-download"></i>
                                    Télécharger l'application
                                </a>
                            <?php else: ?>
                                <div class="download-btn" style="opacity: 0.5; cursor: not-allowed;">
                                    <i class="fas fa-clock"></i>
                                    Bientôt disponible
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-apps-message">
                    <i class="fas fa-users"></i>
                    <h3>Applications clients en cours de développement</h3>
                    <p>Les applications pour clients seront bientôt disponibles. Restez connectés !</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Footer -->
    <footer class="app-footer">
        <p>&copy; <?= date('Y') ?> Suzosky - Conciergerie Privée. Tous droits réservés.</p>
    </footer>

    <script>
        function showAppType(type) {
            // Masquer toutes les sections
            document.querySelectorAll('.app-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Réinitialiser tous les boutons
            document.querySelectorAll('.app-type-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Afficher la section demandée
            document.getElementById('section-' + type).classList.add('active');
            document.getElementById('btn-' + type).classList.add('active');
        }

        // Effet de chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation d'entrée pour les cartes
            const cards = document.querySelectorAll('.app-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });

        // Smooth scroll pour les ancres
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>