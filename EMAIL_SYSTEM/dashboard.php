<?php
/**
 * DASHBOARD DE MONITORING - SYSTÈME EMAIL ROBUSTE
 * 
 * Permet de surveiller:
 * - Statistiques d'envoi en temps réel
 * - Logs détaillés
 * - Taux de succès
 * - Détection des problèmes
 */

require_once __DIR__ . '/RobustEmailSystem.php';

// Sécurité basique
if (!isset($_GET['auth']) || $_GET['auth'] !== 'suzosky2025') {
    die('Accès non autorisé');
}

$emailSystem = new RobustEmailSystem();

// Récupérer les statistiques
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$statsToday = $emailSystem->getEmailStats($today);
$statsYesterday = $emailSystem->getEmailStats($yesterday);

// Lire les logs récents
$logFile = __DIR__ . "/logs/email_$today.log";
$recentLogs = [];
if (file_exists($logFile)) {
    $logs = file($logFile, FILE_IGNORE_NEW_LINES);
    $recentLogs = array_slice($logs, -20); // 20 dernières entrées
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Email - Suzosky</title>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f6fa;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { 
            background: linear-gradient(135deg, #D4A853, #B8941F);
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 30px;
            text-align: center;
        }
        .stats-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        .stat-card { 
            background: white; 
            padding: 25px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #D4A853;
        }
        .stat-number { 
            font-size: 2.5em; 
            font-weight: bold; 
            color: #2c3e50; 
            margin: 0;
        }
        .stat-label { 
            color: #7f8c8d; 
            font-size: 0.9em; 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
        .logs-section { 
            background: white; 
            border-radius: 10px; 
            padding: 25px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .log-entry { 
            font-family: 'Monaco', 'Consolas', monospace; 
            font-size: 12px; 
            padding: 8px; 
            border-bottom: 1px solid #ecf0f1; 
            margin: 0;
        }
        .log-success { background-color: #d5f4e6; }
        .log-error { background-color: #fadbd8; }
        .log-warning { background-color: #fcf3cf; }
        .refresh-btn {
            background: #D4A853;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        .progress-bar {
            background: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
            margin: 10px 0;
        }
        .progress-fill {
            background: #27ae60;
            height: 100%;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Dashboard Email Robuste</h1>
            <p>Suivi en temps réel du système de réinitialisation de mots de passe</p>
            <button class="refresh-btn" onclick="location.reload()">🔄 Actualiser</button>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number success"><?= $statsToday['success'] ?></div>
                <div class="stat-label">Emails envoyés aujourd'hui</div>
                <?php if($statsToday['total'] > 0): ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= ($statsToday['success']/$statsToday['total'])*100 ?>%"></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <div class="stat-number error"><?= $statsToday['failed'] ?></div>
                <div class="stat-label">Échecs aujourd'hui</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number warning"><?= $statsToday['retry'] ?></div>
                <div class="stat-label">Tentatives multiples</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?= $statsToday['total'] ?></div>
                <div class="stat-label">Total demandes</div>
                <?php if($statsYesterday['total'] > 0): ?>
                    <?php 
                    $evolution = (($statsToday['total'] - $statsYesterday['total']) / $statsYesterday['total']) * 100;
                    $evolutionClass = $evolution > 0 ? 'warning' : 'success';
                    ?>
                    <small class="<?= $evolutionClass ?>">
                        <?= $evolution > 0 ? '+' : '' ?><?= round($evolution, 1) ?>% vs hier
                    </small>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="logs-section">
            <h3>📋 Logs récents (<?= date('d/m/Y') ?>)</h3>
            
            <?php if (empty($recentLogs)): ?>
                <p style="color: #7f8c8d; font-style: italic;">Aucun log disponible pour aujourd'hui</p>
            <?php else: ?>
                <?php foreach (array_reverse($recentLogs) as $log): ?>
                    <?php
                    $logClass = '';
                    if (strpos($log, '[SUCCESS]') !== false) $logClass = 'log-success';
                    elseif (strpos($log, '[ERROR]') !== false || strpos($log, '[CRITICAL]') !== false) $logClass = 'log-error';
                    elseif (strpos($log, '[WARNING]') !== false) $logClass = 'log-warning';
                    ?>
                    <div class="log-entry <?= $logClass ?>"><?= htmlspecialchars($log) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: white; border-radius: 10px;">
            <h3>🔧 Actions rapides</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <button class="refresh-btn" onclick="testEmail()">
                    ✉️ Tester l'envoi
                </button>
                
                <button class="refresh-btn" onclick="viewFullLogs()">
                    📜 Logs complets
                </button>
                
                <button class="refresh-btn" onclick="clearOldLogs()">
                    🗑️ Nettoyer logs anciens
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function testEmail() {
            const email = prompt('Email de test:');
            if (email) {
                fetch('/reset_password_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=reset_password_request&email_or_phone=${encodeURIComponent(email)}`
                })
                .then(r => r.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) location.reload();
                })
                .catch(e => alert('Erreur: ' + e.message));
            }
        }
        
        function viewFullLogs() {
            window.open('?auth=suzosky2025&view=logs', '_blank');
        }
        
        function clearOldLogs() {
            if (confirm('Supprimer les logs de plus de 7 jours ?')) {
                // Implement cleanup logic
                alert('Fonction en cours de développement');
            }
        }
        
        // Auto-refresh toutes les 30 secondes
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>