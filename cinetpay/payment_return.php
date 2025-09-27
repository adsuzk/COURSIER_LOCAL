<?php
// Charger d'abord la configuration racine (DB, helpers globaux)
require_once dirname(__DIR__) . '/config.php';
// Puis la configuration locale CinetPay et l'intégration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cinetpay_integration.php';
/**
 * CINETPAY PAYMENT RETURN HANDLER - SUZOSKY COURSIER V2.1
 * =======================================================
 * Gestion du retour de paiement CINETPAY
 */

// Les inclusions précédentes ont déjà chargé la configuration et l'intégration

// Initialiser les tables si nécessaire
createPaymentTables();

$cinetpay = new SuzoskyCinetPayIntegration();

// Récupérer les paramètres de retour (CinetPay peut ne pas renvoyer cpm_trans_id, on ajoute tid)
$transactionId = $_GET['cpm_trans_id'] ?? $_GET['transaction_id'] ?? $_GET['tid'] ?? '';
$orderNumber = $_GET['order'] ?? '';
// Token de sécurité CinetPay
$token = $_GET['token'] ?? '';

// Si pas de transaction fournie mais un numéro de commande est présent, tenter de retrouver la dernière transaction
if (empty($transactionId) && !empty($orderNumber)) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT transaction_id FROM order_payments WHERE order_number = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$orderNumber]);
        $transactionId = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {
        // ignore, handled below
    }
}

if (empty($transactionId)) {
    header('Location: index.html?error=transaction_invalide');
    exit;
}

