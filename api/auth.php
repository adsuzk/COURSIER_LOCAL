<?php
// api/auth.php - API d'authentification pour clients particuliers
require_once __DIR__ . '/../config.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Gestion fine du CORS pour éviter les erreurs "network" sur les requêtes authentifiées
$originHeader = $_SERVER['HTTP_ORIGIN'] ?? null;
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$defaultOrigin = ($isHttps ? 'https' : 'http') . '://' . $host;

// Autoriser uniquement les origines correspondant à l'hôte courant (localhost, prod, etc.)
$allowedOrigin = $defaultOrigin;
if ($originHeader) {
    // On n'autorise l'origine que si elle cible le même hôte (évite ouverture CORS totale)
    $originHost = parse_url($originHeader, PHP_URL_HOST) ?? '';
    if (strcasecmp($originHost, $host) === 0) {
        $allowedOrigin = $originHeader;
    }
}

header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');

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

// Journalisation de diagnostic des sessions (fichier: api/logs/session_debug_YYYY-MM-DD.log)
function sessionDebugLog($event, array $extra = []) {
    try {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        $entry = [
            'ts' => date('c'),
            'event' => $event,
            'php_session_id' => session_id(),
            'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'action' => $_GET['action'] ?? ($_POST['action'] ?? null),
        ];
        $sess = [];
        foreach (['client_id','client_session_token'] as $k) {
            if (isset($_SESSION[$k])) { $sess[$k] = $_SESSION[$k]; }
        }
        $entry['session'] = $sess;
        $entry = array_merge($entry, $extra);
        @file_put_contents($logDir . '/session_debug_' . date('Y-m-d') . '.log', json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* ignore */ }
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

// Assurer les colonnes de gestion de session unique (compatible MySQL 5.7+)
try {
    $needToken = true; $needLastLogin = true; $needLoginIp = true; $needLoginUa = true;
    try {
        $c1 = $pdo->query("SHOW COLUMNS FROM clients_particuliers LIKE 'current_session_token'");
        if ($c1 && $c1->rowCount() > 0) { $needToken = false; }
    } catch (Throwable $ignore) {}
    try {
        $c2 = $pdo->query("SHOW COLUMNS FROM clients_particuliers LIKE 'last_login_at'");
        if ($c2 && $c2->rowCount() > 0) { $needLastLogin = false; }
    } catch (Throwable $ignore) {}
    if ($needToken) {
        $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN current_session_token VARCHAR(100) NULL AFTER password");
    }
    if ($needLastLogin) {
        $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN last_login_at DATETIME NULL AFTER current_session_token");
    }
    try {
        $c3 = $pdo->query("SHOW COLUMNS FROM clients_particuliers LIKE 'last_login_ip'");
        if ($c3 && $c3->rowCount() > 0) { $needLoginIp = false; }
    } catch (Throwable $ignore) {}
    try {
        $c4 = $pdo->query("SHOW COLUMNS FROM clients_particuliers LIKE 'last_login_user_agent'");
        if ($c4 && $c4->rowCount() > 0) { $needLoginUa = false; }
    } catch (Throwable $ignore) {}
    if ($needLoginIp) {
        $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN last_login_ip VARCHAR(64) NULL AFTER last_login_at");
    }
    if ($needLoginUa) {
        $pdo->exec("ALTER TABLE clients_particuliers ADD COLUMN last_login_user_agent VARCHAR(255) NULL AFTER last_login_ip");
    }
} catch (Exception $e) {
    // best-effort
}

// Gérer les données JSON et POST/GET
$input = [];
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $input = json_decode($rawInput, true) ?? [];
}
// Combiner avec $_POST et $_GET, priorité au JSON
$data = array_merge($_GET, $_POST, $input);

$action = $data['action'] ?? '';

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
    global $pdo, $data;
    
    // Support de 'email' et 'phone' comme clés d'entrée
    $login = trim($data['email'] ?? $data['phone'] ?? ''); 
    $password = $data['password'] ?? '';
    
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
            // Générer un token de session unique (priorité à la nouvelle connexion)
            $newToken = bin2hex(random_bytes(16));
            try {
                $tokStmt = $pdo->prepare("UPDATE clients_particuliers SET current_session_token = ?, last_login_at = NOW(), last_login_ip = ?, last_login_user_agent = ? WHERE id = ?");
                $tokStmt->execute([$newToken, ($_SERVER['REMOTE_ADDR'] ?? null), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250), $client['id']]);
            } catch (Throwable $e) { /* ignore */ }
            // Créer la session
            createSession($client, $newToken);
            sessionDebugLog('client_login_first_password', ['client_id' => $client['id'], 'db_token' => $newToken]);
            echo json_encode([
                'success' => true, 
                'message' => 'Mot de passe créé avec succès',
                'client' => [
                    'id' => $client['id'],
                    'nom' => $client['nom'],
                    'prenoms' => $client['prenoms'],
                    'email' => $client['email'],
                    'telephone' => $client['telephone'],
                    'session_token' => $newToken
                ]
            ]);
        } else {
            // Vérifier le mot de passe existant
            if (password_verify($password, $client['password'])) {
                // Nouvelle connexion => invalider les anciennes via un nouveau token
                $newToken = bin2hex(random_bytes(16));
                try {
                    $tokStmt = $pdo->prepare("UPDATE clients_particuliers SET current_session_token = ?, last_login_at = NOW(), last_login_ip = ?, last_login_user_agent = ? WHERE id = ?");
                    $tokStmt->execute([$newToken, ($_SERVER['REMOTE_ADDR'] ?? null), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250), $client['id']]);
                } catch (Throwable $e) { /* ignore */ }
                createSession($client, $newToken);
                sessionDebugLog('client_login', ['client_id' => $client['id'], 'db_token' => $newToken]);
                echo json_encode([
                    'success' => true,
                    'message' => 'Connexion réussie',
                    'client' => [
                        'id' => $client['id'],
                        'nom' => $client['nom'],
                        'prenoms' => $client['prenoms'],
                        'email' => $client['email'],
                        'telephone' => $client['telephone'],
                        'session_token' => $newToken
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

/**
 * Vérifie que la session client courante correspond bien au jeton en base.
 * Si invalide, détruit la session et répond en JSON, puis exit.
 */
function ensureClientSessionValid() {
    if (!isset($_SESSION['client_id'])) {
        echo json_encode(['success' => false, 'error' => 'Non connecté']);
        exit;
    }
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT current_session_token, last_login_at, last_login_ip, last_login_user_agent FROM clients_particuliers WHERE id = ? AND statut = 'actif'");
        $stmt->execute([$_SESSION['client_id']]);
        $row = $stmt->fetch();
        $current = (string)($row['current_session_token'] ?? '');
        $local = (string)($_SESSION['client_session_token'] ?? '');
        if ($current !== '') {
            if ($local === '') {
                // Auto-synchroniser la session locale avec le jeton serveur (compatibilité)
                $_SESSION['client_session_token'] = $current;
                sessionDebugLog('client_session_auto_sync_local_empty', ['client_id' => $_SESSION['client_id'], 'db_token' => $current]);
            } elseif (!hash_equals($current, $local)) {
                // Si la dernière connexion est très récente depuis même IP/UA, auto-sync
                $recent = false;
                try { $recent = (isset($row['last_login_at']) && (time() - strtotime($row['last_login_at'])) <= 15); } catch (Throwable $ignore) {}
                $sameIp = (!empty($row['last_login_ip']) && $row['last_login_ip'] === ($_SERVER['REMOTE_ADDR'] ?? ''));
                $uaNow = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
                $sameUa = (!empty($row['last_login_user_agent']) && $row['last_login_user_agent'] === $uaNow);
                if ($recent && ($sameIp || $sameUa)) {
                    $_SESSION['client_session_token'] = $current;
                    sessionDebugLog('client_session_auto_sync_recent_same', ['client_id' => $_SESSION['client_id'], 'db_token' => $current, 'local_token' => $local]);
                } else {
                    sessionDebugLog('client_session_revoked', ['client_id' => $_SESSION['client_id'], 'db_token' => $current, 'local_token' => $local]);
                    destroySession();
                    echo json_encode(['success' => false, 'error' => 'SESSION_REVOKED']);
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        // En cas d'erreur DB, mieux vaut bloquer l'accès
        echo json_encode(['success' => false, 'error' => 'Erreur de vérification session']);
        exit;
    }
}

function handleRegister() {
    global $pdo, $data;
    
    $nom = trim($data['nom'] ?? '');
    $prenoms = trim($data['prenoms'] ?? '');
    $email = trim($data['email'] ?? '');
    $telephone = trim($data['telephone'] ?? '');
    $password = $data['password'] ?? '';
    
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
        // Générer un token de session unique et créer la session
        $newToken = bin2hex(random_bytes(16));
        try {
            $tokStmt = $pdo->prepare("UPDATE clients_particuliers SET current_session_token = ?, last_login_at = NOW(), last_login_ip = ?, last_login_user_agent = ? WHERE id = ?");
            $tokStmt->execute([$newToken, ($_SERVER['REMOTE_ADDR'] ?? null), substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250), $clientId]);
        } catch (Throwable $e) { /* ignore */ }
        createSession($client, $newToken);
        
        echo json_encode([
            'success' => true,
            'message' => 'Compte créé avec succès',
            'client' => $client + ['session_token' => $newToken]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la création du compte: ' . $e->getMessage()]);
    }
}

function handleCheckSession() {
    if (isset($_SESSION['client_id'])) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, nom, prenoms, email, telephone, current_session_token, last_login_at, last_login_ip, last_login_user_agent FROM clients_particuliers WHERE id = ? AND statut = 'actif'");
            $stmt->execute([$_SESSION['client_id']]);
            $client = $stmt->fetch();
            
            if ($client) {
                // Vérifier la validité du token de session (session unique)
                $currentToken = (string)($client['current_session_token'] ?? '');
                $sessionToken = (string)($_SESSION['client_session_token'] ?? '');
                unset($client['current_session_token']);
                if ($currentToken !== '') {
                    if ($sessionToken === '') {
                        // Auto-synchroniser pour éviter une fausse révocation juste après login
                        $_SESSION['client_session_token'] = $currentToken;
                        sessionDebugLog('client_check_session_auto_sync_local_empty', ['client_id' => $client['id'], 'db_token' => $currentToken]);
                        echo json_encode(['success' => true, 'client' => $client]);
                    } elseif (!hash_equals($currentToken, $sessionToken)) {
                        // Si c'est une reconnexion très récente du même appareil, auto-sync
                        $recent = false;
                        try { $recent = (isset($client['last_login_at']) && (time() - strtotime($client['last_login_at'])) <= 15); } catch (Throwable $ignore) {}
                        $sameIp = (!empty($client['last_login_ip']) && $client['last_login_ip'] === ($_SERVER['REMOTE_ADDR'] ?? ''));
                        $uaNow = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
                        $sameUa = (!empty($client['last_login_user_agent']) && $client['last_login_user_agent'] === $uaNow);
                        if ($recent && ($sameIp || $sameUa)) {
                            $_SESSION['client_session_token'] = $currentToken;
                            sessionDebugLog('client_check_session_auto_sync_recent_same', ['client_id' => $client['id'], 'db_token' => $currentToken, 'local_token' => $sessionToken]);
                            echo json_encode(['success' => true, 'client' => $client]);
                        } else {
                            // La session courante a été révoquée par une nouvelle connexion ailleurs
                            sessionDebugLog('client_check_session_revoked', ['client_id' => $client['id'], 'db_token' => $currentToken, 'local_token' => $sessionToken]);
                            destroySession();
                            echo json_encode(['success' => false, 'error' => 'SESSION_REVOKED', 'message' => 'Connecté sur un autre appareil']);
                        }
                    } else {
                        echo json_encode(['success' => true, 'client' => $client]);
                    }
                } else {
                    echo json_encode(['success' => true, 'client' => $client]);
                }
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
    global $pdo, $data;
    $phone = trim($data['phone'] ?? '');
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

function createSession($client, $token = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['client_id'] = $client['id'];
    $_SESSION['client_email'] = $client['email'];
    $_SESSION['client_nom'] = $client['nom'] . ' ' . $client['prenoms'];
    $_SESSION['client_telephone'] = $client['telephone'];
    $_SESSION['auth_time'] = time();
    if ($token !== null) {
        $_SESSION['client_session_token'] = $token;
    }
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
    ensureClientSessionValid();
    
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
    ensureClientSessionValid();
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
    ensureClientSessionValid();
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
