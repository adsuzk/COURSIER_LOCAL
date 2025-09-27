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
    $otpCode = trim($input['otp_code'] ?? '');
    $coursierId = isset($input['coursier_id']) ? (int)$input['coursier_id'] : null;
    $cashCollected = isset($input['cash_collected']) ? (bool)$input['cash_collected'] : false;
    $cashAmount = isset($input['cash_amount']) ? (float)$input['cash_amount'] : null;

    if (!$commandeId || $otpCode === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }

    $pdo = getPDO();

    // Charger OTP
    $stmt = $pdo->prepare("SELECT * FROM delivery_otps WHERE commande_id = ? LIMIT 1");
    $stmt->execute([$commandeId]);
    $otp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$otp) {
        echo json_encode(['success' => false, 'error' => 'OTP introuvable']);
        exit;
    }
    if (!empty($otp['validated_at'])) {
        echo json_encode(['success' => false, 'error' => 'OTP déjà utilisé']);
        exit;
    }
    if (strtotime($otp['expires_at']) < time()) {
        echo json_encode(['success' => false, 'error' => 'OTP expiré']);
        exit;
    }

    // Vérifier tentative
    $attempts = (int)$otp['attempts'];
    $maxAttempts = (int)$otp['max_attempts'];
    if ($attempts >= $maxAttempts) {
        echo json_encode(['success' => false, 'error' => 'Trop de tentatives']);
        exit;
    }

    if (hash_equals($otp['otp_code'], $otpCode)) {
        // Valider OTP
        $pdo->prepare("UPDATE delivery_otps SET validated_at = NOW(), validated_by = ? WHERE id = ?")
            ->execute([$coursierId, $otp['id']]);

        // Historique
        $pdo->prepare("INSERT INTO order_status_history (commande_id, old_status, new_status, changed_by, coursier_id, note) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$commandeId, 'en_cours', 'livree', 'coursier', $coursierId, 'Livraison confirmée par OTP']);

        // Mettre à jour le statut via API interne
        $payload = [
            'commande_id' => $commandeId,
            'statut' => 'livree'
        ];
        if ($cashCollected) { $payload['cash_collected'] = true; }
        if ($cashAmount !== null) { $payload['cash_amount'] = $cashAmount; }

        // Appel direct DB (éviter HTTP interne). Mettre à jour commandes_classiques si dispo
        try {
            $hasTable = $pdo->query("SHOW TABLES LIKE 'commandes_classiques'")->rowCount() > 0;
            if ($hasTable) {
                $setExtra = ', delivered_time = NOW()';
                if ($cashCollected) { $setExtra .= ', cash_collected = 1'; }
                if ($cashAmount !== null) { $setExtra .= ', cash_amount = ' . $pdo->quote($cashAmount); }
                $pdo->prepare("UPDATE commandes_classiques SET statut = 'livree' $setExtra WHERE id = ?")
                    ->execute([$commandeId]);
            }
        } catch (Throwable $e) { /* best-effort */ }

        echo json_encode(['success' => true]);
    } else {
        // Incrémenter tentatives
        $pdo->prepare("UPDATE delivery_otps SET attempts = attempts + 1 WHERE id = ?")->execute([$otp['id']]);
        echo json_encode(['success' => false, 'error' => 'OTP incorrect', 'attempts' => $attempts + 1]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
