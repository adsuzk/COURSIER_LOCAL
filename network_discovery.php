<?php
/**
 * DÉCOUVERTE AUTOMATIQUE DU RÉSEAU SUZOSKY
 * Détecte automatiquement tous les composants du système
 */

require_once __DIR__ . '/config.php';

class NetworkDiscovery {
    private $pdo;
    private $baseUrl;
    private $discoveries = [];
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->baseUrl = 'http://localhost/COURSIER_LOCAL';
    }
    
    /**
     * Scanner automatique complet du réseau
     */
    public function discoverAllNetworkComponents(): array {
        echo "🔍 DÉCOUVERTE AUTOMATIQUE DU RÉSEAU SUZOSKY\n";
        echo "=" . str_repeat("=", 60) . "\n";
        
        $this->discoveries = [
            'apis' => [],
            'admin_sections' => [],
            'database_tables' => [],
            'files_system' => [],
            'services' => [],
            'monitoring' => []
        ];
        
        // 1. Découverte des APIs
        $this->discoverAPIs();
        
        // 2. Découverte des sections admin
        $this->discoverAdminSections();
        
        // 3. Découverte des tables de base de données
        $this->discoverDatabaseTables();
        
        // 4. Découverte des fichiers système critiques
        $this->discoverSystemFiles();
        
        // 5. Découverte des services et processus
        $this->discoverServices();
        
        // 6. Découverte des outils de monitoring
        $this->discoverMonitoringTools();
        
        return $this->discoveries;
    }
    
    /**
     * Découvrir toutes les APIs automatiquement
     */
    private function discoverAPIs(): void {
        echo "\n🔌 1. DÉCOUVERTE DES APIs\n";
        
        $apiDir = __DIR__ . '/api';
        if (is_dir($apiDir)) {
            $files = glob($apiDir . '/*.php');
            foreach ($files as $file) {
                $filename = basename($file);
                $apiName = str_replace('.php', '', $filename);
                
                // Analyser le fichier pour extraire la description
                $description = $this->extractApiDescription($file);
                $methods = $this->detectApiMethods($file);
                $purpose = $this->detectApiPurpose($file);
                
                $this->discoveries['apis'][] = [
                    'name' => "API " . ucwords(str_replace('_', ' ', $apiName)),
                    'url' => $this->baseUrl . '/api/' . $filename,
                    'file' => $filename,
                    'description' => $description,
                    'purpose' => $purpose,
                    'methods' => $methods,
                    'auto_discovered' => true
                ];
                
                echo "   📡 Découvert: $filename - $description\n";
            }
        }
        
        // Découvrir aussi les APIs dans d'autres dossiers
        $otherApiFiles = [
            'mobile_sync_api.php' => 'Synchronisation mobile complète',
            'attribution_intelligente.php' => 'Attribution automatique des commandes',
            'fcm_token_security.php' => 'Sécurité des tokens FCM'
        ];
        
        foreach ($otherApiFiles as $file => $desc) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $this->discoveries['apis'][] = [
                    'name' => "Service " . ucwords(str_replace('_', ' ', str_replace('.php', '', $file))),
                    'url' => $this->baseUrl . '/' . $file,
                    'file' => $file,
                    'description' => $desc,
                    'purpose' => 'Système interne',
                    'methods' => ['GET', 'POST'],
                    'auto_discovered' => true
                ];
                
                echo "   🔧 Découvert: $file - $desc\n";
            }
        }
    }
    
    /**
     * Découvrir les sections admin automatiquement
     */
    private function discoverAdminSections(): void {
        echo "\n🎛️ 2. DÉCOUVERTE DES SECTIONS ADMIN\n";
        
        $adminDir = __DIR__ . '/admin';
        if (is_dir($adminDir)) {
            // Scanner les sections principales
            $sections = ['dashboard', 'commandes', 'finances', 'coursiers', 'reseau', 'logs'];
            
            foreach ($sections as $section) {
                $sectionFile = $adminDir . '/' . $section . '.php';
                if (file_exists($sectionFile)) {
                    $description = $this->extractSectionDescription($sectionFile);
                } else {
                    $description = "Section $section (détection dynamique)";
                }
                
                $this->discoveries['admin_sections'][] = [
                    'name' => "Admin " . ucfirst($section),
                    'url' => $this->baseUrl . '/admin.php?section=' . $section,
                    'section' => $section,
                    'description' => $description,
                    'auto_discovered' => true
                ];
                
                echo "   🎯 Découvert: admin/$section - $description\n";
            }
            
            // Scanner les sous-dossiers
            $subDirs = glob($adminDir . '/sections_*', GLOB_ONLYDIR);
            foreach ($subDirs as $subDir) {
                $dirName = basename($subDir);
                $files = glob($subDir . '/*.php');
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    $this->discoveries['admin_sections'][] = [
                        'name' => "Module " . str_replace('.php', '', $filename),
                        'path' => str_replace(__DIR__ . '/', '', $file),
                        'description' => "Module admin: " . str_replace('_', ' ', $dirName),
                        'auto_discovered' => true
                    ];
                }
                
                echo "   📁 Découvert: $dirName (" . count($files) . " modules)\n";
            }
        }
    }
    
    /**
     * Découvrir les tables de base de données
     */
    private function discoverDatabaseTables(): void {
        echo "\n🗄️ 3. DÉCOUVERTE DES TABLES BDD\n";
        
        try {
            $stmt = $this->pdo->query('SHOW TABLES');
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Obtenir des infos sur la table
                $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
                $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table`");
                $rowCount = $stmt->fetchColumn();
                
                // Détecter le type de table
                $tableType = $this->detectTableType($table, $createTable['Create Table']);
                
                $this->discoveries['database_tables'][] = [
                    'name' => $table,
                    'type' => $tableType,
                    'row_count' => $rowCount,
                    'description' => $this->getTableDescription($table, $tableType),
                    'auto_discovered' => true
                ];
                
                echo "   📊 Découvert: $table ($tableType, $rowCount lignes)\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Erreur découverte BDD: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Découvrir les fichiers système critiques
     */
    private function discoverSystemFiles(): void {
        echo "\n📂 4. DÉCOUVERTE DES FICHIERS SYSTÈME\n";
        
        $criticalFiles = [
            'config.php' => 'Configuration principale du système',
            'lib/coursier_presence.php' => 'Gestion de la présence des coursiers',
            'lib/SystemSync.php' => 'Synchronisation système',
            'fcm_manager.php' => 'Gestionnaire des notifications FCM',
            'index.php' => 'Interface publique principale',
            'auth.php' => 'Authentification admin',
            'logout.php' => 'Déconnexion sécurisée'
        ];
        
        foreach ($criticalFiles as $file => $description) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $fileSize = filesize(__DIR__ . '/' . $file);
                $lastModified = date('Y-m-d H:i:s', filemtime(__DIR__ . '/' . $file));
                
                $this->discoveries['files_system'][] = [
                    'name' => $file,
                    'description' => $description,
                    'size' => $fileSize,
                    'last_modified' => $lastModified,
                    'status' => 'active',
                    'auto_discovered' => true
                ];
                
                echo "   📄 Découvert: $file (" . round($fileSize/1024, 1) . " KB)\n";
            }
        }
        
        // Scanner les dossiers de logs
        $logDirs = ['logs', 'diagnostic_logs'];
        foreach ($logDirs as $logDir) {
            if (is_dir(__DIR__ . '/' . $logDir)) {
                $logFiles = glob(__DIR__ . '/' . $logDir . '/*.{log,json}', GLOB_BRACE);
                foreach ($logFiles as $logFile) {
                    $filename = str_replace(__DIR__ . '/', '', $logFile);
                    $this->discoveries['files_system'][] = [
                        'name' => $filename,
                        'description' => 'Fichier de log système',
                        'size' => filesize($logFile),
                        'last_modified' => date('Y-m-d H:i:s', filemtime($logFile)),
                        'status' => 'log',
                        'auto_discovered' => true
                    ];
                }
                echo "   📋 Découvert: $logDir (" . count($logFiles) . " fichiers de log)\n";
            }
        }
    }
    
    /**
     * Découvrir les services et processus
     */
    private function discoverServices(): void {
        echo "\n⚙️ 5. DÉCOUVERTE DES SERVICES\n";
        
        // Services identifiés par la présence de classes ou fonctions
        $services = [
            'FCMManager' => ['fcm_manager.php', 'Gestionnaire des notifications push'],
            'FCMTokenSecurity' => ['fcm_token_security.php', 'Sécurité des tokens FCM'],
            'SecureOrderAssignment' => ['secure_order_assignment.php', 'Assignation sécurisée des commandes'],
            'NetworkDiscovery' => [__FILE__, 'Découverte automatique du réseau'],
        ];
        
        foreach ($services as $serviceName => $info) {
            list($file, $description) = $info;
            if (file_exists($file) || file_exists(__DIR__ . '/' . $file)) {
                $this->discoveries['services'][] = [
                    'name' => $serviceName,
                    'file' => $file,
                    'description' => $description,
                    'status' => $this->checkServiceStatus($serviceName),
                    'auto_discovered' => true
                ];
                
                echo "   🔧 Découvert: $serviceName - $description\n";
            }
        }
    }
    
    /**
     * Découvrir les outils de monitoring
     */
    private function discoverMonitoringTools(): void {
        echo "\n📈 6. DÉCOUVERTE DES OUTILS DE MONITORING\n";
        
        $monitoringFiles = glob(__DIR__ . '/diagnostic_*.php');
        $monitoringFiles = array_merge($monitoringFiles, glob(__DIR__ . '/test_*.php'));
        $monitoringFiles = array_merge($monitoringFiles, glob(__DIR__ . '/surveillance_*.php'));
        
        foreach ($monitoringFiles as $file) {
            $filename = basename($file);
            $purpose = $this->detectMonitoringPurpose($filename);
            
            $this->discoveries['monitoring'][] = [
                'name' => str_replace('.php', '', $filename),
                'file' => $filename,
                'description' => $purpose,
                'url' => $this->baseUrl . '/' . $filename,
                'auto_discovered' => true
            ];
            
            echo "   📊 Découvert: $filename - $purpose\n";
        }
    }
    
    // Méthodes utilitaires d'extraction d'informations
    
    private function extractApiDescription($file): string {
        $content = file_get_contents($file);
        
        // Chercher dans les commentaires de header
        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\n/', $content, $matches)) {
            return trim($matches[1]);
        }
        
        // Chercher dans les commentaires simples
        if (preg_match('/\/\/\s*(.+?)(?:\n|$)/', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return "API découverte automatiquement";
    }
    
    private function detectApiMethods($file): array {
        $content = file_get_contents($file);
        $methods = [];
        
        if (strpos($content, '$_GET') !== false) $methods[] = 'GET';
        if (strpos($content, '$_POST') !== false) $methods[] = 'POST';
        if (strpos($content, 'php://input') !== false) $methods[] = 'POST JSON';
        if (strpos($content, '$_REQUEST') !== false && empty($methods)) $methods[] = 'GET/POST';
        
        return empty($methods) ? ['GET'] : $methods;
    }
    
    private function detectApiPurpose($file): string {
        $content = file_get_contents($file);
        
        if (strpos($content, 'mobile') !== false) return 'Application mobile';
        if (strpos($content, 'admin') !== false) return 'Interface admin';
        if (strpos($content, 'coursier') !== false) return 'Gestion coursiers';
        if (strpos($content, 'commande') !== false) return 'Gestion commandes';
        if (strpos($content, 'fcm') !== false || strpos($content, 'notification') !== false) return 'Notifications';
        
        return 'Usage général';
    }
    
    private function detectTableType($tableName, $createStatement): string {
        $name = strtolower($tableName);
        
        if (strpos($name, 'log') !== false) return 'Logs';
        if (strpos($name, 'token') !== false) return 'Sécurité';
        if (strpos($name, 'agent') !== false || strpos($name, 'coursier') !== false) return 'Coursiers';
        if (strpos($name, 'commande') !== false) return 'Commandes';
        if (strpos($name, 'notification') !== false) return 'Notifications';
        if (strpos($name, 'recharge') !== false || strpos($name, 'wallet') !== false) return 'Finance';
        
        return 'Données';
    }
    
    private function getTableDescription($tableName, $tableType): string {
        $descriptions = [
            'agents_suzosky' => 'Table principale des coursiers avec soldes et statuts',
            'commandes' => 'Gestion des commandes et leur progression',
            'device_tokens' => 'Tokens FCM pour les notifications push',
            'notifications_log_fcm' => 'Historique des notifications envoyées',
            'recharges' => 'Historique des rechargements de wallet'
        ];
        
        return $descriptions[$tableName] ?? "Table $tableType découverte automatiquement";
    }
    
    private function checkServiceStatus($serviceName): string {
        // Logique simple de vérification de statut
        try {
            if (class_exists($serviceName)) {
                return 'actif';
            }
        } catch (Exception $e) {
            // Service peut exister mais pas encore chargé
        }
        
        return 'détecté';
    }
    
    private function detectMonitoringPurpose($filename): string {
        if (strpos($filename, 'diagnostic') !== false) return 'Diagnostic système';
        if (strpos($filename, 'test') !== false) return 'Test et validation';
        if (strpos($filename, 'surveillance') !== false) return 'Surveillance temps réel';
        if (strpos($filename, 'fcm') !== false) return 'Monitoring FCM';
        
        return 'Outil de monitoring';
    }
}
?>