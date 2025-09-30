<?php
// api/get_coursier_availability.php
// Renvoie si des coursiers (device tokens actifs) sont disponibles.
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDBConnection();

    // Strategy: prefer device_tokens.is_active + last_ping freshness (if present),
    // otherwise fallback to agents_suzosky.statut_connexion = 'en_ligne'.

    // Check active device tokens count (recent tokens)
    $available = false;
    $message = '';

    try {
        // If column last_ping exists, consider freshness (120s)
        $hasLastPing = false;
        $cols = $pdo->query("SHOW COLUMNS FROM device_tokens LIKE 'last_ping'")->fetchAll();
        if (count($cols) > 0) $hasLastPing = true;

        if ($hasLastPing) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM device_tokens WHERE is_active=1 AND (last_ping IS NOT NULL AND TIMESTAMPDIFF(SECOND, last_ping, NOW()) < 180)");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM device_tokens WHERE is_active=1");
        }
        $stmt->execute();
        $count = intval($stmt->fetchColumn());
        if ($count > 0) {
            $available = true;
            $message = "Coursiers disponibles";
        }
    } catch (Throwable $e) {
        // ignore and fallback
    }

    if (!$available) {
        // fallback to agents_suzosky table
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
            $stmt->execute();
            $c = intval($stmt->fetchColumn());
            if ($c > 0) {
                $available = true;
                $message = "Coursiers en ligne (agents_suzosky)";
            } else {
                $message = "Aucun coursier en ligne";
            }
        } catch (Throwable $e) {
            // If even this fails, return unknown
            $message = 'Échec lecture disponibilité';
        }
    }

    echo json_encode([
        'success' => true,
        'available' => $available,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>
