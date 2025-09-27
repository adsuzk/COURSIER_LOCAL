<?php
// Page de gestion des mises à jour de l'app Android
// Note: on n'inclut PAS app_monitoring.php ici (c'est une page complète indépendante)
include __DIR__ . '/auto_detect_apk.php';
require_once __DIR__ . '/../lib/version_helpers.php';
require_once __DIR__ . '/../config.php';

// Charger la configuration et appliquer la dernière APK uploadée
$config = vu_load_versions_config();

$uploadMetaFile = __DIR__ . '/uploads/latest_apk.json';
$uploadedApks = ['current' => null, 'previous' => null];
if (file_exists($uploadMetaFile)) {
    $uploadData = json_decode(file_get_contents($uploadMetaFile), true);
    if (is_array($uploadData)) {
        $uploadedApks['current'] = $uploadData;
        if (isset($uploadData['previous']) && is_array($uploadData['previous'])) {
            $uploadedApks['previous'] = $uploadData['previous'];
        }
    }
}

// Traiter les formulaires (publication version / paramètres)
$success_message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_version') {
        $vc = max(1, (int)($_POST['version_code'] ?? 1));
        $vn = trim($_POST['version_name'] ?? '1.0.0');
        $apk_url = trim($_POST['apk_url'] ?? '');
        $apk_size = max(0, (int)($_POST['apk_size'] ?? 0));
        $min_supported = max(1, (int)($_POST['min_supported_version'] ?? 1));
        $force = !empty($_POST['force_update']);
        $changelog_raw = trim($_POST['changelog'] ?? '');
        $changelog = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $changelog_raw)), fn($l) => $l !== ''));

        $config['current_version'] = [
            'version_code' => $vc,
            'version_name' => $vn,
            'apk_url' => $apk_url,
            'apk_size' => $apk_size,
            'release_date' => date('Y-m-d H:i:s'),
            'force_update' => $force,
            'min_supported_version' => $min_supported,
            'changelog' => $changelog
        ];

        vu_persist_versions_config($config);
        $success_message = 'Version publiée avec succès.';
    } elseif ($action === 'update_settings') {
        $config['update_check_interval'] = (int)($_POST['check_interval'] ?? 3600) ?: 3600;
        $config['auto_install'] = !empty($_POST['auto_install']);
        vu_persist_versions_config($config);
        $success_message = 'Paramètres enregistrés.';
    }
}

// Valeurs par défaut si non présentes - utiliser l'APK détectée si dispo
if (empty($config['current_version'])) {
    $config['current_version'] = [
        'version_code' => !empty($uploadedApks['current']['version_code']) ? (int)$uploadedApks['current']['version_code'] : 1,
        'version_name' => !empty($uploadedApks['current']['version_name']) ? $uploadedApks['current']['version_name'] : '1.0.0',
        'apk_url' => !empty($uploadedApks['current']['file']) ? '/admin/download_apk.php?file=' . urlencode($uploadedApks['current']['file']) : '',
        'apk_size' => !empty($uploadedApks['current']['apk_size']) ? (int)$uploadedApks['current']['apk_size'] : 0,
        'force_update' => false,
        'min_supported_version' => 1,
        'release_date' => !empty($uploadedApks['current']['uploaded_at']) ? $uploadedApks['current']['uploaded_at'] : date('Y-m-d H:i:s'),
        'changelog' => []
    ];
}

// Toujours synchroniser current_version avec la dernière APK uploadée si présente
vu_overlay_with_latest_upload($config, true);

$devices = $config['devices'] ?? [];
$current_version = $config['current_version'];
// Vue interne: mises à jour (par défaut) ou télémétrie
$view = $_GET['view'] ?? 'updates';
?>

<div class="fade-in">
    <h2 style="color:var(--primary-gold);font-size:2rem;font-weight:800;margin-bottom:2rem;display:flex;align-items:center;">
        <i class="fas fa-mobile-alt" style="margin-right:1rem;"></i>Gestion des Mises à Jour
    </h2>

    <!-- Navigation par type d'application et vue -->
    <div style="margin-bottom:2rem;">
        <div style="display:flex;gap:0.5rem;margin-bottom:1rem;">
            <button onclick="showAppType('coursiers')" id="btn-app-coursiers" class="btn btn-primary">
                <i class="fas fa-motorcycle"></i> Mises à jour Coursiers
            </button>
            <button onclick="showAppType('clients')" id="btn-app-clients" class="btn btn-secondary">
                <i class="fas fa-users"></i> Mises à jour Clients
            </button>
        </div>
        
        <div style="display:flex;gap:0.5rem;">
            <a href="admin.php?section=app_updates&view=updates" class="btn <?php echo $view==='updates'?'btn-primary':'btn-secondary'; ?>">
                <i class="fas fa-cloud-download-alt"></i> Configuration
            </a>
            <a href="admin.php?section=app_updates&view=telemetry" class="btn <?php echo $view==='telemetry'?'btn-primary':'btn-secondary'; ?>">
                <i class="fas fa-wave-square"></i> Télémétrie
            </a>
        </div>
    </div>

