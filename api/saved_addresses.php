<?php
/**
 * API minimal pour gérer les adresses favorites d'un client par téléphone.
 * Endpoints:
 *  - POST action=list        { phone }
 *  - POST action=add         { phone, label, address, lat, lng }
 *  - POST action=delete      { phone, id }
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

function out($ok, $data = null, $msg = null) {
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $msg]);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    out(false, null, 'JSON invalide');
}
$action = trim($data['action'] ?? '');
$phone  = trim($data['phone'] ?? '');
if ($phone === '') out(false, null, 'Téléphone requis');

try {
    $pdo = getPDO();
    // Créer la table si absente
    $pdo->exec("CREATE TABLE IF NOT EXISTS client_saved_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone VARCHAR(32) NOT NULL,
        label VARCHAR(100) NOT NULL,
        address TEXT NOT NULL,
        lat DOUBLE NULL,
        lng DOUBLE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(phone)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    if ($action === 'list') {
        $st = $pdo->prepare("SELECT id, label, address, lat, lng FROM client_saved_addresses WHERE phone = ? ORDER BY created_at DESC");
        $st->execute([$phone]);
        out(true, $st->fetchAll(PDO::FETCH_ASSOC));
    }
    if ($action === 'add') {
        $label = trim($data['label'] ?? '');
        $address = trim($data['address'] ?? '');
        $lat = isset($data['lat']) ? floatval($data['lat']) : null;
        $lng = isset($data['lng']) ? floatval($data['lng']) : null;
        if ($label === '' || $address === '') out(false, null, 'Label et adresse requis');
        $st = $pdo->prepare("INSERT INTO client_saved_addresses (phone, label, address, lat, lng) VALUES (?, ?, ?, ?, ?)");
        $st->execute([$phone, $label, $address, $lat, $lng]);
        out(true, ['id' => $pdo->lastInsertId()], 'Ajouté');
    }
    if ($action === 'delete') {
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) out(false, null, 'ID requis');
        $st = $pdo->prepare("DELETE FROM client_saved_addresses WHERE id = ? AND phone = ? LIMIT 1");
        $st->execute([$id, $phone]);
        out(true, null, 'Supprimé');
    }
    out(false, null, 'Action inconnue');
} catch (Throwable $e) {
    out(false, null, 'Erreur: ' . $e->getMessage());
}
