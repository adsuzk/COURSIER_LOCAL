<?php
// setup_cron_system.php - Configuration automatique du système CRON
require_once __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🤖 Configuration Automatique Système - Suzosky</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
            color: white;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.1);
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        .step {
            background: rgba(212, 168, 83, 0.1);
            padding: 20px;
            margin: 15px 0;
            border-radius: 10px;
            border-left: 4px solid #D4A853;
        }
        .step h3 {
            margin: 0 0 10px 0;
            color: #D4A853;
        }
        .big-btn {
            background: linear-gradient(135deg, #27AE60 0%, #2ECC71 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin: 10px;
            display: inline-block;
            text-decoration: none;
        }
        .big-btn:hover {
            transform: scale(1.05);
        }
        .status {
            display: none;
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
        }
        .success { background: rgba(39, 174, 96, 0.2); border: 2px solid #27AE60; }
        .warning { background: rgba(255, 193, 7, 0.2); border: 2px solid #FFC107; }
        .error { background: rgba(231, 76, 60, 0.2); border: 2px solid #E74C3C; }
        .auto-center { text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🤖 SUZOSKY AUTO-CONFIG</div>
            <p>Configuration automatique du système - ZÉRO MANIPULATION !</p>
        </div>

        <div class="step success">
            <h3>✅ ÉTAPE 1 : Vérification du système</h3>
            <p>Vérifions que tous les composants sont en place :</p>
            <div class="auto-center">
                <button class="big-btn" onclick="checkSystem()">🔍 VÉRIFIER LE SYSTÈME</button>
            </div>
            <div id="system-results" class="status"></div>
        </div>

        <div class="step">
            <h3>🚀 ÉTAPE 2 : Activation automatique (UN SEUL CLIC)</h3>
            <p>Activez le système automatique qui va gérer tout sans votre intervention :</p>
            <div class="auto-center">
                <button class="big-btn" onclick="activateSystem()">🎯 ACTIVER LE SYSTÈME AUTOMATIQUE</button>
            </div>
            <div id="activation-results" class="status"></div>
        </div>

        <div class="step success">
            <h3>📊 ÉTAPE 3 : Vérification du fonctionnement</h3>
            <p>Vérifiez que le système fonctionne correctement :</p>
            <div class="auto-center">
                <button class="big-btn" onclick="checkStatus()">✅ TESTER LE FONCTIONNEMENT</button>
            </div>
            <div id="status-results" class="status"></div>
        </div>

        <div class="step warning">
            <h3>📋 ÉTAPE 4 : Surveillance des logs</h3>
            <p>Une fois activé, vous pouvez surveiller l'activité ici :</p>
            <div class="auto-center">
                <a href="system_logs.php" class="big-btn">📄 VOIR LES LOGS</a>
                <button class="big-btn" onclick="showLogLocation()">📍 EMPLACEMENT LOGS</button>
            </div>
            <div id="log-info" class="status"></div>
        </div>
    </div>

    <script>
        function checkSystem() {
            const div = document.getElementById('system-results');
            div.style.display = 'block';
            div.className = 'status warning';
            div.innerHTML = '🔄 Vérification en cours...';
            
            fetch('system_health.php')
                .then(response => response.text())
                .then(data => {
                    div.className = 'status success';
                    div.innerHTML = '<h4>✅ Résultats de la vérification :</h4><pre style="font-size:12px;overflow:auto;max-height:300px;">' + data + '</pre>';
                })
                .catch(error => {
                    div.className = 'status error';
                    div.innerHTML = '❌ Erreur lors de la vérification : ' + error.message;
                });
        }

        function activateSystem() {
            const div = document.getElementById('activation-results');
            div.style.display = 'block';
            div.className = 'status warning';
            div.innerHTML = '🔄 Activation du système automatique...';
            
            fetch('?action=activate_system')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        div.className = 'status success';
                        div.innerHTML = '🎉 <strong>SYSTÈME ACTIVÉ !</strong><br>' + 
                                       'Votre plateforme fonctionne maintenant en mode automatique.<br>' +
                                       'Les tâches de maintenance se lancent automatiquement à chaque visite.';
                    } else {
                        div.className = 'status error';
                        div.innerHTML = '❌ Erreur : ' + data.message;
                    }
                })
                .catch(error => {
                    div.className = 'status error';
                    div.innerHTML = '❌ Erreur lors de l\'activation : ' + error.message;
                });
        }

        function checkStatus() {
            const div = document.getElementById('status-results');
            div.style.display = 'block';
            div.className = 'status warning';
            div.innerHTML = '🔄 Vérification du statut...';
            
            fetch('system_status.php')
                .then(response => response.text())
                .then(data => {
                    div.className = 'status success';
                    div.innerHTML = '<h4>📊 Statut du système :</h4><pre style="font-size:12px;overflow:auto;max-height:300px;">' + data + '</pre>';
                })
                .catch(error => {
                    div.className = 'status error';
                    div.innerHTML = '❌ Erreur lors de la vérification : ' + error.message;
                });
        }

        function showLogLocation() {
            const div = document.getElementById('log-info');
            div.style.display = 'block';
            div.className = 'status success';
            div.innerHTML = '<h4>📍 Emplacements des logs :</h4>' +
                           '<p><strong>Logs système :</strong> diagnostic_logs/system_auto.log</p>' +
                           '<p><strong>Logs migrations :</strong> diagnostic_logs/db_migrations.log</p>' +
                           '<p><strong>Logs FCM :</strong> diagnostic_logs/fcm_operations.log</p>' +
                           '<p>Ces fichiers se créent automatiquement une fois le système activé.</p>';
        }
    </script>
</body>
</html>

<?php
// Gestion des actions AJAX
if (isset($_GET['action']) && $_GET['action'] === 'activate_system') {
    header('Content-Type: application/json');
    
    try {
        // Créer le répertoire de logs s'il n'existe pas
        $logDir = __DIR__ . '/diagnostic_logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Créer le fichier d'activation
        $systemActiveFile = $logDir . '/system_auto_active.flag';
        $activationTime = date('Y-m-d H:i:s');
        $success = file_put_contents($systemActiveFile, 
            "$activationTime - Système automatique activé\n" .
            "Mode: Auto-pilot complet\n" .
            "Status: OPERATIONAL\n", 
            FILE_APPEND
        );
        
        // Créer le fichier de dernière exécution
        $lastRunFile = $logDir . '/system_last_run.txt';
        file_put_contents($lastRunFile, time());
        
        // Log d'activation
        $logFile = $logDir . '/system_auto.log';
        file_put_contents($logFile, 
            "$activationTime - SYSTÈME AUTO-PILOTÉ ACTIVÉ\n" .
            "Composants actifs: FCM Security, DB Migration, Cleanup\n" .
            "Fréquence: Automatique selon trafic\n" .
            "Status: ✅ OPÉRATIONNEL\n\n", 
            FILE_APPEND
        );
        
        if ($success) {
            echo json_encode([
                'success' => true, 
                'message' => 'Système auto-piloté activé avec succès',
                'activation_time' => $activationTime
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de créer les fichiers de configuration']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>