<?php if ($view === 'updates'): ?>
    
    <!-- Section Mises à jour Coursiers -->
    <div id="updates-section-coursiers" class="updates-section">
        <h3 style="color:var(--primary-gold);font-size:1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;">
            <i class="fas fa-motorcycle" style="margin-right:0.5rem;"></i>Gestion des Mises à Jour - Applications Coursiers
        </h3>

        <?php if (isset($success_message)): ?>
        <div class="alert alert-success" style="background:#27ae60;color:#fff;padding:1rem;border-radius:8px;margin-bottom:2rem;">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($uploadedApks['current']) || !empty($uploadedApks['previous'])): ?>
        <div class="card" style="margin-bottom:2rem;">
            <div class="card-header">
                <h4><i class="fas fa-upload"></i> APKs Coursiers Détectées</h4>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
                    <?php if (!empty($uploadedApks['current']) && is_array($uploadedApks['current'])): ?>
                    <div style="background:rgba(39,174,96,0.1);padding:1rem;border-radius:8px;border:1px solid rgba(39,174,96,0.3);">
                        <h5 style="color:#27ae60;margin:0 0 0.5rem 0;"><i class="fas fa-star"></i> Version Actuelle Coursier</h5>
                        <div><strong>Fichier :</strong> <?= htmlspecialchars($uploadedApks['current']['file'] ?? 'N/A') ?></div>
                        <div><strong>Version :</strong> v<?= htmlspecialchars($uploadedApks['current']['version_name'] ?? 'N/A') ?> (code <?= $uploadedApks['current']['version_code'] ?? 'N/A' ?>)</div>
                        <div><strong>Taille :</strong> <?= isset($uploadedApks['current']['apk_size']) ? number_format($uploadedApks['current']['apk_size']/1024/1024, 2) : '0.00' ?> MB</div>
                        <div><strong>Uploadée :</strong> <?= isset($uploadedApks['current']['uploaded_at']) ? date('d/m/Y H:i', strtotime($uploadedApks['current']['uploaded_at'])) : 'N/A' ?></div>
                        <?php if (!empty($uploadedApks['current']['file'])):
                            $currentFile = $uploadedApks['current']['file'];
                            $currentDownload = function_exists('routePath')
                                ? routePath('admin/download_apk.php?file=' . rawurlencode($currentFile))
                                : '/admin/download_apk.php?file=' . rawurlencode($currentFile);
                        ?>
                        <a href="<?= htmlspecialchars($currentDownload, ENT_QUOTES) ?>" class="btn btn-primary" style="margin-top:0.5rem;" download>
                            <i class="fas fa-download"></i> Télécharger
                        </a>
                        <?php endif; ?>
                    <button type="button" class="btn btn-secondary" style="margin-top:0.5rem;" onclick="fillFormWithVersion('current')">
                        <i class="fas fa-edit"></i> Utiliser cette version
                    </button>
                </div>
                <?php else: ?>
                <div style="background:rgba(231,76,60,0.1);padding:1rem;border-radius:8px;border:1px solid rgba(231,76,60,0.3);">
                    <h4 style="color:#e74c3c;margin:0 0 0.5rem 0;"><i class="fas fa-exclamation-triangle"></i> Aucune APK Détectée</h4>
                    <p>Aucun fichier APK n'a été détecté dans les répertoires surveillés.</p>
                    <p><small>Répertoires scannés: <code>/admin/uploads/</code> et <code>/Applications APK/Coursiers APK/release/</code></small></p>
                </div>
                <?php endif; ?>
                    </div>
                    <?php if (!empty($uploadedApks['previous']) && is_array($uploadedApks['previous'])): ?>
                    <div style="background:rgba(52,152,219,0.1);padding:1rem;border-radius:8px;border:1px solid rgba(52,152,219,0.3);">
                        <h5 style="color:#3498db;margin:0 0 0.5rem 0;"><i class="fas fa-history"></i> Version Précédente Coursier</h5>
                        <div><strong>Fichier :</strong> <?= htmlspecialchars($uploadedApks['previous']['file'] ?? 'N/A') ?></div>
                        <div><strong>Version :</strong> v<?= htmlspecialchars($uploadedApks['previous']['version_name'] ?? 'N/A') ?> (code <?= $uploadedApks['previous']['version_code'] ?? 'N/A' ?>)</div>
                        <div><strong>Taille :</strong> <?= isset($uploadedApks['previous']['apk_size']) ? number_format($uploadedApks['previous']['apk_size']/1024/1024, 2) : '0.00' ?> MB</div>
                        <div><strong>Uploadée :</strong> <?= isset($uploadedApks['previous']['uploaded_at']) ? date('d/m/Y H:i', strtotime($uploadedApks['previous']['uploaded_at'])) : 'N/A' ?></div>
                        <?php if (!empty($uploadedApks['previous']['file'])):
                            $previousFile = $uploadedApks['previous']['file'];
                            $previousDownload = function_exists('routePath')
                                ? routePath('admin/download_apk.php?file=' . rawurlencode($previousFile))
                                : '/admin/download_apk.php?file=' . rawurlencode($previousFile);
                        ?>
                        <a href="<?= htmlspecialchars($previousDownload, ENT_QUOTES) ?>" class="btn btn-primary" style="margin-top:0.5rem;" download>
                            <i class="fas fa-download"></i> Télécharger
                        </a>
                        <?php endif; ?>
                        <button type="button" class="btn btn-secondary" style="margin-top:0.5rem;" onclick="fillFormWithVersion('previous')">
                            <i class="fas fa-undo"></i> Restaurer cette version
                        </button>
                    </div>
                    <?php elseif (!empty($uploadedApks['current'])): ?>
                    <div style="background:rgba(149,165,166,0.1);padding:1rem;border-radius:8px;border:1px solid rgba(149,165,166,0.3);">
                        <h5 style="color:#95a5a6;margin:0 0 0.5rem 0;"><i class="fas fa-info-circle"></i> Pas de Version Précédente</h5>
                        <p>Seule la version coursier actuelle est disponible.</p>
                        <p><small>La version précédente apparaîtra lors du prochain upload d'APK coursier.</small></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($current_version['apk_url'])): ?>
        <div class="alert alert-info" style="background:#3498db;color:#fff;padding:1rem;border-radius:8px;margin-bottom:2rem;">
            <i class="fas fa-info-circle"></i> Aucune version d'application coursier n'est encore configurée.<br>
            Veuillez renseigner les informations ci-dessous pour publier la première version coursier.
        </div>
        <?php endif; ?>

        <!-- Section Version actuelle Coursier -->
        <div class="card" style="margin-bottom:2rem;">
            <div class="card-header">
                <h4><i class="fas fa-tag"></i> Configuration Version Coursier Actuelle</h4>
            </div>
            <div class="card-body">
                <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
                    <input type="hidden" name="action" value="update_version">
                    <input type="hidden" name="app_type" value="coursier">

                    <div>
                        <label>Code de version coursier (numérique) *</label>
                        <input type="number" name="version_code" value="<?php echo isset($current_version['version_code']) ? $current_version['version_code'] : 1; ?>" required class="form-input" placeholder="ex: 1">
                    </div>

                <div>
                    <label>Nom de version coursier *</label>
                    <input type="text" name="version_name" value="<?php echo htmlspecialchars($current_version['version_name'] ?? ''); ?>" required class="form-input" placeholder="ex: 1.0">
                </div>

                <div>
                    <label>URL de l'APK coursier *</label>
                    <input type="text" name="apk_url" value="<?php echo htmlspecialchars($current_version['apk_url'] ?? ''); ?>" required class="form-input" placeholder="ex: assets/apk/app-release.apk">
                </div>

                <div>
                    <label>Taille de l'APK coursier (bytes)</label>
                    <input type="number" name="apk_size" value="<?php echo isset($current_version['apk_size']) ? $current_version['apk_size'] : 0; ?>" class="form-input" placeholder="ex: 12345678">
                </div>

                <div>
                    <label>Version minimale supportée (coursier)</label>
                    <input type="number" name="min_supported_version" value="<?php echo isset($current_version['min_supported_version']) ? $current_version['min_supported_version'] : 1; ?>" class="form-input" placeholder="ex: 1">
                </div>

                <div>
                    <label>
                        <input type="checkbox" name="force_update" <?php echo !empty($current_version['force_update']) ? 'checked' : ''; ?>>
                        Forcer la mise à jour coursier (obligatoire)
                    </label>
                </div>

                <div style="grid-column:span 2;">
                    <label>Notes de version coursier (une par ligne)</label>
                    <textarea name="changelog" rows="4" class="form-input" placeholder="- Nouvelle fonctionnalité&#10;- Correction de bugs&#10;- Améliorations"><?php echo !empty($current_version['changelog']) ? implode("\n", $current_version['changelog']) : ''; ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="grid-column:span 2;">
                    <i class="fas fa-save"></i> Publier cette version coursier
                </button>
            </form>
        </div>
    </div>
    </div>

    <!-- Section Mises à jour Clients -->
    <div id="updates-section-clients" class="updates-section" style="display:none;">
        <h3 style="color:var(--primary-gold);font-size:1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;">
            <i class="fas fa-users" style="margin-right:0.5rem;"></i>Gestion des Mises à Jour - Applications Clients
        </h3>

        <div class="alert alert-info" style="background:#3498db;color:#fff;padding:1rem;border-radius:8px;margin-bottom:2rem;">
            <i class="fas fa-info-circle"></i> Configuration des mises à jour pour les applications clients.<br>
            <small>Les applications clients auront leur propre système de versioning et de déploiement.</small>
        </div>

        <!-- Placeholder pour futures fonctionnalités clients -->
        <div class="card" style="margin-bottom:2rem;">
            <div class="card-header">
                <h4><i class="fas fa-cog"></i> Configuration Future - Clients</h4>
            </div>
            <div class="card-body">
                <p style="text-align:center;padding:2rem;color:rgba(255,255,255,0.7);">
                    <i class="fas fa-tools" style="font-size:3rem;margin-bottom:1rem;display:block;"></i>
                    Section en cours de développement pour la gestion des applications clients.
                </p>
            </div>
        </div>
    </div>
    
    <!-- Section Paramètres partagés -->
    <div class="card" style="margin-bottom:2rem;">
        <div class="card-header">
            <h4><i class="fas fa-cog"></i> Paramètres Généraux</h4>
        </div>
        <div class="card-body">
            <form method="post" style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
                <input type="hidden" name="action" value="update_settings">
                
                <div>
                    <label>Intervalle de vérification (secondes)</label>
                    <select name="check_interval" class="form-input">
                        <option value="1800" <?php echo $config['update_check_interval'] == 1800 ? 'selected' : ''; ?>>30 minutes</option>
                        <option value="3600" <?php echo $config['update_check_interval'] == 3600 ? 'selected' : ''; ?>>1 heure</option>
                        <option value="7200" <?php echo $config['update_check_interval'] == 7200 ? 'selected' : ''; ?>>2 heures</option>
                        <option value="21600" <?php echo $config['update_check_interval'] == 21600 ? 'selected' : ''; ?>>6 heures</option>
                        <option value="43200" <?php echo $config['update_check_interval'] == 43200 ? 'selected' : ''; ?>>12 heures</option>
                        <option value="86400" <?php echo $config['update_check_interval'] == 86400 ? 'selected' : ''; ?>>24 heures</option>
                    </select>
                </div>
                
                <div>
                    <label>
                        <input type="checkbox" name="auto_install" <?php echo $config['auto_install'] ? 'checked' : ''; ?>>
                        Installation automatique (sans intervention)
                    </label>
                </div>
                
                <button type="submit" class="btn btn-secondary" style="grid-column:span 2;">
                    <i class="fas fa-save"></i> Sauvegarder les paramètres
                </button>
            </form>
        </div>
    </div>
    
    <!-- Section Appareils connectés -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-devices"></i> Appareils connectés (<?php echo is_array($devices) ? count($devices) : 0; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($devices) || !is_array($devices)): ?>
            <p style="color:#ccc;text-align:center;padding:2rem;">Aucun appareil connecté pour le moment.<br>Les appareils apparaîtront ici dès qu'ils utiliseront l'application.</p>
            <?php else: ?>
            <div class="data-table">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>ID Appareil</th>
                            <th>Modèle</th>
                            <th>Android</th>
                            <th>Version App</th>
                            <th>Dernière vérification</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $device): ?>
                        <tr>
                            <td style="font-family:monospace;font-size:0.9rem;">
                                <?php echo substr($device['device_id'], 0, 8) . '...'; ?>
                            </td>
                            <td><?php echo htmlspecialchars($device['device_model'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($device['android_version'] ?? 'N/A'); ?></td>
                            <td>
                                <span style="font-weight:bold;color:<?php echo $device['current_version_code'] < $current_version['version_code'] ? '#e74c3c' : '#27ae60'; ?>">
                                    v<?php echo $device['app_version'] ?? 'N/A'; ?>
                                    (<?php echo $device['current_version_code'] ?? 0; ?>)
                                </span>
                                <?php if ($device['current_version_code'] < $current_version['version_code']): ?>
                                <span style="color:#e74c3c;font-size:0.8rem;">OBSOLÈTE</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.9rem;">
                                <?php 
                                $last_check = strtotime($device['last_check']);
                                $diff = time() - $last_check;
                                if ($diff < 3600) echo floor($diff/60) . 'min';
                                elseif ($diff < 86400) echo floor($diff/3600) . 'h';
                                else echo floor($diff/86400) . 'j';
                                ?>
                            </td>
                            <td>
                                <?php
                                $status = $device['update_status'] ?? 'active';
                                $status_colors = [
                                    'active' => '#27ae60',
                                    'downloading' => '#f39c12', 
                                    'installing' => '#3498db',
                                    'installed' => '#27ae60',
                                    'failed' => '#e74c3c'
                                ];
                                $status_labels = [
                                    'active' => 'Actif',
                                    'downloading' => 'Téléchargement',
                                    'installing' => 'Installation', 
                                    'installed' => 'Installé',
                                    'failed' => 'Échec'
                                ];
                                ?>
                                <span style="color:<?php echo $status_colors[$status] ?? '#ccc'; ?>">
                                    <i class="fas fa-circle" style="font-size:0.6rem;margin-right:0.5rem;"></i>
                                    <?php echo $status_labels[$status] ?? $status; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistiques -->
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem;margin-top:2rem;">
        <div class="stat-card" style="background:var(--glass-bg);padding:1.5rem;border-radius:12px;text-align:center;">
            <div style="color:var(--primary-gold);font-size:2.5rem;font-weight:800;">
                <?php echo count(array_filter($devices, fn($d) => $d['current_version_code'] >= $current_version['version_code'])); ?>
            </div>
            <div style="color:#ccc;">À jour</div>
        </div>
        
        <div class="stat-card" style="background:var(--glass-bg);padding:1.5rem;border-radius:12px;text-align:center;">
            <div style="color:#e74c3c;font-size:2.5rem;font-weight:800;">
                <?php echo count(array_filter($devices, fn($d) => $d['current_version_code'] < $current_version['version_code'])); ?>
            </div>
            <div style="color:#ccc;">Obsolètes</div>
        </div>
        
        <div class="stat-card" style="background:var(--glass-bg);padding:1.5rem;border-radius:12px;text-align:center;">
            <div style="color:#f39c12;font-size:2.5rem;font-weight:800;">
                <?php echo count(array_filter($devices, fn($d) => ($d['update_status'] ?? '') === 'downloading')); ?>
            </div>
            <div style="color:#ccc;">Téléchargement</div>
        </div>
        
        <div class="stat-card" style="background:var(--glass-bg);padding:1.5rem;border-radius:12px;text-align:center;">
            <div style="color:#3498db;font-size:2.5rem;font-weight:800;">
                    <?php 
                    // Utiliser la taille depuis les métadonnées détectées automatiquement
                    if (!empty($uploadedApks['current']['apk_size'])) {
                        echo round($uploadedApks['current']['apk_size'] / 1024 / 1024, 1);
                    } else {
                        echo "0";
                    }
                    ?>MB
            </div>
            <div style="color:#ccc;">Taille APK</div>
        </div>
    </div>

<?php else: /* === TELEMETRY VIEW === */ ?>
    <?php
    // Connexion DB pour la télémétrie
    try { $pdo = getPDO(); } catch (Throwable $e) { $pdo = null; }
    $telemetryError = '';
    if (!$pdo) { $telemetryError = "Impossible de se connecter à la base de données."; }

    $metrics = [
        'total' => 0,
        'active_today' => 0,
        'inactive_week' => 0,
        'dormant' => 0,
        'crashes_24h' => 0,
        'devices_with_issues' => 0,
        'up_to_date' => 0,
        'update_needed' => 0,
    ];
    $deviceRows = [];
    $latestCode = (int)($current_version['version_code'] ?? 1);
    if ($pdo) {
        try {
            $metrics['total'] = (int)$pdo->query("SELECT COUNT(*) FROM app_devices WHERE is_active=1")->fetchColumn();
            $metrics['active_today'] = (int)$pdo->query("SELECT COUNT(*) FROM app_devices WHERE is_active=1 AND last_seen >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
            $metrics['inactive_week'] = (int)$pdo->query("SELECT COUNT(*) FROM app_devices WHERE is_active=1 AND last_seen < DATE_SUB(NOW(), INTERVAL 1 DAY) AND last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $metrics['dormant'] = (int)$pdo->query("SELECT COUNT(*) FROM app_devices WHERE is_active=1 AND last_seen < DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $metrics['crashes_24h'] = (int)$pdo->query("SELECT COUNT(*) FROM app_crashes WHERE last_occurred >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
            $metrics['devices_with_issues'] = (int)$pdo->query("SELECT COUNT(DISTINCT device_id) FROM app_crashes WHERE is_resolved=0 AND last_occurred >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $metrics['up_to_date'] = (int)$pdo->query("SELECT COUNT(*) FROM app_devices WHERE is_active=1 AND app_version_code >= " . $latestCode)->fetchColumn();
            $metrics['update_needed'] = max(0, $metrics['total'] - $metrics['up_to_date']);

            // Liste appareils structurée
            $sql = "
                SELECT 
                    d.device_id,
                    d.device_brand,
                    d.device_model,
                    d.android_version,
                    d.app_version_name,
                    d.app_version_code,
                    d.last_seen,
                    d.total_sessions,
                    -- Dernier crash et nb de crashs récents
                    (SELECT MAX(c2.last_occurred) FROM app_crashes c2 WHERE c2.device_id = d.device_id) AS last_crash,
                    (SELECT COUNT(*) FROM app_crashes c3 WHERE c3.device_id = d.device_id AND c3.last_occurred >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS recent_crashes,
                    -- Localisation si dispo dans events JSON
                    (
                        SELECT JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.lat')) 
                        FROM app_events e 
                        WHERE e.device_id = d.device_id AND JSON_EXTRACT(e.event_data, '$.lat') IS NOT NULL 
                        ORDER BY e.occurred_at DESC LIMIT 1
                    ) AS lat,
                    (
                        SELECT JSON_UNQUOTE(JSON_EXTRACT(e.event_data, '$.lng')) 
                        FROM app_events e 
                        WHERE e.device_id = d.device_id AND JSON_EXTRACT(e.event_data, '$.lng') IS NOT NULL 
                        ORDER BY e.occurred_at DESC LIMIT 1
                    ) AS lng
                FROM app_devices d
                WHERE d.is_active = 1
                ORDER BY d.last_seen DESC
            ";
            $stmt = $pdo->query($sql);
            $deviceRows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $telemetryError = 'Erreur requêtes télémétrie: ' . $e->getMessage();
        }
    }
    ?>

    <?php if ($telemetryError): ?>
        <div class="alert alert-info" style="background:#e74c3c;color:#fff;padding:1rem;border-radius:8px;margin-bottom:2rem;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($telemetryError); ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:var(--primary-gold);font-size:2rem;font-weight:800;"><?php echo (int)$metrics['total']; ?></div>
            <div style="color:#ccc;">Appareils actifs</div>
        </div>
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:#27ae60;font-size:2rem;font-weight:800;"><?php echo (int)$metrics['active_today']; ?></div>
            <div style="color:#ccc;">Actifs aujourd'hui</div>
        </div>
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:#f39c12;font-size:2rem;font-weight:800;"><?php echo (int)$metrics['inactive_week']; ?></div>
            <div style="color:#ccc;">Inactifs (7j)</div>
        </div>
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:#95a5a6;font-size:2rem;font-weight:800;"><?php echo (int)$metrics['dormant']; ?></div>
            <div style="color:#ccc;">Dormants</div>
        </div>
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:#e74c3c;font-size:2rem;font-weight:800;"><?php echo (int)$metrics['crashes_24h']; ?></div>
            <div style="color:#ccc;">Crashes (24h)</div>
        </div>
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:#e67e22;font-size:2rem;font-weight:800;"><?php echo (int)$metrics['devices_with_issues']; ?></div>
            <div style="color:#ccc;">Appareils à problème</div>
        </div>
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:#27ae60;font-size:2rem;font-weight:800;"><?php echo (int)$metrics['up_to_date']; ?></div>
            <div style="color:#ccc;">À jour</div>
        </div>
        <div class="stat-card" style="background:var(--glass-bg);padding:1rem;border-radius:12px;text-align:center;">
            <div style="color:#e74c3c;font-size:2rem;font-weight:800;"><?php echo (int)$metrics['update_needed']; ?></div>
            <div style="color:#ccc;">Maj requise</div>
        </div>
    </div>

    <!-- Carte globale (Leaflet) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <div id="telemetry-map" style="height:360px;border-radius:12px;overflow:hidden;margin:1rem 0;background:var(--glass-bg);"></div>

    <!-- Outils de tableau: recherche / colonnes / export -->
    <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin:1rem 0;">
        <input id="telemetry-search" class="form-input" type="text" placeholder="Rechercher (appareil, modèle, Android, statut)…" style="max-width:380px;">
        <div style="display:flex;gap:.5rem;align-items:center;color:#ccc;font-size:.9rem;">
            <span>Colonnes:</span>
            <label><input type="checkbox" class="col-toggle" data-col="1" checked> Appareil</label>
            <label><input type="checkbox" class="col-toggle" data-col="2" checked> Modèle</label>
            <label><input type="checkbox" class="col-toggle" data-col="3" checked> Android</label>
            <label><input type="checkbox" class="col-toggle" data-col="4" checked> Version</label>
            <label><input type="checkbox" class="col-toggle" data-col="5" checked> Activité</label>
            <label><input type="checkbox" class="col-toggle" data-col="6" checked> Crashes</label>
            <label><input type="checkbox" class="col-toggle" data-col="7" checked> Localisation</label>
            <label><input type="checkbox" class="col-toggle" data-col="8" checked> Statut</label>
        </div>
        <button id="export-csv" class="btn btn-secondary"><i class="fas fa-file-csv"></i> Export CSV (visible)</button>
    </div>

    <!-- Tableur appareils (télémétrie) -->
    <div class="data-table">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Appareil</th>
                    <th>Modèle</th>
                    <th>Android</th>
                    <th>Version App</th>
                    <th>Dernière activité</th>
                    <th>Crashes (7j)</th>
                    <th>Localisation</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deviceRows as $row): 
                    $lastSeen = strtotime($row['last_seen'] ?? '');
                    $diff = time() - ($lastSeen ?: time());
                    $status = 'OK'; $color = '#27ae60';
                    if ($diff > 7*86400) { $status='Hors ligne'; $color='#95a5a6'; }
                    elseif ((int)$row['recent_crashes'] > 0) { $status='Instable'; $color='#e67e22'; }
                    elseif ((int)$row['app_version_code'] < $latestCode) { $status='Obsolète'; $color='#e74c3c'; }
                    $loc = (!empty($row['lat']) && !empty($row['lng'])) ? (round((float)$row['lat'],5).', '.round((float)$row['lng'],5)) : '—';
                ?>
                <tr class="telemetry-row" data-device-id="<?php echo htmlspecialchars($row['device_id']); ?>" style="cursor:pointer;">
                    <td style="font-family:monospace;font-size:0.9rem;"><?php echo htmlspecialchars(substr($row['device_id'],0,10)).'…'; ?></td>
                    <td><?php echo htmlspecialchars(trim(($row['device_brand']??'').' '.($row['device_model']??''))); ?></td>
                    <td><?php echo htmlspecialchars($row['android_version'] ?? 'N/A'); ?></td>
                    <td>v<?php echo htmlspecialchars(($row['app_version_name']??'N/A')).' ('.(int)$row['app_version_code'].')'; ?></td>
                    <td><?php 
                        if ($diff < 3600) echo floor($diff/60).'min';
                        elseif ($diff < 86400) echo floor($diff/3600).'h';
                        else echo floor($diff/86400).'j';
                    ?></td>
                    <td><?php echo (int)$row['recent_crashes']; ?></td>
                    <td><?php echo htmlspecialchars($loc); ?></td>
                    <td><span style="color:<?php echo $color; ?>;font-weight:600;"><?php echo $status; ?></span></td>
                    <td>
                        <button class="btn btn-secondary btn-issue" data-device-id="<?php echo htmlspecialchars($row['device_id']); ?>" title="Signaler une panne">
                            <i class="fas fa-exclamation-circle"></i>
                        </button>
                        <button class="btn btn-secondary btn-resolve" data-device-id="<?php echo htmlspecialchars($row['device_id']); ?>" title="Marquer résolu" style="margin-left:.25rem;">
                            <i class="fas fa-check-circle"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Panneau de détails -->
    <div id="telemetry-details" style="margin-top:1.5rem;display:none;background:var(--glass-bg);border-radius:12px;">
        <div style="padding:1rem;border-bottom:1px solid rgba(255,255,255,0.1);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;color:var(--primary-gold);"><i class="fas fa-info-circle"></i> Détails appareil</h3>
            <button id="close-details" class="btn btn-secondary"><i class="fas fa-times"></i></button>
        </div>
        <div id="details-content" style="padding:1rem;"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    // Données pour la carte globale
    const telemetryDevices = <?php echo json_encode(array_map(function($r){
        return [
            'device_id' => $r['device_id'],
            'label' => trim(($r['device_brand']??'').' '.($r['device_model']??'')) . ' • v' . ($r['app_version_name']??'') . ' (' . (int)($r['app_version_code']??0) . ')',
            'lat' => isset($r['lat']) ? (float)$r['lat'] : null,
            'lng' => isset($r['lng']) ? (float)$r['lng'] : null,
            'last_seen' => $r['last_seen']??''
        ];
    }, $deviceRows)); ?>;

    // Init carte globale
    (function initGlobalMap(){
        try {
            const mapEl = document.getElementById('telemetry-map');
            if (!mapEl) return;
            const map = L.map('telemetry-map');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
            const pts = telemetryDevices.filter(d=>d.lat!=null && d.lng!=null);
            if (pts.length === 0) { map.setView([0,0], 1); return; }
            const bounds = [];
            pts.forEach(d=>{
                const m = L.marker([d.lat, d.lng]).addTo(map);
                m.bindPopup(`<b>${(d.label||'')}</b><br><small>${(d.device_id||'')}</small><br>Vu: ${d.last_seen||'N/A'}`);
                bounds.push([d.lat, d.lng]);
            });
            map.fitBounds(bounds, {padding:[20,20]});
        } catch (e) { /* ignore */ }
    })();

    // Recherche simple client-side
    const searchInput = document.getElementById('telemetry-search');
    if (searchInput) {
        searchInput.addEventListener('input', ()=>{
            const q = searchInput.value.trim().toLowerCase();
            document.querySelectorAll('.data-table tbody tr').forEach(tr=>{
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    }

    // Toggle colonnes (1-based index)
    document.querySelectorAll('.col-toggle').forEach(cb=>{
        cb.addEventListener('change', ()=>{
            const col = parseInt(cb.getAttribute('data-col'),10);
            const show = cb.checked;
            document.querySelectorAll('.data-table table tr').forEach(tr=>{
                const td = tr.querySelector(`:scope > *:nth-child(${col})`);
                if (td) td.style.display = show ? '' : 'none';
            });
        });
    });

    // Export CSV des lignes visibles
    document.getElementById('export-csv')?.addEventListener('click', ()=>{
        const rows = Array.from(document.querySelectorAll('.data-table table tr')).filter(r=>r.offsetParent!==null);
        const csv = rows.map(r=>{
            const cells = Array.from(r.children);
            return cells.filter((_,i)=>{
                const isLast = i === (cells.length - 1); // exclure colonne Actions
                if (isLast) return false;
                const cb = document.querySelector(`.col-toggle[data-col="${i+1}"]`);
                return !cb || cb.checked; // inclure seulement colonnes visibles
            }).map(td=>('"'+td.innerText.replace(/"/g,'""').trim()+'"')).join(',');
        }).join('\n');
        const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'telemetrie.csv'; a.click();
        URL.revokeObjectURL(url);
    });
    document.querySelectorAll('.telemetry-row').forEach(tr => {
        tr.addEventListener('click', (e) => {
            if (e.target.closest('.btn-issue')) return; // éviter conflit clic
            const id = tr.getAttribute('data-device-id');
            fetch('admin/ajax_telemetry.php?action=device_details&device_id='+encodeURIComponent(id), {cache:'no-store'})
                .then(r=>r.json())
                .then(data => {
                    const box = document.getElementById('telemetry-details');
                    const c = document.getElementById('details-content');
                    if (!data || !data.success) { c.innerHTML = '<div style="color:#e74c3c;">Erreur chargement détails.</div>'; box.style.display='block'; return; }
                    const d = data.device||{};
                    const loc = data.location ? (Number(data.location.lat).toFixed(5)+', '+Number(data.location.lng).toFixed(5)+' • '+data.location.when) : '—';
                    const crashes = (data.crashes||[]).map(x=>`<li><b>${x.last_occurred}</b> · ${x.exception_class||x.crash_type||'Crash'} · v${x.app_version_code||'?'}<br><small>${(x.exception_message||'').substring(0,140)}</small></li>`).join('');
                    const sessions = (data.sessions||[]).map(s=>`<li><b>${s.started_at}</b> · ${s.duration_seconds||0}s · ${s.crashed?'<span style="color:#e74c3c;">CRASH</span>':'OK'}</li>`).join('');
                    const events = (data.events||[]).map(ev=>`<li><b>${ev.occurred_at}</b> · ${ev.event_type} · ${ev.event_name||''}</li>`).join('');
                    c.innerHTML = `
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">
                          <div style="background:rgba(255,255,255,0.06);padding:1rem;border-radius:8px;">
                            <h4 style="margin:0 0 .5rem 0;color:#fff;">Résumé</h4>
                            <div><b>ID:</b> ${d.device_id||''}</div>
                            <div><b>Modèle:</b> ${[d.device_brand||'',d.device_model||''].join(' ').trim()}</div>
                            <div><b>Android:</b> ${d.android_version||'N/A'}</div>
                            <div><b>App:</b> v${d.app_version_name||'?'} (${d.app_version_code||'?'})</div>
                            <div><b>Dernière activité:</b> ${d.last_seen||'N/A'}</div>
                            <div><b>Sessions (total):</b> ${d.total_sessions||0}</div>
                            <div><b>Localisation:</b> ${loc}</div>
                          </div>
                          <div style="background:rgba(255,255,255,0.06);padding:1rem;border-radius:8px;">
                            <h4 style="margin:0 0 .5rem 0;color:#fff;">Crashes récents</h4>
                            <ul>${crashes || '<li>Aucun</li>'}</ul>
                          </div>
                          <div style="background:rgba(255,255,255,0.06);padding:1rem;border-radius:8px;">
                            <h4 style="margin:0 0 .5rem 0;color:#fff;">Sessions récentes</h4>
                            <ul>${sessions || '<li>N/A</li>'}</ul>
                          </div>
                          <div style="background:rgba(255,255,255,0.06);padding:1rem;border-radius:8px;">
                            <h4 style="margin:0 0 .5rem 0;color:#fff;">Événements</h4>
                            <ul>${events || '<li>N/A</li>'}</ul>
                          </div>
                          <div style="background:rgba(255,255,255,0.06);padding:0;border-radius:8px;min-height:260px;">
                            <div id="device-map" style="height:260px;border-radius:8px;"></div>
                          </div>
                        </div>`;
                    box.style.display='block';

                    // Mini-carte appareil
                    try {
                        if (data.location && typeof L !== 'undefined') {
                            const dm = L.map('device-map');
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(dm);
                            const lat = Number(data.location.lat), lng = Number(data.location.lng);
                            if (!isNaN(lat) && !isNaN(lng)) {
                                L.marker([lat,lng]).addTo(dm);
                                dm.setView([lat,lng], 13);
                            } else {
                                dm.setView([0,0], 1);
                            }
                        }
                    } catch (_) {}
                })
                .catch(()=>{
                    const box = document.getElementById('telemetry-details');
                    document.getElementById('details-content').innerHTML = '<div style="color:#e74c3c;">Erreur réseau.</div>';
                    box.style.display='block';
                });
        });
    });
    document.getElementById('close-details')?.addEventListener('click', ()=>{
        document.getElementById('telemetry-details').style.display='none';
    });
    document.querySelectorAll('.btn-issue').forEach(btn=>{
        btn.addEventListener('click', (e)=>{
            e.stopPropagation();
            const id = btn.getAttribute('data-device-id');
            const msg = prompt('Décrire brièvement le problème (optionnel):', 'Application ne fonctionne pas');
            fetch('admin/ajax_telemetry.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'mark_issue', device_id:id, message:msg||''})})
             .then(r=>r.json()).then(j=>{
                if (j && j.success) alert('Signalement enregistré.'); else alert('Échec du signalement.');
             }).catch(()=>alert('Erreur réseau.'));
        });
    });
    document.querySelectorAll('.btn-resolve').forEach(btn=>{
        btn.addEventListener('click', (e)=>{
            e.stopPropagation();
            const id = btn.getAttribute('data-device-id');
            if (!confirm('Marquer comme résolus les incidents récents pour cet appareil ?')) return;
            fetch('admin/ajax_telemetry.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'resolve_issues', device_id:id})})
             .then(r=>r.json()).then(j=>{
                if (j && j.success) alert('Incidents marqués comme résolus.'); else alert('Action échouée.');
             }).catch(()=>alert('Erreur réseau.'));
        });
    });
    </script>
<?php endif; ?>
</div>

<style>
.form-input {
    width: 100%;
    padding: 0.75rem;
    background: var(--glass-bg);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #fff;
    font-size: 0.95rem;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary-gold);
    box-shadow: 0 0 0 2px rgba(212, 168, 83, 0.2);
}

.card {
    background: var(--glass-bg);
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    background: rgba(212, 168, 83, 0.1);
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.card-header h3 {
    margin: 0;
    color: var(--primary-gold);
    font-size: 1.2rem;
}

.card-body {
    padding: 1.5rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-primary {
    background: var(--primary-gold);
    color: var(--primary-dark);
}

.btn-secondary {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
</style>

<script>
const uploadedApks = <?= json_encode($uploadedApks) ?>;

function showAppType(type) {
    // Masquer toutes les sections de mises à jour
    document.querySelectorAll('.updates-section').forEach(section => {
        section.style.display = 'none';
    });
    
    // Réinitialiser les boutons d'applications
    document.querySelectorAll('[id^="btn-app-"]').forEach(btn => {
        btn.className = btn.className.replace('btn-primary', 'btn-secondary');
    });
    
    // Afficher la section demandée
    document.getElementById('updates-section-' + type).style.display = 'block';
    document.getElementById('btn-app-' + type).className = document.getElementById('btn-app-' + type).className.replace('btn-secondary', 'btn-primary');
}

function fillFormWithVersion(type) {
    if (!uploadedApks[type]) return;
    
    const apk = uploadedApks[type];
    document.querySelector('input[name="version_code"]').value = apk.version_code || 1;
    document.querySelector('input[name="version_name"]').value = apk.version_name || '1.0';
    document.querySelector('input[name="apk_url"]').value = '/admin/download_apk.php?file=' + encodeURIComponent(apk.file);
    document.querySelector('input[name="apk_size"]').value = apk.apk_size || 0;
    
    // Message de confirmation
    const messageDiv = document.createElement('div');
    messageDiv.style.cssText = 'position:fixed;top:20px;right:20px;background:#27ae60;color:#fff;padding:1rem;border-radius:8px;z-index:9999;';
    messageDiv.innerHTML = '<i class="fas fa-check"></i> Formulaire pré-rempli avec ' + (type === 'current' ? 'la version actuelle' : 'la version précédente');
    document.body.appendChild(messageDiv);
    setTimeout(() => messageDiv.remove(), 3000);
}

// Afficher la section coursiers par défaut au chargement
document.addEventListener('DOMContentLoaded', function() {
    showAppType('coursiers');
});
</script>