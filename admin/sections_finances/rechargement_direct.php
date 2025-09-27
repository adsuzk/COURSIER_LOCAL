<?php
/**
 * MODULE RECHARGEMENT DIRECT - ADMIN
 * Rechargement instantané des comptes coursiers avec synchronisation FCM
 * Respect des coloris Suzosky et UI/UX moderne
 */

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
                    
                    // Mettre à jour le solde directement dans agents_suzosky
                    $stmt = $pdo->prepare("UPDATE agents_suzosky SET solde_wallet = ? WHERE id = ?");
                    $stmt->execute([$nouveauSolde, $coursier_id]);
                    
                    // Enregistrer la transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions_financieres (
                            type, montant, compte_type, compte_id, reference, description, statut, date_creation
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $reference = 'ADMIN_RECH_' . date('YmdHis') . '_' . $coursier_id;
                    $description = "Rechargement admin: {$montant} FCFA" . ($motif ? " - {$motif}" : "");
                    
                    $stmt->execute([
                        'credit',
                        $montant,
                        'coursier',
                        $coursier_id,
                        $reference,
                        $description,
                        'reussi'
                    ]);
                    
                    // Envoyer notification FCM au coursier
                    $stmt = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
                    $stmt->execute([$coursier_id]);
                    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    $notificationsSent = 0;
                    foreach ($tokens as $token) {
                        $message = "💰 Compte rechargé! +{$montant} FCFA - Nouveau solde: {$nouveauSolde} FCFA";
                        
                        // Log notification FCM
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications_log_fcm (coursier_id, token_used, message, status, created_at)
                            VALUES (?, ?, ?, 'sent', NOW())
                        ");
                        $stmt->execute([$coursier_id, $token, $message]);
                        $notificationsSent++;
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
                            'notifications_sent' => $notificationsSent
                        ]
                    );
                    
                    $_SESSION['success_message'] = "✅ Rechargement réussi! {$montant} FCFA ajoutés au compte de {$coursier['nom']} {$coursier['prenoms']}. {$notificationsSent} notification(s) envoyée(s).";
                    
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

$connectedCoursiers = getConnectedCouriers($pdo);
$connectedIds = array_column($connectedCoursiers, 'id');

// Statistiques rapides
$totalCoursiers = count($coursiers);
$coursiersConnectes = array_filter($coursiers, fn($c) => in_array($c['id'], $connectedIds, true));
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
                    <div class="stat-number"><?= count($coursiersConnectes) ?></div>
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
                            <div>
                                <div class="status-badge <?= $coursier['statut_connexion'] === 'en_ligne' ? 'status-online' : 'status-offline' ?>">
                                    <?= $coursier['statut_connexion'] === 'en_ligne' ? '🟢 En ligne' : '⚫ Hors ligne' ?>
                                </div>
                                <div class="fcm-indicator <?= $coursier['fcm_tokens'] > 0 ? 'fcm-ok' : 'fcm-warning' ?>">
                                    🔔 FCM: <?= $coursier['fcm_tokens'] ?> token(s)
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