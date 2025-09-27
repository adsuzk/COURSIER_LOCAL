<?php
// admin/app_monitoring.php
// Dashboard de monitoring des applications - Interface avec onglets par application

require_once __DIR__ . '/../config.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    die("Erreur de connexion base de données : " . htmlspecialchars($e->getMessage()));
}

/**
 * Récupération des applications disponibles
 */
function getAvailableApps($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                DISTINCT app_name,
                COUNT(DISTINCT device_id) as total_devices,
                COUNT(DISTINCT CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN device_id END) as active_today,
                MAX(app_version_code) as latest_version,
                COUNT(DISTINCT CASE WHEN c.device_id IS NOT NULL THEN d.device_id END) as devices_with_issues
            FROM app_devices d
            LEFT JOIN app_crashes c ON c.device_id = d.device_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND c.is_resolved = 0
            WHERE d.is_active = 1 AND d.app_name IS NOT NULL AND d.app_name != ''
            GROUP BY app_name
            ORDER BY total_devices DESC
        ");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Exception $e) {
        error_log("Erreur getAvailableApps: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupération des appareils pour une application spécifique
 */
function getDevicesForApp($pdo, $appName) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                d.device_id,
                d.device_brand,
                d.device_model,
                d.android_version,
                d.app_version_name,
                d.app_version_code,
                d.last_seen,
                d.first_seen,
                d.installation_source,
                TIMESTAMPDIFF(SECOND, d.last_seen, NOW()) as seconds_since_seen,
                COUNT(c.id) as total_crashes,
                COUNT(CASE WHEN c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_crashes,
                MAX(c.last_occurred) as last_crash,
                GROUP_CONCAT(DISTINCT c.exception_class ORDER BY c.last_occurred DESC SEPARATOR ', ') as crash_types,
                (SELECT COUNT(*) FROM app_sessions s WHERE s.device_id = d.device_id AND s.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as sessions_count,
                (SELECT AVG(duration_seconds) FROM app_sessions s WHERE s.device_id = d.device_id AND s.ended_at IS NOT NULL AND s.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as avg_session_duration
            FROM app_devices d
            LEFT JOIN app_crashes c ON c.device_id = d.device_id
            WHERE d.is_active = 1 AND d.app_name = ?
            GROUP BY d.device_id
            ORDER BY d.last_seen DESC
        ");
        $stmt->execute([$appName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur getDevicesForApp: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupération des appareils avec problèmes pour une application
 */
function getProblematicDevices($pdo, $appName) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                d.device_id,
                d.device_brand,
                d.device_model,
                d.android_version,
                d.app_version_name,
                d.app_version_code,
                d.last_seen,
                COUNT(c.id) as total_crashes,
                COUNT(CASE WHEN c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as crashes_24h,
                COUNT(CASE WHEN c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as crashes_7d,
                MAX(c.last_occurred) as last_crash,
                GROUP_CONCAT(
                    DISTINCT CONCAT(
                        c.exception_class, 
                        ' (', c.occurrence_count, 'x)',
                        CASE WHEN c.screen_name THEN CONCAT(' on ', c.screen_name) ELSE '' END
                    ) 
                    ORDER BY c.last_occurred DESC 
                    SEPARATOR ' | '
                ) as detailed_issues
            FROM app_devices d
            JOIN app_crashes c ON c.device_id = d.device_id
            WHERE d.is_active = 1 AND d.app_name = ? AND c.is_resolved = 0
            GROUP BY d.device_id
            HAVING total_crashes > 0
            ORDER BY crashes_24h DESC, crashes_7d DESC, last_crash DESC
        ");
        $stmt->execute([$appName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur getProblematicDevices: " . $e->getMessage());
        return [];
    }
}

/**
 * Récupération du diagnostic détaillé d'un appareil
 */
function getDeviceDiagnostic($pdo, $deviceId, $appName) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                d.*,
                TIMESTAMPDIFF(SECOND, d.last_seen, NOW()) as seconds_since_seen,
                (SELECT COUNT(*) FROM app_sessions s WHERE s.device_id = d.device_id AND s.started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as sessions_7d,
                (SELECT AVG(duration_seconds) FROM app_sessions s WHERE s.device_id = d.device_id AND s.ended_at IS NOT NULL) as avg_session_duration,
                (SELECT COUNT(*) FROM app_crashes c WHERE c.device_id = d.device_id) as total_crashes_ever
            FROM app_devices d
            WHERE d.device_id = ? AND d.app_name = ?
        ");
        $stmt->execute([$deviceId, $appName]);
        $device = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                TIMESTAMPDIFF(MINUTE, c.last_occurred, NOW()) as minutes_ago
            FROM app_crashes c
            WHERE c.device_id = ?
            ORDER BY c.last_occurred DESC
            LIMIT 10
        ");
        $stmt->execute([$deviceId]);
        $crashes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT 
                s.*,
                TIMESTAMPDIFF(MINUTE, s.started_at, NOW()) as minutes_ago
            FROM app_sessions s
            WHERE s.device_id = ?
            ORDER BY s.started_at DESC
            LIMIT 5
        ");
        $stmt->execute([$deviceId]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'device' => $device,
            'crashes' => $crashes,
            'sessions' => $sessions
        ];
    } catch (Exception $e) {
        error_log("Erreur getDeviceDiagnostic: " . $e->getMessage());
        return [
            'device' => null,
            'crashes' => [],
            'sessions' => []
        ];
    }
}

/**
 * Fonctions utilitaires
 */
function getStatusBadge($secondsSinceLastSeen) {
    if ($secondsSinceLastSeen < 300) return '<span class="status online">🟢 En ligne</span>'; // 5 min
    if ($secondsSinceLastSeen < 3600) return '<span class="status recent">🟡 Récent</span>'; // 1h
    if ($secondsSinceLastSeen < 86400) return '<span class="status away">🟠 Absent</span>'; // 24h
    if ($secondsSinceLastSeen < 604800) return '<span class="status offline">🔴 Inactif</span>'; // 7j
    return '<span class="status dead">⚫ Dormant</span>';
}

function formatTimeAgo($seconds) {
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds/60) . 'min';
    if ($seconds < 86400) return floor($seconds/3600) . 'h';
    return floor($seconds/86400) . 'j';
}

function getInstallationSourceBadge($source) {
    switch ($source) {
        case 'play_store': return '<span class="install-source play-store">📱 Play Store</span>';
        case 'sideload': return '<span class="install-source sideload">🔧 Installation manuelle</span>';
        case 'shared': return '<span class="install-source shared">🤝 Partagé</span>';
        case 'unknown': default: return '<span class="install-source unknown">❓ Source inconnue</span>';
    }
}

// Initialisation des variables
$apps = [];
$devices = [];
$problematicDevices = [];
$diagnostic = null;
$selectedApp = 'coursier';
$selectedDevice = null;
$error = null;

// Récupération des données
try {
    $apps = getAvailableApps($pdo);
    
    // Si aucune app trouvée, créer un tableau par défaut
    if (empty($apps)) {
        $apps = [
            [
                'app_name' => 'coursier',
                'total_devices' => 0,
                'active_today' => 0,
                'latest_version' => 0,
                'devices_with_issues' => 0
            ]
        ];
    }
    
    $selectedApp = $_GET['app'] ?? ($apps[0]['app_name'] ?? 'coursier');
    $selectedDevice = $_GET['device'] ?? null;
    
    $devices = getDevicesForApp($pdo, $selectedApp) ?? [];
    $problematicDevices = getProblematicDevices($pdo, $selectedApp) ?? [];
    
    if ($selectedDevice) {
        $diagnostic = getDeviceDiagnostic($pdo, $selectedDevice, $selectedApp);
    }
    
} catch (PDOException $e) {
    $error = "Erreur base de données : " . $e->getMessage();
} catch (Exception $e) {
    $error = "Erreur système : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Applications - Onglets</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #FFD700;
            --danger: #FF4444;
            --warning: #FF8800;
            --success: #44AA44;
            --info: #4488FF;
            --bg-dark: #0f0f0f;
            --bg-card: #1a1a1a;
            --bg-section: #252525;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --border: rgba(255,255,255,0.1);
            --hover: rgba(255,215,0,0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-dark);
            color: var(--text-primary);
            line-height: 1.5;
            overflow-x: hidden;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 1rem;
        }

        .header {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-section));
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .header h1 {
            font-size: 2.2rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .header .subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Onglets Applications */
        .app-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding: 0.5rem 0;
        }

        .app-tab {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            min-width: 180px;
            text-align: center;
            position: relative;
            text-decoration: none;
            color: var(--text-primary);
        }

        .app-tab:hover {
            background: var(--hover);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .app-tab.active {
            background: linear-gradient(135deg, var(--primary), #FFA500);
            color: #000;
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(255,215,0,0.3);
        }

        .app-tab .app-name {
            font-weight: bold;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .app-tab .app-stats {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .app-tab.active .app-stats {
            color: #333;
        }

        /* Grille principale */
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 1200px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        .section {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .section-header {
            background: var(--bg-section);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .section-title {
            font-size: 1.3rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .section-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .section-content {
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
        }

        /* Tables */
        .device-table {
            width: 100%;
            border-collapse: collapse;
        }

        .device-table th {
            background: var(--bg-section);
            padding: 1rem;
            text-align: left;
            color: var(--primary);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .device-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            transition: background 0.2s ease;
        }

        .device-table tr {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .device-table tr:hover {
            background: var(--hover);
        }

        .device-table tr.selected {
            background: rgba(255,215,0,0.2);
            border-left: 4px solid var(--primary);
        }

        /* Status badges */
        .status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status.online { background: var(--success); color: white; }
        .status.recent { background: var(--info); color: white; }
        .status.away { background: var(--warning); color: white; }
        .status.offline { background: var(--danger); color: white; }
        .status.dead { background: #666; color: white; }

        .install-source {
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .install-source.play-store { background: #34A853; color: white; }
        .install-source.sideload { background: #FF6B35; color: white; }
        .install-source.shared { background: #4285F4; color: white; }
        .install-source.unknown { background: #666; color: white; }

        /* Diagnostic panel */
        .diagnostic-panel {
            background: var(--bg-card);
            border-radius: 12px;
            border: 2px solid var(--primary);
            margin-top: 2rem;
            overflow: hidden;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .diagnostic-header {
            background: linear-gradient(135deg, var(--primary), #FFA500);
            color: #000;
            padding: 1.5rem;
            font-weight: bold;
        }

        .diagnostic-content {
            padding: 2rem;
        }

        .diagnostic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .diagnostic-card {
            background: var(--bg-section);
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid var(--border);
        }

        .diagnostic-card h4 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        /* Scrollbar personnalisé */
        .section-content::-webkit-scrollbar { width: 6px; }
        .section-content::-webkit-scrollbar-track { background: var(--bg-dark); }
        .section-content::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }

        /* Refresh indicator */
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1000;
        }

        .refresh-indicator.show {
            opacity: 1;
        }

        /* Empty states */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .app-tabs {
                flex-direction: column;
            }
            
            .app-tab {
                min-width: auto;
            }
            
            .device-table {
                font-size: 0.9rem;
            }
            
            .device-table th,
            .device-table td {
                padding: 0.5rem;
            }
        }

        /* Animation des badges */
        .status, .install-source {
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1><i class="fas fa-mobile-alt"></i> Monitoring Applications</h1>
            <p class="subtitle">Interface de surveillance temps réel par application</p>
        </header>

        <!-- Indicateur de refresh -->
        <div id="refreshIndicator" class="refresh-indicator">
            <i class="fas fa-sync-alt fa-spin"></i> Synchronisation...
        </div>

        <?php if (isset($error)): ?>
            <div style="background: var(--danger); color: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Onglets des applications -->
        <div class="app-tabs">
            <?php if (!empty($apps)): ?>
                <?php foreach ($apps as $app): ?>
                    <a href="?app=<?= urlencode($app['app_name']) ?>" 
                       class="app-tab <?= $app['app_name'] === $selectedApp ? 'active' : '' ?>">
                        <div class="app-name"><?= htmlspecialchars($app['app_name']) ?></div>
                        <div class="app-stats">
                            <?= number_format($app['total_devices']) ?> appareils
                            • <?= number_format($app['active_today']) ?> actifs
                            <?php if ($app['devices_with_issues'] > 0): ?>
                                • <span style="color: var(--danger);"><?= $app['devices_with_issues'] ?> problèmes</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="margin: 2rem 0; text-align: center;">
                    <i class="fas fa-mobile-alt"></i>
                    <p>Aucune application trouvée</p>
                    <small>Vérifiez que des appareils sont connectés</small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contenu principal -->
        <?php if (!isset($error)): ?>
        <div class="main-grid">
            <!-- Liste de tous les appareils -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i> Tous les Appareils
                    </h2>
                    <p class="section-subtitle">
                        <?= count($devices ?? []) ?> appareils avec l'application "<?= htmlspecialchars($selectedApp) ?>"
                    </p>
                </div>
                <div class="section-content">
                    <?php if (empty($devices)): ?>
                        <div class="empty-state">
                            <i class="fas fa-mobile-alt"></i>
                            <p>Aucun appareil trouvé pour cette application</p>
                        </div>
                    <?php else: ?>
                        <table class="device-table">
                            <thead>
                                <tr>
                                    <th>Appareil</th>
                                    <th>Version</th>
                                    <th>Status</th>
                                    <th>Source</th>
                                    <th>Sessions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devices as $device): ?>
                                    <tr onclick="loadDiagnostic('<?= $device['device_id'] ?>')" 
                                        class="<?= $selectedDevice === $device['device_id'] ? 'selected' : '' ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($device['device_brand'] . ' ' . $device['device_model']) ?></strong>
                                            <br>
                                            <small style="color: var(--text-secondary);">
                                                Android <?= htmlspecialchars($device['android_version']) ?>
                                                • ID: <?= htmlspecialchars(substr($device['device_id'], 0, 8)) ?>...
                                            </small>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($device['app_version_name']) ?>
                                            <br>
                                            <small style="color: var(--text-secondary);">v<?= $device['app_version_code'] ?></small>
                                        </td>
                                        <td>
                                            <?= getStatusBadge($device['seconds_since_seen']) ?>
                                            <br>
                                            <small style="color: var(--text-secondary);">
                                                <?= formatTimeAgo($device['seconds_since_seen']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?= getInstallationSourceBadge($device['installation_source']) ?>
                                        </td>
                                        <td>
                                            <strong><?= $device['sessions_count'] ?></strong> sessions
                                            <br>
                                            <?php if ($device['total_crashes'] > 0): ?>
                                                <small style="color: var(--danger);">
                                                    <?= $device['total_crashes'] ?> crashes
                                                </small>
                                            <?php else: ?>
                                                <small style="color: var(--success);">Stable</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Appareils avec problèmes -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-exclamation-triangle"></i> Appareils Problématiques
                    </h2>
                    <p class="section-subtitle">
                        <?= count($problematicDevices ?? []) ?> appareils avec des crashes récents
                    </p>
                </div>
                <div class="section-content">
                    <?php if (empty($problematicDevices)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Aucun problème détecté !</p>
                            <small>Tous les appareils fonctionnent correctement</small>
                        </div>
                    <?php else: ?>
                        <table class="device-table">
                            <thead>
                                <tr>
                                    <th>Appareil</th>
                                    <th>Crashes</th>
                                    <th>Dernier</th>
                                    <th>Problèmes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($problematicDevices as $device): ?>
                                    <tr onclick="loadDiagnostic('<?= $device['device_id'] ?>')"
                                        class="<?= $selectedDevice === $device['device_id'] ? 'selected' : '' ?>">
                                        <td>
                                            <strong style="color: var(--danger);">
                                                <?= htmlspecialchars($device['device_brand'] . ' ' . $device['device_model']) ?>
                                            </strong>
                                            <br>
                                            <small style="color: var(--text-secondary);">
                                                Android <?= htmlspecialchars($device['android_version']) ?>
                                                • v<?= htmlspecialchars($device['app_version_name']) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span style="color: var(--danger); font-weight: bold;">
                                                <?= $device['crashes_24h'] ?>
                                            </span> / 24h
                                            <br>
                                            <small style="color: var(--text-secondary);">
                                                <?= $device['crashes_7d'] ?> / 7j
                                            </small>
                                        </td>
                                        <td>
                                            <?= formatTimeAgo(time() - strtotime($device['last_crash'])) ?>
                                        </td>
                                        <td>
                                            <small style="color: var(--text-secondary); line-height: 1.3;">
                                                <?= htmlspecialchars(substr($device['detailed_issues'], 0, 60)) ?>...
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Diagnostic détaillé -->
        <?php if ($diagnostic && $diagnostic['device']): ?>
            <div class="diagnostic-panel">
                <div class="diagnostic-header">
                    <h3><i class="fas fa-stethoscope"></i> Diagnostic Détaillé - <?= htmlspecialchars($diagnostic['device']['device_brand'] . ' ' . $diagnostic['device']['device_model']) ?></h3>
                    <p>ID: <?= htmlspecialchars($diagnostic['device']['device_id']) ?> • Android <?= htmlspecialchars($diagnostic['device']['android_version']) ?></p>
                </div>
                <div class="diagnostic-content">
                    <div class="diagnostic-grid">
                        <!-- Informations générales -->
                        <div class="diagnostic-card">
                            <h4><i class="fas fa-info-circle"></i> Informations Générales</h4>
                            <table style="width: 100%; font-size: 0.9rem;">
                                <tr>
                                    <td style="color: var(--text-secondary); padding: 0.3rem 0;">Status:</td>
                                    <td><?= getStatusBadge($diagnostic['device']['seconds_since_seen']) ?></td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-secondary); padding: 0.3rem 0;">Version App:</td>
                                    <td><?= htmlspecialchars($diagnostic['device']['app_version_name']) ?> (v<?= $diagnostic['device']['app_version_code'] ?>)</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-secondary); padding: 0.3rem 0;">Installation:</td>
                                    <td><?= getInstallationSourceBadge($diagnostic['device']['installation_source']) ?></td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-secondary); padding: 0.3rem 0;">Première connexion:</td>
                                    <td><?= date('d/m/Y H:i', strtotime($diagnostic['device']['first_seen'])) ?></td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-secondary); padding: 0.3rem 0;">Dernière activité:</td>
                                    <td><?= date('d/m/Y H:i', strtotime($diagnostic['device']['last_seen'])) ?></td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-secondary); padding: 0.3rem 0;">Sessions 7j:</td>
                                    <td><strong style="color: var(--info);"><?= $diagnostic['device']['sessions_7d'] ?></strong> sessions</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-secondary); padding: 0.3rem 0;">Durée moyenne:</td>
                                    <td><?= $diagnostic['device']['avg_session_duration'] ? round($diagnostic['device']['avg_session_duration']/60, 1) . ' min' : 'N/A' ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Crashes récents -->
                        <div class="diagnostic-card">
                            <h4><i class="fas fa-bug"></i> Crashes Récents (<?= count($diagnostic['crashes']) ?>)</h4>
                            <?php if (empty($diagnostic['crashes'])): ?>
                                <p style="color: var(--success); text-align: center; padding: 2rem;">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i><br>
                                    Aucun crash détecté !
                                </p>
                            <?php else: ?>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($diagnostic['crashes'] as $crash): ?>
                                        <div style="border-left: 4px solid var(--danger); padding: 1rem; margin-bottom: 1rem; background: rgba(255,68,68,0.1); border-radius: 4px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                                <strong style="color: var(--danger); flex: 1;">
                                                    <?= htmlspecialchars($crash['exception_class']) ?>
                                                </strong>
                                                <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                                    il y a <?= formatTimeAgo($crash['minutes_ago'] * 60) ?>
                                                </span>
                                            </div>
                                            <?php if ($crash['screen_name']): ?>
                                                <p style="color: var(--text-secondary); font-size: 0.8rem; margin-bottom: 0.5rem;">
                                                    <i class="fas fa-mobile-screen"></i> Écran: <?= htmlspecialchars($crash['screen_name']) ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($crash['error_message']): ?>
                                                <p style="color: var(--text-secondary); font-size: 0.8rem; line-height: 1.3;">
                                                    <?= htmlspecialchars(substr($crash['error_message'], 0, 150)) ?>...
                                                </p>
                                            <?php endif; ?>
                                            <div style="margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-secondary);">
                                                Occurrences: <strong style="color: var(--danger);"><?= $crash['occurrence_count'] ?>x</strong>
                                                • Dernière: <?= date('d/m H:i', strtotime($crash['last_occurred'])) ?>
                                                <?php if (!$crash['is_resolved']): ?>
                                                    • <span style="color: var(--warning);">Non résolu</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sessions récentes -->
                        <div class="diagnostic-card">
                            <h4><i class="fas fa-clock"></i> Sessions Récentes (<?= count($diagnostic['sessions']) ?>)</h4>
                            <?php if (empty($diagnostic['sessions'])): ?>
                                <p style="text-align: center; color: var(--text-secondary); padding: 1rem;">Aucune session enregistrée</p>
                            <?php else: ?>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <?php foreach ($diagnostic['sessions'] as $session): ?>
                                        <div style="border-left: 4px solid var(--success); padding: 1rem; margin-bottom: 1rem; background: rgba(68,170,68,0.1); border-radius: 4px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                                <strong style="color: var(--success);">
                                                    Session #<?= substr($session['session_id'], -8) ?>
                                                </strong>
                                                <span style="color: var(--text-secondary); font-size: 0.8rem;">
                                                    il y a <?= formatTimeAgo($session['minutes_ago'] * 60) ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                                <p>Début: <?= date('d/m H:i:s', strtotime($session['started_at'])) ?></p>
                                                <?php if ($session['ended_at']): ?>
                                                    <p>Fin: <?= date('d/m H:i:s', strtotime($session['ended_at'])) ?></p>
                                                    <p>Durée: <strong style="color: var(--info);"><?= round($session['duration_seconds']/60, 1) ?> min</strong></p>
                                                <?php else: ?>
                                                    <p style="color: var(--warning);">Session en cours...</p>
                                                <?php endif; ?>
                                                <?php if ($session['screen_name']): ?>
                                                    <p><i class="fas fa-mobile-screen"></i> Écran: <?= htmlspecialchars($session['screen_name']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                        <h4 style="color: var(--primary); margin-bottom: 1rem;">
                            <i class="fas fa-tools"></i> Actions Rapides
                        </h4>
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button onclick="refreshDiagnostic()" style="background: var(--info); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
                                <i class="fas fa-sync"></i> Actualiser
                            </button>
                            <button onclick="closeDiagnostic()" style="background: var(--danger); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
                                <i class="fas fa-times"></i> Fermer
                            </button>
                            <?php if (count($diagnostic['crashes']) > 0): ?>
                                <button onclick="markCrashesResolved('<?= $diagnostic['device']['device_id'] ?>')" style="background: var(--warning); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">
                                    <i class="fas fa-check"></i> Marquer résolu
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Variables globales
        let refreshInterval;
        let isRefreshing = false;

        // Fonction pour charger le diagnostic d'un appareil
        function loadDiagnostic(deviceId) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('device', deviceId);
            window.location.href = currentUrl.toString();
        }

        // Fonction pour fermer le diagnostic
        function closeDiagnostic() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.delete('device');
            window.location.href = currentUrl.toString();
        }

        // Fonction pour actualiser le diagnostic
        function refreshDiagnostic() {
            window.location.reload();
        }

        // Fonction pour marquer les crashes comme résolus
        function markCrashesResolved(deviceId) {
            if (!confirm('Marquer tous les crashes de cet appareil comme résolus ?')) return;
            
            fetch('api/mark_crashes_resolved.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ device_id: deviceId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showRefreshIndicator();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour');
            });
        }

        // Fonction pour afficher l'indicateur de refresh
        function showRefreshIndicator() {
            const indicator = document.getElementById('refreshIndicator');
            indicator.classList.add('show');
            setTimeout(() => {
                indicator.classList.remove('show');
            }, 2000);
        }

        // Auto-refresh en temps réel
        function startAutoRefresh() {
            if (refreshInterval) return;
            
            refreshInterval = setInterval(() => {
                if (isRefreshing || document.hidden) return;
                
                isRefreshing = true;
                showRefreshIndicator();
                
                // Refresh uniquement les données, pas toute la page
                fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    // Parse le nouveau HTML pour récupérer les sections mises à jour
                    const parser = new DOMParser();
                    const newDoc = parser.parseFromString(html, 'text/html');
                    
                    // Mise à jour des onglets (statistiques)
                    const newTabs = newDoc.querySelector('.app-tabs');
                    if (newTabs) {
                        document.querySelector('.app-tabs').innerHTML = newTabs.innerHTML;
                    }
                    
                    // Mise à jour des tableaux
                    const newTables = newDoc.querySelectorAll('.device-table');
                    const currentTables = document.querySelectorAll('.device-table');
                    
                    newTables.forEach((newTable, index) => {
                        if (currentTables[index]) {
                            currentTables[index].innerHTML = newTable.innerHTML;
                        }
                    });
                    
                    // Mise à jour des subtitles
                    const newSubtitles = newDoc.querySelectorAll('.section-subtitle');
                    const currentSubtitles = document.querySelectorAll('.section-subtitle');
                    
                    newSubtitles.forEach((newSubtitle, index) => {
                        if (currentSubtitles[index]) {
                            currentSubtitles[index].textContent = newSubtitle.textContent;
                        }
                    });
                    
                    isRefreshing = false;
                })
                .catch(error => {
                    console.error('Erreur refresh:', error);
                    isRefreshing = false;
                });
            }, 15000); // Refresh toutes les 15 secondes
        }

        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }

        // Gérer la visibilité de la page pour optimiser les performances
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });

        // Démarrer le refresh automatique au chargement
        document.addEventListener('DOMContentLoaded', () => {
            startAutoRefresh();
            
            // Ajouter des événements de clic pour maintenir la sélection
            document.querySelectorAll('.device-table tr').forEach(row => {
                row.addEventListener('click', function() {
                    // Retirer la classe selected de tous les autres éléments
                    document.querySelectorAll('.device-table tr.selected').forEach(r => r.classList.remove('selected'));
                    // Ajouter à l'élément cliqué
                    this.classList.add('selected');
                });
            });
        });

        // Nettoyage avant le déchargement de la page
        window.addEventListener('beforeunload', stopAutoRefresh);
    </script>
</body>
</html>