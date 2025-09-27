<?php
/**
 * CONTRÔLEUR PRINCIPAL - SECTION FINANCES
 * Architecture modulaire pour la gestion financière Suzosky
 * Date: 27 Septembre 2025
 */

require_once __DIR__ . '/../config.php';

// Désactiver le cache pour les données financières en temps réel
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Initialisation PDO
$pdo = getPDO();

// Gestion des onglets
$tab = $_GET['tab'] ?? 'dashboard';
$validTabs = [
    'dashboard', 
    'coursiers', 
    'clients_particuliers', 
    'clients_business', 
    'transactions', 
    'recharges', 
    'rechargement_direct',  // NOUVEAU: Rechargement direct admin
    'pricing', 
    'reports'
];

if (!in_array($tab, $validTabs)) {
    $tab = 'dashboard';
}

// Chargement du module approprié selon l'onglet
switch ($tab) {
    case 'rechargement_direct':
        require_once __DIR__ . '/sections_finances/rechargement_direct.php';
        break;
        
    case 'dashboard':
        require_once __DIR__ . '/sections_finances/dashboard.php';
        break;
        
    case 'coursiers':
        require_once __DIR__ . '/sections_finances/coursiers.php';
        break;
        
    case 'transactions':
        require_once __DIR__ . '/sections_finances/transactions.php';
        break;
        
    case 'recharges':
        require_once __DIR__ . '/sections_finances/recharges.php';
        break;
        
    case 'pricing':
        require_once __DIR__ . '/sections_finances/pricing.php';
        break;
        
    case 'reports':
        require_once __DIR__ . '/sections_finances/reports.php';
        break;
        
    case 'clients_particuliers':
        require_once __DIR__ . '/sections_finances/clients_particuliers.php';
        break;
        
    case 'clients_business':
        require_once __DIR__ . '/sections_finances/clients_business.php';
        break;
        
    default:
        require_once __DIR__ . '/sections_finances/dashboard.php';
        break;
}
?>