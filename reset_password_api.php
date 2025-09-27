<?php
/**
 * SYSTÈME ROBUSTE DE RÉINITIALISATION MOT DE PASSE
 * 
 * Fonctionnalités avancées:
 * - Vérification préalable des comptes
 * - Envoi d'emails professionnels anti-spam
 * - Suivi technique complet
 * - Retry automatique
 * - Logs détaillés
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Charger le système d'emails robuste
require_once __DIR__ . '/EMAIL_SYSTEM/RobustEmailSystem.php';
require_once __DIR__ . '/config.php';

// Gestion erreurs pour JSON valide
error_reporting(E_ALL);
ini_set('display_errors', 0);

function jsonResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Méthode non autorisée');
}

$action = $_POST['action'] ?? '';

/**
 * Retourne une connexion PDO basée sur la configuration unifiée.
 */
function getPasswordResetPDO(): PDO {
    try {
        return getDBConnection();
    } catch (Throwable $e) {
        throw new PDOException('Connexion base de données impossible: ' . $e->getMessage(), (int) $e->getCode(), $e);
    }
}

if ($action === 'reset_password_request') {
    
    $emailOrPhone = trim($_POST['email_or_phone'] ?? '');
    
    if (empty($emailOrPhone)) {
        jsonResponse(false, 'Email ou téléphone requis pour la vérification');
    }
    
    try {
        $pdo = getPasswordResetPDO();
        
        // Initialiser le système d'emails robuste
        $emailSystem = new RobustEmailSystem();
        
        // ÉTAPE 1: Vérifier que le compte existe AVANT tout traitement
        $client = $emailSystem->verifyAccountExists($emailOrPhone, $pdo);
        
        if (!$client) {
            jsonResponse(false, 'Aucun compte trouvé avec cet email ou téléphone');
        }
        
        if (empty($client['email'])) {
            jsonResponse(false, 'Ce compte n\'a pas d\'adresse email configurée');
        }
        
        // ÉTAPE 2: Préparer les colonnes DB si nécessaire
        try {
            $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN IF NOT EXISTS reset_token VARCHAR(100) NULL");
            $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN IF NOT EXISTS reset_expires_at DATETIME NULL");
        } catch (Exception $e) {
            // Colonnes existent déjà
        }
        
        // ÉTAPE 3: Générer token sécurisé
        $token = bin2hex(random_bytes(32)); // Token plus long pour sécurité renforcée
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 heure
        
        // ÉTAPE 4: Sauvegarder en base AVANT l'envoi
        $upd = $pdo->prepare("UPDATE clients_particuliers SET reset_token = ?, reset_expires_at = ? WHERE id = ?");
        if (!$upd->execute([$token, $expires, $client['id']])) {
            jsonResponse(false, 'Erreur lors de la préparation du reset');
        }
        
        // ÉTAPE 5: ENVOI ROBUSTE avec suivi technique complet
        $result = $emailSystem->sendPasswordResetEmail($client, $token);
        
        if ($result['success']) {
            jsonResponse(true, $result['message'], [
                'email_id' => $result['email_id'],
                'sent_to' => $client['email'],
                'expires_in' => '1 heure'
            ]);
        } else {
            // Nettoyer le token en cas d'échec d'envoi
            $cleanup = $pdo->prepare("UPDATE clients_particuliers SET reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
            $cleanup->execute([$client['id']]);
            
            jsonResponse(false, $result['error'], [
                'technical_error' => $result['technical_error'] ?? null
            ]);
        }
        
    } catch (Exception $e) {
        // Log erreur technique détaillée
        file_put_contents(__DIR__ . '/EMAIL_SYSTEM/logs/critical_errors.log', 
            date('Y-m-d H:i:s') . " - ERREUR CRITIQUE: " . $e->getMessage() . " | " . $e->getTraceAsString() . "\n", 
            FILE_APPEND | LOCK_EX);
        
        jsonResponse(false, 'Erreur technique du serveur');
    }
    
} elseif ($action === 'reset_password_do') {
    
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    if (empty($token) || empty($password)) {
        jsonResponse(false, 'Token et mot de passe requis');
    }
    
    if ($password !== $confirmPassword) {
        jsonResponse(false, 'Les mots de passe ne correspondent pas');
    }
    
    if (strlen($password) !== 5) {
        jsonResponse(false, 'Le mot de passe doit contenir exactement 5 caractères');
    }
    
    try {
        $pdo = getPasswordResetPDO();
        
        // Vérifier token
        $stmt = $pdo->prepare("SELECT id FROM clients_particuliers WHERE reset_token = ? AND reset_expires_at > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $client = $stmt->fetch();
        
        if (!$client) {
            jsonResponse(false, 'Token invalide ou expiré');
        }
        
        // Changer mot de passe
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE clients_particuliers SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        $upd->execute([$hash, $client['id']]);
        
        jsonResponse(true, 'Mot de passe changé avec succès');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Erreur lors du changement');
    }
    
} else {
    jsonResponse(false, 'Action non reconnue');
}
?>