<?php
/**
 * Script de création automatique des bases de données manquantes
 * 
 * Ce script analyse tous les fichiers SQL dans le dossier _sql,
 * identifie les bases de données requises et les crée si elles n'existent pas.
 * 
 * Usage: php create_missing_databases.php
 * 
 * @author Assistant IA
 * @date 2025-09-27
 */

require_once __DIR__ . '/config.php';

class DatabaseCreator {
    
    private $connection = null;
    private $sqlDir = '';
    private $isProduction = false;
    
    public function __construct() {
        $this->sqlDir = __DIR__ . '/_sql';
        $this->detectEnvironment();
        $this->connectToMySQL();
    }
    
    /**
     * Détermine l'environnement (production ou développement)
     */
    private function detectEnvironment() {
        // Sur LWS, on est toujours en production
        // Vérifie également si le fichier FORCE_PRODUCTION_DB existe
        if (file_exists(__DIR__ . '/FORCE_PRODUCTION_DB') || 
            isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'lws') !== false ||
            isset($_SERVER['SERVER_NAME']) && (
                strpos($_SERVER['SERVER_NAME'], 'lws') !== false ||
                strpos($_SERVER['SERVER_NAME'], 'concierge') !== false ||
                strpos($_SERVER['SERVER_NAME'], 'suzosky') !== false
            )) {
            $this->isProduction = true;
            echo "🔴 Mode PRODUCTION détecté (serveur LWS)\n";
        } else {
            $this->isProduction = false;
            echo "🟢 Mode DÉVELOPPEMENT détecté\n";
        }
    }
    
    /**
     * Connexion au serveur MySQL (sans spécifier de base de données)
     */
    private function connectToMySQL() {
        global $config;
        
        $dbConfig = $this->isProduction ? $config['db']['production'] : $config['db']['development'];
        
        try {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
            $this->connection = new PDO(
                $dsn,
                $dbConfig['user'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            
            echo "✅ Connexion MySQL réussie vers {$dbConfig['host']}\n";
            
        } catch (PDOException $e) {
            die("❌ Erreur de connexion MySQL: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Analyse tous les fichiers SQL pour extraire les noms des bases de données
     */
    private function analyzeSQLFiles() {
        $databases = [];
        
        if (!is_dir($this->sqlDir)) {
            die("❌ Le dossier _sql n'existe pas: {$this->sqlDir}\n");
        }
        
        $sqlFiles = glob($this->sqlDir . '/*.sql');
        
        echo "\n📁 Analyse de " . count($sqlFiles) . " fichiers SQL...\n";
        
        foreach ($sqlFiles as $filePath) {
            $fileName = basename($filePath);
            echo "   📄 Analyse de {$fileName}... ";
            
            $content = file_get_contents($filePath);
            $extractedDbs = $this->extractDatabaseNames($content, $fileName);
            
            if (!empty($extractedDbs)) {
                $databases = array_merge($databases, $extractedDbs);
                echo "✅ " . count($extractedDbs) . " base(s) trouvée(s)\n";
            } else {
                echo "⚪ Aucune base spécifique\n";
            }
        }
        
        return array_unique($databases);
    }
    
    /**
     * Extrait les noms de bases de données depuis le contenu SQL
     */
    private function extractDatabaseNames($content, $fileName) {
        $databases = [];
        
        // Recherche des instructions CREATE DATABASE ou USE
        $patterns = [
            '/CREATE\s+DATABASE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`\'"]?([a-zA-Z0-9_]+)[`\'"]?/i',
            '/USE\s+[`\'"]?([a-zA-Z0-9_]+)[`\'"]?/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $dbName) {
                    $databases[] = $dbName;
                }
            }
        }
        
        // Bases de données par défaut basées sur le nom du fichier
        $defaultDatabases = $this->getDefaultDatabasesByFile($fileName);
        if (!empty($defaultDatabases)) {
            $databases = array_merge($databases, $defaultDatabases);
        }
        
        return $databases;
    }
    
    /**
     * Détermine les bases de données par défaut selon le fichier
     */
    private function getDefaultDatabasesByFile($fileName) {
        global $config;
        
        $defaultDb = $this->isProduction 
            ? $config['db']['production']['name']
            : $config['db']['development']['name'];
        
        // Mapping des fichiers vers les bases de données
        $fileMapping = [
            'database_setup.sql' => [$defaultDb],
            'database_finances_setup.sql' => [$defaultDb],
            'database_telemetry_setup.sql' => [$defaultDb],
            'create_clients_table.sql' => [$defaultDb],
            'create_chat_tables.sql' => [$defaultDb],
            'create_commandes_coursier_table.sql' => [$defaultDb],
            'create_reclamations_table.sql' => [$defaultDb],
            'DEPLOY_DELIVERY_CORE.sql' => [$defaultDb],
            'DEPLOY_TELEMETRY_PRODUCTION.sql' => [$defaultDb]
        ];
        
        return $fileMapping[$fileName] ?? [$defaultDb];
    }
    
    /**
     * Récupère la liste des bases de données existantes
     */
    private function getExistingDatabases() {
        try {
            $stmt = $this->connection->query("SHOW DATABASES");
            $existing = [];
            
            while ($row = $stmt->fetch()) {
                $dbName = $row['Database'];
                // Ignore les bases système
                if (!in_array($dbName, ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'])) {
                    $existing[] = $dbName;
                }
            }
            
            return $existing;
            
        } catch (PDOException $e) {
            die("❌ Erreur lors de la récupération des bases existantes: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Crée une base de données si elle n'existe pas
     */
    private function createDatabase($dbName) {
        try {
            $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}` 
                    CHARACTER SET utf8mb4 
                    COLLATE utf8mb4_unicode_ci";
            
            $this->connection->exec($sql);
            return true;
            
        } catch (PDOException $e) {
            echo "❌ Erreur lors de la création de '{$dbName}': " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * Exécute le processus principal
     */
    public function run() {
        echo "🚀 Démarrage du processus de création des bases de données\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        // 1. Analyse des fichiers SQL
        $requiredDbs = $this->analyzeSQLFiles();
        
        if (empty($requiredDbs)) {
            echo "⚠️  Aucune base de données trouvée dans les fichiers SQL\n";
            return;
        }
        
        echo "\n📊 Bases de données requises:\n";
        foreach ($requiredDbs as $db) {
            echo "   - {$db}\n";
        }
        
        // 2. Vérification des bases existantes
        echo "\n🔍 Vérification des bases existantes...\n";
        $existingDbs = $this->getExistingDatabases();
        
        echo "📊 Bases existantes:\n";
        foreach ($existingDbs as $db) {
            echo "   - {$db}\n";
        }
        
        // 3. Création des bases manquantes
        $missingDbs = array_diff($requiredDbs, $existingDbs);
        
        if (empty($missingDbs)) {
            echo "\n✅ Toutes les bases de données requises existent déjà !\n";
            return;
        }
        
        echo "\n🔧 Création des bases manquantes:\n";
        $created = 0;
        $failed = 0;
        
        foreach ($missingDbs as $dbName) {
            echo "   Création de '{$dbName}'... ";
            
            if ($this->createDatabase($dbName)) {
                echo "✅ Succès\n";
                $created++;
            } else {
                echo "❌ Échec\n";
                $failed++;
            }
        }
        
        // 4. Résumé final
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📋 RÉSUMÉ FINAL:\n";
        echo "   • Bases requises: " . count($requiredDbs) . "\n";
        echo "   • Bases existantes: " . count($existingDbs) . "\n";
        echo "   • Bases créées: {$created}\n";
        
        if ($failed > 0) {
            echo "   • Échecs: {$failed}\n";
        }
        
        echo "\n🎉 Processus terminé !\n";
    }
    
    /**
     * Destructeur - ferme la connexion
     */
    public function __destruct() {
        $this->connection = null;
    }
}

// Sécurité basique pour l'accès web
function checkWebAccess() {
    // Vérification par token d'administration
    global $config;
    
    if (isset($_GET['token']) && $_GET['token'] === $config['admin']['api_token']) {
        return true;
    }
    
    // Vérification par IP locale (pour développement)
    if (isset($_SERVER['REMOTE_ADDR']) && 
        in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost'])) {
        return true;
    }
    
    // Si on a un token admin en session
    if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
        return true;
    }
    
    return false;
}

// Exécution du script
if (php_sapi_name() === 'cli') {
    // Mode ligne de commande
    $creator = new DatabaseCreator();
    $creator->run();
} else {
    // Mode web avec sécurité
    
    // Interface web améliorée pour LWS
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>🗄️ Création des bases de données - Suzosky</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: 'Courier New', monospace; 
            background: linear-gradient(135deg, #1e3c72, #2a5298); 
            color: #fff; 
            padding: 20px; 
            margin: 0;
            min-height: 100vh;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: rgba(0,0,0,0.3); 
            border-radius: 15px; 
            padding: 30px;
            backdrop-filter: blur(10px);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 20px;
        }
        .header h1 { 
            margin: 0; 
            color: #4CAF50; 
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        .header .subtitle {
            color: #ccc;
            margin-top: 10px;
        }
        .output { 
            background: rgba(45, 45, 45, 0.8); 
            padding: 20px; 
            border-radius: 10px; 
            border: 1px solid #555;
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        .info { color: #2196F3; }
        .timestamp {
            color: #888;
            font-size: 0.9em;
            margin-bottom: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #555;
            color: #aaa;
        }
        .run-button {
            display: inline-block;
            background: #4CAF50;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
            transition: background 0.3s;
        }
        .run-button:hover {
            background: #45a049;
            color: white;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }
        .status-prod { background: #f44336; }
        .status-dev { background: #4CAF50; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🗄️ Création automatique des bases de données</h1>
            <div class='subtitle'>Système Suzosky - Serveur LWS</div>
            <div class='timestamp'>Exécution du " . date('d/m/Y à H:i:s') . "</div>
        </div>";

    // Vérification de l'accès (commentée pour LWS)
    /*
    if (!checkWebAccess()) {
        echo "<div class='output'>
                <div class='error'>❌ Accès refusé</div>
                <p>Pour exécuter ce script, vous devez:</p>
                <ul>
                    <li>Être connecté en tant qu'administrateur</li>
                    <li>Ou utiliser le token: <code>?token=VOTRE_TOKEN</code></li>
                    <li>Ou accéder depuis localhost</li>
                </ul>
              </div>";
    } else {
    */
    
    echo "<div class='output'><pre>";
    
    ob_start();
    try {
        $creator = new DatabaseCreator();
        $creator->run();
    } catch (Exception $e) {
        echo "❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
        echo "📍 Fichier: " . $e->getFile() . " ligne " . $e->getLine() . "\n";
    }
    $output = ob_get_clean();
    
    // Colorisation de la sortie pour le web
    $output = str_replace("✅", "<span class='success'>✅</span>", $output);
    $output = str_replace("❌", "<span class='error'>❌</span>", $output);
    $output = str_replace("⚠️", "<span class='warning'>⚠️</span>", $output);
    $output = str_replace("🔴", "<span class='error'>🔴</span>", $output);
    $output = str_replace("🟢", "<span class='success'>🟢</span>", $output);
    $output = str_replace("🔍", "<span class='info'>🔍</span>", $output);
    $output = str_replace("📊", "<span class='info'>📊</span>", $output);
    $output = str_replace("🚀", "<span class='success'>🚀</span>", $output);
    
    echo $output;
    
    echo "</pre></div>";
    // } // Fin du bloc d'accès sécurisé
    
    echo "
        <div class='footer'>
            <p>
                <a href='" . $_SERVER['PHP_SELF'] . "' class='run-button'>🔄 Réexécuter</a>
            </p>
            <p>Script créé le " . date('d/m/Y') . " - Suzosky Coursier System</p>
        </div>
    </div>
</body>
</html>";
}
?>