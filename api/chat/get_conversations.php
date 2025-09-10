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
    switch ($type) {
        case 'business':
            $stmt = $pdo->query("SELECT id, nom_entreprise AS client_name, 'business' AS type, MAX(date_creation) AS last_message_time FROM business_clients GROUP BY id ORDER BY last_message_time DESC");
            break;
        case 'agents':
            $stmt = $pdo->query("SELECT id, nom AS client_name, 'agent' AS type, MAX(date) AS last_message_time FROM agent_logs GROUP BY id ORDER BY last_message_time DESC");
            break;
        case 'particulier':
        default:
            $stmt = $pdo->query("SELECT id, CONCAT(prenoms,' ',nom) AS client_name, 'particulier' AS type, MAX(date_creation) AS last_message_time FROM clients_particuliers GROUP BY id ORDER BY last_message_time DESC");
    }
    $convs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $convs[] = [
            'id' => $row['id'],
            'client_name' => $row['client_name'],
            'type' => $row['type'],
            'last_message' => '', // à récupérer séparément
            'timestamp' => $row['last_message_time'],
            'unread_count' => 0,
            'avatar' => strtoupper(substr($row['client_name'],0,2))
        ];
    }
    echo json_encode(['success' => true, 'data' => $convs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
