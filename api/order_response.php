<?php
// API pour accepter ou refuser une commande (coursier mobile)

// IMPORTANT: Capture toutes les sorties avant d'envoyer les headers
ob_start();

// Désactiver l'affichage des erreurs HTML
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Fonction pour envoyer une réponse JSON propre
function sendJsonResponse($data, $code = 200) {
    // Nettoyer tout ce qui a pu être affiché avant
    if (ob_get_level()) ob_clean();
    
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode($data);
    exit;
}

// Gestionnaire d'erreurs global
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    sendJsonResponse([
        'success' => false,
        'error' => "Erreur PHP: $errstr",
        'file' => basename($errfile),
        'line' => $errline
    ], 500);
});

// Gestionnaire d'exceptions global
set_exception_handler(function($exception) {
    sendJsonResponse([
        'success' => false,
        'error' => 'Exception: ' . $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine()
    ], 500);
});

// Capture toutes les erreurs pour retourner du JSON au lieu de HTML
try {
    require_once '../config.php';
} catch (Exception $e) {
    sendJsonResponse([
        'success' => false, 
        'error' => 'Erreur de configuration: ' . $e->getMessage()
    ], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['success' => false, 'error' => 'Méthode non autorisée'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

// Paramètres requis
$order_id = $input['order_id'] ?? null;
$coursier_id = $input['coursier_id'] ?? null;
$action = $input['action'] ?? null; // 'accept' ou 'refuse'

if (!$order_id || !$coursier_id || !$action) {
    sendJsonResponse([
        'success' => false, 
        'error' => 'Paramètres manquants: order_id, coursier_id, action requis'
    ], 400);
}

if (!in_array($action, ['accept', 'refuse'])) {
    sendJsonResponse([
        'success' => false, 
        'error' => 'Action invalide. Utilisez "accept" ou "refuse"'
    ], 400);
}

try {
    // Tenter de se connecter à la base de données
    try {
        $pdo = getDBConnection();
    } catch (Exception $dbException) {
        sendJsonResponse([
            'success' => false, 
            'error' => 'Erreur de connexion à la base de données',
            'details' => $dbException->getMessage()
        ], 503);
    }
    
    // Vérifier que la commande existe et est bien assignée au coursier
    $stmt = $pdo->prepare('SELECT * FROM commandes WHERE id = ? AND coursier_id = ?');
    $stmt->execute([$order_id, $coursier_id]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        sendJsonResponse([
            'success' => false, 
            'error' => 'Commande non trouvée ou non assignée à ce coursier'
        ], 404);
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