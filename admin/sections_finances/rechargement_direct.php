<?php
/**
 * MODULE RECHARGEMENT DIRECT - ADMIN
 * Rechargement instantané des comptes coursiers avec synchronisation FCM
 * Respect des coloris Suzosky et UI/UX moderne
 */

require_once __DIR__ . '/../../fcm_manager.php';

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
                    $fcm = new FCMManager();
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
                        
                        // Log détaillé de la notification FCM
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications_log_fcm 
                            (coursier_id, token_used, message, type, status, response_data, created_at)
                            VALUES (?, ?, ?, 'wallet_recharge', ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $coursier_id, 
                            $token, 
                            $body,
                            $result['success'] ? 'sent' : 'failed',
                            json_encode($result)
                        ]);
                        
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
        echo '<script>window.location.href="admin.php?section=finances&tab=rechargement_direct";</script>';
        exit;
    }
}

require_once __DIR__ . '/../../lib/coursier_presence.php';

$allowedTypes = ['coursier', 'coursier_moto', 'coursier_velo', 'coursier_cargo'];
$allCoursiers = getAllCouriers($pdo);
$coursiers = [];

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

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rechargement Direct - Admin Suzosky</title>
    <style>
        /* Coloris Suzosky officiels */
        :root {
            --primary-color: #D4A853;
            --secondary-color: #1A1A2E;
            --accent-color: #16213E;
            --light-accent: #0F3460;
            --success-color: #27AE60;
            --warning-color: #F39C12;
            --danger-color: #E94560;
            --text-light: #ECF0F1;
        }

        body {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--accent-color) 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            color: var(--text-light);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-section {
            background: rgba(26, 26, 46, 0.9);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(212, 168, 83, 0.2);
            backdrop-filter: blur(10px);
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .header-title h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 2rem;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .stat-card {
            background: rgba(22, 33, 62, 0.8);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(212, 168, 83, 0.3);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-light);
            opacity: 0.8;
        }

        .main-content {
            background: rgba(26, 26, 46, 0.9);
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(212, 168, 83, 0.2);
            backdrop-filter: blur(10px);
        }

        .coursiers-grid {
            display: grid;
            gap: 20px;
        }

        .coursier-card {
            background: rgba(22, 33, 62, 0.8);
            border-radius: 12px;
            border: 1px solid rgba(212, 168, 83, 0.3);
            padding: 20px;
            transition: all 0.3s ease;
        }

        .coursier-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 168, 83, 0.2);
        }

        .coursier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .coursier-info h3 {
            margin: 0 0 5px 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .coursier-details {
            font-size: 0.9rem;
            color: var(--text-light);
            opacity: 0.8;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-online {
            background: var(--success-color);
            color: white;
        }

        .status-offline {
            background: var(--danger-color);
            color: white;
        }

        .solde-display {
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .solde-positive {
            background: rgba(39, 174, 96, 0.2);
            color: var(--success-color);
        }

        .solde-zero {
            background: rgba(233, 69, 96, 0.2);
            color: var(--danger-color);
        }

        .recharge-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-size: 0.9rem;
            color: var(--text-light);
            opacity: 0.9;
        }

        .form-group input {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(212, 168, 83, 0.3);
            background: rgba(15, 52, 96, 0.8);
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(212, 168, 83, 0.3);
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #B8941F);
            color: var(--secondary-color);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(212, 168, 83, 0.4);
        }

        .fcm-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.8rem;
        }

        .fcm-ok { color: var(--success-color); }
        .fcm-warning { color: var(--warning-color); }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }

        .alert-danger {
            background: rgba(233, 69, 96, 0.1);
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        @media (max-width: 768px) {
            .recharge-form {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
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
        <div class="main-content">
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>