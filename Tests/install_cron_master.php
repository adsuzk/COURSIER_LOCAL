<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation CRON Master - Coursier Suzosky</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .title {
            color: #333;
            font-size: 1.8em;
            margin: 0;
        }
        .subtitle {
            color: #666;
            font-size: 1.1em;
            margin-top: 5px;
        }
        .cron-command {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .step {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            position: relative;
        }
        .step-number {
            position: absolute;
            top: -15px;
            left: 20px;
            background: #007bff;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .step h3 {
            color: #007bff;
            margin-top: 0;
            padding-left: 20px;
        }
        .advantages {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .advantages h4 {
            color: #155724;
            margin-top: 0;
        }
        .advantages ul {
            margin: 0;
            color: #155724;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
        }
        .warning h4 {
            color: #856404;
            margin-top: 0;
        }
        .test-section {
            background: #e2e3e5;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: center;
        }
        .test-button {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
            transition: all 0.3s ease;
        }
        .test-button:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🚀</div>
            <h1 class="title">CRON Master - Installation Unique</h1>
            <p class="subtitle">Une seule tâche CRON pour tout gérer !</p>
        </div>

        <div class="advantages">
            <h4>✅ Avantages du CRON Master :</h4>
            <ul>
                <li><strong>Une seule configuration</strong> - Plus simple à installer</li>
                <li><strong>Gestion intelligente</strong> - Exécution selon les besoins</li>
                <li><strong>Logs centralisés</strong> - Surveillance unifiée</li>
                <li><strong>Performance optimisée</strong> - Moins de charge serveur</li>
                <li><strong>Maintenance facile</strong> - Un seul point de contrôle</li>
            </ul>
        </div>

        <div class="step">
            <div class="step-number">1</div>
            <h3>Commande CRON Unique à Ajouter</h3>
            <p>Copiez cette ligne dans votre crontab :</p>
            <div class="cron-command">
* * * * * /usr/bin/php /home/coursier/public_html/Scripts/Scripts\ cron/cron_master.php
            </div>
            <p><strong>Explication :</strong> Exécute toutes les 2 minutes, mais les tâches s'adaptent automatiquement selon leur fréquence nécessaire.</p>
        </div>

        <div class="step">
            <div class="step-number">2</div>
            <h3>Installation via SSH</h3>
            <p>Connectez-vous à votre serveur LWS et tapez :</p>
            <div class="cron-command">
crontab -e
            </div>
            <p>Puis ajoutez la ligne du CRON Master et sauvegardez (Ctrl+O puis Ctrl+X).</p>
        </div>

        <div class="step">
            <div class="step-number">3</div>
            <h3>Installation via Panel LWS</h3>
            <p>Dans votre interface d'administration LWS :</p>
            <ul>
                <li>Allez dans "Tâches CRON" ou "CRON Jobs"</li>
                <li>Cliquez sur "Ajouter une tâche"</li>
                <li>Fréquence : <strong>Toutes les 2 minutes</strong></li>
                <li>Commande : <code>/usr/bin/php /home/coursier/public_html/Tests/cron_master.php</code></li>
            </ul>
        </div>

        <div class="warning">
            <h4>⚠️ Chemin Important</h4>
            <p>Assurez-vous que le chemin <code>/home/coursier/public_html/</code> correspond à votre installation. 
            Adaptez-le si nécessaire selon votre configuration LWS.</p>
        </div>

        <div class="step">
            <div class="step-number">4</div>
            <h3>Tâches Automatiques Incluses</h3>
            <ul>
                <li><strong>Toutes les 2min :</strong> Assignation commandes, Surveillance temps réel</li>
                <li><strong>Toutes les 5min :</strong> MAJ statuts coursiers</li>
                <li><strong>Toutes les 15min :</strong> Nettoyage statuts</li>
                <li><strong>Toutes les heures :</strong> Sécurité FCM, Nettoyage tokens, Vérification système</li>
                <li><strong>Quotidien (2h) :</strong> Nettoyage BDD, Rotation logs</li>
            </ul>
        </div>

        <div class="test-section">
            <h3>🧪 Tester l'Installation</h3>
            <p>Une fois le CRON configuré, testez son fonctionnement :</p>
            <a href="../diagnostic_logs/cron_master.log" class="test-button" target="_blank">
                📋 Voir les Logs CRON
            </a>
            <a href="test_cron_lws.php" class="test-button" target="_blank">
                🔍 Test Système
            </a>
        </div>

        <div class="footer">
            <p>🚀 Coursier Suzosky - Système CRON Master v1.0</p>
            <p>Installation terminée ? Le système s'occupe du reste automatiquement !</p>
        </div>
    </div>
</body>
</html>