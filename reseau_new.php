<?php
/**
 * RÉSEAU SUZOSKY - Interface simple de monitoring
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

// Fonction simple de test API
function testApiSimple($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réseau Suzosky</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1A1A2E;
            color: white;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #D4A853;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .api-card {
            background: rgba(255,255,255,0.1);
            border: 2px solid #D4A853;
            border-radius: 10px;
            padding: 20px;
            color: white;
        }
        
        .api-name {
            color: #D4A853;
            font-weight: bold;
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .api-description {
            margin: 10px 0;
            line-height: 1.5;
        }
        
        .api-status {
            text-align: right;
            font-weight: bold;
        }
        
        .status-online { color: #27AE60; }
        .status-offline { color: #E94560; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: rgba(212,168,83,0.1);
            border: 1px solid #D4A853;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #D4A853;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Réseau Système Suzosky</h1>
        
        <!-- Statistiques rapides -->
        <div class="stats">
            <?php
            // Compter quelques éléments de base
            try {
                $coursiers = $pdo->query("SELECT COUNT(*) FROM agents_suzosky")->fetchColumn();
                $commandes = $pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn();
                $clients = $pdo->query("SELECT COUNT(*) FROM clients_particuliers")->fetchColumn();
            } catch (Exception $e) {
                $coursiers = $commandes = $clients = 0;
            }
            ?>
            <div class="stat-box">
                <div class="stat-number"><?= $coursiers ?></div>
                <div class="stat-label">Coursiers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $commandes ?></div>
                <div class="stat-label">Commandes</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $clients ?></div>
                <div class="stat-label">Clients</div>
            </div>
        </div>
        
        <!-- APIs principales -->
        <h2 style="color: #D4A853; margin: 30px 0;">APIs Principales</h2>
        <div class="api-grid">
            <?php
            // Liste simple des APIs principales
            $apis = [
                [
                    'name' => 'API Authentification Coursier',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/agent_auth.php',
                    'description' => 'Connexion des coursiers à l\'application mobile'
                ],
                [
                    'name' => 'API Données Coursier',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/get_coursier_data.php',
                    'description' => 'Récupération des informations du coursier (profil, solde, statistiques)'
                ],
                [
                    'name' => 'API Commandes Coursier',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/get_coursier_orders.php',
                    'description' => 'Liste des commandes assignées au coursier'
                ],
                [
                    'name' => 'API Soumission Commande',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/submit_order.php',
                    'description' => 'Création de nouvelles commandes par les clients'
                ],
                [
                    'name' => 'API Statut Commande',
                    'url' => 'http://localhost/COURSIER_LOCAL/api/update_order_status.php',
                    'description' => 'Mise à jour du statut des commandes'
                ]
            ];
            
            foreach ($apis as $api):
                $status = testApiSimple($api['url']);
                $isOnline = $status == 200;
            ?>
                <div class="api-card">
                    <div class="api-name"><?= htmlspecialchars($api['name']) ?></div>
                    <div class="api-description"><?= htmlspecialchars($api['description']) ?></div>
                    <div class="api-status">
                        <span class="<?= $isOnline ? 'status-online' : 'status-offline' ?>">
                            <?= $isOnline ? 'ONLINE' : 'OFFLINE' ?> (<?= $status ?>)
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <p style="text-align: center; color: #D4A853; margin-top: 30px;">
            Dernière vérification: <?= date('d/m/Y H:i:s') ?>
        </p>
    </div>
</body>
</html>