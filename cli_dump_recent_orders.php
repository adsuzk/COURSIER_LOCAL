<?php
require __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    $columns = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
    $preferred = ['id', 'coursier_id', 'assigned', 'assigned_to', 'statut', 'code_commande', 'order_number', 'created_at', 'updated_at'];
    $selected = array_values(array_intersect($preferred, $columns));
    $selectClause = $selected ? implode(', ', $selected) : '*';

    $stmt = $pdo->query("SELECT {$selectClause} FROM commandes ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll();
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
