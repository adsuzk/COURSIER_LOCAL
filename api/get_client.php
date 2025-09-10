<?php
/**
 * api/get_client.php
 * Récupère les informations d'un client par téléphone
 * Input JSON: { "phone": "+225..." }
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

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
    $stmt = $pdo->prepare("SELECT nom, prenoms, email, telephone FROM clients_particuliers WHERE telephone = ?");
    $stmt->execute([$phone]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client) {
        echo json_encode(['success' => true, 'data' => $client]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Client non trouvé']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
