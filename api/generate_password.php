<?php
// API endpoint for generating a new password for an agent
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? null;
if (!is_numeric($id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing agent ID']);
    exit;
}

// Optional: add admin session check here

try {
    $pdo = getPDO();
    $newPwd = generatePassword();
    $hashed = password_hash($newPwd, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE agents_suzosky SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, (int) $id]);
    getJournal()->logMaxDetail(
        'API_AGENT_PASSWORD_RESET',
        "Password generated for agent #{$id} via API",
        ['agent_id' => (int) $id, 'new_password' => $newPwd]
    );
    echo json_encode(['success' => true, 'password' => $newPwd, 'hashed' => $hashed]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
