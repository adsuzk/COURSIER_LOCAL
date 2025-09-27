<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) { $input = $_POST; }

    $commandeId = (int)($input['commande_id'] ?? 0);
    $length = (int)($input['length'] ?? 4);
    $ttl = (int)($input['ttl_seconds'] ?? 900); // 15min par défaut

    if (!$commandeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'commande_id requis']);
        exit;
    }

    $length = max(3, min($length, 8));
    $otp = '';
    for ($i=0; $i<$length; $i++) { $otp .= strval(random_int(0,9)); }

    $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

    $pdo = getPDO();
    // upsert-like: si existe -> update, sinon insert
    $exists = $pdo->prepare("SELECT id FROM delivery_otps WHERE commande_id = ?");
    $exists->execute([$commandeId]);
    $row = $exists->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $stmt = $pdo->prepare("UPDATE delivery_otps SET otp_code = ?, attempts = 0, max_attempts = 5, expires_at = ?, generated_at = NOW(), validated_at = NULL, validated_by = NULL WHERE commande_id = ?");
        $stmt->execute([$otp, $expiresAt, $commandeId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO delivery_otps (commande_id, otp_code, attempts, max_attempts, expires_at) VALUES (?, ?, 0, 5, ?)");
        $stmt->execute([$commandeId, $otp, $expiresAt]);
    }

    echo json_encode(['success' => true, 'otp' => $otp, 'expires_at' => $expiresAt]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
