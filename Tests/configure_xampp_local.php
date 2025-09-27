<?php
/**
 * CONFIGURATEUR XAMPP LOCAL
 * Configure automatiquement coursier_local pour XAMPP
 */

echo "🔧 CONFIGURATION XAMPP LOCAL\n";
echo "============================\n\n";

$configFile = __DIR__ . '/config.php';
$backupFile = __DIR__ . '/config_production_backup.php';

// 1. Sauvegarder la config production
if (file_exists($configFile)) {
    copy($configFile, $backupFile);
    echo "✅ Config production sauvegardée\n";
}

// 2. Nouvelle configuration XAMPP
$localConfig = '<?php
/**
 * CONFIGURATION LOCALE XAMPP
 * Version développement local
 */

// Configuration base de données XAMPP
define("DB_HOST", "localhost");
define("DB_NAME", "coursier_local"); 
define("DB_USER", "root");
define("DB_PASSWORD", ""); // Vide par défaut sur XAMPP

// Configuration environnement
define("ENVIRONMENT", "local");
define("DEBUG_MODE", true);

// Configuration serveur local
define("BASE_URL", "http://localhost/coursier_local");
define("SITE_NAME", "Coursier Local - XAMPP");

// Configuration email locale (pour tests)
define("SMTP_HOST", "localhost");
define("SMTP_PORT", 25);
define("FROM_EMAIL", "noreply@localhost");
define("FROM_NAME", "Coursier Local");

// Fonction de connexion PDO
function getPDO() {
    try {
        // Auto-création de la base si elle n\'existe pas
        $pdo_check = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASSWORD);
        $pdo_check->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASSWORD,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        if (DEBUG_MODE) {
            die("Erreur connexion DB: " . $e->getMessage());
        } else {
            die("Erreur de connexion à la base de données");
        }
    }
}

// Configuration des chemins
define("ROOT_PATH", __DIR__);
define("UPLOAD_PATH", ROOT_PATH . "/uploads");

// Configuration de développement
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set("display_errors", 1);
    ini_set("log_errors", 1);
    ini_set("error_log", ROOT_PATH . "/debug_local.log");
}

// Headers de sécurité basiques
if (!headers_sent()) {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-Content-Type-Options: nosniff");
}

// Session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    ini_set("session.cookie_httponly", 1);
    ini_set("session.use_strict_mode", 1);
    session_start();
}

echo "<!-- Configuration XAMPP locale chargée -->";
?>';

file_put_contents($configFile, $localConfig);
echo "✅ Configuration XAMPP créée\n";

// 3. Créer un script de setup de base de données
$setupSQL = '-- SETUP BASE DE DONNÉES LOCALE XAMPP

CREATE DATABASE IF NOT EXISTS coursier_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE coursier_local;

-- Table clients_particuliers
CREATE TABLE IF NOT EXISTS clients_particuliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    mail VARCHAR(255) UNIQUE NOT NULL,
    telephone VARCHAR(20),
    mot_de_passe VARCHAR(255) NOT NULL,
    reset_token VARCHAR(64) NULL,
    reset_expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (mail),
    INDEX idx_reset_token (reset_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table email_logs pour le système email
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body TEXT,
    status ENUM("pending", "sent", "failed", "bounced") DEFAULT "pending",
    error_message TEXT,
    tracking_id VARCHAR(32) UNIQUE,
    opened_at DATETIME NULL,
    clicked_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    INDEX idx_recipient (recipient_email),
    INDEX idx_status (status),
    INDEX idx_tracking (tracking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insérer un utilisateur de test
INSERT IGNORE INTO clients_particuliers (nom, prenom, mail, telephone, mot_de_passe) 
VALUES ("Test", "User", "test@localhost.com", "0123456789", "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi");

-- Afficher le résultat
SELECT "✅ Base de données locale configurée!" as status;
SELECT "👤 Utilisateur test créé: test@localhost.com / password" as info;';

file_put_contents(__DIR__ . '/setup_local_db.sql', $setupSQL);
echo "✅ Script SQL créé: setup_local_db.sql\n";

// 4. Créer un fichier de test de connexion
$testConnection = '<?php
require_once "config.php";

echo "<h2>🧪 Test Configuration XAMPP</h2>";

try {
    $pdo = getPDO();
    echo "<p style=\"color:green\">✅ Connexion base de données OK</p>";
    
    // Test une requête simple
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients_particuliers");
    $result = $stmt->fetch();
    echo "<p>📊 Nombre d\'utilisateurs: " . $result["count"] . "</p>";
    
    echo "<p style=\"color:blue\">🚀 Configuration locale fonctionnelle !</p>";
    echo "<p><a href=\"index.php\">Aller à l\'accueil</a> | <a href=\"admin.php\">Panel admin</a></p>";
    
} catch (Exception $e) {
    echo "<p style=\"color:red\">❌ Erreur: " . $e->getMessage() . "</p>";
    echo "<p>💡 Assurez-vous que XAMPP MySQL est démarré</p>";
}
?>';

file_put_contents(__DIR__ . '/test_local.php', $testConnection);
echo "✅ Fichier test créé: test_local.php\n";

echo "\n🎊 CONFIGURATION XAMPP TERMINÉE !\n";
echo "==================================\n";
echo "📁 Dossier: C:\\xampp\\htdocs\\coursier_local\n";
echo "🌐 URL locale: http://localhost/coursier_local\n";
echo "🧪 Test config: http://localhost/coursier_local/test_local.php\n";
echo "\n📋 ÉTAPES SUIVANTES:\n";
echo "1. Démarrez XAMPP (Apache + MySQL)\n";
echo "2. Allez sur: http://localhost/coursier_local/test_local.php\n";
echo "3. Si erreur DB, importez: setup_local_db.sql dans phpMyAdmin\n";
echo "4. Testez l\'application: http://localhost/coursier_local\n";
?>