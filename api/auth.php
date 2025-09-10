<?php
// api/auth.php - API d'authentification pour clients particuliers
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

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion base de données: ' . $e->getMessage()]);
    exit;
}

// Seed or reset test account (test@test.com / abcde) on each request
try {
    $testEmail = 'test@test.com';
    $testPassword = 'abcde'; // simple 5-char password
    $hashed = password_hash($testPassword, PASSWORD_DEFAULT);
    // Check if test account exists
    $stmt = $pdo->prepare("SELECT id FROM clients_particuliers WHERE email = ?");
    $stmt->execute([$testEmail]);
    $row = $stmt->fetch();
    if ($row) {
        // Reset password to known test password
        $update = $pdo->prepare("UPDATE clients_particuliers SET password = ? WHERE id = ?");
        $update->execute([$hashed, $row['id']]);
    } else {
        // Insert new test user
        $insert = $pdo->prepare(
            "INSERT INTO clients_particuliers (nom, prenoms, telephone, email, password, statut, date_creation)
             VALUES ('Test', 'User', '+22500 00 00 00 00', ?, ?, 'actif', NOW())"
        );
        $insert->execute([$testEmail, $hashed]);
    }
} catch (Exception $e) {
    // ignore errors on test seeding
}

// Vérifier si la table a le champ password
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM clients_particuliers LIKE 'password'");
    $hasPasswordField = $stmt->rowCount() > 0;
    
    if (!$hasPasswordField) {
        // Ajouter le champ password si il n'existe pas
        $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN password VARCHAR(255) NULL AFTER email");
        $pdo->exec("ALTER TABLE clients_particuliers ADD INDEX idx_email (email)");
    }
} catch (Exception $e) {
    // Ignorer les erreurs de structure (champ peut déjà exister)
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'orders':
        handleGetOrders();
        break;
    case 'updateProfile':
        handleUpdateProfile();
        break;
    case 'changePassword':
        handleChangePassword();
        break;
    case 'check_session':
        handleCheckSession();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check_phone':
        handleCheckPhone();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Action non spécifiée']);
}

