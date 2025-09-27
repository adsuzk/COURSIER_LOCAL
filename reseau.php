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
        <!-- En-tête -->
        <div class="header">
            <h1><i class="fas fa-network-wired"></i> Réseau Système Suzosky</h1>
            <p>Monitoring complet des APIs, synchronisations et état de santé du système</p>
            <button class="refresh-btn" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Actualiser
            </button>
        </div>

        <?php 
        // Récupérer les données de synchronisation
        $fcmSync = checkFCMTokenSync($pdo);
        $systemSync = checkSystemSync($pdo);
        ?>

        <!-- Statistiques globales -->
        <div class="global-stats">
            <div class="stat-card">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="number"><?= $systemSync['agents']['total_agents'] ?? 0 ?></div>
                <div class="label">Coursiers Total</div>
                <div class="health <?= ($systemSync['agents']['active_agents'] ?? 0) > 0 ? 'good' : 'warning' ?>">
                    <?= $systemSync['agents']['active_agents'] ?? 0 ?> Actifs
                </div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fas fa-shipping-fast"></i></div>
                <div class="number"><?= $systemSync['orders']['total_orders'] ?? 0 ?></div>
                <div class="label">Commandes Total</div>
                <div class="health <?= ($systemSync['orders']['orders_24h'] ?? 0) > 0 ? 'good' : 'warning' ?>">
                    <?= $systemSync['orders']['orders_24h'] ?? 0 ?> Aujourd'hui
                </div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                <div class="number"><?= $fcmSync['total_tokens'] ?? 0 ?></div>
                <div class="label">Tokens FCM</div>
                <div class="health <?= ($fcmSync['sync_health'] === 'GOOD') ? 'good' : (($fcmSync['sync_health'] === 'WARNING') ? 'warning' : 'error') ?>">
                    <?= $fcmSync['sync_health'] ?? 'ERROR' ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class="fas fa-credit-card"></i></div>
                <div class="number"><?= $systemSync['payments']['total_payments'] ?? 0 ?></div>
                <div class="label">Paiements Total</div>
                <div class="health <?= ($systemSync['payments']['payments_24h'] ?? 0) > 0 ? 'good' : 'warning' ?>">
                    <?= $systemSync['payments']['payments_24h'] ?? 0 ?> Aujourd'hui
                </div>
            </div>
        </div>

        <!-- État des synchronisations -->
        <div class="sync-status">
            <div class="sync-header">
                <h2><i class="fas fa-sync-alt"></i> État des Synchronisations</h2>
                <p>Suivi en temps réel des synchronisations critiques du système</p>
            </div>
            <div class="sync-grid">
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-bell"></i></div>
                    <div class="number"><?= $fcmSync['notifications_24h'] ?? 0 ?></div>
                    <div class="label">Notifications 24h</div>
                    <div class="health <?= ($fcmSync['notifications_24h'] ?? 0) > 0 ? 'good' : 'warning' ?>">
                        Système Notif: <?= ($fcmSync['notifications_24h'] ?? 0) > 0 ? 'ACTIF' : 'INACTIF' ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-user-clock"></i></div>
                    <div class="number"><?= $systemSync['agents']['online_agents'] ?? 0 ?></div>
                    <div class="label">Coursiers En Ligne</div>
                    <div class="health <?= ($systemSync['agents']['online_agents'] ?? 0) > 0 ? 'good' : 'warning' ?>">
                        Dernière Heure
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="icon"><i class="fas fa-tasks"></i></div>
                    <div class="number"><?= $systemSync['orders']['active_orders'] ?? 0 ?></div>
                    <div class="label">Commandes Actives</div>
                    <div class="health <?= ($systemSync['orders']['active_orders'] ?? 0) >= 0 ? 'good' : 'warning' ?>">
                        En Cours & Assignées
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Configuration complète des APIs par catégories
        $apiCategories = [
            'auth' => [
                'title' => 'Authentification & Sécurité',
                'icon' => 'fas fa-shield-alt',
                'description' => 'APIs de connexion, authentification et sécurité des utilisateurs',
                'apis' => [
                    [
                        'name' => 'Authentification Coursier',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/agent_auth.php',
                        'file' => '/api/agent_auth.php',
                        'description' => 'Connexion des coursiers à l\'application mobile avec validation des identifiants',
                        'purpose' => 'Permet aux coursiers de se connecter à l\'app mobile',
                        'connected_to' => 'Application Android Coursier, Base de données agents_suzosky'
                    ],
                    [
                        'name' => 'Authentification Admin',
                        'url' => 'http://localhost/COURSIER_LOCAL/auth.php',
                        'file' => '/auth.php',
                        'description' => 'Système d\'authentification pour l\'interface administrateur',
                        'purpose' => 'Sécurise l\'accès à l\'interface admin',
                        'connected_to' => 'Interface Web Admin, Sessions PHP'
                    ],
                    [
                        'name' => 'Génération de Mots de Passe',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/generate_password.php',
                        'file' => '/api/generate_password.php',
                        'description' => 'Génération automatique de mots de passe sécurisés',
                        'purpose' => 'Crée des mots de passe forts pour nouveaux agents',
                        'connected_to' => 'Système de création d\'agents'
                    ],
                    [
                        'name' => 'Réinitialisation Mot de Passe',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/reset_agent_password.php',
                        'file' => '/api/reset_agent_password.php',
                        'description' => 'Système de réinitialisation sécurisée des mots de passe',
                        'purpose' => 'Permet aux agents de récupérer leur accès',
                        'connected_to' => 'Système d\'envoi d\'emails, Base agents'
                    ]
                ]
            ],
            
            'coursier' => [
                'title' => 'Gestion des Coursiers',
                'icon' => 'fas fa-motorcycle',
                'description' => 'APIs dédiées aux coursiers : données, commandes, position, statut',
                'apis' => [
                    [
                        'name' => 'Données du Coursier',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/get_coursier_data.php',
                        'file' => '/api/get_coursier_data.php',
                        'description' => 'Récupération complète des informations du coursier (profil, solde, stats)',
                        'purpose' => 'Affiche le dashboard du coursier dans l\'app mobile',
                        'connected_to' => 'App Android, Table agents_suzosky, Portefeuille'
                    ],
                    [
                        'name' => 'Commandes du Coursier',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/get_coursier_orders.php',
                        'file' => '/api/get_coursier_orders.php',
                        'description' => 'Liste des commandes assignées au coursier avec détails complets',
                        'purpose' => 'Affiche les missions du coursier dans l\'app',
                        'connected_to' => 'App Android, Table commandes, Système d\'attribution'
                    ],
                    [
                        'name' => 'Mise à Jour Position',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/update_coursier_position.php',
                        'file' => '/api/update_coursier_position.php',
                        'description' => 'Réception et traitement de la géolocalisation du coursier',
                        'purpose' => 'Tracking en temps réel des coursiers',
                        'connected_to' => 'GPS Mobile, Suivi temps réel, Attribution automatique'
                    ],
                    [
                        'name' => 'Statut du Coursier',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/update_coursier_status.php',
                        'file' => '/api/update_coursier_status.php',
                        'description' => 'Gestion du statut disponible/occupé/hors-ligne du coursier',
                        'purpose' => 'Contrôle de la disponibilité pour nouvelles missions',
                        'connected_to' => 'Système d\'attribution, Dashboard admin'
                    ],
                    [
                        'name' => 'Positions des Coursiers',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/get_coursiers_positions.php',
                        'file' => '/api/get_coursiers_positions.php',
                        'description' => 'Récupération des positions de tous les coursiers actifs',
                        'purpose' => 'Carte administrative des coursiers en temps réel',
                        'connected_to' => 'Interface admin, Système d\'attribution'
                    ]
                ]
            ],
            
            'orders' => [
                'title' => 'Gestion des Commandes',
                'icon' => 'fas fa-box',
                'description' => 'APIs pour la création, suivi et gestion complète des commandes',
                'apis' => [
                    [
                        'name' => 'Soumission Commande',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/submit_order.php',
                        'file' => '/api/submit_order.php',
                        'description' => 'Création de nouvelles commandes par les clients avec calcul automatique',
                        'purpose' => 'Point d\'entrée pour toutes les nouvelles commandes',
                        'connected_to' => 'Site web client, Calcul prix, Attribution automatique'
                    ],
                    [
                        'name' => 'Statut Commande',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/update_order_status.php',
                        'file' => '/api/update_order_status.php',
                        'description' => 'Mise à jour du statut des commandes par les coursiers',
                        'purpose' => 'Suivi du cycle de vie des commandes',
                        'connected_to' => 'App Coursier, Notifications clients, Timeline'
                    ],
                    [
                        'name' => 'Suivi Temps Réel',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/tracking_realtime.php',
                        'file' => '/api/tracking_realtime.php',
                        'description' => 'Système de tracking en temps réel des commandes en cours',
                        'purpose' => 'Suivi live pour les clients',
                        'connected_to' => 'Interface client, GPS coursier, WebSocket'
                    ],
                    [
                        'name' => 'Attribution Intelligente',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/assign_nearest_coursier.php',
                        'file' => '/api/assign_nearest_coursier.php',
                        'description' => 'Attribution automatique au coursier le plus proche disponible',
                        'purpose' => 'Optimise l\'attribution des commandes',
                        'connected_to' => 'Géolocalisation, Algorithme d\'optimisation'
                    ],
                    [
                        'name' => 'Confirmation Livraison',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/confirm_delivery.php',
                        'file' => '/api/confirm_delivery.php',
                        'description' => 'Processus de confirmation de livraison avec code OTP',
                        'purpose' => 'Finalise la livraison de manière sécurisée',
                        'connected_to' => 'App Coursier, Paiement, Notifications'
                    ]
                ]
            ],
            
            'notifications' => [
                'title' => 'Notifications & Communications',
                'icon' => 'fas fa-bell',
                'description' => 'Système de notifications push et communications en temps réel',
                'apis' => [
                    [
                        'name' => 'Enregistrement Token FCM',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/register_device_token.php',
                        'file' => '/api/register_device_token.php',
                        'description' => 'Enregistrement des tokens FCM pour les notifications push',
                        'purpose' => 'Permet l\'envoi de notifications aux appareils',
                        'connected_to' => 'Firebase Cloud Messaging, App Android'
                    ],
                    [
                        'name' => 'Synchronisation Tokens',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/sync_tokens.php',
                        'file' => '/api/sync_tokens.php',
                        'description' => 'Synchronisation et nettoyage des tokens FCM obsolètes',
                        'purpose' => 'Maintient la base de tokens à jour',
                        'connected_to' => 'Firebase, Nettoyage automatique'
                    ],
                    [
                        'name' => 'Chat IA',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/ai_chat.php',
                        'file' => '/api/ai_chat.php',
                        'description' => 'Système de chat intelligent pour support client automatisé',
                        'purpose' => 'Support client 24/7 avec IA',
                        'connected_to' => 'Interface client, Base de connaissances'
                    ]
                ]
            ],
            
            'payments' => [
                'title' => 'Paiements & Finances',
                'icon' => 'fas fa-credit-card',
                'description' => 'APIs de gestion des paiements, portefeuilles et transactions',
                'apis' => [
                    [
                        'name' => 'Initiation Paiement',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/initiate_order_payment.php',
                        'file' => '/api/initiate_order_payment.php',
                        'description' => 'Démarrage du processus de paiement pour une commande',
                        'purpose' => 'Lance le paiement via CinetPay ou autres',
                        'connected_to' => 'CinetPay Gateway, Commandes'
                    ],
                    [
                        'name' => 'Callback CinetPay',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/cinetpay_callback.php',
                        'file' => '/api/cinetpay_callback.php',
                        'description' => 'Traitement des retours de paiement CinetPay',
                        'purpose' => 'Confirme ou rejette les paiements',
                        'connected_to' => 'CinetPay, Validation commandes, Portefeuilles'
                    ],
                    [
                        'name' => 'Rechargement Portefeuille',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/init_recharge.php',
                        'file' => '/api/init_recharge.php',
                        'description' => 'Système de rechargement du portefeuille client',
                        'purpose' => 'Permet aux clients d\'ajouter des fonds',
                        'connected_to' => 'Portefeuille client, Paiements'
                    ],
                    [
                        'name' => 'Enregistrements Financiers',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/create_financial_records.php',
                        'file' => '/api/create_financial_records.php',
                        'description' => 'Création automatique des enregistrements comptables',
                        'purpose' => 'Traçabilité financière complète',
                        'connected_to' => 'Comptabilité, Audit, Rapports'
                    ]
                ]
            ],
            
            'system' => [
                'title' => 'Système & Monitoring',
                'icon' => 'fas fa-cogs',
                'description' => 'APIs de monitoring, maintenance et utilitaires système',
                'apis' => [
                    [
                        'name' => 'Vérification Santé Auth',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/auth_healthcheck.php',
                        'file' => '/api/auth_healthcheck.php',
                        'description' => 'Diagnostic de santé du système d\'authentification',
                        'purpose' => 'Surveille le bon fonctionnement de l\'auth',
                        'connected_to' => 'Monitoring, Alertes admin'
                    ],
                    [
                        'name' => 'Statut Synchronisation',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/system_sync_status.php',
                        'file' => '/api/system_sync_status.php',
                        'description' => 'État global des synchronisations du système',
                        'purpose' => 'Dashboard de santé système',
                        'connected_to' => 'Interface admin, Monitoring'
                    ],
                    [
                        'name' => 'Mises à Jour App',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/app_updates.php',
                        'file' => '/api/app_updates.php',
                        'description' => 'Gestion des mises à jour de l\'application mobile',
                        'purpose' => 'Notifie et distribue les mises à jour',
                        'connected_to' => 'App Store, Téléchargements'
                    ],
                    [
                        'name' => 'Télémétrie',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/telemetry.php',
                        'file' => '/api/telemetry.php',
                        'description' => 'Collecte de données de performance et d\'usage',
                        'purpose' => 'Analyse performance et comportement utilisateurs',
                        'connected_to' => 'Analytics, Optimisation'
                    ],
                    [
                        'name' => 'Journalisation Erreurs JS',
                        'url' => 'http://localhost/COURSIER_LOCAL/api/log_js_error.php',
                        'file' => '/api/log_js_error.php',
                        'description' => 'Collecte centralisée des erreurs JavaScript client',
                        'purpose' => 'Debug et amélioration interface web',
                        'connected_to' => 'Frontend, Logs centralisés'
                    ]
                ]
            ]
        ];

        // Afficher chaque catégorie d'APIs
        foreach ($apiCategories as $categoryKey => $category): ?>
            <div class="api-section">
                <div class="section-header">
                    <h2>
                        <i class="<?= $category['icon'] ?>"></i>
                        <?= $category['title'] ?>
                    </h2>
                    <p><?= $category['description'] ?></p>
                </div>
                
                <div class="api-grid">
                    <?php foreach ($category['apis'] as $api):
                        $apiTest = testApiAdvanced($api['url']);
                    ?>
                        <div class="api-card">
                            <div class="api-header">
                                <div class="api-name"><?= htmlspecialchars($api['name']) ?></div>
                                <div class="api-status">
                                    <div class="status-badge <?= $apiTest['is_online'] ? 'online' : 'offline' ?>">
                                        <?= $apiTest['is_online'] ? 'ONLINE' : 'OFFLINE' ?>
                                    </div>
                                    <?php if ($apiTest['is_online']): ?>
                                        <div class="response-time"><?= $apiTest['response_time'] ?>ms</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="api-description">
                                <strong>🔧 Fonction :</strong> <?= htmlspecialchars($api['description']) ?><br><br>
                                <strong>🎯 Utilité :</strong> <?= htmlspecialchars($api['purpose']) ?><br><br>
                                <strong>🔗 Connecté à :</strong> <?= htmlspecialchars($api['connected_to']) ?>
                            </div>
                            
                            <div class="api-details">
                                <div class="detail-item">
                                    <span class="detail-label">Fichier :</span>
                                    <span class="detail-value"><?= htmlspecialchars($api['file']) ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Code HTTP :</span>
                                    <span class="detail-value"><?= $apiTest['status_code'] ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Type :</span>
                                    <span class="detail-value"><?= $apiTest['content_type'] ?: 'N/A' ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Catégorie :</span>
                                    <span class="detail-value"><?= ucfirst($categoryKey) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Footer -->
        <div style="text-align: center; margin: 40px 0; padding: 20px; background: var(--glass-bg); border-radius: 12px;">
            <p style="color: var(--primary-gold); font-size: 1.1rem; margin-bottom: 10px;">
                <i class="fas fa-clock"></i> Dernière vérification : <?= date('d/m/Y H:i:s') ?>
            </p>
            <p style="opacity: 0.7;">
                Interface de monitoring automatique - Système Suzosky v2.0
            </p>
        </div>
    </div>
</body>
</html>