try {
    // Vérifier le statut du paiement
    $paymentStatus = $cinetpay->checkPaymentStatus($transactionId);
    
    if ($paymentStatus['success'] && $paymentStatus['status'] === 'completed') {
        // Paiement réussi
        $amountFmt = number_format($paymentStatus['amount'], 0, ',', ' ');
        // Si c'est un paiement de commande, marquer comme payé
        if (!empty($orderNumber)) {
            try {
                // Mettre à jour order_payments et commande
                $pdo = getDBConnection();
                $pdo->prepare("UPDATE order_payments SET status='completed', updated_at = NOW() WHERE transaction_id = ?")->execute([$transactionId]);
                $cols = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
                $orderNumCol = in_array('numero_commande', $cols) ? 'numero_commande' : (in_array('order_number', $cols) ? 'order_number' : (in_array('code_commande', $cols) ? 'code_commande' : 'numero_commande'));
                if (in_array('paiement_confirme', $cols)) {
                    $pdo->prepare("UPDATE commandes SET paiement_confirme = 1 WHERE {$orderNumCol} = ?")->execute([$orderNumber]);
                } elseif (in_array('statut_paiement', $cols)) {
                    $pdo->prepare("UPDATE commandes SET statut_paiement = 'paye' WHERE {$orderNumCol} = ?")->execute([$orderNumber]);
                }
                // Assignation automatique d’un coursier actif
                try {
                    // Récupérer l'adresse de départ
                    $stmtOrder = $pdo->prepare("SELECT adresse_depart FROM commandes WHERE {$orderNumCol} = ?");
                    $stmtOrder->execute([$orderNumber]);
                    $row = $stmtOrder->fetch(PDO::FETCH_ASSOC);
                    if (!empty($row['adresse_depart'])) {
                        $coords = geocodeAddress($row['adresse_depart']);
                        if (!empty($coords['lat']) && !empty($coords['lng'])) {
                            $courier = assignNearestCourier($coords['lat'], $coords['lng']);
                            if (!empty($courier['id_coursier'])) {
                                // Mettre à jour la commande avec l'ID du coursier
                                $pdo->prepare("UPDATE commandes SET coursier_id = ? WHERE {$orderNumCol} = ?")
                                    ->execute([$courier['id_coursier'], $orderNumber]);
                                // Notifier le coursier assigné via FCM
                                try {
                                    require_once __DIR__ . '/../api/lib/fcm_enhanced.php';
                                    $pdo->exec("CREATE TABLE IF NOT EXISTS device_tokens (id INT AUTO_INCREMENT PRIMARY KEY, coursier_id INT NOT NULL, token VARCHAR(255) NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY unique_token (token)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                                    $stTok = $pdo->prepare("SELECT token FROM device_tokens WHERE coursier_id = ? ORDER BY updated_at DESC");
                                    $stTok->execute([(int)$courier['id_coursier']]);
                                    $tokens = array_column($stTok->fetchAll(PDO::FETCH_ASSOC), 'token');
                                    if (!empty($tokens)) {
                                        // Récupérer l'ID de la commande pour le log
                                        $stId = $pdo->prepare("SELECT id FROM commandes WHERE {$orderNumCol} = ?");
                                        $stId->execute([$orderNumber]);
                                        $orderId = (int)($stId->fetchColumn() ?: 0);
                                        fcm_send_with_log(
                                            $tokens,
                                            'Commande payée assignée',
                                            'Une commande payée vous a été attribuée',
                                            [
                                                'type' => 'paid_assigned',
                                                'order_id' => $orderId
                                            ],
                                            (int)$courier['id_coursier'],
                                            $orderId
                                        );
                                    }
                                } catch (Throwable $e) { /* ne bloque pas l'affichage */ }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('Erreur assignment coursier: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                error_log('Maj commande payée échouée: ' . $e->getMessage());
            }
            $message = "Le paiement de votre commande {$orderNumber} de {$amountFmt} FCFA a été confirmé. Merci !";
        } else {
            $message = "Votre recharge de {$amountFmt} FCFA a été effectuée avec succès !";
        }
        $alertType = 'success';
        error_log("Paiement CINETPAY réussi - Transaction: {$transactionId}, Montant: {$paymentStatus['amount']}");
    } else {
        // Paiement échoué
        $message = !empty($orderNumber)
            ? ("Le paiement de la commande {$orderNumber} a échoué. " . ($paymentStatus['message'] ?? 'Veuillez réessayer.'))
            : ("Votre paiement a échoué. " . ($paymentStatus['message'] ?? 'Veuillez réessayer.'));
        $alertType = 'error';
        
        // Log de l'échec
        error_log("Paiement CINETPAY échoué - Transaction: {$transactionId}, Message: " . ($paymentStatus['message'] ?? 'Inconnu'));
    }
    
} catch (Exception $e) {
    $message = "Une erreur s'est produite lors de la vérification du paiement. Veuillez contacter le support.";
    $alertType = 'error';
    error_log("Erreur vérification paiement CINETPAY: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Résultat du Paiement - Suzosky Coursier</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a1a2e;
            --secondary-dark: #16213e;
            --primary-gold: #d4a853;
            --accent-red: #e94560;
            --glass-bg: rgba(255,255,255,0.1);
            --glass-border: rgba(255,255,255,0.2);
            --gradient-gold: linear-gradient(135deg, #d4a853 0%, #f4d03f 100%);
            --success-color: #28a745;
            --warning-color: #ffc107;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .payment-result-container {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            border: 2px solid var(--glass-border);
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 25px 80px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
        }

        .payment-result-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .payment-result-container:hover::before {
            left: 100%;
        }

        .result-icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: iconPulse 2s infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .result-icon.success {
            color: var(--success-color);
        }

        .result-icon.error {
            color: var(--accent-red);
        }

        .result-title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 20px;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 1px;
        }

        .result-message {
            font-size: 16px;
            color: rgba(255,255,255,0.9);
            margin-bottom: 40px;
            line-height: 1.6;
            font-weight: 600;
        }

        .return-btn {
            background: var(--gradient-gold);
            color: var(--primary-dark);
            border: none;
            padding: 18px 40px;
            border-radius: 15px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.4s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .return-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .return-btn:hover::before {
            left: 100%;
        }

        .return-btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 15px 40px rgba(212, 168, 83, 0.5);
            filter: brightness(1.1);
        }

        .transaction-details {
            background: rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 25px;
            margin: 30px 0;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .transaction-id {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255,255,255,0.1);
            border-left: 4px solid var(--primary-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .payment-result-container {
                padding: 30px 20px;
                margin: 10px;
            }

            .result-icon {
                font-size: 60px;
            }

            .result-title {
                font-size: 24px;
            }

            .result-message {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="payment-result-container">
        <div class="result-icon <?= $alertType ?>">
            <i class="fas <?= $alertType === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
        </div>
        
        <h1 class="result-title">
            <?= $alertType === 'success' ? 'Paiement Réussi !' : 'Paiement Échoué' ?>
        </h1>
        
        <p class="result-message">
            <?= htmlspecialchars($message) ?>
        </p>
        
        <?php if (!empty($transactionId)): ?>
        <div class="transaction-details">
            <div class="transaction-id">
                Transaction ID: <?= htmlspecialchars($transactionId) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <a href="coursier.php" class="return-btn">
            <i class="fas fa-arrow-left"></i>
            Retour à l'interface
        </a>
    </div>

    <!-- Loading overlay pour redirection automatique -->
    <script>
        // Redirection automatique après 5 secondes en cas de succès
        <?php if ($alertType === 'success'): ?>
        setTimeout(() => {
            const loadingOverlay = document.createElement('div');
            loadingOverlay.className = 'loading-overlay';
            loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(loadingOverlay);
            
            setTimeout(() => {
                window.location.href = 'coursier.php?recharge_success=1';
            }, 2000);
        }, 3000);
        <?php endif; ?>

        // Sons de notification
        document.addEventListener('DOMContentLoaded', function() {
            // Son de succès ou d'échec
            const context = new (window.AudioContext || window.webkitAudioContext)();
            
            <?php if ($alertType === 'success'): ?>
            // Son de succès (accord majeur)
            const frequencies = [523.25, 659.25, 783.99]; // Do-Mi-Sol
            <?php else: ?>
            // Son d'échec (dissonance)
            const frequencies = [220, 233.08, 246.94]; // La-La#-Si
            <?php endif; ?>
            
            frequencies.forEach((freq, index) => {
                setTimeout(() => {
                    const oscillator = context.createOscillator();
                    const gainNode = context.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(context.destination);
                    
                    oscillator.frequency.setValueAtTime(freq, context.currentTime);
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0.1, context.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.001, context.currentTime + 0.5);
                    
                    oscillator.start(context.currentTime);
                    oscillator.stop(context.currentTime + 0.5);
                }, index * 200);
            });
        });
    </script>
</body>
</html>
