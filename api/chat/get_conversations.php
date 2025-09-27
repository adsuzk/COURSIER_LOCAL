<?php
/**
 * Récupère la liste des conversations selon le type : particuliers, business, agents
 */
require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$type = $input['type'] ?? 'particulier';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT c.id, c.client_id, c.type, c.last_message, c.last_timestamp, c.unread_count,
                                   COALESCE(cp.nom, CONCAT(cp2.prenoms,' ',cp2.nom), bc.nom_entreprise, CONCAT(ag.prenoms,' ',ag.nom)) AS client_name
                            FROM chat_conversations c
                            LEFT JOIN clients_particuliers cp2 ON (c.type='particulier' AND cp2.id = c.client_id)
                            LEFT JOIN business_clients bc ON (c.type='business' AND bc.id = c.client_id)
                            LEFT JOIN agents_suzosky ag ON (c.type='agents' AND ag.id_coursier = c.client_id)
                            LEFT JOIN clients_particuliers cp ON 1=0
                            WHERE c.type = ?
                            ORDER BY c.last_timestamp DESC NULLS LAST");
    $stmt->execute([$type]);
    $convs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = $row['client_name'] ?: ('ID ' . $row['client_id']);
        $convs[] = [
            'id' => (int)$row['id'],
            'client_id' => (int)$row['client_id'],
            'client_name' => $name,
            'type' => $row['type'],
            'last_message' => $row['last_message'] ?? '',
            'timestamp' => $row['last_timestamp'] ?? null,
            'unread_count' => (int)($row['unread_count'] ?? 0),
            'avatar' => strtoupper(substr($name,0,2))
        ];
    }
    echo json_encode(['success' => true, 'data' => $convs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
