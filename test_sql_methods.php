<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    echo "=== TESTING RAW SQL ===\n";
    
    // Test 1: Direct SHOW COLUMNS
    echo "Test 1: Direct SHOW COLUMNS\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM device_tokens");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Found columns: " . count($columns) . "\n";
    
    // Test 2: SHOW COLUMNS with LIKE (no prepared statement)
    echo "\nTest 2: SHOW COLUMNS with LIKE\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'device_type'");
    $result = $stmt->fetchColumn();
    echo "device_type exists: " . ($result ? "YES" : "NO") . "\n";
    
    // Test 3: Using INFORMATION_SCHEMA instead
    echo "\nTest 3: Using INFORMATION_SCHEMA\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = ? 
        AND COLUMN_NAME = ?
    ");
    $stmt->execute(['device_tokens', 'device_type']);
    $count = $stmt->fetchColumn();
    echo "device_type via INFORMATION_SCHEMA: " . ($count > 0 ? "EXISTS" : "NOT FOUND") . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
}
?>