<?php
// admin/applications.php
// Logique d'upload déplacée dans admin.php pour éviter les headers déjà envoyés

// Auto-détection et mise à jour des métadonnées APK
include __DIR__ . '/auto_detect_apk.php';
require_once __DIR__ . '/../config.php';

// 1) Charger la configuration des applications
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$applications = require __DIR__ . '/../applis.php';
?>
<div class="fade-in">
    <h2 style="color:var(--primary-gold);font-size:2rem;font-weight:800;margin-bottom:2rem;display:flex;align-items:center;"><i class="fas fa-th-large" style="margin-right:1rem;"></i>Gestion des Applications</h2>
    
    <!-- Navigation par type d'application -->
    <div style="margin-bottom:2rem;display:flex;gap:0.5rem;">
        <button onclick="showApplicationType('coursiers')" id="btn-coursiers" class="btn btn-primary">
            <i class="fas fa-motorcycle"></i> Applications Coursiers
        </button>
        <button onclick="showApplicationType('clients')" id="btn-clients" class="btn btn-secondary">
            <i class="fas fa-users"></i> Applications Clients
        </button>
    </div>

    <?php if (!empty($_GET['uploaded'])): ?>
        <div style="margin-bottom:1rem;padding:10px;border-radius:8px;background:rgba(40,167,69,.15);color:#7CFC9A;">
            ✅ APK téléversée avec succès.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div style="margin-bottom:1rem;padding:10px;border-radius:8px;background:rgba(220,53,69,.15);color:#FF7B7B;">
            ❌ <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Section Applications Coursiers -->
    <div id="section-coursiers" class="app-section">
        <h3 style="color:var(--primary-gold);font-size:1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;">
            <i class="fas fa-motorcycle" style="margin-right:0.5rem;"></i>Applications pour Coursiers
        </h3>

        <!-- Formulaire d'upload APK Coursiers -->
        <div class="data-table" data-no-refresh="true" style="margin-bottom:2rem;padding:1rem;border:1px solid rgba(255,255,255,.08);border-radius:12px;">
            <h4 style="margin:0 0 15px 0;color:var(--primary-gold);">
                <i class="fas fa-upload" style="margin-right:0.5rem;"></i>Charger une APK Coursier
            </h4>
            <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="action" value="upload_apk" />
                <input type="hidden" name="app_type" value="coursier" />
                <input type="file" name="apk_file" accept=".apk" required style="padding:8px;background:#111;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#fff;" />
                <input type="file" name="apk_meta" accept=".json" style="padding:8px;background:#111;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#fff;" />
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Téléverser APK Coursier</button>
                <small style="opacity:.7;">Max 200 Mo. Fichier JSON optionnel pour métadonnées (output-metadata.json).</small>
            </form>
            <?php
            $latestMeta = @json_decode(@file_get_contents($uploadDir . '/latest_apk.json'), true) ?: null;
            if ($latestMeta && is_file($uploadDir . '/' . ($latestMeta['file'] ?? ''))) {
                $latestFile = $latestMeta['file'];
                $filesize = filesize($uploadDir . '/' . $latestFile);
                echo '<div style="margin-top:.75rem;color:rgba(255,255,255,.8);"><strong>Dernière APK Coursier:</strong> <b>' . htmlspecialchars($latestFile) . '</b> (' . number_format($filesize/1024/1024,2) . ' Mo), mise en ligne le ' . date('d/m/Y H:i', filemtime($uploadDir . '/' . $latestFile)) . '.</div>';
                $latestDownload = function_exists('routePath')
                    ? routePath('admin/download_apk.php?file=' . rawurlencode($latestFile))
                    : '/admin/download_apk.php?file=' . rawurlencode($latestFile);
                echo '<div style="margin-top:.25rem;"><a class="btn btn-secondary" href="' . htmlspecialchars($latestDownload, ENT_QUOTES) . '" download><i class="fas fa-download"></i> Télécharger APK Coursier</a></div>';
            }
            ?>
        </div>

        <!-- Liste des applications coursiers -->
        <div class="data-table" style="margin-bottom:2rem;">
            <h4 style="margin:0 0 15px 0;color:var(--primary-gold);">
                <i class="fas fa-list" style="margin-right:0.5rem;"></i>Applications Coursiers Disponibles
            </h4>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Description</th>
                        <th>Plateformes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                    <?php if (stripos($app['nom'], 'coursier') !== false || stripos($app['description'], 'livraison') !== false): ?>
                    <tr>
                        <td style="font-weight:700;color:var(--primary-gold);font-size:1.1rem;">
                            <?php if (!empty($app['icon'])): ?>
                                <img src="<?php echo htmlspecialchars($app['icon']); ?>" alt="icon" style="width:24px;height:24px;vertical-align:middle;margin-right:0.5rem;border-radius:6px;background:#fff;" />
                            <?php else: ?>
                                <i class="fas fa-motorcycle" style="margin-right:0.5rem;color:var(--primary-gold);"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($app['nom']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($app['description']); ?></td>
                        <td><?php echo htmlspecialchars(implode(', ', $app['plateformes'])); ?></td>
                        <td>
                            <?php if (!empty($app['lien'])): ?>
                            <a href="<?php echo htmlspecialchars($app['lien']); ?>" class="btn btn-primary" target="_blank" download>
                                <i class="fas fa-download"></i> v<?php echo htmlspecialchars($app['version']); ?>
                            </a>
                            <?php if (!empty($app['lien_precedent'])): ?>
                            <br><br>
                            <a href="<?php echo htmlspecialchars($app['lien_precedent']); ?>" class="btn btn-secondary" target="_blank" download style="font-size:0.9rem;">
                                <i class="fas fa-history"></i> Précédente (<?php echo htmlspecialchars($app['version_precedente']); ?>)
                            </a>
                            <?php endif; ?>
                            <?php else: ?>
                            <span style="color:#ccc;">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section Applications Clients -->
    <div id="section-clients" class="app-section" style="display:none;">
        <h3 style="color:var(--primary-gold);font-size:1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;">
            <i class="fas fa-users" style="margin-right:0.5rem;"></i>Applications pour Clients
        </h3>

        <!-- Formulaire d'upload APK Clients -->
        <div class="data-table" data-no-refresh="true" style="margin-bottom:2rem;padding:1rem;border:1px solid rgba(255,255,255,.08);border-radius:12px;">
            <h4 style="margin:0 0 15px 0;color:var(--primary-gold);">
                <i class="fas fa-upload" style="margin-right:0.5rem;"></i>Charger une APK Client
            </h4>
            <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="action" value="upload_apk" />
                <input type="hidden" name="app_type" value="client" />
                <input type="file" name="apk_file" accept=".apk" required style="padding:8px;background:#111;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#fff;" />
                <input type="file" name="apk_meta" accept=".json" style="padding:8px;background:#111;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#fff;" />
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Téléverser APK Client</button>
                <small style="opacity:.7;">Max 200 Mo. Fichier JSON optionnel pour métadonnées (output-metadata.json).</small>
            </form>
            <div style="margin-top:.75rem;color:rgba(255,255,255,.6);font-style:italic;">
                <i class="fas fa-info-circle"></i> Les APKs clients seront stockées séparément et gérées indépendamment.
            </div>
        </div>

        <!-- Liste des applications clients -->
        <div class="data-table" style="margin-bottom:2rem;">
            <h4 style="margin:0 0 15px 0;color:var(--primary-gold);">
                <i class="fas fa-list" style="margin-right:0.5rem;"></i>Applications Clients Disponibles
            </h4>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Application</th>
                        <th>Description</th>
                        <th>Plateformes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $hasClientApps = false;
                    foreach ($applications as $app): 
                        if (stripos($app['nom'], 'client') !== false || stripos($app['description'], 'client') !== false):
                            $hasClientApps = true;
                    ?>
                    <tr>
                        <td style="font-weight:700;color:var(--primary-gold);font-size:1.1rem;">
                            <?php if (!empty($app['icon'])): ?>
                                <img src="<?php echo htmlspecialchars($app['icon']); ?>" alt="icon" style="width:24px;height:24px;vertical-align:middle;margin-right:0.5rem;border-radius:6px;background:#fff;" />
                            <?php else: ?>
                                <i class="fas fa-users" style="margin-right:0.5rem;color:var(--primary-gold);"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($app['nom']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($app['description']); ?></td>
                        <td><?php echo htmlspecialchars(implode(', ', $app['plateformes'])); ?></td>
                        <td>
                            <?php if (!empty($app['lien'])): ?>
                            <a href="<?php echo htmlspecialchars($app['lien']); ?>" class="btn btn-primary" target="_blank" download>
                                <i class="fas fa-download"></i> v<?php echo htmlspecialchars($app['version']); ?>
                            </a>
                            <?php else: ?>
                            <span style="color:#ccc;">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        endif;
                    endforeach; 
                    if (!$hasClientApps):
                    ?>
                    <tr>
                        <td colspan="4" style="text-align:center;color:rgba(255,255,255,.6);font-style:italic;padding:2rem;">
                            <i class="fas fa-info-circle"></i> Aucune application client disponible pour le moment.
                            <br><small>Utilisez le formulaire ci-dessus pour téléverser la première APK client.</small>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function showApplicationType(type) {
        // Masquer toutes les sections
        document.querySelectorAll('.app-section').forEach(section => {
            section.style.display = 'none';
        });
        
        // Réinitialiser les boutons
        document.querySelectorAll('[id^="btn-"]').forEach(btn => {
            btn.className = btn.className.replace('btn-primary', 'btn-secondary');
        });
        
        // Afficher la section demandée
        document.getElementById('section-' + type).style.display = 'block';
        document.getElementById('btn-' + type).className = document.getElementById('btn-' + type).className.replace('btn-secondary', 'btn-primary');
    }
    
    // Afficher la section coursiers par défaut au chargement
    document.addEventListener('DOMContentLoaded', function() {
        showApplicationType('coursiers');
    });
    </script>
    <div style="text-align:right;color:rgba(255,255,255,0.5);font-size:0.95rem;margin-top:2rem;">
        <i class="fas fa-copyright"></i> Suzosky - Tous droits réservés
    </div>
</div>
