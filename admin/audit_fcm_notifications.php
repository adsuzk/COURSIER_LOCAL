<?php
// Script d'audit rapide des notifications FCM récentes
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');
$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, coursier_id, commande_id, title, message, status, fcm_response_code, LEFT(fcm_response, 200) as fcm_response, created_at FROM notifications_log_fcm ORDER BY created_at DESC LIMIT 20");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo "[{$row['created_at']}] ";
    echo "Coursier #{$row['coursier_id']} | Commande #{$row['commande_id']} | Status: {$row['status']} | Code: {$row['fcm_response_code']}\n";
    echo "Titre: {$row['title']}\nMessage: {$row['message']}\n";
    echo "Réponse FCM: {$row['fcm_response']}\n";
    echo str_repeat('-', 80) . "\n";
}
