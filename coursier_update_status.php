<?php
// coursier_update_status.php - Interface simple pour simuler les mises à jour du coursier
require_once 'config.php';

// Récupérer les commandes actives
function getActiveOrders() {
    try {
        $pdo = new PDO(
            "mysql:host=127.0.0.1;dbname=coursier_prod;charset=utf8mb4",
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("
            SELECT id, code_commande, statut, lieu_depart, lieu_arrivee, 
                   coursier_id, created_at, updated_at
            FROM commandes 
            WHERE statut IN ('nouvelle', 'assignee', 'en_cours') 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Traitement des mises à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? '';
    $new_status = $_POST['status'] ?? '';
    
    if ($order_id && $new_status) {
        try {
            $pdo = new PDO(
                "mysql:host=127.0.0.1;dbname=coursier_prod;charset=utf8mb4",
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Mettre à jour la commande
            $updateFields = ['statut = ?', 'updated_at = NOW()'];
            $params = [$new_status];

            switch ($new_status) {
                case 'assignee':
                    $updateFields[] = 'assigned_at = NOW()';
                    break;
                case 'en_cours':
                    $updateFields[] = 'picked_up_at = NOW()';
                    break;
                case 'livree':
                    $updateFields[] = 'delivered_at = NOW()';
                    break;
            }

            $sql = "UPDATE commandes SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $params[] = $order_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo "<div style='color:green;margin:10px 0;'>✅ Statut mis à jour avec succès!</div>";
        } catch (Exception $e) {
            echo "<div style='color:red;margin:10px 0;'>❌ Erreur: " . $e->getMessage() . "</div>";
        }
    }
}

$orders = getActiveOrders();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coursier - Mise à jour des statuts</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 {
            color: #D4A853;
            text-align: center;
            margin-bottom: 30px;
        }
        .order-card {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .order-code {
            font-weight: bold;
            color: #D4A853;
            font-size: 1.1rem;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .status-nouvelle { background: rgba(59, 130, 246, 0.2); color: #3B82F6; }
        .status-assignee { background: rgba(245, 158, 11, 0.2); color: #F59E0B; }
        .status-en_cours { background: rgba(16, 185, 129, 0.2); color: #10B981; }
        .status-livree { background: rgba(34, 197, 94, 0.2); color: #22C55E; }
        
        .route-info {
            color: #ccc;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-assign { background: #F59E0B; color: white; }
        .btn-pickup { background: #10B981; color: white; }
        .btn-deliver { background: #22C55E; color: white; }
        .btn:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .empty-state {
            text-align: center;
            color: #ccc;
            font-style: italic;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚴‍♂️ Interface Coursier - Suivi des commandes</h1>
        
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                Aucune commande active pour le moment
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-code"><?= htmlspecialchars($order['code_commande']) ?></div>
                        <div class="status-badge status-<?= htmlspecialchars($order['statut']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $order['statut'])) ?>
                        </div>
                    </div>
                    
                    <div class="route-info">
                        <div><strong>De:</strong> <?= htmlspecialchars($order['lieu_depart']) ?></div>
                        <div><strong>Vers:</strong> <?= htmlspecialchars($order['lieu_arrivee']) ?></div>
                        <div><strong>Créée:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></div>
                    </div>
                    
                    <div class="actions">
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <?php if ($order['statut'] === 'nouvelle'): ?>
                                <button type="submit" name="status" value="assignee" class="btn btn-assign">
                                    ✋ Accepter la commande
                                </button>
                            <?php elseif ($order['statut'] === 'assignee'): ?>
                                <button type="submit" name="status" value="en_cours" class="btn btn-pickup">
                                    📦 Colis récupéré
                                </button>
                            <?php elseif ($order['statut'] === 'en_cours'): ?>
                                <button type="submit" name="status" value="livree" class="btn btn-deliver">
                                    ✅ Marquer comme livré
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div style="text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);">
            <a href="index.php" style="color:#D4A853;text-decoration:none;">← Retour à l'accueil</a>
        </div>
    </div>
    
    <script>
        // Auto-refresh toutes les 10 secondes
        setTimeout(() => {
            window.location.reload();
        }, 10000);
    </script>
</body>
</html>