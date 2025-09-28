<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    echo "=== TESTING SPECIFIC MIGRATION STEP ===\n";
    
    // Test the condition evaluation that's failing
    function tableExists(PDO $pdo, string $table): bool {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
    
    function columnExists(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    function evaluateCondition(PDO $pdo, array $condition): bool {
        if (isset($condition['tableExists'])) {
            return tableExists($pdo, (string) $condition['tableExists']);
        }
        if (isset($condition['columnExists'])) {
            $cfg = $condition['columnExists'];
            $table = (string) ($cfg['table'] ?? '');
            $column = (string) ($cfg['column'] ?? '');
            if ($table === '' || $column === '') {
                return false;
            }
            return columnExists($pdo, $table, $column);
        }
        return false;
    }
    
    // Test the exact condition from the migration
    $condition = ['tableExists' => 'device_tokens'];
    echo "tableExists condition: " . (evaluateCondition($pdo, $condition) ? "TRUE" : "FALSE") . "\n";
    
    // Test actual table existence
    echo "device_tokens exists: " . (tableExists($pdo, 'device_tokens') ? "TRUE" : "FALSE") . "\n";
    
    // Check what's actually in the first migration step
    $migrationsFile = __DIR__ . '/Scripts/db_schema_migrations.php';
    $migrations = require $migrationsFile;
    $firstMigration = $migrations[0];
    $firstStep = $firstMigration['steps'][0];
    
    echo "\nFirst migration step:\n";
    echo "Type: " . ($firstStep['type'] ?? 'unknown') . "\n";
    echo "Table: " . ($firstStep['table'] ?? 'unknown') . "\n";
    echo "Column: " . ($firstStep['column'] ?? 'unknown') . "\n";
    echo "Has onlyIf: " . (isset($firstStep['onlyIf']) ? "YES" : "NO") . "\n";
    
    if (isset($firstStep['onlyIf'])) {
        echo "onlyIf condition: ";
        print_r($firstStep['onlyIf']);
        echo "Condition result: " . (evaluateCondition($pdo, $firstStep['onlyIf']) ? "TRUE" : "FALSE") . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>