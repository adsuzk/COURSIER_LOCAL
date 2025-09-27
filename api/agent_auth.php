<?php
// api/agent_auth.php - API d'authentification pour agents/coursiers (agents_suzosky)
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = getDBConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB_ERROR', 'message' => $e->getMessage()]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = [];
if ($raw) {
    $payload = json_decode($raw, true) ?: [];
}
$data = array_merge($_GET, $_POST, $payload);
$action = $data['action'] ?? 'login';

// Logging léger des tentatives (sans mots de passe)
try {
    $idMask = '';
    if (!empty($data['identifier'])) {
        $id = (string)$data['identifier'];
        $idMask = substr($id, 0, 2) . str_repeat('*', max(0, strlen($id) - 4)) . substr($id, -2);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    error_log("AGENT_AUTH attempt method=" . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ", action=$action, id=$idMask, ip=$ip, ua='$ua'");
} catch (Throwable $e) { /* ignore logging errors */ }

switch ($action) {
    case 'login':
        $identifier = trim($data['identifier'] ?? ''); // matricule ou telephone
        $password = (string)($data['password'] ?? '');
        if ($identifier === '' || $password === '') {
            echo json_encode(['success' => false, 'error' => 'MISSING_FIELDS', 'message' => 'Identifiant et mot de passe requis']);
            exit;
        }
        try {
            // Recherche par matricule OU téléphone
            $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE matricule = ? OR telephone = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $agent = $stmt->fetch();
            if (!$agent) {
                echo json_encode(['success' => false, 'error' => 'INVALID_CREDENTIALS', 'message' => 'Identifiant ou mot de passe incorrect']);
                exit;
            }
            // Vérif mot de passe
            $stored = $agent['password'] ?? '';
            $ok = false;
            if (!empty($stored)) {
                $ok = password_verify($password, $stored);
            }
            if (!$ok && !empty($agent['plain_password'])) {
                $ok = hash_equals($agent['plain_password'], $password);
                if ($ok) {
                    // Sécuriser: remplacer plain par hash et vider plain_password après 1ère connexion
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = NULL, updated_at = NOW() WHERE id = ?");
                    try { $upd->execute([$hash, (int)$agent['id']]); } catch (Throwable $ignore) {}
                }
            }
            if (!$ok) {
                echo json_encode(['success' => false, 'error' => 'INVALID_CREDENTIALS', 'message' => 'Identifiant ou mot de passe incorrect']);
                exit;
            }
            // Assurer la colonne de jeton de session côté DB
            try {
                $pdo->exec("ALTER TABLE agents_suzosky
                    ADD COLUMN IF NOT EXISTS current_session_token VARCHAR(100) NULL,
                    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL,
                    ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(64) NULL,
                    ADD COLUMN IF NOT EXISTS last_login_user_agent VARCHAR(255) NULL
                ");
            } catch (Throwable $e) { /* best-effort */ }

            // Générer un nouveau jeton de session et invalider l'ancien
            $newToken = bin2hex(random_bytes(16));
            $loginIp = $_SERVER['REMOTE_ADDR'] ?? null;
            $loginUa = $_SERVER['HTTP_USER_AGENT'] ?? null;
            try {
                $updTok = $pdo->prepare("UPDATE agents_suzosky SET current_session_token = ?, last_login_at = NOW(), last_login_ip = ?, last_login_user_agent = ? WHERE id = ?");
                $updTok->execute([
                    $newToken,
                    $loginIp,
                    $loginUa ? substr($loginUa, 0, 240) : null,
                    (int)$agent['id']
                ]);
            } catch (Throwable $e) { /* ignore */ }

            // Créer session serveur
            $_SESSION['coursier_logged_in'] = true;
            $_SESSION['coursier_id'] = (int)$agent['id'];
            $_SESSION['coursier_table'] = 'agents_suzosky';
            $_SESSION['coursier_matricule'] = $agent['matricule'];
            $_SESSION['coursier_nom'] = trim(($agent['nom'] ?? '') . ' ' . ($agent['prenoms'] ?? ''));
            $_SESSION['coursier_session_token'] = $newToken;
            // Retourner profil minimal
            $profile = [
                'id' => (int)$agent['id'],
                'matricule' => $agent['matricule'],
                'nom' => $agent['nom'] ?? '',
                'prenoms' => $agent['prenoms'] ?? '',
                'telephone' => $agent['telephone'] ?? '',
                'type_poste' => $agent['type_poste'] ?? null,
                'nationalite' => $agent['nationalite'] ?? null,
                'session_token' => $newToken,
                'last_login_ip' => $loginIp,
                'last_login_at' => date('c'),
            ];
            echo json_encode(['success' => true, 'message' => 'Connexion réussie', 'agent' => $profile]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'LOGIN_ERROR', 'message' => $e->getMessage()]);
        }
        break;
    case 'check_session':
        if (!empty($_SESSION['coursier_logged_in']) && !empty($_SESSION['coursier_id'])) {
            $id = (int)$_SESSION['coursier_id'];
            $stmt = $pdo->prepare("SELECT id, matricule, nom, prenoms, telephone, type_poste, nationalite, current_session_token FROM agents_suzosky WHERE id = ?");
            $stmt->execute([$id]);
            if ($row = $stmt->fetch()) {
                $valid = true;
                if (!empty($row['current_session_token'])) {
                    $valid = hash_equals($row['current_session_token'], $_SESSION['coursier_session_token'] ?? '');
                }
                if ($valid) {
                    unset($row['current_session_token']);
                    echo json_encode(['success' => true, 'agent' => $row]);
                    break;
                } else {
                    // Session invalide (connecté ailleurs), forcer déconnexion locale
                    $_SESSION = [];
                    if (ini_get('session.use_cookies')) {
                        $params = session_get_cookie_params();
                        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                    }
                    session_destroy();
                    echo json_encode(['success' => false, 'error' => 'SESSION_REVOKED']);
                    break;
                }
            }
        }
        echo json_encode(['success' => false, 'error' => 'NO_SESSION']);
        break;
    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Déconnexion réussie']);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'UNKNOWN_ACTION']);
}
