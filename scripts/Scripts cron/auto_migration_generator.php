<?php
declare(strict_types=1);

/**
 * GÉNÉRATEUR AUTOMATIQUE DE MIGRATIONS
 * Détecte automatiquement les différences entre la structure locale et production
 * et génère les migrations nécessaires
 */

require_once dirname(__DIR__, 2) . '/config.php';

class AutoMigrationGenerator {
    private $pdo;
    private $logFile;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->logFile = dirname(__DIR__, 2) . '/diagnostic_logs/auto_migration_generator.log';
    }
    
    private function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";
        echo $logEntry;
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Analyse la structure actuelle de la base de données
     */
    public function analyzeCurrentStructure(): array {
        $this->log("🔍 Analyse de la structure actuelle de la base de données...");
        
        $structure = [
            'tables' => [],
            'columns' => [],
            'indexes' => []
        ];
        
        // Récupérer toutes les tables
        $stmt = $this->pdo->query("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ");
        
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $structure['tables'][] = $table;
            
            // Analyser les colonnes de chaque table
            $stmt = $this->pdo->prepare("
                SELECT 
                    COLUMN_NAME,
                    COLUMN_TYPE,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    EXTRA,
                    COLUMN_COMMENT
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            ");
            $stmt->execute([$table]);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $structure['columns'][$table] = $columns;
            
            // Analyser les index de chaque table
            $stmt = $this->pdo->prepare("
                SELECT 
                    INDEX_NAME,
                    COLUMN_NAME,
                    NON_UNIQUE,
                    SEQ_IN_INDEX
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX
            ");
            $stmt->execute([$table]);
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Grouper les colonnes par index
            $groupedIndexes = [];
            foreach ($indexes as $idx) {
                $indexName = $idx['INDEX_NAME'];
                if (!isset($groupedIndexes[$indexName])) {
                    $groupedIndexes[$indexName] = [
                        'columns' => [],
                        'unique' => $idx['NON_UNIQUE'] == '0'
                    ];
                }
                $groupedIndexes[$indexName]['columns'][] = $idx['COLUMN_NAME'];
            }
            
            $structure['indexes'][$table] = $groupedIndexes;
        }
        
        $this->log("✅ Structure analysée : " . count($tables) . " tables trouvées");
        return $structure;
    }
    
    /**
     * Sauvegarde la structure actuelle comme référence
     */
    public function saveStructureSnapshot(): void {
        $structure = $this->analyzeCurrentStructure();
        $snapshotFile = dirname(__DIR__, 2) . '/diagnostic_logs/db_structure_snapshot.json';
        
        file_put_contents($snapshotFile, json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->log("📸 Snapshot de structure sauvegardé dans db_structure_snapshot.json");
    }
    
    /**
     * Compare la structure actuelle avec le snapshot précédent
     */
    public function detectChanges(): array {
        $snapshotFile = dirname(__DIR__, 2) . '/diagnostic_logs/db_structure_snapshot.json';
        
        if (!file_exists($snapshotFile)) {
            $this->log("⚠️  Aucun snapshot trouvé. Création du snapshot initial...");
            $this->saveStructureSnapshot();
            return ['migrations' => [], 'message' => 'Snapshot initial créé'];
        }
        
        $previousStructure = json_decode(file_get_contents($snapshotFile), true);
        $currentStructure = $this->analyzeCurrentStructure();
        
        $migrations = [];
        
        // Détecter nouvelles tables
        $newTables = array_diff($currentStructure['tables'], $previousStructure['tables']);
        foreach ($newTables as $table) {
            $migrations[] = $this->generateCreateTableMigration($table, $currentStructure);
        }
        
        // Détecter nouvelles colonnes
        foreach ($currentStructure['columns'] as $table => $columns) {
            if (!isset($previousStructure['columns'][$table])) {
                continue; // Table entière déjà traitée comme nouvelle
            }
            
            $previousColumns = array_column($previousStructure['columns'][$table], 'COLUMN_NAME');
            $currentColumns = array_column($columns, 'COLUMN_NAME');
            
            $newColumns = array_diff($currentColumns, $previousColumns);
            foreach ($newColumns as $columnName) {
                $columnInfo = null;
                foreach ($columns as $col) {
                    if ($col['COLUMN_NAME'] === $columnName) {
                        $columnInfo = $col;
                        break;
                    }
                }
                if ($columnInfo) {
                    $migrations[] = $this->generateAddColumnMigration($table, $columnInfo);
                }
            }
        }
        
        // Détecter nouveaux index
        foreach ($currentStructure['indexes'] as $table => $indexes) {
            if (!isset($previousStructure['indexes'][$table])) {
                continue; // Table entière déjà traitée
            }
            
            $previousIndexes = array_keys($previousStructure['indexes'][$table]);
            $currentIndexes = array_keys($indexes);
            
            $newIndexes = array_diff($currentIndexes, $previousIndexes);
            foreach ($newIndexes as $indexName) {
                if ($indexName !== 'PRIMARY') { // Skip primary key
                    $migrations[] = $this->generateAddIndexMigration($table, $indexName, $indexes[$indexName]);
                }
            }
        }
        
        return [
            'migrations' => $migrations,
            'message' => count($migrations) . ' changements détectés'
        ];
    }
    
    private function generateCreateTableMigration(string $table, array $structure): array {
        return [
            'type' => 'create_table',
            'table' => $table,
            'description' => "Création automatique de la table {$table}",
            'sql' => "-- Table {$table} sera créée automatiquement lors de la synchronisation"
        ];
    }
    
    private function generateAddColumnMigration(string $table, array $columnInfo): array {
        $definition = $columnInfo['COLUMN_TYPE'];
        
        if ($columnInfo['IS_NULLABLE'] === 'NO') {
            $definition .= ' NOT NULL';
        }
        
        if ($columnInfo['COLUMN_DEFAULT'] !== null) {
            $default = $columnInfo['COLUMN_DEFAULT'];
            if (strtolower($default) === 'current_timestamp' || strtolower($default) === 'now()') {
                $definition .= ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $definition .= " DEFAULT '{$default}'";
            }
        }
        
        if ($columnInfo['EXTRA']) {
            $definition .= ' ' . $columnInfo['EXTRA'];
        }
        
        return [
            'type' => 'ensureColumn',
            'table' => $table,
            'column' => $columnInfo['COLUMN_NAME'],
            'definition' => $definition,
            'onlyIf' => ['tableExists' => $table]
        ];
    }
    
    private function generateAddIndexMigration(string $table, string $indexName, array $indexInfo): array {
        return [
            'type' => 'ensureIndex',
            'table' => $table,
            'index' => $indexName,
            'columns' => $indexInfo['columns'],
            'unique' => $indexInfo['unique'],
            'onlyIf' => ['tableExists' => $table]
        ];
    }
    
    /**
     * Génère automatiquement le fichier de migrations
     */
    public function generateMigrationsFile(): void {
        $this->log("🔄 Génération automatique des migrations...");
        
        $changes = $this->detectChanges();
        
        if (empty($changes['migrations'])) {
            $this->log("✅ Aucun changement détecté - pas de migration nécessaire");
            return;
        }
        
        // Charger les migrations existantes
        $migrationsFile = dirname(__DIR__) . '/db_schema_migrations.php';
        $existingMigrations = [];
        
        if (file_exists($migrationsFile)) {
            $existingMigrations = require $migrationsFile;
        }
        
        // Ajouter les nouvelles migrations
        $timestamp = date('Y_m_d_His');
        $newMigrationId = "{$timestamp}_auto_sync";
        
        $newMigration = [
            'id' => $newMigrationId,
            'description' => "Synchronisation automatique - " . $changes['message'],
            'steps' => []
        ];
        
        foreach ($changes['migrations'] as $migration) {
            if ($migration['type'] === 'create_table') {
                // Pour les nouvelles tables, on ajoute juste une note
                $newMigration['steps'][] = [
                    'type' => 'runSql',
                    'label' => $migration['description'],
                    'sql' => "SELECT 'Table {$migration['table']} détectée lors de la synchronisation' AS info",
                    'onlyIf' => ['tableExists' => $migration['table']]
                ];
            } else {
                $newMigration['steps'][] = $migration;
            }
        }
        
        $existingMigrations[] = $newMigration;
        
        // Sauvegarder le fichier de migrations
        $content = "<?php\ndeclare(strict_types=1);\n\n";
        $content .= "/**\n";
        $content .= " * MIGRATIONS AUTO-GÉNÉRÉES\n";
        $content .= " * Fichier mis à jour automatiquement le " . date('Y-m-d H:i:s') . "\n";
        $content .= " * Détection automatique des changements de structure\n";
        $content .= " */\n\n";
        $content .= "return " . var_export($existingMigrations, true) . ";\n";
        
        file_put_contents($migrationsFile, $content);
        
        // Mettre à jour le snapshot
        $this->saveStructureSnapshot();
        
        $this->log("✅ Fichier de migrations généré avec " . count($changes['migrations']) . " nouveaux éléments");
        $this->log("📝 Migration ID: {$newMigrationId}");
    }
}

// Exécution automatique si appelé directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $generator = new AutoMigrationGenerator();
        
        echo "=== GÉNÉRATEUR AUTOMATIQUE DE MIGRATIONS ===\n";
        echo "Détection des changements de structure DB...\n\n";
        
        $generator->generateMigrationsFile();
        
        echo "\n✅ Génération terminée avec succès !\n";
        
    } catch (Exception $e) {
        echo "❌ ERREUR: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>