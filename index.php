<?php
// Page d'accueil modulaire : inclut les différentes sections depuis le dossier /sections index

// DÉTECTEUR D'ERREURS DE DÉPLOIEMENT - DOIT ÊTRE EN PREMIER
require_once __DIR__ . '/diagnostic_logs/deployment_error_detector.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si le système de logging existe avant de l'utiliser
if (file_exists(__DIR__ . '/diagnostic_logs/logging_hooks.php')) {
    // Intégrer le système de logging avancé
    require_once __DIR__ . '/diagnostic_logs/logging_hooks.php';
    
    // Initialiser le logging pour l'interface INDEX
    $interface_start_time = initLogging('INDEX');
    
    // Log du chargement de la page d'accueil
    logInfo("Chargement page d'accueil", [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'referer' => $_SERVER['HTTP_REFERER'] ?? 'direct'
    ], 'INDEX');
} else {
    // Log d'erreur si le système de logging n'existe pas
    logDeploymentError("CRITICAL: logging_hooks.php not found", [
        'expected_path' => __DIR__ . '/diagnostic_logs/logging_hooks.php',
        'current_script' => $_SERVER['SCRIPT_FILENAME'] ?? 'unknown'
    ]);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- 🎯 TITRE PRINCIPAL OPTIMISÉ SEO -->
    <title>🚴 Coursier Abidjan | Livraison Express 24h/7j | Suzosky N°1 Côte d'Ivoire</title>
    
    <!-- 📝 META DESCRIPTIONS OPTIMISÉES -->
    <meta name="description" content="⚡ Coursier #1 à Abidjan ! Livraison express en 30min, tarif dès 800 FCFA. Paiement Mobile Money Orange/MTN. Commander maintenant ✅ Suzosky Conciergerie">
    <meta name="keywords" content="coursier abidjan, livraison express abidjan, coursier côte d'ivoire, livraison rapide abidjan, coursier cocody, coursier plateau, coursier marcory, coursier treichville, coursier yopougon, livraison moto abidjan, coursier urgent abidjan, suzosky coursier, conciergerie privée abidjan, livraison 24h abidjan, coursier pas cher abidjan, mobile money orange mtn, paiement mobile abidjan">
    
    <!-- 🌍 RÉFÉRENCES GÉOGRAPHIQUES -->
    <meta name="geo.region" content="CI-01">
    <meta name="geo.placename" content="Abidjan">
    <meta name="geo.position" content="5.316667;-4.033333">
    <meta name="ICBM" content="5.316667, -4.033333">
    <meta name="location" content="Abidjan, Côte d'Ivoire">
    
    <!-- 🔗 CANONICAL ET ALTERNATES -->
    <link rel="canonical" href="https://coursier.conciergerie-privee-suzosky.com/">
    <link rel="alternate" hreflang="fr" href="https://coursier.conciergerie-privee-suzosky.com/">
    <link rel="alternate" hreflang="fr-ci" href="https://coursier.conciergerie-privee-suzosky.com/">
    
    <!-- 📱 OPEN GRAPH OPTIMISÉ -->
    <meta property="og:title" content="🚴 Coursier Abidjan N°1 | Livraison Express 30min | Suzosky">
    <meta property="og:description" content="⚡ Coursier express à Abidjan dès 800 FCFA ! Livraison en 30min, paiement Mobile Money. Cocody, Plateau, Marcory, Treichville. Commander maintenant !">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://coursier.conciergerie-privee-suzosky.com/">
    <meta property="og:image" content="https://coursier.conciergerie-privee-suzosky.com/assets/og-image-suzosky.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="Suzosky Coursier Abidjan">
    <meta property="og:locale" content="fr_CI">
    
    <!-- 🐦 TWITTER CARDS -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="🚴 Coursier Abidjan N°1 | Livraison Express 30min | Suzosky">
    <meta name="twitter:description" content="⚡ Coursier express à Abidjan dès 800 FCFA ! Livraison en 30min, paiement Mobile Money. Commander maintenant !">
    <meta name="twitter:image" content="https://coursier.conciergerie-privee-suzosky.com/assets/twitter-card-suzosky.jpg">
    <meta name="twitter:site" content="@SuzoskyCi">
    <meta name="twitter:creator" content="@SuzoskyCi">
    
    <!-- 🏢 ORGANISATION SCHEMA.ORG -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "Suzosky Coursier Abidjan",
        "description": "Service de coursier et livraison express à Abidjan. Livraison rapide, sécurisée avec paiement mobile money.",
        "url": "https://coursier.conciergerie-privee-suzosky.com",
        "logo": "https://coursier.conciergerie-privee-suzosky.com/assets/logo-suzosky.svg",
        "image": "https://coursier.conciergerie-privee-suzosky.com/assets/og-image-suzosky.jpg",
        "telephone": "+225 07 07 07 07 07",
        "email": "contact@conciergerie-privee-suzosky.com",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Cocody Riviera",
            "addressLocality": "Abidjan",
            "addressRegion": "Abidjan",
            "postalCode": "00225",
            "addressCountry": "CI"
        },
        "geo": {
            "@type": "GeoCoordinates",
            "latitude": 5.316667,
            "longitude": -4.033333
        },
        "openingHours": "Mo-Su 06:00-23:00",
        "serviceArea": {
            "@type": "GeoCircle",
            "geoMidpoint": {
                "@type": "GeoCoordinates",
                "latitude": 5.316667,
                "longitude": -4.033333
            },
            "geoRadius": "50000"
        },
        "priceRange": "800-5000 FCFA",
        "currenciesAccepted": "XOF",
        "paymentAccepted": "Mobile Money, Espèces, Carte bancaire",
        "areaServed": ["Abidjan", "Cocody", "Plateau", "Marcory", "Treichville", "Yopougon", "Adjamé", "Koumassi"],
            "hasOfferCatalog": {
                "@type": "OfferCatalog",
                "name": "Services de Coursier",
                "itemListElement": [
                    {
                        "@type": "Offer",
                        "itemOffered": {
                            "@type": "Service",
                            "name": "Livraison Express",
                            "description": "Livraison rapide en moins de 30 minutes"
                        }
                    },
                    {
                        "@type": "Offer",
                        "itemOffered": {
                            "@type": "Service",
                            "name": "Livraison Standard",
                            "description": "Livraison dans la journée"
                        }
                    }
                ]
            },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "150",
            "bestRating": "5",
            "worstRating": "1"
        },
        "sameAs": [
            "https://www.facebook.com/suzoskyCi",
            "https://www.instagram.com/suzoskyCi",
            "https://www.linkedin.com/company/suzosky"
        ]
        }
        </script>
    
    <!-- 🎯 ROBOTS ET INDEXATION -->
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    <meta name="bingbot" content="index, follow">
    
    <!-- ⚡ PERFORMANCE ET VITESSE -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" as="style">
    
    <!-- 📱 FAVICONS ET ICONS -->
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/icon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#D4A853">
    <meta name="msapplication-TileColor" content="#D4A853">
    <meta name="msapplication-config" content="browserconfig.xml">
    
    <!-- 🎨 STYLES -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <!-- 🔄 STYLES PARTAGÉS SYNCHRONISÉS - NE PAS MODIFIER MANUELLEMENT -->
    <style>
