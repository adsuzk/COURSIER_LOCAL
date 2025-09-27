<?php
/**
 * API Solde Wallet - Conforme à la documentation finale
 * Endpoint: api/get_wallet_balance.php
 * Usage: GET/POST avec coursier_id
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config.php';

// Récupération de l'ID coursier
$coursier_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $coursier_id = isset($_GET['coursier_id']) ? intval($_GET['coursier_id']) : null;
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $coursier_id = isset($data['coursier_id']) ? intval($data['coursier_id']) : null;
}

if (!$coursier_id || $coursier_id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'MISSING_COURSIER_ID',
        'message' => 'ID coursier manquant ou invalide'
    ]);
    exit;
}

try {
    $pdo = getPDO();
    
    // Récupération depuis agents_suzosky (table principale selon documentation)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nom,
            prenoms,
            solde_wallet,
            statut_connexion,
            last_login_at
        FROM agents_suzosky 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->execute([$coursier_id]);
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coursier) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'COURSIER_NOT_FOUND',
            'message' => 'Coursier introuvable'
        ]);
        exit;
    }
    
    $solde = (float)($coursier['solde_wallet'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'coursier_id' => (int)$coursier['id'],
            'nom_complet' => trim(($coursier['prenoms'] ?? '') . ' ' . ($coursier['nom'] ?? '')),
            'solde_wallet' => $solde,
            'solde_wallet_formatted' => number_format($solde, 0, ',', ' ') . ' FCFA',
            'statut_connexion' => $coursier['statut_connexion'] ?? 'hors_ligne',
            'last_sync' => date('Y-m-d H:i:s'),
            'source' => 'agents_suzosky'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SYSTEM_ERROR',
        'message' => 'Erreur lors de la récupération du solde',
        'debug' => [
            'err' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]
    ]);
}