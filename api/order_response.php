<?php
// API pour accepter ou refuser une commande (coursier mobile)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Capture toutes les erreurs pour retourner du JSON au lieu de HTML
try {
    require_once '../config.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur de configuration: ' . $e->getMessage()
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Paramètres requis
$order_id = $input['order_id'] ?? null;
$coursier_id = $input['coursier_id'] ?? null;
$action = $input['action'] ?? null; // 'accept' ou 'refuse'

if (!$order_id || !$coursier_id || !$action) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Paramètres manquants: order_id, coursier_id, action requis'
    ]);
    exit;
}

if (!in_array($action, ['accept', 'refuse'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Action invalide. Utilisez "accept" ou "refuse"'
    ]);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Vérifier que la commande existe et est bien assignée au coursier
    $stmt = $pdo->prepare('SELECT * FROM commandes WHERE id = ? AND coursier_id = ?');
    $stmt->execute([$order_id, $coursier_id]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => 'Commande non trouvée ou non assignée à ce coursier'
        ]);
        exit;
    }
    
    if ($action === 'accept') {
        // Accepter la commande
        $stmt_update = $pdo->prepare('UPDATE commandes SET statut = "acceptee", heure_acceptation = NOW() WHERE id = ? AND coursier_id = ?');
        $stmt_update->execute([$order_id, $coursier_id]);
        
        $response = [
            'success' => true,
            'action' => 'accepted',
            'order_id' => $order_id,
            'message' => 'Commande acceptée avec succès',
            'new_status' => 'acceptee',
            'stop_ring' => true // Signal pour arrêter la sonnerie
        ];
        
    } else { // refuse
        // Refuser la commande - la remettre en attente pour un autre coursier
        $stmt_update = $pdo->prepare('UPDATE commandes SET statut = "nouvelle", coursier_id = NULL, assigned_at = NULL WHERE id = ?');
        $stmt_update->execute([$order_id]);
        
        // Optionnel: blacklister temporairement ce coursier pour cette commande
        // (éviter de la reassigner immédiatement au même coursier)
        
        $response = [
            'success' => true,
            'action' => 'refused',
            'order_id' => $order_id,
            'message' => 'Commande refusée - remise en attribution',
            'new_status' => 'nouvelle',
            'stop_ring' => true // Signal pour arrêter la sonnerie
        ];
    }
    
    // Logger l'action
    error_log("Coursier $coursier_id a " . ($action === 'accept' ? 'accepté' : 'refusé') . " la commande $order_id");
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>