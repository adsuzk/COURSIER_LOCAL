<?php
// Dashboard moderne Suzosky avec système de feux pour coursiers
require_once __DIR__ . '/../config.php';

// Fonctions pour récupérer les données du dashboard
function getDashboardStats() {
    $pdo = getDBConnection();
    
    // Statistiques des commandes
    $commandesStats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'nouvelle' THEN 1 ELSE 0 END) as nouvelles,
            SUM(CASE WHEN statut = 'assignee' THEN 1 ELSE 0 END) as assignees,
            SUM(CASE WHEN statut = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut = 'livree' THEN 1 ELSE 0 END) as livrees,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as aujourdhui
        FROM commandes
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Statistiques des coursiers (compteur total uniquement ici, présence gérée côté API)
    try {
        $totalCoursiers = (int) $pdo->query('SELECT COUNT(*) FROM agents_suzosky')->fetchColumn();
    } catch (Throwable $ignored) {
        $totalCoursiers = 0;
    }

    $coursiersStats = [
        'total' => $totalCoursiers,
        'en_ligne' => '--',
        'hors_ligne' => '--',
        'occupe' => '--',
        'avec_token' => '--'
    ];
    
    // Revenus du jour
    $revenusQuery = $pdo->query("
        SELECT 
            COALESCE(SUM(prix_total), 0) as revenus_jour,
            COUNT(*) as commandes_jour
        FROM commandes 
        WHERE DATE(created_at) = CURDATE() AND statut = 'livree'
    ")->fetch(PDO::FETCH_ASSOC);
    
    $revenus = [
        'revenus_jour' => (float) ($revenusQuery['revenus_jour'] ?? 0),
        'commandes_jour' => (int) ($revenusQuery['commandes_jour'] ?? 0)
    ];
    
    return [
        'commandes' => $commandesStats ?: ['total' => 0, 'nouvelles' => 0, 'assignees' => 0, 'en_cours' => 0, 'livrees' => 0, 'aujourdhui' => 0],
        'coursiers' => $coursiersStats,
        'revenus' => $revenus
    ];
}

