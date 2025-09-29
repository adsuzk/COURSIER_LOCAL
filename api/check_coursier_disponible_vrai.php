<?php
// API avancée pour vérifier la disponibilité réelle d'un coursier (token FCM actif ET pas de commande en cours)
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query('
        SELECT a.id
        FROM agents_suzosky a
        INNER JOIN device_tokens dt ON dt.coursier_id = a.id AND dt.is_active = 1
        LEFT JOIN commandes c ON c.coursier_id = a.id AND c.statut IN (\'attribuee\', \'en_cours\')
        WHERE c.id IS NULL
        LIMIT 1
    ');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $available = $row ? true : false;
    echo json_encode(['success' => true, 'available' => $available]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
