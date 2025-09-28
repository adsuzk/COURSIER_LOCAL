<?php
// api/coursiers_connectes.php - Source unique pour l'état de connexion des coursiers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/coursier_presence.php';

try {
    $pdo = getDBConnection();
    $coursiers = getConnectedCouriers($pdo);

    $data = array_map(function (array $coursier): array {
        $statusLight = $coursier['status_light'] ?? getCoursierStatusLight($coursier);

        return [
            'id' => (int)($coursier['id'] ?? 0),
            'nom' => $coursier['nom'] ?? '',
            'prenoms' => $coursier['prenoms'] ?? '',
            'telephone' => $coursier['telephone'] ?? '',
            'type_poste' => $coursier['type_poste'] ?? null,
            'status_light' => $statusLight,
            'last_login_at' => $coursier['last_login_at'] ?? null,
            'last_seen_at' => $coursier['connexion_last_seen_at'] ?? null,
            'fcm_tokens' => (int)($coursier['active_fcm_tokens'] ?? 0),
        ];
    }, $coursiers);

    echo json_encode([
        'success' => true,
        'meta' => [
            'total' => count($data),
            'generated_at' => date('c'),
        ],
        'data' => $data,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'CONNECTIVITY_ERROR',
        'message' => $e->getMessage(),
    ]);
}