function getCoursiersList() {
    $pdo = getDBConnection();
    
    try {
        $coursiers = $pdo->query("
            SELECT id, nom, prenoms, telephone, statut_connexion, last_login_at, last_login_ip
            FROM agents_suzosky 
            ORDER BY statut_connexion DESC, last_login_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Error fetching coursiers: " . $e->getMessage());
        $coursiers = [];
    }
    
    foreach ($coursiers as &$coursier) {
        $coursier['status_info'] = getCoursierStatus($coursier);
    }
    
    return $coursiers;
}

function getCoursierStatus($coursier) {
    $status = $coursier['statut_connexion'] ?? 'hors_ligne';
    $lastLogin = $coursier['last_login_at'] ?? null;
    
    $info = [
        'status' => $status,
        'color' => 'gray',
        'text' => 'Hors ligne'
    ];
    
    if ($status === 'en_ligne') {
        $info['color'] = 'green';
        $info['text'] = 'En ligne';
    } elseif ($status === 'occupe') {
        $info['color'] = 'orange';
        $info['text'] = 'Occupé';
    }
    
    return $info;
}

try {
    $stats = getDashboardStats();
    $coursiers = getCoursiersList();
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $stats = ['commandes' => ['total' => 0, 'nouvelles' => 0, 'assignees' => 0, 'en_cours' => 0, 'livrees' => 0, 'aujourdhui' => 0], 'coursiers' => ['total' => 0, 'en_ligne' => 0, 'hors_ligne' => 0, 'occupe' => 0, 'avec_token' => 0], 'revenus' => ['revenus_jour' => 0, 'commandes_jour' => 0]];
    $coursiers = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Suzosky - Administration</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            min-height: 100vh;
            color: #FFFFFF;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(212, 168, 83, 0.3);
        }

        .header-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-right: 15px;
        }

        .stat-icon.commandes { background: linear-gradient(135deg, #0F3460, #16213E); }
        .stat-icon.coursiers { background: linear-gradient(135deg, #27AE60, #2dd981); }
        .stat-icon.revenus { background: linear-gradient(135deg, #D4A853, #F4E4B8); }

        .stat-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #D4A853;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 10px;
        }

        .stat-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .stat-detail {
            background: rgba(212, 168, 83, 0.15);
            border: 1px solid rgba(212, 168, 83, 0.3);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
            color: #D4A853;
            backdrop-filter: blur(10px);
        }

        .coursiers-section {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 15px;
            color: #667eea;
        }

        .coursiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .coursier-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-left: 5px solid transparent;
        }

        .coursier-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .coursier-card.status-green { border-left-color: #48bb78; }
        .coursier-card.status-orange { border-left-color: #ed8936; }
        .coursier-card.status-red { border-left-color: #f56565; }

        .coursier-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .coursier-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .coursier-matricule {
            font-size: 0.9rem;
            color: #718096;
            background: #edf2f7;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .status-light {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            position: relative;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        .status-light.green {
            background: #48bb78;
            box-shadow: 0 0 15px rgba(72, 187, 120, 0.6);
        }

        .status-light.orange {
            background: #ed8936;
            box-shadow: 0 0 15px rgba(237, 137, 54, 0.6);
        }

        .status-light.red {
            background: #f56565;
            box-shadow: 0 0 15px rgba(245, 101, 101, 0.6);
        }

        .status-light::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 8px;
            height: 8px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }

        .coursier-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.9rem;
            color: #4a5568;
        }

        .detail-item {
            display: flex;
            align-items: center;
        }

        .detail-item i {
            margin-right: 8px;
            width: 16px;
            color: #718096;
        }

        .status-label {
            background: #edf2f7;
            color: #4a5568;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 10px;
            display: inline-block;
        }

        .status-label.green { background: #c6f6d5; color: #22543d; }
        .status-label.orange { background: #fbd38d; color: #744210; }
        .status-label.red { background: #fed7d7; color: #742a2a; }

        .token-indicator {
            display: flex;
            align-items: center;
            font-size: 0.8rem;
            margin-top: 8px;
        }

        .token-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .token-dot.active { background: #48bb78; }
        .token-dot.inactive { background: #cbd5e0; }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
            transition: transform 0.2s ease;
        }

        .refresh-btn:hover {
            transform: scale(1.1);
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(72, 187, 120, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(72, 187, 120, 0); }
            100% { box-shadow: 0 0 0 0 rgba(72, 187, 120, 0); }
        }

        .status-light.green {
            animation: pulse 2s infinite;
        }

        @media (max-width: 768px) {
            .dashboard-container { padding: 15px; }
            .header-title { font-size: 2rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .coursiers-grid { grid-template-columns: 1fr; }
            .coursier-details { grid-template-columns: 1fr; }
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #718096;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1 class="header-title">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard Suzosky
            </h1>
            <p class="header-subtitle">
                Tableau de bord en temps réel - <?= date('d/m/Y H:i') ?>
            </p>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <!-- Commandes -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon commandes">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-title">Commandes</div>
                </div>
                <div class="stat-value"><?= number_format($stats['commandes']['total']) ?></div>
                <div class="stat-details">
                    <div class="stat-detail">Nouvelles: <?= $stats['commandes']['nouvelles'] ?></div>
                    <div class="stat-detail">En cours: <?= $stats['commandes']['en_cours'] ?></div>
                    <div class="stat-detail">Livrées: <?= $stats['commandes']['livrees'] ?></div>
                    <div class="stat-detail">Aujourd'hui: <?= $stats['commandes']['aujourdhui'] ?></div>
                </div>
            </div>

            <!-- Coursiers -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon coursiers">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <div class="stat-title">Coursiers</div>
                </div>
                <div class="stat-value" data-dashboard-total data-total-count="<?= (int) $stats['coursiers']['total'] ?>"><?= number_format((int) $stats['coursiers']['total']) ?></div>
                <div class="stat-details">
                    <div class="stat-detail">En ligne: <span data-dashboard-online><?= htmlspecialchars($stats['coursiers']['en_ligne']) ?></span></div>
                    <div class="stat-detail">Hors ligne: <span data-dashboard-offline><?= htmlspecialchars($stats['coursiers']['hors_ligne']) ?></span></div>
                    <div class="stat-detail">Occupés: <span data-dashboard-busy><?= htmlspecialchars($stats['coursiers']['occupe']) ?></span></div>
                    <div class="stat-detail">Avec token: <span data-dashboard-tokens><?= htmlspecialchars($stats['coursiers']['avec_token']) ?></span></div>
                </div>
            </div>

            <!-- Revenus -->
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon revenus">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-title">Revenus du jour</div>
                </div>
                <div class="stat-value"><?= number_format($stats['revenus']['revenus_jour']) ?> FCFA</div>
                <div class="stat-details">
                    <div class="stat-detail">Commandes livrées: <?= $stats['revenus']['commandes_jour'] ?></div>
                    <div class="stat-detail">Moyenne: <?= $stats['revenus']['commandes_jour'] > 0 ? number_format($stats['revenus']['revenus_jour'] / $stats['revenus']['commandes_jour']) : 0 ?> FCFA</div>
                </div>
            </div>
        </div>

        <!-- Section Coursiers avec Feux -->
        <div class="coursiers-section">
            <h2 class="section-title">
                <i class="fas fa-traffic-light"></i>
                Statut des Coursiers en Temps Réel
            </h2>

            <div class="coursiers-grid" data-coursiers-grid>
                <div class="empty-state" data-coursiers-empty>
                    <i class="fas fa-spinner fa-spin"></i>
                    <h3>Chargement des coursiers…</h3>
                    <p>Les coursiers apparaîtront ici dès qu'ils seront connectés.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bouton Refresh -->
    <button class="refresh-btn" id="dashboardRefreshBtn" type="button" title="Actualiser">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        const connectivityEndpoint = '../api/coursiers_connectes.php';

        const htmlEscape = (value) => {
            if (value === undefined || value === null) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        };

        const formatRelativeTime = (value) => {
            if (!value) {
                return 'Dernière activité inconnue';
            }
            const normalized = String(value).replace(' ', 'T');
            const timestamp = Date.parse(normalized);
            if (Number.isNaN(timestamp)) {
                return 'Dernière activité inconnue';
            }
            const diffMs = Date.now() - timestamp;
            if (diffMs <= 0) {
                return "Dernière activité : à l'instant";
            }
            const diffSec = Math.floor(diffMs / 1000);
            if (diffSec < 60) {
                return "Dernière activité : à l'instant";
            }
            if (diffSec < 3600) {
                const minutes = Math.floor(diffSec / 60);
                return `Dernière activité : il y a ${minutes} minute${minutes > 1 ? 's' : ''}`;
            }
            if (diffSec < 86400) {
                const hours = Math.floor(diffSec / 3600);
                return `Dernière activité : il y a ${hours} heure${hours > 1 ? 's' : ''}`;
            }
            const days = Math.floor(diffSec / 86400);
            return `Dernière activité : il y a ${days} jour${days > 1 ? 's' : ''}`;
        };

        async function refreshDashboardConnectivity() {
            const grid = document.querySelector('[data-coursiers-grid]');
            const emptyPlaceholder = document.querySelector('[data-coursiers-empty]');
            const totalEl = document.querySelector('[data-dashboard-total]');
            const onlineEl = document.querySelector('[data-dashboard-online]');
            const offlineEl = document.querySelector('[data-dashboard-offline]');
            const busyEl = document.querySelector('[data-dashboard-busy]');
            const tokensEl = document.querySelector('[data-dashboard-tokens]');

            if (!grid) {
                return;
            }

            try {
                if (emptyPlaceholder) {
                    emptyPlaceholder.innerHTML = `
                        <i class="fas fa-spinner fa-spin"></i>
                        <h3>Chargement des coursiers…</h3>
                        <p>Les coursiers apparaîtront ici dès qu'ils seront connectés.</p>
                    `;
                }

                const response = await fetch(connectivityEndpoint, { cache: 'no-store' });
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                const payload = await response.json();
                const couriers = Array.isArray(payload.data) ? payload.data : [];
                const summary = payload.meta && payload.meta.fcm_summary ? payload.meta.fcm_summary : null;

                const fragment = document.createDocumentFragment();
                let orangeCount = 0;

                couriers.forEach((coursier) => {
                    const id = Number.parseInt(coursier.id, 10) || 0;
                    const statusLight = coursier.status_light || {};
                    let color = String(statusLight.color || '').toLowerCase();
                    if (!['green', 'orange', 'red'].includes(color)) {
                        color = 'red';
                    }
                    if (color === 'orange') {
                        orangeCount += 1;
                    }

                    const nameParts = [coursier.nom || '', coursier.prenoms || '']
                        .map((part) => part ? String(part).trim() : '')
                        .filter(Boolean);
                    const displayName = nameParts.join(' ') || `Coursier #${id}`;
                    const statusLabel = statusLight.label || 'Statut inconnu';
                    const lastSeen = coursier.last_seen_at || coursier.last_login_at || null;
                    const telephone = coursier.telephone ? String(coursier.telephone).trim() : 'N/A';
                    const typePoste = coursier.type_poste ? String(coursier.type_poste).replace(/_/g, ' ').toUpperCase() : 'TYPE INCONNU';
                    const fcmTokens = Number.parseInt(coursier.fcm_tokens, 10) || 0;

                    const card = document.createElement('div');
                    card.className = `coursier-card status-${color}`;
                    card.innerHTML = `
                        <div class="coursier-header">
                            <div class="coursier-info">
                                <h3>${htmlEscape(displayName)}</h3>
                                <div class="coursier-matricule">ID #${htmlEscape(id)}</div>
                            </div>
                            <div class="status-light ${color}"></div>
                        </div>
                        <div class="coursier-details">
                            <div class="detail-item">
                                <i class="fas fa-phone"></i>
                                ${htmlEscape(telephone)}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                ${htmlEscape(formatRelativeTime(lastSeen))}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-bell"></i>
                                ${fcmTokens} token${fcmTokens > 1 ? 's' : ''}
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-briefcase"></i>
                                ${htmlEscape(typePoste)}
                            </div>
                        </div>
                        <div class="status-label ${color}">
                            ${htmlEscape(statusLabel)}
                        </div>
                    `;
                    fragment.appendChild(card);
                });

                grid.innerHTML = '';
                if (couriers.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'empty-state';
                    empty.innerHTML = `
                        <i class="fas fa-motorcycle"></i>
                        <h3>Aucun coursier actif</h3>
                        <p>Les coursiers apparaîtront ici lorsqu'ils se connecteront à l'application.</p>
                    `;
                    grid.appendChild(empty);
                } else {
                    grid.appendChild(fragment);
                }

                const totalCount = totalEl ? Number.parseInt(totalEl.dataset.totalCount || totalEl.textContent, 10) || 0 : 0;
                if (onlineEl) onlineEl.textContent = couriers.length;
                if (offlineEl) offlineEl.textContent = totalCount > 0 ? Math.max(totalCount - couriers.length, 0) : '--';
                if (busyEl) busyEl.textContent = orangeCount;
                if (tokensEl) {
                    const withTokens = summary && typeof summary.with_fcm === 'number' ? summary.with_fcm : couriers.filter(c => Number.parseInt(c.fcm_tokens, 10) > 0).length;
                    tokensEl.textContent = withTokens;
                    if (summary && typeof summary.fcm_rate === 'number') {
                        tokensEl.setAttribute('title', `FCM actif : ${summary.fcm_rate}% (${withTokens}/${summary.total_connected ?? couriers.length})`);
                    } else {
                        tokensEl.removeAttribute('title');
                    }
                }

                const lights = grid.querySelectorAll('.status-light.green');
                lights.forEach((light) => {
                    light.style.animation = 'pulse 2s infinite';
                });
            } catch (error) {
                console.error('Dashboard connectivity error:', error);
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-wifi"></i>
                        <h3>Impossible de charger les coursiers</h3>
                        <p>Vérifiez votre connexion réseau et réessayez.</p>
                    </div>
                `;
                if (onlineEl) onlineEl.textContent = '--';
                if (offlineEl) offlineEl.textContent = '--';
                if (busyEl) busyEl.textContent = '--';
                if (tokensEl) tokensEl.textContent = '--';
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const refreshBtn = document.getElementById('dashboardRefreshBtn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    refreshDashboardConnectivity();
                });
            }

            refreshDashboardConnectivity();
            setInterval(refreshDashboardConnectivity, 30000);
        });
    </script>
</body>
</html>