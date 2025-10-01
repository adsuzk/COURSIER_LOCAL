<?php
/**
 * MODULE RECHARGEMENT DIRECT - ADMIN
 * Rechargement instantané des comptes coursiers avec synchronisation FCM
 * Respect des coloris Suzosky et UI/UX moderne
 */

require_once __DIR__ . '/../../fcm_v1_manager.php';

/**
 * Insère un log FCM en s'adaptant dynamiquement à la structure de la table.
 */
if (!function_exists('logFcmNotification')) {
    function logFcmNotification(\PDO $pdo, array $data): void
    {
        static $availableColumns = null;

        if ($availableColumns === null) {
            $availableColumns = [];
            try {
                $stmt = $pdo->query('DESCRIBE notifications_log_fcm');
                if ($stmt) {
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($columns as $column) {
                        if (isset($column['Field'])) {
                            $availableColumns[] = $column['Field'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('notifications_log_fcm describe failed: ' . $e->getMessage());
                $availableColumns = [];
            }
        }

        if (empty($availableColumns)) {
            return;
        }

        $columnsToInsert = [];
        $values = [];

        foreach ($data as $column => $value) {
            if (in_array($column, $availableColumns, true)) {
                $columnsToInsert[] = $column;
                $values[] = $value;
            }
        }

        if (empty($columnsToInsert)) {
            return;
        }

        $columnList = implode(', ', $columnsToInsert);
        $placeholders = implode(', ', array_fill(0, count($columnsToInsert), '?'));

        try {
            $stmt = $pdo->prepare("INSERT INTO notifications_log_fcm ($columnList) VALUES ($placeholders)");
            $stmt->execute($values);
        } catch (\Throwable $e) {
            error_log('notifications_log_fcm insert failed: ' . $e->getMessage());
        }
    }
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Action: Rechargement direct d'un coursier
    if ($_POST['action'] === 'recharge_direct') {
        $coursier_id = (int)$_POST['coursier_id'];
        $montant = (float)$_POST['montant'];
        $motif = trim($_POST['motif'] ?? '');
        
        if ($coursier_id > 0 && $montant > 0) {
            try {
                $pdo->beginTransaction();
                
                // Vérifier que le coursier existe
                $stmt = $pdo->prepare("SELECT id, nom, prenoms, email, solde_wallet FROM agents_suzosky WHERE id = ?");
                $stmt->execute([$coursier_id]);
                $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($coursier) {
                    // Ancienne valeur pour log
                    $ancienSolde = $coursier['solde_wallet'] ?? 0;
                    $nouveauSolde = $ancienSolde + $montant;
                    
                    // 1. Mettre à jour le solde directement dans agents_suzosky
                    $stmt = $pdo->prepare("UPDATE agents_suzosky SET solde_wallet = ? WHERE id = ?");
                    $stmt->execute([$nouveauSolde, $coursier_id]);
                    
                    // 2. Enregistrer dans la table recharges (conforme documentation)
                    $stmt = $pdo->prepare("
                        INSERT INTO recharges (
                            coursier_id, montant, currency, status, created_at, updated_at, details
                        ) VALUES (?, ?, ?, ?, NOW(), NOW(), ?)
                    ");
                    
                    $details = json_encode([
                        'type' => 'rechargement_admin_direct',
                        'admin_user' => $_SESSION['admin_user'] ?? 'admin',
                        'motif' => $motif,
                        'ancien_solde' => $ancienSolde,
                        'nouveau_solde' => $nouveauSolde,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    
                    $stmt->execute([
                        $coursier_id,
                        $montant,
                        'FCFA',
                        'success',
                        $details
                    ]);
                    
                    // 3. Envoyer notification FCM RÉELLE via FCMManager
                                $fcmManager = new FCMv1Manager();
                    $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
                    $stmt->execute([$coursier_id]);
                    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $notificationsSent = 0;
                    $notificationsFailed = 0;
                    
                    foreach ($tokens as $token) {
                        $title = '💰 Compte Rechargé!';
                        $body = "Votre compte a été crédité de {$montant} FCFA\nNouveau solde: {$nouveauSolde} FCFA" . ($motif ? "\nMotif: {$motif}" : '');
                        
                        $data = [
                            'type' => 'wallet_recharge',
                            'montant' => (string)$montant,
                            'nouveau_solde' => (string)$nouveauSolde,
                            'motif' => $motif,
                            'action' => 'refresh_wallet'
                        ];
                        
                        $result = $fcm->envoyerNotification($token, $title, $body, $data);
                        
                        // Log détaillé de la notification FCM en fonction de la structure disponible
                        $logStatus = $result['success'] ? 'sent' : 'failed';
                        $responsePayload = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        if ($responsePayload === false) {
                            $responsePayload = null;
                        }

                        $logPayload = [
                            'coursier_id' => $coursier_id,
                            'token_used' => $token,
                            'message' => $body,
                            'type' => 'wallet_recharge',
                            'status' => $logStatus,
                            'response_data' => $responsePayload
                        ];
                        logFcmNotification($pdo, $logPayload);
                        
                        if ($result['success']) {
                            $notificationsSent++;
                        } else {
                            $notificationsFailed++;
                        }
                    }
                    
                    $pdo->commit();
                    
                    // Journal admin
                    getJournal()->logMaxDetail(
                        'RECHARGEMENT_DIRECT_ADMIN',
                        "Rechargement direct coursier {$coursier['nom']} {$coursier['prenoms']}",
                        [
                            'coursier_id' => $coursier_id,
                            'montant' => $montant,
                            'ancien_solde' => $ancienSolde,
                            'nouveau_solde' => $nouveauSolde,
                            'motif' => $motif,
                            'notifications_sent' => $notificationsSent,
                            'notifications_failed' => $notificationsFailed
                        ]
                    );
                    
                    $fcmStatus = "";
                    if ($notificationsSent > 0) {
                        $fcmStatus .= " ✅ {$notificationsSent} notification(s) FCM envoyée(s)";
                    }
                    if ($notificationsFailed > 0) {
                        $fcmStatus .= " ⚠️ {$notificationsFailed} notification(s) échouée(s)";
                    }
                    
                    $_SESSION['success_message'] = "✅ Rechargement réussi! {$montant} FCFA ajoutés au compte de {$coursier['nom']} {$coursier['prenoms']}.{$fcmStatus}";
                    
                } else {
                    $_SESSION['error_message'] = "❌ Coursier introuvable.";
                }
                
            } catch (Exception $e) {
                $pdo->rollback();
                $_SESSION['error_message'] = "❌ Erreur: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = "❌ Données invalides.";
        }
        
        // Redirection pour éviter double soumission
        $redirectUrl = 'admin.php?section=finances&tab=rechargement_direct';
        if (!headers_sent()) {
            header('Location: ' . $redirectUrl);
        } else {
            echo '<script>window.location.href="' . addslashes($redirectUrl) . '";</script>';
        }
        exit;
    }
}

require_once __DIR__ . '/../../lib/coursier_presence.php';

$allowedTypes = ['coursier', 'coursier_moto', 'coursier_velo', 'coursier_cargo'];
$allCoursiers = getAllCouriers($pdo);
$coursiers = [];

$apiCoursiersUrl = function_exists('routePath')
    ? routePath('api/coursiers_connectes.php')
    : rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/api/coursiers_connectes.php';

foreach ($allCoursiers as $coursier) {
    $type = strtolower($coursier['type_poste'] ?? '');
    if ($type !== '' && !in_array($type, $allowedTypes, true)) {
        continue;
    }

    $coursiers[] = array_merge($coursier, [
        'solde' => (float)($coursier['solde_wallet'] ?? 0),
        'fcm_tokens' => (int)($coursier['active_fcm_tokens'] ?? 0)
    ]);
}

// Statistiques rapides
$totalCoursiers = count($coursiers);
$coursiersAvecSolde = array_filter($coursiers, fn($c) => ($c['solde'] ?? 0) > 0);

include __DIR__ . '/../functions.php';
?>

<style>
    .finance-recharge {
        --primary-color: #D4A853;
        --secondary-color: #1A1A2E;
        --accent-color: #16213E;
        --success-color: #27AE60;
        --warning-color: #F39C12;
        --danger-color: #E94560;
        --text-light: #ECF0F1;
        padding: 24px;
        background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
        border-radius: 20px;
        border: 1px solid rgba(212, 168, 83, 0.18);
        box-shadow: 0 20px 40px rgba(10, 10, 30, 0.35);
        color: #ECF0F1;
        position: relative;
        overflow: hidden;
    }

    .finance-recharge::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top left, rgba(212,168,83,0.25), transparent 55%),
                    radial-gradient(circle at bottom right, rgba(13, 110, 253, 0.18), transparent 60%);
        pointer-events: none;
        opacity: 0.6;
    }

    .finance-recharge__inner {
        position: relative;
        z-index: 1;
        width: 100%;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 30px;
    }

    .finance-recharge * {
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .finance-recharge .header-section {
        background: rgba(26, 26, 46, 0.85);
        border-radius: 16px;
        padding: 28px;
        border: 1px solid rgba(212, 168, 83, 0.25);
        backdrop-filter: blur(12px);
    }

    .finance-recharge .header-title {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 18px;
    }

    .finance-recharge .header-title h1 {
        color: #D4A853;
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0;
    }

    .finance-recharge .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 18px;
    }

    .finance-recharge .stat-card {
        background: rgba(22, 33, 62, 0.78);
        padding: 18px;
        border-radius: 14px;
        border: 1px solid rgba(212, 168, 83, 0.28);
        text-align: center;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }

    .finance-recharge .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 26px rgba(5, 5, 25, 0.45);
    }

    .finance-recharge .stat-number {
        font-size: 2.1rem;
        font-weight: 800;
        color: #F5D78C;
        margin-bottom: 6px;
    }

    .finance-recharge .stat-label {
        font-size: 0.85rem;
        letter-spacing: 0.03em;
        opacity: 0.75;
    }

    .finance-recharge .agents-section {
        background: rgba(26, 26, 46, 0.9);
        border-radius: 18px;
        border: 1px solid rgba(212, 168, 83, 0.22);
        padding: 28px;
        backdrop-filter: blur(12px);
    }

    .finance-recharge .coursiers-grid {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .finance-recharge .coursier-card {
        background: rgba(22, 33, 62, 0.82);
        border-radius: 16px;
        border: 1px solid rgba(212, 168, 83, 0.28);
        padding: 24px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-wrap: wrap;
        align-items: stretch;
        gap: 24px;
    }

    .finance-recharge .coursier-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 20px 35px rgba(15, 20, 60, 0.35);
    }

    .finance-recharge .coursier-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        flex: 2 1 340px;
        min-width: 280px;
    }

    .finance-recharge .coursier-info h3 {
        margin: 0 0 6px;
        color: #F5D78C;
        font-size: 1.2rem;
        text-transform: capitalize;
    }

    .finance-recharge .coursier-details {
        font-size: 0.9rem;
        opacity: 0.82;
        line-height: 1.45;
    }

    .finance-recharge .status-wrapper {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
        min-width: 150px;
    }

    .finance-recharge .status-badge {
        padding: 6px 12px;
        border-radius: 18px;
        font-size: 0.82rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .finance-recharge .status-online {
        background: rgba(46, 204, 113, 0.18);
        color: #2ecc71;
    }

    .finance-recharge .status-offline {
        background: rgba(231, 76, 60, 0.16);
        color: #e74c3c;
    }

    .finance-recharge .status-warning {
        background: rgba(243, 156, 18, 0.2);
        color: #f39c12;
    }

    .finance-recharge .status-pending {
        background: rgba(255, 255, 255, 0.12);
        color: rgba(236, 240, 241, 0.85);
    }

    .finance-recharge .status-meta {
        font-size: 0.75rem;
        display: flex;
        flex-direction: column;
        gap: 5px;
        align-items: flex-end;
        opacity: 0.75;
    }

    .finance-recharge .solde-display {
        font-size: 1.55rem;
        font-weight: 700;
        text-align: center;
        padding: 12px 18px;
        border-radius: 12px;
        flex: 0 0 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 200px;
    }

    .finance-recharge .solde-positive {
        background: rgba(39, 174, 96, 0.18);
        color: #2ecc71;
    }

    .finance-recharge .solde-zero {
        background: rgba(233, 69, 96, 0.16);
        color: #e94560;
    }

    .finance-recharge .recharge-form {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 12px;
        align-items: end;
        flex: 1 1 320px;
        min-width: 280px;
    }

    .finance-recharge .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .finance-recharge .form-group label {
        font-size: 0.85rem;
        opacity: 0.85;
    }

    .finance-recharge .form-group input {
        padding: 9px 14px;
        border-radius: 8px;
        border: 1px solid rgba(212, 168, 83, 0.35);
        background: rgba(15, 52, 96, 0.82);
        color: #f7f7f7;
        font-size: 0.92rem;
    }

    .finance-recharge .form-group input:focus {
        outline: none;
        border-color: rgba(245, 215, 140, 0.9);
        box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.18);
    }

    .finance-recharge .btn {
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-weight: 700;
        letter-spacing: 0.03em;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        justify-content: center;
        background: linear-gradient(135deg, #DAB861, #B8941F);
        color: #1A1A2E;
    }

    .finance-recharge .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(212, 168, 83, 0.35);
    }

    .finance-recharge .alert {
        padding: 16px 20px;
        border-radius: 12px;
        border: 1px solid;
        background: rgba(255, 255, 255, 0.06);
        font-weight: 600;
    }

    .finance-recharge .alert-success {
        border-color: rgba(39, 174, 96, 0.65);
        color: #27AE60;
    }

    .finance-recharge .alert-danger {
        border-color: rgba(233, 69, 96, 0.65);
        color: #E94560;
    }

    @media (max-width: 1024px) {
        .finance-recharge {
            padding: 18px;
        }
    }

    @media (max-width: 1024px) {
        .finance-recharge .coursier-card {
            flex-direction: column;
            align-items: stretch;
        }

        .finance-recharge .coursier-header,
        .finance-recharge .solde-display,
        .finance-recharge .recharge-form {
            flex: 1 1 100%;
            min-width: 0;
        }
    }

    @media (max-width: 768px) {
        .finance-recharge .recharge-form {
            grid-template-columns: 1fr;
        }

        .finance-recharge .header-title h1 {
            font-size: 1.4rem;
        }

        .finance-recharge .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
    }
</style>

<section class="finance-recharge">
    <div class="finance-recharge__inner">
        <!-- Header avec statistiques -->
        <div class="header-section">
            <div class="header-title">
                <i class="fas fa-wallet" style="font-size: 2rem; color: var(--primary-color);"></i>
                <h1>Rechargement Direct Coursiers</h1>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $totalCoursiers ?></div>
                    <div class="stat-label">Total Coursiers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><span data-connected-count>--</span></div>
                    <div class="stat-label">Connectés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($coursiersAvecSolde) ?></div>
                    <div class="stat-label">Avec Solde > 0</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= round((count($coursiersAvecSolde) / max($totalCoursiers, 1)) * 100) ?>%</div>
                    <div class="stat-label">Taux Solvabilité</div>
                </div>
            </div>
        </div>

        <!-- Messages d'alerte -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

    <!-- Liste des coursiers -->
    <div class="agents-section">
            <div class="coursiers-grid">
                <?php foreach ($coursiers as $coursier): ?>
                    <div class="coursier-card">
                        <div class="coursier-header">
                            <div class="coursier-info">
                                <h3><?= htmlspecialchars($coursier['nom'] . ' ' . $coursier['prenoms']) ?></h3>
                                <div class="coursier-details">
                                    📧 <?= htmlspecialchars($coursier['email']) ?><br>
                                    📱 <?= htmlspecialchars($coursier['telephone'] ?? 'N/A') ?>
                                </div>
                            </div>
                            <div class="status-wrapper"
                                 data-status-panel
                                 data-coursier-id="<?= (int) $coursier['id'] ?>">
                                <div class="status-badge status-pending">
                                    <span class="status-icon" data-status-icon>⏳</span>
                                    <span class="status-text" data-status-text>Synchronisation…</span>
                                </div>
                                <div class="status-meta">
                                    <div class="status-last-seen" data-last-seen>Dernière activité inconnue</div>
                                    <div class="fcm-indicator <?= $coursier['fcm_tokens'] > 0 ? 'fcm-ok' : 'fcm-warning' ?>" data-fcm-indicator>
                                        🔔 FCM: <span data-fcm-count><?= $coursier['fcm_tokens'] ?></span> token(s)
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="solde-display <?= $coursier['solde'] > 0 ? 'solde-positive' : 'solde-zero' ?>">
                            💰 <?= number_format($coursier['solde'], 0, ',', ' ') ?> FCFA
                        </div>

                        <form method="POST" class="recharge-form">
                            <input type="hidden" name="action" value="recharge_direct">
                            <input type="hidden" name="coursier_id" value="<?= $coursier['id'] ?>">
                            
                            <div class="form-group">
                                <label for="montant_<?= $coursier['id'] ?>">Montant (FCFA)</label>
                                <input type="number" 
                                       id="montant_<?= $coursier['id'] ?>" 
                                       name="montant" 
                                       min="100" 
                                       max="1000000" 
                                       step="100" 
                                       placeholder="Ex: 5000" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="motif_<?= $coursier['id'] ?>">Motif (optionnel)</label>
                                <input type="text" 
                                       id="motif_<?= $coursier['id'] ?>" 
                                       name="motif" 
                                       placeholder="Ex: Bonus performance" 
                                       maxlength="100">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                💳 Recharger
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($coursiers)): ?>
                <div style="text-align: center; padding: 50px; color: var(--text-light); opacity: 0.7;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 20px; color: var(--primary-color);"></i>
                    <h3>Aucun coursier trouvé</h3>
                    <p>Aucun coursier n'est enregistré dans le système.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
        (function () {
            const API_URL = '<?= addslashes($apiCoursiersUrl) ?>';
            const connectedCountEl = document.querySelector('[data-connected-count]');
            const panelElements = Array.from(document.querySelectorAll('[data-status-panel]'));

            if (!panelElements.length) {
                if (connectedCountEl) {
                    connectedCountEl.textContent = '0';
                }
                return;
            }

            const panels = new Map();
            panelElements.forEach((element) => {
                const id = element.getAttribute('data-coursier-id');
                if (!id) {
                    return;
                }
                panels.set(String(id), {
                    badge: element.querySelector('.status-badge'),
                    icon: element.querySelector('[data-status-icon]'),
                    text: element.querySelector('[data-status-text]'),
                    lastSeen: element.querySelector('[data-last-seen]'),
                    fcmIndicator: element.querySelector('[data-fcm-indicator]'),
                    fcmCount: element.querySelector('[data-fcm-count]')
                });
            });

            function setFCMState(entry, tokens) {
                if (!entry || !entry.fcmIndicator) {
                    return;
                }
                const count = Number.isFinite(tokens) ? tokens : 0;
                if (entry.fcmCount) {
                    entry.fcmCount.textContent = count;
                }
                entry.fcmIndicator.classList.toggle('fcm-ok', count > 0);
                entry.fcmIndicator.classList.toggle('fcm-warning', count <= 0);
            }

            function applyDefaultState(entry) {
                if (!entry || !entry.badge) {
                    return;
                }
                entry.badge.classList.remove('status-online', 'status-warning', 'status-pending');
                entry.badge.classList.add('status-offline');
                if (entry.icon) {
                    entry.icon.textContent = '⚫';
                }
                if (entry.text) {
                    entry.text.textContent = 'Hors ligne';
                }
                if (entry.lastSeen) {
                    entry.lastSeen.textContent = 'Dernière activité inconnue';
                }
                if (entry.fcmIndicator) {
                    const baseCount = entry.fcmCount ? parseInt(entry.fcmCount.textContent, 10) || 0 : 0;
                    setFCMState(entry, baseCount);
                }
            }

            function formatRelativeTime(value) {
                if (!value) {
                    return 'Dernière activité inconnue';
                }

                const normalized = value.replace(' ', 'T');
                const date = new Date(normalized);
                if (Number.isNaN(date.getTime())) {
                    return 'Dernière activité inconnue';
                }

                const diffMs = Date.now() - date.getTime();
                if (diffMs < 0) {
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
            }

            async function refreshConnectivity() {
                if (!panels.size) {
                    return;
                }

                try {
                    const response = await fetch(API_URL, { cache: 'no-store' });
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const payload = await response.json();
                    const couriers = Array.isArray(payload.data) ? payload.data : [];

                    panels.forEach((entry) => applyDefaultState(entry));

                    couriers.forEach((coursier) => {
                        const id = String(coursier.id ?? '');
                        if (!panels.has(id)) {
                            return;
                        }

                        const entry = panels.get(id);
                        const statusLight = coursier.status_light || {};
                        const color = String(statusLight.color || '').toLowerCase();

                        entry.badge.classList.remove('status-online', 'status-warning', 'status-offline', 'status-pending');

                        if (color === 'green') {
                            entry.badge.classList.add('status-online');
                            if (entry.icon) {
                                entry.icon.textContent = '🟢';
                            }
                        } else if (color === 'orange') {
                            entry.badge.classList.add('status-warning');
                            if (entry.icon) {
                                entry.icon.textContent = '🟠';
                            }
                        } else {
                            entry.badge.classList.add('status-offline');
                            if (entry.icon) {
                                entry.icon.textContent = '⚫';
                            }
                        }

                        if (entry.text) {
                            entry.text.textContent = statusLight.label || 'Statut inconnu';
                        }

                        if (entry.lastSeen) {
                            const lastSeen = coursier.last_seen_at || coursier.last_login_at || null;
                            entry.lastSeen.textContent = formatRelativeTime(lastSeen);
                        }

                        const tokenCount = parseInt(coursier.fcm_tokens, 10);
                        setFCMState(entry, Number.isNaN(tokenCount) ? 0 : tokenCount);
                    });

                    if (connectedCountEl) {
                        if (payload && payload.meta && typeof payload.meta.total === 'number') {
                            connectedCountEl.textContent = payload.meta.total;
                        } else {
                            connectedCountEl.textContent = couriers.length;
                        }
                    }
                } catch (error) {
                    if (connectedCountEl) {
                        connectedCountEl.textContent = '??';
                    }
                    console.warn('Impossible de récupérer les coursiers connectés', error);
                }
            }

            refreshConnectivity();
            setInterval(refreshConnectivity, 30000);
        })();
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>