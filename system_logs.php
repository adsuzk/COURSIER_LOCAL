<?php
// system_logs.php - Visualiseur de logs système
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 Logs Système - Suzosky</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1A1A2E;
            color: #fff;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            background: rgba(212, 168, 83, 0.1);
            padding: 20px;
            border-radius: 10px;
        }
        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #D4A853;
            margin-bottom: 10px;
        }
        .log-section {
            background: rgba(255, 255, 255, 0.05);
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid rgba(212, 168, 83, 0.3);
        }
        .log-header {
            background: rgba(212, 168, 83, 0.2);
            padding: 15px;
            font-weight: bold;
            border-bottom: 1px solid rgba(212, 168, 83, 0.3);
        }
        .log-content {
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.4;
        }
        .log-empty {
            color: #888;
            font-style: italic;
        }
        .refresh-btn {
            background: #D4A853;
            color: #1A1A2E;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 10px 5px;
        }
        .refresh-btn:hover {
            background: #B8941F;
        }
        .controls {
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">📊 LOGS SYSTÈME SUZOSKY</div>
        <p>Surveillance en temps réel de votre plateforme auto-pilotée</p>
        <div class="controls">
            <button class="refresh-btn" onclick="location.reload()">🔄 Actualiser</button>
            <button class="refresh-btn" onclick="autoRefresh()">⏰ Auto-refresh</button>
            <button class="refresh-btn" onclick="clearLogs()">🗑️ Vider les logs</button>
        </div>
    </div>

    <?php
    $logFiles = [
        'Système Auto-Piloté' => __DIR__ . '/diagnostic_logs/system_auto.log',
        'Migrations Base de Données' => __DIR__ . '/diagnostic_logs/db_migrations.log',
        'Opérations FCM' => __DIR__ . '/diagnostic_logs/fcm_operations.log',
        'Sécurité FCM' => __DIR__ . '/diagnostic_logs/fcm_security.log',
        'Activité Générale' => __DIR__ . '/diagnostic_logs/general.log'
    ];

    foreach ($logFiles as $title => $path) {
        echo '<div class="log-section">';
        echo '<div class="log-header">📄 ' . htmlspecialchars($title) . '</div>';
        echo '<div class="log-content">';
        
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if (!empty(trim($content))) {
                // Afficher les 50 dernières lignes
                $lines = explode("\n", trim($content));
                $recentLines = array_slice($lines, -50);
                
                foreach ($recentLines as $line) {
                    if (!empty(trim($line))) {
                        $line = htmlspecialchars($line);
                        
                        // Coloration selon le contenu
                        if (strpos($line, '✅') !== false || strpos($line, 'SUCCESS') !== false) {
                            echo '<div style="color: #2ECC71;">' . $line . '</div>';
                        } elseif (strpos($line, '❌') !== false || strpos($line, 'ERROR') !== false) {
                            echo '<div style="color: #E74C3C;">' . $line . '</div>';
                        } elseif (strpos($line, '⚠️') !== false || strpos($line, 'WARNING') !== false) {
                            echo '<div style="color: #F39C12;">' . $line . '</div>';
                        } else {
                            echo '<div>' . $line . '</div>';
                        }
                    }
                }
            } else {
                echo '<div class="log-empty">Fichier vide - aucune activité enregistrée</div>';
            }
        } else {
            echo '<div class="log-empty">Fichier non créé - en attente de la première exécution</div>';
        }
        
        echo '</div></div>';
    }
    ?>

    <div class="log-section">
        <div class="log-header">🎯 Actions Rapides</div>
        <div class="log-content">
            <p><strong>🔄 Forcer une exécution :</strong> Visitez simplement votre site - le système se déclenche automatiquement</p>
            <p><strong>📊 Vérifier le statut :</strong> <a href="system_status.php" style="color: #D4A853;">system_status.php</a></p>
            <p><strong>🔍 Vérification santé :</strong> <a href="system_health.php" style="color: #D4A853;">system_health.php</a></p>
            <p><strong>⚙️ Configuration :</strong> <a href="setup_cron_system.php" style="color: #D4A853;">setup_cron_system.php</a></p>
        </div>
    </div>

    <script>
        let autoRefreshInterval;
        
        function autoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                alert('Auto-refresh désactivé');
            } else {
                autoRefreshInterval = setInterval(() => {
                    location.reload();
                }, 30000); // 30 secondes
                alert('Auto-refresh activé (30s)');
            }
        }
        
        function clearLogs() {
            if (confirm('Voulez-vous vraiment vider tous les logs ?')) {
                fetch('?action=clear_logs')
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message);
                        location.reload();
                    })
                    .catch(error => {
                        alert('Erreur: ' + error.message);
                    });
            }
        }
    </script>
</body>
</html>

<?php
// Gestion du vidage des logs
if (isset($_GET['action']) && $_GET['action'] === 'clear_logs') {
    header('Content-Type: application/json');
    
    try {
        $cleared = 0;
        foreach ($logFiles as $title => $path) {
            if (file_exists($path)) {
                file_put_contents($path, '');
                $cleared++;
            }
        }
        
        echo json_encode(['success' => true, 'message' => "$cleared fichiers de logs vidés"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>