<?php
/**
 * API for registering or updating a client particulier
 */
require_once __DIR__ . '/../config.php';

// Headers par défaut
header('Content-Type: application/json; charset=utf-8');

// Only accept POST
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

// Required fields
$name = trim($data['name'] ?? '');
$firstName = trim($data['firstName'] ?? '');
$phone = trim($data['phone'] ?? '');
$email = trim($data['email'] ?? '');

if ($name === '' || $firstName === '' || $phone === '') {
    echo json_encode(['success' => false, 'message' => 'Champs requis manquants']);
    exit;
}

try {
    $pdo = getDBConnection();
    // Insert or update
    // Insertion ou mise à jour du client
    $sql = "INSERT INTO clients_particuliers (nom, prenoms, telephone, email, date_creation) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE prenoms=VALUES(prenoms), email=VALUES(email), date_derniere_commande=NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$name, $firstName, $phone, $email]);
    // Récupérer l'ID du client inséré ou existant
    if ($pdo->lastInsertId()) {
        $clientId = $pdo->lastInsertId();
    } else {
        $stmt2 = $pdo->prepare("SELECT id FROM clients_particuliers WHERE telephone = ?");
        $stmt2->execute([$phone]);
        $clientId = $stmt2->fetchColumn();
    }

    // Log
    // Journaliser l'enregistrement ou la mise à jour du client
    getJournal()->logMaxDetail(
        'client_registered',
        "Client enregistré ou mis à jour: {$firstName} {$name} ({$phone})",
        [
            'client_id' => $clientId,
            'file' => __FILE__,
            'line' => __LINE__
        ]
    );

    echo json_encode(['success' => true, 'data' => ['client_id' => $clientId]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
