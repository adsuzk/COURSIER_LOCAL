<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    echo "=== TESTING FIXED FUNCTIONS ===\n";
    
    function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $escapedTable = str_replace('`', '``', $table);
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$escapedTable}` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetchColumn();
    }
    
    function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $escapedTable = str_replace('`', '``', $table);
        $stmt = $pdo->prepare("SHOW INDEX FROM `{$escapedTable}` WHERE Key_name = ?");
        $stmt->execute([$index]);
        return (bool) $stmt->fetchColumn();
    }
    
    echo "columnExists('device_tokens', 'device_type'): " . (columnExists($pdo, 'device_tokens', 'device_type') ? "TRUE" : "FALSE") . "\n";
    echo "columnExists('device_tokens', 'nonexistent'): " . (columnExists($pdo, 'device_tokens', 'nonexistent') ? "TRUE" : "FALSE") . "\n";
    
    echo "indexExists('device_tokens', 'PRIMARY'): " . (indexExists($pdo, 'device_tokens', 'PRIMARY') ? "TRUE" : "FALSE") . "\n";
    echo "indexExists('device_tokens', 'nonexistent_idx'): " . (indexExists($pdo, 'device_tokens', 'nonexistent_idx') ? "TRUE" : "FALSE") . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>