/* === STYLES PARTAGÉS SUZOSKY === */

/* shared/config/colors.css */
/* 🎨 COULEURS OFFICIELLES SUZOSKY - FICHIER PARTAGÉ */
/* 🔒 VERROUILLÉ - NE PAS MODIFIER SANS AUTORISATION */

:root {
    /* Couleurs principales */
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    
    /* Effets Glass Morphism */
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    --glass-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    
    /* Gradients signatures */
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
    --gradient-glass: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
    
    /* Couleurs fonctionnelles */
    --success-color: #28a745;
    --error-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    
    /* Transparences */
    --overlay-dark: rgba(26, 26, 46, 0.9);
    --overlay-light: rgba(255, 255, 255, 0.1);
}

/* Adaptation automatique mode sombre */
@media (prefers-color-scheme: dark) {
    :root {
        --glass-bg: rgba(0,0,0,0.1);
        --glass-border: rgba(255,255,255,0.1);
    }
}

/* Variables mobile */
@media (max-width: 768px) {
    :root {
        --mobile-padding: 1rem;
        --mobile-margin: 0.5rem;
        --mobile-font-scale: 0.9;
    }
}

/* === FIN STYLES PARTAGÉS === */
    </style>
</head>
<body>
<?php
try {
    // Sections HTML principales
    include __DIR__ . '/sections index/header.php';
    include __DIR__ . '/sections index/order_form.php';
    // include map section removed to avoid duplicate map block
    include __DIR__ . '/sections index/services.php';

    // Sections du footer (divisées)
    include __DIR__ . '/sections index/footer_copyright.php';
    include __DIR__ . '/sections index/modals.php';
    include __DIR__ . '/sections index/chat_support.php';

    // Sections d'authentification
    
    // Définir la racine de l'application pour JS
    echo "<script>const ROOT_PATH = '" . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . "';</script>";

    // Sections JavaScript (divisées par fonctionnalité)
    include __DIR__ . '/sections index/js_google_maps.php';
    // JS modal Connexion Particulier
    echo '<script src="assets/js/connexion_modal.js"></script>';
    include __DIR__ . '/sections index/js_autocomplete.php';
    include __DIR__ . '/sections index/js_route_calculation.php';
    include __DIR__ . '/sections index/js_geolocation.php';
    include __DIR__ . '/sections index/js_authentication.php';
    include __DIR__ . '/sections index/js_form_handling.php';
    include __DIR__ . '/sections index/js_chat_support.php';
    include __DIR__ . '/sections index/js_payment.php';
    // Price calculation script (affiche estimation de prix) - SPÉCIFIQUE LOCAL AVANCÉ
    include __DIR__ . '/sections index/js_price_calculation.php';
    include __DIR__ . '/sections index/js_initialization.php';

    // Log de succès du chargement complet
    logInfo("Page d'accueil chargée avec succès", [
        'sections_loaded' => 18,
        'load_time' => round(microtime(true) - $interface_start_time, 4)
    ], 'INDEX');

} catch (Exception $e) {
    // Log des erreurs de chargement
    logInterfaceError("Erreur lors du chargement de la page d'accueil", [
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'stack_trace' => $e->getTraceAsString()
    ]);
    
    // Affichage d'erreur pour l'utilisateur
    echo '<div style="padding: 20px; background: #ff4757; color: white; text-align: center;">';
    echo 'Une erreur est survenue lors du chargement de la page. Veuillez réessayer.';
    echo '</div>';
}

// Log final des statistiques de performance
$final_memory = memory_get_usage(true);
$peak_memory = memory_get_peak_usage(true);
$execution_time = microtime(true) - $interface_start_time;

logPerformance('index_page_complete', $interface_start_time, microtime(true), 'INDEX');
?>
</body>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A&libraries=places&callback=initMap" async defer></script>
</html>
