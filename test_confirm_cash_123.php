<?php
require_once 'config.php';

$pdo = getDBConnection();

// Tester l'appel API confirm_cash_received
$commande_id = 123;
$coursier_id = 5;

echo "=== TEST CONFIRM CASH API ===\n";
echo "Commande: $commande_id\n";
echo "Coursier: $coursier_id\n\n";

// Simuler l'appel API
$_REQUEST['action'] = 'confirm_cash_received';
$_REQUEST['commande_id'] = $commande_id;
$_REQUEST['coursier_id'] = $coursier_id;

// Inclure l'API
ob_start();
include 'mobile_sync_api.php';
$output = ob_get_clean();

echo "R\u00e9ponse API:\n";
echo $output . "\n\n";

// V\u00e9rifier l'\u00e9tat dans la BDD
$stmt = $pdo->prepare('SELECT id, statut, cash_recupere, mode_paiement FROM commandes WHERE id = ?');
$stmt->execute([$commande_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== \u00c9TAT DANS LA BDD ===\n";
echo json_encode($result, JSON_PRETTY_PRINT);
