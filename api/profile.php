<?php
// api/profile.php - API pour la gestion du profil utilisateur
require_once __DIR__ . '/../config.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestion des requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion base de données: ' . $e->getMessage()]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'update_profile':
        updateProfile();
        break;
    case 'change_password':
        changePassword();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Action non spécifiée']);
}

function updateProfile() {
    global $pdo;
    $client_id = $_SESSION['client_id'];
    
    $nom = trim($_POST['nom'] ?? '');
    $prenoms = trim($_POST['prenoms'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    
    if (empty($nom) || empty($prenoms) || empty($email) || empty($telephone)) {
        echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis']);
        return;
    }
    
    // Validation email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Format email invalide']);
        return;
    }
    
    // Validation téléphone ivoirien
    if (!preg_match('/^\+225\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}$/', $telephone)) {
        echo json_encode(['success' => false, 'error' => 'Format de téléphone ivoirien invalide (+225 XX XX XX XX XX)']);
        return;
    }
    
    try {
        // Vérifier que l'email et le téléphone ne sont pas déjà utilisés par un autre client
        $stmt = $pdo->prepare("
            SELECT id FROM clients_particuliers 
            WHERE (email = ? OR telephone = ?) AND id != ?
        ");
        $stmt->execute([$email, $telephone, $client_id]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Email ou téléphone déjà utilisé par un autre compte']);
            return;
        }
        
        // Mettre à jour le profil
        $stmt = $pdo->prepare("
            UPDATE clients_particuliers 
            SET nom = ?, prenoms = ?, email = ?, telephone = ?, date_modification = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$nom, $prenoms, $email, $telephone, $client_id]);
        
        // Mettre à jour les informations de session
        $_SESSION['client_email'] = $email;
        $_SESSION['client_nom'] = $nom . ' ' . $prenoms;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'client' => [
                'id' => $client_id,
                'nom' => $nom,
                'prenoms' => $prenoms,
                'email' => $email,
                'telephone' => $telephone
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()]);
    }
}

function changePassword() {
    global $pdo;
    $client_id = $_SESSION['client_id'];
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis']);
        return;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'error' => 'Les nouveaux mots de passe ne correspondent pas']);
        return;
    }
    
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères']);
        return;
    }
    
    try {
        // Récupérer le mot de passe actuel
        $stmt = $pdo->prepare("SELECT password FROM clients_particuliers WHERE id = ?");
        $stmt->execute([$client_id]);
        $result = $stmt->fetch();
        
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Utilisateur non trouvé']);
            return;
        }
        
        // Vérifier le mot de passe actuel
        if (!password_verify($currentPassword, $result['password'])) {
            echo json_encode(['success' => false, 'error' => 'Mot de passe actuel incorrect']);
            return;
        }
        
        // Mettre à jour avec le nouveau mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE clients_particuliers 
            SET password = ?, date_modification = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $client_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors du changement: ' . $e->getMessage()]);
    }
}
?>
