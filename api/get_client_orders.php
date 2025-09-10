<?php
/**
 * Récupère l'historique des commandes d'un client par téléphone
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON invalide']);
    exit;
}
$phone = trim($data['phone'] ?? '');
if ($phone === '') {
    echo json_encode(['success' => false, 'message' => 'Téléphone requis']);
    exit;
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        "SELECT numero_commande, adresse_depart, adresse_arrivee, prix_estime, date_creation FROM commandes WHERE telephone_expediteur = ? ORDER BY date_creation DESC"
    );
    $stmt->execute([$phone]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $orders]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
