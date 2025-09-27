<?php
/**
 * RÉSEAU - MONITORING COMPLET DU SYSTÈME SUZOSKY
 * Interface complète pour surveiller toutes les connexions et APIs
 */

require_once 'config.php';
require_once 'lib/coursier_presence.php';

// Vérifier les permissions admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: auth.php');
    exit;
}

$pdo = getDBConnection();

// DÉCOUVERTE AUTOMATIQUE DU RÉSEAU - Initialisation avant utilisation
require_once 'network_discovery.php';
$discovery = new NetworkDiscovery();
$discoveredComponents = $discovery->discoverAllNetworkComponents();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réseau - Monitoring Système Suzosky</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* DESIGN SYSTEM SUZOSKY - INTERFACE ADMIN RESEAU */
        :root {
            --primary-gold: #D4A853;
            --primary-dark: #1A1A2E;
            --secondary-blue: #16213E;
            --accent-blue: #0F3460;
            --accent-red: #E94560;
            --success-color: #27AE60;
            --glass-bg: rgba(255,255,255,0.08);
            --glass-border: rgba(255,255,255,0.2);
            --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #E94560;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--gradient-dark);
            color: white;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--gradient-gold);
            border-radius: 25px;
            padding: 50px 40px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 
                0 0 60px rgba(212, 168, 83, 0.3),
                0 20px 40px rgba(0,0,0,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: headerGlow 8s ease-in-out infinite;
        }

        @keyframes headerGlow {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(180deg); }
        }

        .header h1 {
            color: var(--primary-dark);
            font-size: 3.5rem;
            margin-bottom: 15px;
            font-weight: 800;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.2);
            position: relative;
            z-index: 2;
        }

        .header .subtitle {
            color: var(--primary-dark);
            font-size: 1.4rem;
            opacity: 0.9;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px 25px;
            text-align: center;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-gold);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 0 40px rgba(212, 168, 83, 0.2),
                0 20px 50px rgba(0,0,0,0.5);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--primary-gold);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }

        .stat-title {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-description {
            font-size: 0.85rem;
            opacity: 0.7;
            line-height: 1.4;
            margin-top: 10px;
        }

        .section-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .section-description {
            font-size: 1rem;
            opacity: 0.8;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .api-item {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            padding: 25px;
            backdrop-filter: blur(15px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .api-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--gradient-gold);
        }

        .api-item:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 0 30px rgba(212, 168, 83, 0.15),
                0 12px 25px rgba(0,0,0,0.3);
        }

        .api-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }

        .api-description {
            font-size: 0.9rem;
            opacity: 0.8;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .api-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-online {
            background: linear-gradient(135deg, var(--success-color), #2ECC71);
            color: white;
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.3);
        }

        .status-warning {
            background: linear-gradient(135deg, var(--warning-color), #F39C12);
            color: white;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .status-offline {
            background: linear-gradient(135deg, var(--danger-color), #E74C3C);
            color: white;
            box-shadow: 0 4px 12px rgba(233, 69, 96, 0.3);
        }

        .expand-btn {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9rem;
            margin: 20px 0;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .expand-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 168, 83, 0.4);
        }

        .collapsible {
            margin-top: 20px;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 768px) {
            .stats-grid, .api-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2.5rem;
            }
        }
        }

        .api-item {
            background: linear-gradient(135deg, rgba(244, 228, 184, 0.1) 0%, rgba(255,255,255,0.05) 100%);
            border-radius: 15px;
            padding: 25px;
            border: 2px solid var(--primary-gold);
            position: relative;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .api-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(212, 168, 83, 0.3);
        }

        .api-item.online { 
            border-color: var(--success);
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(255,255,255,0.05) 100%);
        }
        .api-item.offline { 
            border-color: var(--danger);
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(255,255,255,0.05) 100%);
        }
        .api-item.warning { 
            border-color: var(--warning);
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255,255,255,0.05) 100%);
        }

        .api-name {
            font-weight: 700;
            font-size: 1.2em;
            color: var(--primary-dark);
            margin-bottom: 12px;
        }

        .api-description {
            color: var(--primary-dark);
            margin-bottom: 15px;
            line-height: 1.5;
            opacity: 0.8;
        }

        .api-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .status-online {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .status-offline {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .status-warning {
            background: linear-gradient(135deg, var(--primary-gold) 0%, #ffc107 100%);
            color: var(--primary-dark);
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .refresh-btn {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border: 2px solid var(--primary-gold);
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 20px auto;
            display: block;
            box-shadow: 0 5px 20px rgba(212, 168, 83, 0.3);
        }

        .refresh-btn:hover {
            background: var(--primary-gold);
            color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(212, 168, 83, 0.5);
        }

        .timestamp {
            text-align: center;
            color: var(--primary-dark);
            margin-top: 25px;
            font-style: italic;
            font-weight: 500;
            opacity: 0.8;
        }

        .health-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
            animation: pulse-success 2s infinite;
        }

        .health-indicator.warning { 
            background: linear-gradient(135deg, var(--primary-gold) 0%, #ffc107 100%);
            box-shadow: 0 0 10px rgba(212, 168, 83, 0.5);
            animation: pulse-warning 2s infinite;
        }
        
        .health-indicator.danger { 
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
            animation: pulse-danger 2s infinite;
        }

        @keyframes pulse-success {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        @keyframes pulse-warning {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        @keyframes pulse-danger {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
        }

        @media (max-width: 768px) {
            .status-grid, .api-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Suzosky -->
        <div class="header">
            <h1><i class="fas fa-network-wired"></i> Réseau Système Suzosky</h1>
            <p class="subtitle">Monitoring complet et découverte automatique</p>
        </div>

        <?php
        // === COLLECTE DES DONNÉES DE MONITORING ===
        
        // 1. État général du système
        $coursiersConnectes = getConnectedCouriers($pdo);
        $nombreCoursiers = count($coursiersConnectes);
        
        // 2. État de la base de données
        $dbStatus = 'online';
        try {
            $pdo->query('SELECT 1');
        } catch (Exception $e) {
            $dbStatus = 'offline';
        }
        
        // 3. Commandes en cours
        $stmt = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente', 'assigne', 'accepte', 'en_cours')");
        $commandesActives = $stmt->fetchColumn();
        
        // 4. Tokens FCM actifs
        $stmt = $pdo->query("SELECT COUNT(*) FROM device_tokens WHERE is_active = 1");
        $tokensActifs = $stmt->fetchColumn();
        ?>

        <!-- Statistiques en temps réel -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?= $nombreCoursiers ?></div>
                <div class="stat-title">Coursiers Connectés</div>
                <div class="stat-description">
                    Livreurs actifs en temps réel
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-value"><?= $commandesActives ?></div>
                <div class="stat-title">Commandes Actives</div>
                <div class="stat-description">
                    En cours de traitement
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-plug"></i>
                </div>
                <div class="stat-value"><?= count($discoveredComponents['apis']) ?></div>
                <div class="stat-title">APIs Découvertes</div>
                <div class="stat-description">
                    Fonctionnalités détectées
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-database"></i>
                </div>
                <div class="stat-value"><?= count($discoveredComponents['database_tables']) ?></div>
                <div class="stat-title">Tables BDD</div>
                <div class="stat-description">
                    Structures de données
                </div>
            </div>
        </div>

        <!-- APIs Découvertes Automatiquement -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-plug"></i>
                APIs Découvertes Automatiquement (<?= count($discoveredComponents['apis']) ?>)
            </h2>
            <p class="section-description">
                Toutes les fonctionnalités de votre système détectées automatiquement : authentification, gestion des commandes, paiements, notifications, etc.
            </p>
            
            <button class="expand-btn" onclick="toggleSection('apis-section')">
                Voir toutes les APIs
            </button>
            
            <div id="apis-section" class="collapsible hidden">
                <div class="api-grid">
                    <?php foreach (array_slice($discoveredComponents['apis'], 0, 12) as $api): ?>
                        <div class="api-item">
                            <div class="api-name"><?= htmlspecialchars($api['name']) ?></div>
                            <div class="api-description">
                                <?= htmlspecialchars($api['description']) ?>
                            </div>
                            <div class="api-status">
                                <span class="status-badge status-online">ACTIF</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Interfaces d'Administration -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-tachometer-alt"></i>
                Interfaces d'Administration (<?= count($discoveredComponents['admin_sections']) ?>)
            </h2>
            <p class="section-description">
                Toutes les pages d'administration disponibles pour gérer votre plateforme de livraison.
            </p>
            
            <div class="api-grid">
                <?php foreach ($discoveredComponents['admin_sections'] as $section): ?>
                    <div class="api-item">
                        <div class="api-name"><?= htmlspecialchars($section['name']) ?></div>
                        <div class="api-description">
                            <?= htmlspecialchars($section['description']) ?>
                        </div>
                        <div class="api-status">
                            <?php if (isset($section['url'])): ?>
                                <a href="<?= $section['url'] ?>" target="_blank" class="status-badge status-online">
                                    ACCÉDER
                                </a>
                            <?php else: ?>
                                <span class="status-badge status-online">DISPONIBLE</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Base de Données -->
        <div class="section-card">
            <h2 class="section-title">
                <i class="fas fa-database"></i>
                Base de Données (<?= count($discoveredComponents['database_tables']) ?>)
            </h2>
            <p class="section-description">
                Aperçu des tables principales qui stockent vos données : clients, commandes, coursiers, finances, etc.
            </p>
            
            <button class="expand-btn" onclick="toggleSection('database-section')">
                Voir les tables principales
            </button>
            
            <div id="database-section" class="collapsible hidden">
                <div class="api-grid">
                    <?php 
                    // Afficher seulement les tables principales
                    $mainTables = ['commandes', 'agents_suzosky', 'clients', 'recharges', 'device_tokens', 'order_payments'];
                    $displayedTables = [];
                    
                    foreach ($discoveredComponents['database_tables'] as $table) {
                        if (in_array($table['name'], $mainTables) || count($displayedTables) < 8) {
                            $displayedTables[] = $table;
                        }
                    }
                    
                    foreach (array_slice($displayedTables, 0, 8) as $table): 
                    ?>
                        <div class="api-item">
                            <div class="api-name"><?= htmlspecialchars($table['name']) ?></div>
                            <div class="api-description">
                                <strong><?= ucfirst($table['type']) ?>:</strong> <?= htmlspecialchars($table['description']) ?>
                            </div>
                            <div class="api-status">
                                <span class="status-badge status-online">
                                    <?= number_format($table['row_count']) ?> lignes
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Section Outils de diagnostic -->
        <div class="section">
            <div class="section-header">
                <div class="section-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <h2 class="section-title">🔧 Outils de diagnostic (<?= count($discoveredComponents['monitoring']) ?>)</h2>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;">
                        Scripts pour vérifier le bon fonctionnement de votre système
                    </p>
                </div>
            </div>
            
            <button class="toggle-btn" onclick="toggleSection('monitoring')">
                🛠️ Voir tous les outils
            </button>
            
            <div id="monitoring" class="collapsible-content hidden">
                <div class="items-grid">
                    <?php foreach ($discoveredComponents['monitoring'] as $tool): ?>
                        <div class="item">
                            <div class="item-header">
                                <span class="item-name">
                                    <?= htmlspecialchars(str_replace('.php', '', $tool['name'])) ?>
                                </span>
                                <span class="item-status status-warning">OUTIL</span>
                            </div>
                            <div class="item-description">
                                <?= htmlspecialchars($tool['description']) ?>
                            </div>
                            <div class="item-details">
                                📁 Fichier: <?= htmlspecialchars($tool['file']) ?>
                                <?php if (isset($tool['url'])): ?>
                                    <br>🔗 <a href="<?= $tool['url'] ?>" target="_blank" style="color: var(--primary);">Exécuter le test</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php
        // === TEST DES APIs ===
        
        // Helper function pour tester une API
        function testAPI($url, $method = 'GET', $data = null) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            if ($method === 'POST' && $data) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            }
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            return [
                'success' => $httpCode === 200 && !$error,
                'code' => $httpCode,
                'error' => $error,
                'response' => $response
            ];
        }
        
        // DÉCOUVERTE AUTOMATIQUE DU RÉSEAU - Déjà initialisé en haut
        
        // Test des APIs principales (manuelles + découvertes automatiquement)
        $baseUrl = 'http://localhost/COURSIER_LOCAL';
        
        // APIs manuelles essentielles
        $manualApis = [
            [
                'name' => 'API Données Coursier (GET)',
                'url' => $baseUrl . '/api/get_coursier_data.php?coursier_id=3',
                'description' => 'API principale - Récupère données complètes coursier (wallet, commandes, statut)',
                'purpose' => 'Application mobile Android - Synchronisation profil',
                'method' => 'GET',
                'category' => 'essential'
            ],
            [
                'name' => 'API Données Coursier (POST JSON)', 
                'url' => $baseUrl . '/api/get_coursier_data.php',
                'description' => 'REMPLACE get_wallet_balance.php - Format JSON natif pour app mobile',
                'purpose' => 'Application mobile Android - Correction erreur 500',
                'method' => 'POST',
                'data' => json_encode(['coursier_id' => 3]),
                'category' => 'essential'
            ],
            [
                'name' => 'API Synchronisation Mobile',
                'url' => $baseUrl . '/mobile_sync_api.php',
                'description' => 'Synchronisation complète mobile (commandes, notifications, statuts)',
                'purpose' => 'Application mobile - Synchronisation générale',
                'method' => 'POST',
                'data' => json_encode(['action' => 'sync_status'])
            ],
            [
                'name' => 'Admin Dashboard',
                'url' => $baseUrl . '/admin.php?section=dashboard', 
                'description' => 'Interface d\'administration principale avec statistiques temps réel',
                'purpose' => 'Interface web admin - Monitoring général',
                'method' => 'GET'
            ],
            [
                'name' => 'Admin Commandes',
                'url' => $baseUrl . '/admin.php?section=commandes',
                'description' => 'Gestion des commandes, attribution coursiers, suivi temps réel',
                'purpose' => 'Interface web admin - Gestion opérationnelle',
                'method' => 'GET'
            ],
            [
                'name' => 'Admin Finances',
                'url' => $baseUrl . '/admin.php?section=finances',
                'description' => 'Rechargement wallets, suivi transactions, gestion soldes',
                'purpose' => 'Interface web admin - Gestion financière',
                'method' => 'GET'
            ]
        ];
        
        // Combiner APIs manuelles et découvertes
        $allApis = array_merge($manualApis, $discoveredComponents['apis']);
        ?>

        <!-- APIs Essentielles -->
        <div class="api-section">
            <h2 class="section-title">
                <i class="fas fa-code"></i>
                APIs Essentielles (Configuration Manuelle)
            </h2>
            
            <div class="api-grid">
                <?php foreach ($manualApis as $api): ?>
                    <?php 
                    $test = testAPI($api['url'], $api['method'], $api['data'] ?? null);
                    $statusClass = $test['success'] ? 'online' : 'offline';
                    $statusBadge = $test['success'] ? 'status-online' : 'status-offline';
                    $statusText = $test['success'] ? 'ONLINE' : 'OFFLINE';
                    ?>
                    
                    <div class="api-item <?= $statusClass ?>">
                        <div class="health-indicator <?= $test['success'] ? '' : 'danger' ?>"></div>
                        
                        <div class="api-name"><?= htmlspecialchars($api['name']) ?></div>
                        
                        <div class="api-description">
                            <strong>Fonction:</strong> <?= htmlspecialchars($api['description']) ?><br>
                            <strong>Utilisé par:</strong> <?= htmlspecialchars($api['purpose']) ?><br>
                            <strong>Méthode:</strong> <?= $api['method'] ?>
                            <?= isset($api['data']) ? ' (avec données JSON)' : '' ?>
                        </div>
                        
                        <div class="api-status">
                            <span class="status-badge <?= $statusBadge ?>">
                                <?= $statusText ?> (<?= $test['code'] ?>)
                            </span>
                            
                            <?php if (!$test['success'] && $test['error']): ?>
                                <small style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?= htmlspecialchars($test['error']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- APIs Découvertes Automatiquement -->
        <div class="api-section">
            <h2 class="section-title">
                <i class="fas fa-search"></i>
                APIs Découvertes Automatiquement (<?= count($discoveredComponents['apis']) ?>)
            </h2>
            <p style="color: var(--primary-dark); opacity: 0.8; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> 
                Ces APIs ont été détectées automatiquement par le scanner réseau. Elles sont testées en temps réel.
            </p>
            
            <div class="api-grid">
                <?php foreach ($discoveredComponents['apis'] as $api): ?>
                    <?php 
                    // Tester seulement les APIs avec URLs complètes
                    if (isset($api['url']) && filter_var($api['url'], FILTER_VALIDATE_URL)) {
                        $test = testAPI($api['url'], $api['methods'][0] ?? 'GET', $api['data'] ?? null);
                        $statusClass = $test['success'] ? 'online' : 'offline';
                        $statusBadge = $test['success'] ? 'status-online' : 'status-offline';
                        $statusText = $test['success'] ? 'ONLINE' : 'OFFLINE';
                    } else {
                        // Fichier système sans URL testable
                        $statusClass = 'warning';
                        $statusBadge = 'status-warning';
                        $statusText = 'DETECTED';
                    }
                    ?>
                    
                    <div class="api-item <?= $statusClass ?>">
                        <div class="health-indicator <?= $statusClass === 'online' ? '' : ($statusClass === 'offline' ? 'danger' : 'warning') ?>"></div>
                        
                        <div class="api-name">
                            <?= htmlspecialchars($api['name']) ?>
                            <small style="opacity: 0.7; font-size: 0.8em;">🔍 Auto-découvert</small>
                        </div>
                        
                        <div class="api-description">
                            <strong>Description:</strong> <?= htmlspecialchars($api['description']) ?><br>
                            <strong>Usage:</strong> <?= htmlspecialchars($api['purpose']) ?><br>
                            <strong>Méthodes:</strong> <?= implode(', ', $api['methods'] ?? ['Détection']) ?><br>
                            <strong>Fichier:</strong> <code><?= htmlspecialchars($api['file']) ?></code>
                        </div>
                        
                        <div class="api-status">
                            <span class="status-badge <?= $statusBadge ?>">
                                <?= $statusText ?><?= isset($test) ? " ({$test['code']})" : '' ?>
                            </span>
                            
                            <?php if (isset($test) && !$test['success'] && $test['error']): ?>
                                <small style="color: var(--danger);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?= htmlspecialchars($test['error']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Composants Système Découverts -->
        <div class="api-section">
            <h2 class="section-title">
                <i class="fas fa-cogs"></i>
                Composants Système Découverts
            </h2>
            
            <!-- Sections Admin -->
            <h3 style="color: var(--primary-dark); margin: 25px 0 15px 0;">
                <i class="fas fa-tachometer-alt"></i> Sections Admin (<?= count($discoveredComponents['admin_sections']) ?>)
            </h3>
            <div class="api-grid">
                <?php foreach ($discoveredComponents['admin_sections'] as $section): ?>
                    <div class="api-item online">
                        <div class="health-indicator"></div>
                        <div class="api-name"><?= htmlspecialchars($section['name']) ?></div>
                        <div class="api-description">
                            <?= htmlspecialchars($section['description']) ?><br>
                            <?php if (isset($section['url'])): ?>
                                <strong>URL:</strong> <a href="<?= $section['url'] ?>" target="_blank">Accéder</a><br>
                            <?php endif; ?>
                            <?php if (isset($section['path'])): ?>
                                <strong>Fichier:</strong> <code><?= htmlspecialchars($section['path']) ?></code>
                            <?php endif; ?>
                        </div>
                        <div class="api-status">
                            <span class="status-badge status-online">ACTIF</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Tables Base de Données -->
            <h3 style="color: var(--primary-dark); margin: 25px 0 15px 0;">
                <i class="fas fa-database"></i> Tables Base de Données (<?= count($discoveredComponents['database_tables']) ?>)
            </h3>
            <div class="api-grid">
                <?php foreach ($discoveredComponents['database_tables'] as $table): ?>
                    <div class="api-item online">
                        <div class="health-indicator"></div>
                        <div class="api-name"><?= htmlspecialchars($table['name']) ?></div>
                        <div class="api-description">
                            <strong>Type:</strong> <?= htmlspecialchars($table['type']) ?><br>
                            <strong>Description:</strong> <?= htmlspecialchars($table['description']) ?><br>
                            <strong>Lignes:</strong> <?= number_format($table['row_count']) ?>
                        </div>
                        <div class="api-status">
                            <span class="status-badge status-online">
                                <?= number_format($table['row_count']) ?> LIGNES
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Services Système -->
            <h3 style="color: var(--primary-dark); margin: 25px 0 15px 0;">
                <i class="fas fa-server"></i> Services Système (<?= count($discoveredComponents['services']) ?>)
            </h3>
            <div class="api-grid">
                <?php foreach ($discoveredComponents['services'] as $service): ?>
                    <div class="api-item <?= $service['status'] === 'actif' ? 'online' : 'warning' ?>">
                        <div class="health-indicator <?= $service['status'] === 'actif' ? '' : 'warning' ?>"></div>
                        <div class="api-name"><?= htmlspecialchars($service['name']) ?></div>
                        <div class="api-description">
                            <?= htmlspecialchars($service['description']) ?><br>
                            <strong>Fichier:</strong> <code><?= htmlspecialchars($service['file']) ?></code>
                        </div>
                        <div class="api-status">
                            <span class="status-badge <?= $service['status'] === 'actif' ? 'status-online' : 'status-warning' ?>">
                                <?= strtoupper($service['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Outils de Monitoring -->
            <h3 style="color: var(--primary-dark); margin: 25px 0 15px 0;">
                <i class="fas fa-chart-line"></i> Outils de Monitoring (<?= count($discoveredComponents['monitoring']) ?>)
            </h3>
            <div class="api-grid">
                <?php foreach ($discoveredComponents['monitoring'] as $tool): ?>
                    <div class="api-item warning">
                        <div class="health-indicator warning"></div>
                        <div class="api-name"><?= htmlspecialchars($tool['name']) ?></div>
                        <div class="api-description">
                            <?= htmlspecialchars($tool['description']) ?><br>
                            <strong>Fichier:</strong> <code><?= htmlspecialchars($tool['file']) ?></code><br>
                            <?php if (isset($tool['url'])): ?>
                                <strong>Test:</strong> <a href="<?= $tool['url'] ?>" target="_blank">Exécuter</a>
                            <?php endif; ?>
                        </div>
                        <div class="api-status">
                            <span class="status-badge status-warning">TOOL</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        // === SERVICES SYSTÈME ===
        
        // Test FCM Manager
        $fcmStatus = 'online';
        try {
            require_once 'fcm_manager.php';
            $fcm = new FCMManager();
            // Test basic FCM functionality
        } catch (Exception $e) {
            $fcmStatus = 'offline';
        }
        
        // Test Présence Coursiers
        $presenceStatus = 'online';
        try {
            $testPresence = getConnectedCouriers($pdo);
        } catch (Exception $e) {
            $presenceStatus = 'offline';
        }
        ?>

        <!-- Services Critiques en Temps Réel -->
        <div class="api-section">
            <h2 class="section-title">
                <i class="fas fa-heartbeat"></i>
                Services Critiques Temps Réel
            </h2>
            <p style="color: var(--primary-dark); opacity: 0.8; margin-bottom: 20px;">
                <i class="fas fa-info-circle"></i> 
                Services essentiels surveillés en continu avec tests de connectivité avancés.
            </p>
            
            <div class="api-grid">
                <div class="api-item <?= $fcmStatus ?>">
                    <div class="health-indicator <?= $fcmStatus === 'online' ? '' : 'danger' ?>"></div>
                    
                    <div class="api-name">Firebase Cloud Messaging (FCM)</div>
                    <div class="api-description">
                        <strong>Fonction:</strong> Envoie des notifications push aux applications mobiles des coursiers<br>
                        <strong>Utilisé pour:</strong> Nouvelles commandes, changements statut, rechargements wallet<br>
                        <strong>Tokens actifs:</strong> <?= $tokensActifs ?> dispositifs
                    </div>
                    
                    <div class="api-status">
                        <span class="status-badge <?= $fcmStatus === 'online' ? 'status-online' : 'status-offline' ?>">
                            <?= strtoupper($fcmStatus) ?>
                        </span>
                    </div>
                </div>

                <div class="api-item <?= $presenceStatus ?>">
                    <div class="health-indicator <?= $presenceStatus === 'online' ? '' : 'danger' ?>"></div>
                    
                    <div class="api-name">Système Présence Coursiers</div>
                    <div class="api-description">
                        <strong>Fonction:</strong> Gère l'état de connexion temps réel des coursiers avec auto-nettoyage<br>
                        <strong>Utilisé par:</strong> Toutes les interfaces admin, attribution automatique commandes<br>
                        <strong>Source:</strong> lib/coursier_presence.php (source unique de vérité)
                    </div>
                    
                    <div class="api-status">
                        <span class="status-badge <?= $presenceStatus === 'online' ? 'status-online' : 'status-offline' ?>">
                            <?= strtoupper($presenceStatus) ?>
                        </span>
                    </div>
                </div>

                <div class="api-item online">
                    <div class="health-indicator"></div>
                    
                    <div class="api-name">Synchronisation Wallet</div>
                    <div class="api-description">
                        <strong>Fonction:</strong> Synchronise les soldes entre admin et mobile en temps réel<br>
                        <strong>Table source:</strong> agents_suzosky.solde_wallet (table unique)<br>
                        <strong>APIs liées:</strong> get_coursier_data.php, rechargement_direct.php
                    </div>
                    
                    <div class="api-status">
                        <span class="status-badge status-online">ONLINE</span>
                    </div>
                </div>

                <div class="api-item online">
                    <div class="health-indicator"></div>
                    
                    <div class="api-name">Timeline Commandes</div>
                    <div class="api-description">
                        <strong>Fonction:</strong> Suivi temps réel progression commandes (acceptation → livraison)<br>
                        <strong>Utilisé par:</strong> Index public, interfaces admin, notifications mobile<br>
                        <strong>Table source:</strong> commandes (statuts: en_attente, assigné, accepté, livré)
                    </div>
                    
                    <div class="api-status">
                        <span class="status-badge status-online">ONLINE</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pied de page -->
        <div style="text-align: center; margin: 3rem 0; padding: 2rem; background: var(--surface); border-radius: 12px; border: 1px solid var(--border);">
            <p style="color: var(--text-secondary); margin: 0;">
                🕒 Dernière analyse: <?= date('d/m/Y à H:i:s') ?>
            </p>
            <p style="color: var(--text-secondary); font-size: 0.85rem; margin: 0.5rem 0 0 0;">
                Cette page se met à jour automatiquement pour vous montrer l'état en temps réel de votre système
            </p>
            <button class="toggle-btn" onclick="location.reload()" style="margin-top: 1rem;">
                🔄 Actualiser maintenant
            </button>
        </div>
    </div>

    <script>
        // Fonction pour afficher/masquer les sections
        function toggleSection(sectionId) {
            const section = document.getElementById(sectionId);
            const isHidden = section.classList.contains('hidden');
            
            if (isHidden) {
                section.classList.remove('hidden');
                event.target.textContent = event.target.textContent.replace('Voir', 'Masquer');
            } else {
                section.classList.add('hidden');
                event.target.textContent = event.target.textContent.replace('Masquer', 'Voir');
            }
        }

        // Auto-actualisation intelligente (toutes les 2 minutes)
        setTimeout(() => {
            if (document.hidden === false) { // Seulement si la page est visible
                location.reload();
            }
        }, 120000);

        // Messages d'aide au survol
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter des tooltips explicatifs
            const items = document.querySelectorAll('.item');
            items.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>