<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    echo "=== DIAGNOSTIC DB MIGRATION ===\n";
    
    // Check device_tokens table
    $stmt = $pdo->query('SHOW TABLES LIKE "device_tokens"');
    $deviceTokensExists = (bool) $stmt->fetchColumn();
    echo "device_tokens table: " . ($deviceTokensExists ? "EXISTS" : "NOT FOUND") . "\n";
    
    if ($deviceTokensExists) {
        // Check columns in device_tokens
        $stmt = $pdo->query('DESCRIBE device_tokens');
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "device_tokens columns: " . implode(', ', $columns) . "\n";
        
        // Check for specific columns we're trying to add
        $requiredColumns = ['device_type', 'is_active', 'device_info', 'last_ping'];
        foreach ($requiredColumns as $col) {
            echo "Column '{$col}': " . (in_array($col, $columns) ? "EXISTS" : "MISSING") . "\n";
        }
    }
    
    // Check agents_suzosky table
    $stmt = $pdo->query('SHOW TABLES LIKE "agents_suzosky"');
    $agentsExists = (bool) $stmt->fetchColumn();
    echo "\nagents_suzosky table: " . ($agentsExists ? "EXISTS" : "NOT FOUND") . "\n";
    
    if ($agentsExists) {
        $stmt = $pdo->query('DESCRIBE agents_suzosky');
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "agents_suzosky columns: " . implode(', ', $columns) . "\n";
        
        $requiredColumns = ['current_session_token', 'last_login_at', 'last_logout_at', 'statut_connexion'];
        foreach ($requiredColumns as $col) {
            echo "Column '{$col}': " . (in_array($col, $columns) ? "EXISTS" : "MISSING") . "\n";
        }
    }
    
    // Test the columnExists function from migration script
    echo "\n=== TESTING MIGRATION FUNCTIONS ===\n";
    function columnExists(PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
        $stmt->execute([$column]);
        return (bool) $stmt->fetchColumn();
    }
    
    if ($deviceTokensExists) {
        echo "columnExists('device_tokens', 'device_type'): " . (columnExists($pdo, 'device_tokens', 'device_type') ? "TRUE" : "FALSE") . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>