<?php
// auto_cron_installer.php - Installation automatique des CRON via interface web
require_once __DIR__ . '/config.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🤖 Installation Automatique CRON - Suzosky</title>
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
        .command-box {
            background: rgba(0, 0, 0, 0.5);
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
            border: 1px solid rgba(212, 168, 83, 0.3);
            overflow-x: auto;
        }
        .copy-btn {
            background: linear-gradient(135deg, #D4A853 0%, #B8941F 100%);
            color: #1A1A2E;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px 0;
        }
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 168, 83, 0.4);
        }
        .success {
            background: rgba(39, 174, 96, 0.2);
            border-color: #27AE60;
        }
        .warning {
            background: rgba(255, 193, 7, 0.2);
            border-color: #FFC107;
        }
        .error {
            background: rgba(231, 76, 60, 0.2);
            border-color: #E74C3C;
        }
        .auto-test {
            text-align: center;
            margin: 20px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🤖 SUZOSKY AUTO-CRON</div>
            <p>Installation automatique des tâches CRON - ZÉRO MANIPULATION !</p>
        </div>

        <div class="step success">
            <h3>✅ ÉTAPE 1 : Test automatique du système</h3>
            <p>Cliquez sur ce bouton pour tester si votre système peut exécuter les tâches automatiquement :</p>
            <div class="auto-test">
                <button class="big-btn" onclick="testSystem()">🚀 TESTER LE SYSTÈME MAINTENANT</button>
            </div>
            <div id="test-results" class="status"></div>
        </div>

        <div class="step">
            <h3>🔄 ÉTAPE 2 : CRON Automatique via Web (SOLUTION MAGIQUE)</h3>
            <p>Si les vrais CRON ne peuvent pas être configurés, notre système va se lancer automatiquement à chaque visite !</p>
            <div class="auto-test">
                <button class="big-btn" onclick="activateWebCron()">🎯 ACTIVER CRON AUTOMATIQUE</button>
            </div>
            <div id="webcron-status" class="status"></div>
        </div>

        <div class="step warning">
            <h3>⚙️ ÉTAPE 3 : Configuration LWS (si vraiment nécessaire)</h3>
            <p>Seulement si les méthodes automatiques ne marchent pas. Voici les commandes à copier-coller :</p>
            
            <h4>🔍 D'abord, trouvez votre chemin :</h4>
            <div class="command-box" id="path-info">
                Chemin détecté : <?= __DIR__ ?>
            </div>
            
            <h4>📋 Puis copiez ces 4 lignes dans LWS :</h4>
            <div class="command-box" id="cron-commands">
0 2 * * * /usr/bin/php <?= str_replace('\\', '/', __DIR__) ?>/Scripts/Scripts\ cron/automated_db_migration.php
0 1 * * * /usr/bin/php <?= str_replace('\\', '/', __DIR__) ?>/Scripts/Scripts\ cron/fcm_token_security.php
0 */6 * * * /usr/bin/php <?= str_replace('\\', '/', __DIR__) ?>/Scripts/Scripts\ cron/fcm_auto_cleanup.php
30 2 * * * /usr/bin/php <?= str_replace('\\', '/', __DIR__) ?>/Scripts/Scripts\ cron/fcm_daily_diagnostic.php
            </div>
            <button class="copy-btn" onclick="copyToClipboard('cron-commands')">📋 COPIER TOUT</button>
        </div>

        <div class="step success">
            <h3>🎉 ÉTAPE 4 : Vérification automatique</h3>
            <p>Une fois activé, vérifiez que tout fonctionne :</p>
            <div class="auto-test">
                <button class="big-btn" onclick="checkStatus()">🔍 VÉRIFIER LE STATUT</button>
            </div>
            <div id="status-results" class="status"></div>
        </div>
    </div>

    <script>
        function testSystem() {
            const div = document.getElementById('test-results');
            div.style.display = 'block';
            div.className = 'status warning';
            div.innerHTML = '🔄 Test en cours...';
            
            fetch('test_cron_lws.php')
                .then(response => response.text())
                .then(data => {
                    div.className = 'status success';
                    div.innerHTML = '<h4>✅ Résultats du test :</h4><pre style="font-size:12px;overflow:auto;max-height:300px;">' + data + '</pre>';
                })
                .catch(error => {
                    div.className = 'status error';
                    div.innerHTML = '❌ Erreur lors du test : ' + error.message;
                });
        }

        function activateWebCron() {
            const div = document.getElementById('webcron-status');
            div.style.display = 'block';
            div.className = 'status warning';
            div.innerHTML = '🔄 Activation du CRON automatique...';
            
            fetch('?action=activate_webcron')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        div.className = 'status success';
                        div.innerHTML = '🎉 CRON automatique activé ! Les tâches vont maintenant s\'exécuter automatiquement à chaque visite.';
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
            
            fetch('diagnostic_coursiers_disponibilite.php')
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

        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent || element.innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('✅ Copié dans le presse-papier !');
            });
        }
    </script>
</body>
</html>

<?php
// Gestion des actions AJAX
if (isset($_GET['action']) && $_GET['action'] === 'activate_webcron') {
    header('Content-Type: application/json');
    
    try {
        // Créer un fichier pour activer le CRON automatique
        $webCronFile = __DIR__ . '/diagnostic_logs/webcron_active.flag';
        $success = file_put_contents($webCronFile, date('Y-m-d H:i:s') . " - CRON automatique activé\n", FILE_APPEND);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'CRON automatique activé']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de créer le fichier de configuration']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>