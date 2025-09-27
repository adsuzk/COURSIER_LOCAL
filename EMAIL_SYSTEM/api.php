<?php
/**
 * API ROBUSTE POUR RESET PASSWORD
 * Remplace reset_password_api.php avec vérification et suivi complet
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/EmailManager.php';

function jsonResponse($success, $message, $data = null, $code = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data) $response['data'] = $data;
    if ($code) $response['code'] = $code;
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée');
}

try {
    $pdo = getDBConnection();
    
    // Initialiser le gestionnaire d'emails
    $emailManager = new EmailManager($pdo, $config);
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reset_password_request') {
        // === DEMANDE DE RESET ===
        $emailOrPhone = trim($_POST['email_or_phone'] ?? '');
        
        if (empty($emailOrPhone)) {
            jsonResponse(false, 'Email ou téléphone requis', null, 'MISSING_INPUT');
        }
        
        // Envoyer avec vérification et suivi complet
        $result = $emailManager->sendPasswordReset($emailOrPhone);
        
        if ($result['success']) {
            jsonResponse(true, $result['message'], [
                'tracking_id' => $result['tracking_id'] ?? null,
                'log_id' => $result['log_id'] ?? null
            ]);
        } else {
            jsonResponse(false, $result['message'], null, $result['code'] ?? 'SEND_ERROR');
        }
        
    } elseif ($action === 'reset_password_do') {
        // === VALIDATION DU NOUVEAU MOT DE PASSE ===
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        if (empty($token) || empty($password)) {
            jsonResponse(false, 'Token et mot de passe requis', null, 'MISSING_INPUT');
        }
        
        if ($password !== $confirmPassword) {
            jsonResponse(false, 'Les mots de passe ne correspondent pas', null, 'PASSWORD_MISMATCH');
        }
        
        if (strlen($password) !== 5) {
            jsonResponse(false, 'Le mot de passe doit contenir exactement 5 caractères', null, 'INVALID_LENGTH');
        }
        
        // Vérifier token
        $stmt = $pdo->prepare("
            SELECT id, email 
            FROM clients_particuliers 
            WHERE reset_token = ? AND reset_expires_at > NOW() 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$client) {
            jsonResponse(false, 'Token invalide ou expiré', null, 'INVALID_TOKEN');
        }
        
        // Changer le mot de passe
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE clients_particuliers 
            SET password = ?, reset_token = NULL, reset_expires_at = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$hash, $client['id']]);
        
        // Logger le succès
        $logFile = __DIR__ . '/logs/password_changes_' . date('Y-m-d') . '.log';
        $logMessage = date('Y-m-d H:i:s') . " - Mot de passe changé avec succès pour {$client['email']}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        jsonResponse(true, 'Mot de passe changé avec succès', [
            'redirect' => '/'
        ]);
        
    } else {
        jsonResponse(false, 'Action non reconnue', null, 'INVALID_ACTION');
    }
    
} catch (Exception $e) {
    // Logger l'erreur
    $errorLog = __DIR__ . '/logs/errors_' . date('Y-m-d') . '.log';
    $errorMessage = date('Y-m-d H:i:s') . " - Erreur: " . $e->getMessage() . "\n";
    file_put_contents($errorLog, $errorMessage, FILE_APPEND | LOCK_EX);
    
    jsonResponse(false, 'Erreur serveur temporaire. Veuillez réessayer.', null, 'SERVER_ERROR');
}
?>