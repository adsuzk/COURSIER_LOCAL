<?php
/**
 * RÉSEAU SUZOSKY - Monitoring Complet des APIs et Synchronisations
 * Interface complète pour surveiller l'état de santé du système
 */

require_once 'config.php';

// Vérifier les permissions admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: auth.php');
    exit;
}

$pdo = getDBConnection();

// Fonction de test API avancée
function testApiAdvanced($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $start = microtime(true);
    $result = curl_exec($ch);
    $responseTime = round((microtime(true) - $start) * 1000, 2);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    return [
        'status_code' => $httpCode,
        'response_time' => $responseTime,
        'content_type' => $contentType,
        'is_online' => $httpCode == 200 || $httpCode == 302
    ];
}

// Vérifier l'état de la synchronisation des tokens FCM
function checkFCMTokenSync($pdo) {
    try {
        // Vérifier les tokens récents
        $stmt = $pdo->query("SELECT COUNT(*) as total_tokens, 
                            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as recent_tokens
                            FROM fcm_tokens WHERE token IS NOT NULL AND token != ''");
        $tokens = $stmt->fetch();
        
        // Vérifier les notifications envoyées récemment
        $stmt = $pdo->query("SELECT COUNT(*) as notifications_24h 
                            FROM notifications WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $notifications = $stmt->fetch();
        
        return [
            'total_tokens' => $tokens['total_tokens'] ?? 0,
            'recent_tokens' => $tokens['recent_tokens'] ?? 0,
            'notifications_24h' => $notifications['notifications_24h'] ?? 0,
            'sync_health' => ($tokens['total_tokens'] ?? 0) > 0 ? 'GOOD' : 'WARNING'
        ];
    } catch (Exception $e) {
        return ['sync_health' => 'ERROR', 'error' => $e->getMessage()];
    }
}

// Vérifier l'état des synchronisations système
function checkSystemSync($pdo) {
    $results = [];
    
    try {
        // Vérifier la synchronisation des coursiers
        $stmt = $pdo->query("SELECT COUNT(*) as total_agents,
                            COUNT(CASE WHEN statut = 'actif' THEN 1 END) as active_agents,
                            COUNT(CASE WHEN last_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as online_agents
                            FROM agents_suzosky");
        $results['agents'] = $stmt->fetch();
        
        // Vérifier les commandes récentes
        $stmt = $pdo->query("SELECT COUNT(*) as total_orders,
                            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as orders_24h,
                            COUNT(CASE WHEN statut IN ('en_cours', 'assignee') THEN 1 END) as active_orders
                            FROM commandes");
        $results['orders'] = $stmt->fetch();
        
        // Vérifier les paiements
        $stmt = $pdo->query("SELECT COUNT(*) as total_payments,
                            COUNT(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as payments_24h
                            FROM transactions_suzosky WHERE type = 'payment'");
        $results['payments'] = $stmt->fetch();
        
    } catch (Exception $e) {
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réseau & APIs - Suzosky</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #D4A853;
            --primary-dark: #1A1A2E;
            --status-success: #27AE60;
            --status-warning: #F39C12;
            --status-error: #E74C3C;
            --glass-bg: rgba(255,255,255,0.1);
            --glass-border: rgba(255,255,255,0.2);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            color: white;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 3rem;
            color: var(--primary-gold);
            text-shadow: 0 0 30px rgba(212, 168, 83, 0.5);
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.8;
            margin-bottom: 20px;
        }
        
        .refresh-btn {
            background: var(--primary-gold);
            color: var(--primary-dark);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #E8C468;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 168, 83, 0.3);
        }
        
        /* === STATISTIQUES GLOBALES === */
        .global-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .icon {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--primary-gold);
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-gold);
            margin-bottom: 8px;
        }
        
        .stat-card .label {
            font-size: 1rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .stat-card .health {
            margin-top: 12px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .health.good { background: var(--status-success); color: white; }
        .health.warning { background: var(--status-warning); color: white; }
        .health.error { background: var(--status-error); color: white; }
        
        /* === SECTIONS D'APIS === */
        .api-section {
            margin-bottom: 50px;
        }
        
        .section-header {
            background: linear-gradient(135deg, var(--primary-gold), #E8C468);
            color: var(--primary-dark);
            padding: 20px;
            border-radius: 12px 12px 0 0;
            margin-bottom: 0;
        }
        
        .section-header h2 {
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-header p {
            margin-top: 8px;
            opacity: 0.8;
        }
        
        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 0 0 12px 12px;
            padding: 30px;
            backdrop-filter: blur(10px);
        }
        
        .api-card {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .api-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .api-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-gold), #E8C468);
        }
        
        .api-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .api-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary-gold);
            flex: 1;
        }
        
        .api-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-badge.online {
            background: var(--status-success);
            color: white;
        }
        
        .status-badge.offline {
            background: var(--status-error);
            color: white;
        }
        
        .response-time {
            font-size: 0.9rem;
            opacity: 0.7;
        }
        
        .api-description {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 16px;
            opacity: 0.9;
        }
        
        .api-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 0.9rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
        }
        
        .detail-label {
            opacity: 0.7;
        }
        
        .detail-value {
            font-weight: bold;
            color: var(--primary-gold);
        }
        
        /* === SYNC STATUS === */
        .sync-status {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 40px;
            backdrop-filter: blur(10px);
        }
        
        .sync-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .sync-header h2 {
            font-size: 2rem;
            color: var(--primary-gold);
            margin-bottom: 10px;
        }
        
        .sync-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .global-stats {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .api-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            
            .api-card {
                padding: 20px;
            }
            
            .api-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .api-status {
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Réseau Système Suzosky</h1>
        
        <!-- Statistiques rapides -->
        <div class="stats">
            <?php
            // Compter quelques éléments de base
            try {
                $coursiers = $pdo->query("SELECT COUNT(*) FROM agents_suzosky")->fetchColumn();
                $commandes = $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
                $clients = $pdo->query("SELECT COUNT(*) FROM clients_particuliers")->fetchColumn();
            } catch (Exception $e) {
                $coursiers = $commandes = $clients = 0;
            }
            ?>
            <div class="stat-box">
                <div class="stat-number"><?= $coursiers ?></div>
                <div class="stat-label">Coursiers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $commandes ?></div>
                <div class="stat-label">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $clients ?></div>
                <div class="stat-label">Clients</div>
            </div>
        </div>
        
        <!-- APIs principales -->
        <h2 style="color: #D4A853; margin: 30px 0;">APIs Principales</h2>
        <div class="api-grid">
            <?php
            // Liste simple des APIs principales
            $apis = [
                [
                    'name' => 'API Authentification Coursier',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/agent_auth.php',
                    'description' => 'Connexion des coursiers à l\'application mobile'
                ],
                [
                    'name' => 'API Données Coursier',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/get_coursier_data.php',
                    'description' => 'Récupération des informations du coursier (profil, solde, statistiques)'
                ],
                [
                    'name' => 'API Commandes Coursier',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/get_coursier_orders.php',
                    'description' => 'Liste des commandes assignées au coursier'
                ],
                [
                    'name' => 'API Soumission Commande',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/submit_order.php',
                    'description' => 'Création de nouvelles commandes par les clients'
                ],
                [
                    'name' => 'API Statut Commande',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/update_order_status.php',
                    'description' => 'Mise à jour du statut des commandes'
                ]
            ];
            
            foreach ($apis as $api):
                $status = testApiSimple($api['url']);
                $isOnline = $status == 200;
            ?>
                <div class="api-card">
                    <div class="api-name"><?= htmlspecialchars($api['name']) ?></div>
                    <div class="api-description"><?= htmlspecialchars($api['description']) ?></div>
                    <div class="api-status">
                        <span class="<?= $isOnline ? 'status-online' : 'status-offline' ?>">
                            <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?> (<?= $status ?>)
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <p style="text-align: center; color: #D4A853; margin-top: 30px;">
            Dernière vérification: <?= date('d/m/Y H:i:s') ?>
        </p>
    </div>
</body>
</html>