<?php
// Test de connexion DB - exactement comme les vrais fichiers API
header('Content-Type: application/json');

try {
    // Charger config comme les autres fichiers API
    require_once __DIR__ . '/../config.php';
    
    // Tester la connexion
    $pdo = getDBConnection();
    
    // Tester une requête
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM coursiers");
    $count = $stmt->fetchColumn();
    
    // Tester les colonnes de la table coursiers
    $stmt = $pdo->query("SHOW COLUMNS FROM coursiers");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'message' => 'Connexion DB OK',
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'coursiers_count' => $count,
        'coursiers_columns' => $columns,
        'env_override_loaded' => file_exists(__DIR__ . '/../env_override.php'),
        'config_path' => __DIR__ . '/../config.php'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