function handleLogin() {
    global $pdo;
    
    $login = trim($_POST['email'] ?? ''); // Peut être email ou téléphone
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Email/téléphone et mot de passe requis']);
        return;
    }
    
    try {
        // Rechercher le client par email OU téléphone
        $stmt = $pdo->prepare("
            SELECT id, nom, prenoms, email, telephone, password 
            FROM clients_particuliers 
            WHERE (email = ? OR telephone = ?) AND statut = 'actif'
        ");
        $stmt->execute([$login, $login]);
        $client = $stmt->fetch();
        
        if (!$client) {
            echo json_encode(['success' => false, 'error' => 'Email/téléphone ou mot de passe incorrect']);
            return;
        }
        
        // Vérifier le mot de passe
        if (empty($client['password'])) {
            // Premier connexion : créer le mot de passe
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE clients_particuliers SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $client['id']]);
            
            // Créer la session
            createSession($client);
            echo json_encode([
                'success' => true, 
                'message' => 'Mot de passe créé avec succès',
                'client' => [
                    'id' => $client['id'],
                    'nom' => $client['nom'],
                    'prenoms' => $client['prenoms'],
                    'email' => $client['email'],
                    'telephone' => $client['telephone']
                ]
            ]);
        } else {
            // Vérifier le mot de passe existant
            if (password_verify($password, $client['password'])) {
                createSession($client);
                echo json_encode([
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'client' => [
                        'id' => $client['id'],
                        'nom' => $client['nom'],
                        'prenoms' => $client['prenoms'],
                        'email' => $client['email'],
                        'telephone' => $client['telephone']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Email ou mot de passe incorrect']);
            }
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur de connexion: ' . $e->getMessage()]);
    }
}

function handleRegister() {
    global $pdo;
    
    $nom = trim($_POST['nom'] ?? '');
    $prenoms = trim($_POST['prenoms'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($nom) || empty($prenoms) || empty($email) || empty($telephone) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis']);
        return;
    }
    // Enforce password length from config (e.g., 5 characters)
    global $config;
    $requiredLength = $config['app']['password_length'] ?? 5;
    if (mb_strlen($password) !== $requiredLength) {
        echo json_encode(['success' => false, 'error' => "Le mot de passe doit contenir exactement {$requiredLength} caractères"]);
        return;
    }
    
    // Validation email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Format email invalide']);
        return;
    }
    
    // Validation téléphone (format international ou local)
    if (!preg_match('/^[\+]?[0-9\s\-\(\)]{8,}$/', $telephone)) {
        echo json_encode(['success' => false, 'error' => 'Format téléphone invalide']);
        return;
    }
    
    try {
        // Vérifier si email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM clients_particuliers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Un compte existe déjà avec cet email']);
            return;
        }
        
        // Vérifier si téléphone existe déjà
        $stmt = $pdo->prepare("SELECT id FROM clients_particuliers WHERE telephone = ?");
        $stmt->execute([$telephone]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Un compte existe déjà avec ce numéro de téléphone']);
            return;
        }
        
        // Hasher le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insérer le nouveau client
        $stmt = $pdo->prepare("
            INSERT INTO clients_particuliers (nom, prenoms, email, telephone, password, statut, date_creation) 
            VALUES (?, ?, ?, ?, ?, 'actif', NOW())
        ");
        $stmt->execute([$nom, $prenoms, $email, $telephone, $hashedPassword]);
        
        $clientId = $pdo->lastInsertId();
        
        // Récupérer les données du client créé
        $stmt = $pdo->prepare("SELECT id, nom, prenoms, email, telephone FROM clients_particuliers WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
        
        // Créer la session
        createSession($client);
        
        echo json_encode([
            'success' => true,
            'message' => 'Compte créé avec succès',
            'client' => $client
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la création du compte: ' . $e->getMessage()]);
    }
}

function handleCheckSession() {
    if (isset($_SESSION['client_id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, nom, prenoms, email, telephone FROM clients_particuliers WHERE id = ? AND statut = 'actif'");
            $stmt->execute([$_SESSION['client_id']]);
            $client = $stmt->fetch();
            
            if ($client) {
                echo json_encode(['success' => true, 'client' => $client]);
            } else {
                destroySession();
                echo json_encode(['success' => false, 'error' => 'Session invalide']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur de vérification']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Aucune session']);
    }
}

function handleLogout() {
    destroySession();
    echo json_encode(['success' => true, 'message' => 'Déconnexion réussie']);
}

/**
 * Vérifie si un numéro de téléphone existe déjà en base de clients
 */
function handleCheckPhone() {
    global $pdo;
    $phone = trim($_POST['phone'] ?? '');
    if (empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Numéro de téléphone requis']);
        return;
    }
    try {
        $stmt = $pdo->prepare("SELECT id FROM clients_particuliers WHERE telephone = ?");
        $stmt->execute([$phone]);
        $exists = (bool) $stmt->fetch();
        echo json_encode(['success' => true, 'exists' => $exists]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur de vérification']);
    }
}

function createSession($client) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['client_id'] = $client['id'];
    $_SESSION['client_email'] = $client['email'];
    $_SESSION['client_nom'] = $client['nom'] . ' ' . $client['prenoms'];
    $_SESSION['client_telephone'] = $client['telephone'];
    $_SESSION['auth_time'] = time();
}

function destroySession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Clear session data
    session_unset();
    // Destroy session cookie if used
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function handleGetOrders() {
    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success' => false, 'error' => 'Non connecté']);
        return;
    }
    
    global $pdo;
    try {
        // Vérifier si la table commandes existe
        $tables = $pdo->query("SHOW TABLES LIKE 'commandes'")->fetchAll();
        
        if (empty($tables)) {
            echo json_encode(['success' => false, 'error' => 'Table commandes non configurée']);
            return;
        }
        
        // Récupérer les commandes du client connecté
        $stmt = $pdo->prepare("
            SELECT numero_commande, adresse_depart, adresse_arrivee, prix_estime as montant, statut, 
                   DATE_FORMAT(date_creation, '%d/%m/%Y à %H:%i') as date_formatted,
                   date_creation
            FROM commandes 
            WHERE client_id = ? 
            ORDER BY date_creation DESC 
            LIMIT 20
        ");
        $stmt->execute([$_SESSION['client_id']]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'orders' => $orders
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la récupération des commandes: ' . $e->getMessage()]);
    }
}

function handleUpdateProfile() {
    global $pdo;
    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success'=>false,'error'=>'Non connecté']);
        return;
    }
    $id = $_SESSION['client_id'];
    // Only allow updating email and telephone
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    // Vérification du mot de passe actuel pour confirmer la modification
    $current = $_POST['currentPassword'] ?? '';
    if (empty($current)) {
        echo json_encode(['success'=>false,'error'=>'Mot de passe actuel requis']);
        return;
    }
    $stmtPwd = $pdo->prepare("SELECT password FROM clients_particuliers WHERE id=?");
    $stmtPwd->execute([$id]);
    $rowPwd = $stmtPwd->fetch();
    if (!$rowPwd || !password_verify($current, $rowPwd['password'])) {
        echo json_encode(['success'=>false,'error'=>'Mot de passe actuel incorrect']);
        return;
    }
    if (empty($telephone)) {
        echo json_encode(['success'=>false,'error'=>'Le numéro de téléphone est requis']);
        return;
    }
    // Validate email if provided
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success'=>false,'error'=>'Email invalide']);
        return;
    }
    try {
    $stmt = $pdo->prepare("UPDATE clients_particuliers SET email=?, telephone=?, date_modification=NOW() WHERE id=?");
    $stmt->execute([$email, $telephone, $id]);
    // Fetch updated client data
    $fetch = $pdo->prepare("SELECT id, nom, prenoms, email, telephone FROM clients_particuliers WHERE id=?");
    $fetch->execute([$id]);
    $client = $fetch->fetch();
    // Update session email if changed
    $_SESSION['client_email'] = $client['email'];
    echo json_encode(['success'=>true,'message'=>'Profil mis à jour','client'=>$client]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'Erreur lors de la mise à jour']);
    }
}

function handleChangePassword() {
    global $pdo;
    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success'=>false,'error'=>'Non connecté']);
        return;
    }
    $id = $_SESSION['client_id'];
    $current = $_POST['currentPassword'] ?? '';
    $new = $_POST['newPassword'] ?? '';
    if (empty($current) || empty($new)) {
        echo json_encode(['success'=>false,'error'=>'Tous les champs sont requis']);
        return;
    }
    try {
        // Vérifier mot de passe actuel
        $stmt = $pdo->prepare("SELECT password FROM clients_particuliers WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password'])) {
            echo json_encode(['success'=>false,'error'=>'Mot de passe actuel incorrect']);
            return;
        }
        // Hacher et mettre à jour
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE clients_particuliers SET password=?, date_modification=NOW() WHERE id=?");
        $upd->execute([$hash, $id]);
        echo json_encode(['success'=>true,'message'=>'Mot de passe changé']);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'Erreur lors du changement']);
    }
}
?>
