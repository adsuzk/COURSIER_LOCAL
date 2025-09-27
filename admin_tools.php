<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>🛠️ Outils d'administration - Suzosky</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0; padding: 20px; min-height: 100vh;
        }
        .container { 
            max-width: 800px; margin: 0 auto;
            background: white; border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white; padding: 30px; text-align: center;
        }
        .header h1 { margin: 0; font-size: 2.5em; }
        .header p { margin: 10px 0 0 0; opacity: 0.9; }
        
        .tools-grid {
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .tool-card {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #f9f9f9, #fff);
        }
        
        .tool-card:hover {
            border-color: #4CAF50;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(76, 175, 80, 0.2);
        }
        
        .tool-icon {
            font-size: 3em;
            margin-bottom: 15px;
            display: block;
        }
        
        .tool-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        
        .tool-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .tool-button {
            display: inline-block;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .tool-button:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
            color: white;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px; height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-online { background: #4CAF50; }
        .status-offline { background: #f44336; }
        
        .footer {
            background: #f5f5f5;
            padding: 20px;
            text-align: center;
            color: #666;
            border-top: 1px solid #e0e0e0;
        }
        
        .server-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 10px;
            margin: 20px;
            border-left: 4px solid #2196F3;
        }
        
        .server-info h3 {
            margin: 0 0 10px 0;
            color: #1976D2;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛠️ Administration Suzosky</h1>
            <p>Outils de gestion et maintenance - Serveur LWS</p>
        </div>
        
        <div class="server-info">
            <h3>ℹ️ Informations serveur</h3>
            <div class="info-row">
                <span>Serveur:</span>
                <span><strong><?php echo $_SERVER['SERVER_NAME'] ?? 'N/A'; ?></strong></span>
            </div>
            <div class="info-row">
                <span>PHP Version:</span>
                <span><strong><?php echo PHP_VERSION; ?></strong></span>
            </div>
            <div class="info-row">
                <span>Date/Heure:</span>
                <span><strong><?php echo date('d/m/Y H:i:s'); ?></strong></span>
            </div>
            <div class="info-row">
                <span>Base de données:</span>
                <span>
                    <?php
                    try {
                        require_once 'config.php';
                        $dbConfig = $config['db']['production'];
                        $pdo = new PDO(
                            "mysql:host={$dbConfig['host']};port={$dbConfig['port']}", 
                            $dbConfig['user'], 
                            $dbConfig['password']
                        );
                        echo '<span class="status-indicator status-online"></span><strong>Connecté</strong>';
                    } catch (Exception $e) {
                        echo '<span class="status-indicator status-offline"></span><strong>Déconnecté</strong>';
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="tools-grid">
            <div class="tool-card">
                <span class="tool-icon">🗄️</span>
                <div class="tool-title">Création des bases de données</div>
                <div class="tool-description">
                    Analyse tous les fichiers SQL et crée automatiquement les bases de données manquantes sur le serveur LWS.
                </div>
                <a href="create_missing_databases.php" class="tool-button">
                    🚀 Exécuter
                </a>
            </div>
            
            <div class="tool-card">
                <span class="tool-icon">🔧</span>
                <div class="tool-title">Setup Database</div>
                <div class="tool-description">
                    Exécute le script de configuration initial de la base de données principale du système.
                </div>
                <a href="setup_database.php" class="tool-button">
                    ⚙️ Configurer
                </a>
            </div>
            
            <div class="tool-card">
                <span class="tool-icon">👨‍💼</span>
                <div class="tool-title">Administration</div>
                <div class="tool-description">
                    Accès au panneau d'administration principal pour gérer les commandes et les coursiers.
                </div>
                <a href="admin.php" class="tool-button">
                    🔐 Accéder
                </a>
            </div>
            
            <div class="tool-card">
                <span class="tool-icon">🏥</span>
                <div class="tool-title">Diagnostic système</div>
                <div class="tool-description">
                    Vérifie l'état de santé du système, les connexions et les services essentiels.
                </div>
                <a href="server_check.php" class="tool-button">
                    🩺 Diagnostiquer
                </a>
            </div>
        </div>
        
        <div class="footer">
            <p><strong>Suzosky Coursier System</strong> - Version 2025</p>
            <p>⚠️ Réservé à l'administration - Utilisation sécurisée uniquement</p>
        </div>
    </div>
</body>
</html>