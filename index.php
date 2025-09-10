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

try {
    // Sections HTML principales
    include __DIR__ . '/sections index/header.php';
    include __DIR__ . '/sections index/order_form.php';
    include __DIR__ . '/sections index/map.php';
    include __DIR__ . '/sections index/services.php';

    // Sections du footer (divisées)
    include __DIR__ . '/sections index/footer_copyright.php';
    include __DIR__ . '/sections index/modals.php';
    include __DIR__ . '/sections index/chat_support.php';
    
    // Sections d'authentification

    // Sections JavaScript (divisées par fonctionnalité)
    include __DIR__ . '/sections index/js_google_maps.php';
    include __DIR__ . '/sections index/js_autocomplete.php';
    // include __DIR__ . '/sections index/js_route_calculation.php'; // Désactivé, calcul dédié via js_price_calculation
    include __DIR__ . '/sections index/js_geolocation.php';
    include __DIR__ . '/sections index/js_authentication.php';
    include __DIR__ . '/sections index/js_form_handling.php';
    include __DIR__ . '/sections index/js_price_calculation.php';
    
    // Include modal connexion script
    echo '<script src="assets/js/connexion_modal.js"></script>';
    
    include __DIR__ . '/sections index/js_chat_support.php';
    include __DIR__ . '/sections index/js_payment.php';
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
