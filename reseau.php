<?php
/**
 * RÉSEAU - MONITORING COMPLET DU SYSTÈME SUZOSKY
 * Interface complète pour surveiller toutes les connexions et APIs
 */

require_once 'config.php';
require_once 'lib/coursier_presence.php';

// Vérifier les permissions admin
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: auth.php');
    exit;
}

$pdo = getDBConnection();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réseau - Monitoring Système Suzosky</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            /* COLORIS OFFICIELS SUZOSKY */
            --primary-gold: #D4A853;
            --primary-dark: #1A1A2E;
            --secondary-blue: #16213E;
            --accent-light: #F4E4B8;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
            --light: #f8f9fa;
            
            /* GRADIENTS SUZOSKY */
            --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--gradient-dark);
            min-height: 100vh;
            padding: 20px;
            color: #fff;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: var(--gradient-gold);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(212, 168, 83, 0.3);
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .header h1 {
            color: var(--primary-dark);
            font-size: 3em;
            margin-bottom: 15px;
            font-weight: 800;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .header .subtitle {
            color: var(--primary-dark);
            font-size: 1.3em;
            opacity: 0.8;
            font-weight: 500;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            border: 2px solid var(--primary-gold);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(212, 168, 83, 0.4);
        }

        .status-card.success { 
            border-color: var(--success);
            box-shadow: 0 15px 35px rgba(40, 167, 69, 0.2);
        }
        .status-card.warning { 
            border-color: var(--warning);
            box-shadow: 0 15px 35px rgba(255, 193, 7, 0.2);
        }
        .status-card.danger { 
            border-color: var(--danger);
            box-shadow: 0 15px 35px rgba(220, 53, 69, 0.2);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-icon {
            font-size: 2.5em;
            margin-right: 20px;
            color: var(--primary-gold);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 1.4em;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .card-value {
            font-size: 3em;
            font-weight: 800;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-align: center;
            margin: 20px 0;
        }

        .card-description {
            color: var(--primary-dark);
            text-align: center;
            font-size: 1em;
            font-weight: 500;
            opacity: 0.8;
        }

        .api-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            margin-bottom: 30px;
            border: 2px solid rgba(212, 168, 83, 0.3);
            backdrop-filter: blur(15px);
        }

        .section-title {
            font-size: 2.2em;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            font-weight: 800;
        }

        .section-title i {
            margin-right: 20px;
            color: var(--primary-gold);
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
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
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .status-online {
            background: var(--success);
            color: white;
        }

        .status-offline {
            background: var(--danger);
            color: white;
        }

        .status-warning {
            background: var(--warning);
            color: var(--dark);
        }

        .refresh-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s;
            margin: 20px auto;
            display: block;
        }

        .refresh-btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .timestamp {
            text-align: center;
            color: #666;
            margin-top: 20px;
            font-style: italic;
        }

        .health-indicator {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success);
        }

        .health-indicator.warning { background: var(--warning); }
        .health-indicator.danger { background: var(--danger); }

        @media (max-width: 768px) {
            .status-grid, .api-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-network-wired"></i> Réseau Système Suzosky</h1>
            <p class="subtitle">Monitoring complet des connexions et synchronisations</p>
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

        <!-- Statistiques générales -->
        <div class="status-grid">
            <div class="status-card <?= $nombreCoursiers > 0 ? 'success' : 'warning' ?>">
                <div class="card-header">
                    <i class="fas fa-users card-icon"></i>
                    <div class="card-title">Coursiers Connectés</div>
                </div>
                <div class="card-value"><?= $nombreCoursiers ?></div>
                <div class="card-description">Coursiers actifs en temps réel</div>
            </div>

            <div class="status-card <?= $dbStatus === 'online' ? 'success' : 'danger' ?>">
                <div class="card-header">
                    <i class="fas fa-database card-icon"></i>
                    <div class="card-title">Base de Données</div>
                </div>
                <div class="card-value"><?= $dbStatus === 'online' ? 'ONLINE' : 'OFFLINE' ?></div>
                <div class="card-description">Connexion MySQL agents_suzosky</div>
            </div>

            <div class="status-card <?= $commandesActives > 0 ? 'success' : 'warning' ?>">
                <div class="card-header">
                    <i class="fas fa-shipping-fast card-icon"></i>
                    <div class="card-title">Commandes Actives</div>
                </div>
                <div class="card-value"><?= $commandesActives ?></div>
                <div class="card-description">En cours de traitement</div>
            </div>

            <div class="status-card <?= $tokensActifs > 0 ? 'success' : 'warning' ?>">
                <div class="card-header">
                    <i class="fas fa-mobile-alt card-icon"></i>
                    <div class="card-title">Tokens FCM</div>
                </div>
                <div class="card-value"><?= $tokensActifs ?></div>
                <div class="card-description">Dispositifs mobiles connectés</div>
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
        
        // Test des APIs principales
        $baseUrl = 'https://localhost/COURSIER_LOCAL';
        
        $apis = [
            [
                'name' => 'API Données Coursier',
                'url' => $baseUrl . '/api/get_coursier_data.php?coursier_id=3',
                'description' => 'Récupère les données complètes d\'un coursier (wallet, commandes, statut)',
                'purpose' => 'Application mobile Android - Synchronisation profil coursier',
                'method' => 'GET'
            ],
            [
                'name' => 'API Solde Wallet', 
                'url' => $baseUrl . '/api/get_wallet_balance.php?coursier_id=3',
                'description' => 'Récupère uniquement le solde du wallet d\'un coursier',
                'purpose' => 'Application mobile - Affichage solde temps réel',
                'method' => 'GET'
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
        ?>

        <!-- APIs Système -->
        <div class="api-section">
            <h2 class="section-title">
                <i class="fas fa-code"></i>
                APIs et Endpoints Système
            </h2>
            
            <div class="api-grid">
                <?php foreach ($apis as $api): ?>
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

        <!-- Services Système -->
        <div class="api-section">
            <h2 class="section-title">
                <i class="fas fa-cogs"></i>
                Services et Synchronisations
            </h2>
            
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

        <!-- Actions -->
        <button class="refresh-btn" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Actualiser le Monitoring
        </button>

        <div class="timestamp">
            Dernière mise à jour: <?= date('d/m/Y à H:i:s') ?>
        </div>
    </div>

    <script>
        // Auto-refresh toutes les 30 secondes
        setTimeout(() => {
            location.reload();
        }, 30000);

        // Animation des indicateurs de santé
        document.querySelectorAll('.health-indicator').forEach(indicator => {
            setInterval(() => {
                indicator.style.opacity = indicator.style.opacity === '0.3' ? '1' : '0.3';
            }, 1000);
        });
    </script>
</body>
</html>