<?php
/**
 * ============================================================================
 * 🌐 API UNIFIÉE SUZOSKY - CENTRALISATION COMPLÈTE
 * ============================================================================
 * 
 * API unique pour toutes les opérations:
 * - Gestion des agents
 * - Chat temps réel
 * - Authentification
 * - Synchronisation
 * 
 * @version 1.0.0
 * @author Équipe Suzosky
 * @date 2025-08-26
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Gestion des requêtes OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration et connexion
require_once __DIR__ . '/../config.php';
// Autoload 3rd-party libs (e.g., PHPMailer)
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Utilitaires: lecture JSON brut et alias de champs pour compatibilité V7
function readJsonBodyIntoPostIfEmpty() {
    if (!empty($_POST)) { return; }
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $k => $v) { $_POST[$k] = $v; }
            }
        }
    }
}

function firstNonEmpty(array $source, array $keys) {
    foreach ($keys as $k) {
        if (isset($source[$k]) && $source[$k] !== '') { return $source[$k]; }
    }
    return '';
}

// Alimenter $_POST à partir du JSON si besoin (compat clients envoyant JSON)
readJsonBodyIntoPostIfEmpty();

// Fonction de logging sécurisé
function apiLog($message, $level = 'INFO') {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logMsg = date('Y-m-d H:i:s') . " [API-$level] " . $message . "\n";
    @file_put_contents($logDir . '/api_' . date('Y-m-d') . '.log', $logMsg, FILE_APPEND | LOCK_EX);
}

// Fonction de réponse standardisée
function apiResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'status' => $success ? 'ok' : 'error',
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Parsing de l'action depuis l'URL ou POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
// Rôle de l'appelant (admin, concierge, coursier, client, particuler)
$role = $_GET['role'] ?? $_POST['role'] ?? '';

// Déterminer identifiant / mot de passe via multiples alias (compat V7)
$identifierAliases = ['identifier','matricule','username','user','telephone','phone','identifiant','login','pseudo'];
$passwordAliases = ['password','pass','pwd','motdepasse','mdp','mot_de_passe'];
$identifierAny = firstNonEmpty($_POST, $identifierAliases);
$passwordAny = firstNonEmpty($_POST, $passwordAliases);

// Fallback: si action manquante mais credentials présents (via alias), considérer comme login
if ($action === '' && $method === 'POST') {
    if (!empty($identifierAny) && !empty($passwordAny)) {
        // Canonicaliser les clés pour le traitement en aval
        $_POST['identifier'] = $identifierAny;
        $_POST['password'] = $passwordAny;
        $action = 'login';
    }
}

// Alias d'actions héritées → action canonique
if (!empty($action)) {
    $map = [
        'logon' => 'login',
        'connexion' => 'login',
        'connect' => 'login',
        'signin' => 'login',
    ];
    $lower = strtolower($action);
    if (isset($map[$lower])) { $action = $map[$lower]; }
}

// Log d'entrée avec masquage identifier
$idMask = '';
if (!empty($identifierAny)) {
    $id = (string)$identifierAny;
    $idMask = substr($id, 0, 2) . str_repeat('*', max(0, strlen($id) - 4)) . substr($id, -2);
}
apiLog("Requête reçue: $method action=$action role=$role id=$idMask ip=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
// Log des clés transmises (sans valeurs sensibles)
try {
    $keys = array_keys($_POST);
    $keys = array_map(function($k){ return $k === 'password' || $k === 'pwd' || $k === 'pass' || $k === 'motdepasse' || $k === 'mdp' ? $k.'(masked)' : $k; }, $keys);
    apiLog('POST keys: ' . implode(',', $keys));
} catch (Throwable $e) { /* ignore */ }

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        // =====================================
        // AUTHENTIFICATION COURSier (compat V7)
        // =====================================
        case 'login':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            // Accepter alias (déjà canonicalisés plus haut si fallback); revalider ici au cas où action est explicitement fourni
            $identifier = trim(firstNonEmpty($_POST, $identifierAliases));
            $password = (string)trim(firstNonEmpty($_POST, $passwordAliases));
            if ($identifier === '' || $password === '') {
                apiResponse(false, null, 'Identifiant et mot de passe requis', 400);
            }
            try {
                $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE matricule = ? OR telephone = ? LIMIT 1");
                $stmt->execute([$identifier, $identifier]);
                $agent = $stmt->fetch();
                if (!$agent) {
                    apiResponse(false, null, 'Identifiants incorrects', 401);
                }

                $stored = $agent['password'] ?? '';
                $ok = false;
                if (!empty($stored)) {
                    $ok = password_verify($password, $stored);
                }
                if (!$ok && !empty($agent['plain_password'])) {
                    $ok = hash_equals($agent['plain_password'], $password);
                    if ($ok) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        try {
                            $upd = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = NULL, updated_at = NOW() WHERE id = ?");
                            $upd->execute([$hash, (int)$agent['id']]);
                        } catch (Throwable $ignore) {}
                    }
                }
                if (!$ok) {
                    apiResponse(false, null, 'Identifiants incorrects', 401);
                }

                if (session_status() === PHP_SESSION_NONE) { session_start(); }
                $_SESSION['coursier_logged_in'] = true;
                $_SESSION['coursier_id'] = (int)$agent['id'];
                $_SESSION['coursier_table'] = 'agents_suzosky';
                $_SESSION['coursier_matricule'] = $agent['matricule'];
                $_SESSION['coursier_nom'] = trim(($agent['nom'] ?? '') . ' ' . ($agent['prenoms'] ?? ''));

                // Politique session unique: générer un token et invalider les anciennes sessions
                try {
                    // Créer colonnes si absentes (best-effort)
                    try { $pdo->exec("ALTER TABLE agents_suzosky ADD COLUMN IF NOT EXISTS current_session_token VARCHAR(100) NULL, ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL"); } catch (Throwable $ignore) {}
                    $newToken = bin2hex(random_bytes(16));
                    $updTok = $pdo->prepare("UPDATE agents_suzosky SET current_session_token = ?, last_login_at = NOW() WHERE id = ?");
                    $updTok->execute([$newToken, (int)$agent['id']]);
                    $_SESSION['coursier_session_token'] = $newToken;
                } catch (Throwable $ignore) {}

                $profile = [
                    'id' => (int)$agent['id'],
                    'matricule' => $agent['matricule'],
                    'nom' => $agent['nom'] ?? '',
                    'prenoms' => $agent['prenoms'] ?? '',
                    'telephone' => $agent['telephone'] ?? '',
                    'type_poste' => $agent['type_poste'] ?? null,
                    'nationalite' => $agent['nationalite'] ?? null,
                ];

                apiResponse(true, ['agent' => $profile, 'redirect' => routePath('coursier.php')], 'Connexion réussie');
            } catch (Throwable $e) {
                apiResponse(false, null, 'Erreur de connexion: ' . $e->getMessage(), 500);
            }
            break;
        // =====================================
        // CONNECTIVITY CHECKS / HEALTH
        // =====================================
        case 'ping':
            // Minimal reachability check
            apiResponse(true, [
                'pong' => true,
                'server_time' => date('c')
            ], 'pong');
            break;

        case 'check_session':
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            if (!empty($_SESSION['coursier_logged_in']) && !empty($_SESSION['coursier_id'])) {
                $id = (int)$_SESSION['coursier_id'];
                $stmt = $pdo->prepare("SELECT id, matricule, nom, prenoms, telephone, type_poste, nationalite, current_session_token FROM agents_suzosky WHERE id = ?");
                $stmt->execute([$id]);
                if ($row = $stmt->fetch()) {
                    $serverTok = (string)($row['current_session_token'] ?? '');
                    $localTok = (string)($_SESSION['coursier_session_token'] ?? '');
                    unset($row['current_session_token']);
                    // Nouvelle politique: la dernière connexion (serveur) prime -> resynchroniser le token local
                    if ($serverTok !== '' && !hash_equals($serverTok, $localTok)) {
                        $_SESSION['coursier_session_token'] = $serverTok;
                    }
                    apiResponse(true, ['agent' => $row], 'Session active');
                }
            }
            apiResponse(false, null, 'Aucune session', 200);
            break;

        case 'logout':
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            apiResponse(true, null, 'Déconnexion réussie');
            break;

        case 'health':
            // Extended health info without leaking secrets
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $baseUrl = rtrim($scheme . '://' . $host . dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'), '/');

            $data = [
                'ok' => true,
                'server_time' => date('c'),
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'server_ip' => @gethostbyname(gethostname()),
                'host' => $host,
                'scheme' => $scheme,
                'base_url' => $baseUrl,
                'paths' => [
                    'api_dir' => __DIR__,
                    'app_root' => realpath(__DIR__ . '/..'),
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? null,
                ],
                'runtime' => [
                    'php_version' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                ],
                'apache' => [
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ],
            ];

            // Database connectivity probe
            try {
                $db = getDBConnection();
                $stmt = $db->query('SELECT 1');
                $data['db'] = [
                    'connected' => $stmt !== false,
                    'driver' => $db->getAttribute(PDO::ATTR_DRIVER_NAME),
                ];
            } catch (Throwable $e) {
                $data['db'] = [
                    'connected' => false,
                    'error' => $e->getMessage(),
                ];
            }

            apiResponse(true, $data, 'OK');
            break;


        case 'client_signup':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            $nom = trim($_POST['name'] ?? '');
            $prenoms = trim($_POST['firstName'] ?? '');
            $telephone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            if (empty($nom) || empty($prenoms) || empty($telephone)) {
                apiResponse(false, null, 'Nom, prénom et téléphone requis', 400);
            }
            // Insertion
            $stmt = $pdo->prepare(
                'INSERT INTO clients_particuliers (nom, prenoms, telephone, email) VALUES (?, ?, ?, ?)'
            );
            if ($stmt->execute([$nom, $prenoms, $telephone, $email])) {
                $clientId = $pdo->lastInsertId();
                apiResponse(true, ['id' => $clientId], 'Inscription réussie');
            } else {
                apiResponse(false, null, 'Erreur lors de l\'inscription', 500);
            }
            break;
        
        // =====================================
        // GESTION DES AGENTS
        // =====================================
        
        case 'agents_list':
            $search = $_GET['search'] ?? '';
            $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            
            $whereClause = '';
            $params = [];
            
            if (!empty($search)) {
                $whereClause = "WHERE nom LIKE ? OR telephone LIKE ?";
                $params = ["%$search%", "%$search%"];
            }
            
            // Compter le total
            $countSql = "SELECT COUNT(*) FROM agents $whereClause";
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Récupérer les agents
            // Tenter d'inclure le mot de passe pour l’admin, fallback si colonne absente
            // Récupérer la liste des agents (sans colonne 'disponible')
            $sql = "SELECT id, nom, telephone, created_at, password FROM agents $whereClause ORDER BY nom LIMIT $limit OFFSET $offset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $agents = $stmt->fetchAll();
            
            apiResponse(true, [
                'agents' => $agents,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ], 'Liste des agents récupérée');
            break;
            
        case 'agent_create':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            $nom = trim($_POST['nom'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            if (empty($nom) || empty($telephone)) {
                apiResponse(false, null, 'Nom et téléphone requis', 400);
            }
            // Vérifier unicité du téléphone
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents WHERE telephone = ?");
            $stmt->execute([$telephone]);
            if ($stmt->fetchColumn() > 0) {
                apiResponse(false, null, 'Ce numéro de téléphone existe déjà', 409);
            }
            // Générer et hasher le mot de passe
            $plainPwd = generateUnifiedAgentPassword();
            $hashPwd = password_hash($plainPwd, PASSWORD_BCRYPT);
            // Insérer l'agent
            // Insérer l'agent (sans champ 'disponible')
            $stmt = $pdo->prepare(
                "INSERT INTO agents (nom, telephone, password, created_at) VALUES (?, ?, ?, NOW())"
            );
            if ($stmt->execute([$nom, $telephone, $hashPwd])) {
                $agentId = $pdo->lastInsertId();
                apiLog("Agent créé: ID=$agentId, nom=$nom, tel=$telephone");
                apiResponse(true, [
                    'id' => $agentId,
                    'nom' => $nom,
                    'telephone' => $telephone,
                    'disponible' => true
                ], 'Agent créé avec succès');
            } else {
                apiResponse(false, null, 'Erreur lors de la création', 500);
            }
            break;
            
        case 'agent_update':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            
            $id = (int)($_POST['id'] ?? 0);
            $nom = trim($_POST['nom'] ?? '');
            $telephone = trim($_POST['telephone'] ?? '');
            $disponible = isset($_POST['disponible']) ? (bool)$_POST['disponible'] : true;
            
            if ($id <= 0) {
                apiResponse(false, null, 'ID agent invalide', 400);
            }
            
            // Vérifier que l'agent existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() == 0) {
                apiResponse(false, null, 'Agent non trouvé', 404);
            }
            
            // Mettre à jour
            // Mettre à jour l'agent (sans colonne 'disponible')
            $stmt = $pdo->prepare(
                "UPDATE agents SET nom = ?, telephone = ?, updated_at = NOW() WHERE id = ?"
            );
            if ($stmt->execute([$nom, $telephone, $id])) {
                apiLog("Agent mis à jour: ID=$id");
                apiResponse(true, null, 'Agent mis à jour avec succès');
            } else {
                apiResponse(false, null, 'Erreur lors de la mise à jour', 500);
            }
            break;
            
        case 'agent_delete':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                apiResponse(false, null, 'ID agent invalide', 400);
            }
            
            $stmt = $pdo->prepare("DELETE FROM agents WHERE id = ?");
            if ($stmt->execute([$id])) {
                apiLog("Agent supprimé: ID=$id");
                apiResponse(true, null, 'Agent supprimé avec succès');
            } else {
                apiResponse(false, null, 'Erreur lors de la suppression', 500);
            }
            break;

        // =====================================
        // RÉINITIALISATION MOT DE PASSE AGENT
        // =====================================
        case 'agent_reset_password':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                apiResponse(false, null, 'ID agent invalide', 400);
            }
            // Vérifier que l'agent existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM agents WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() === 0) {
                apiResponse(false, null, 'Agent non trouvé', 404);
            }
            // Générer et hasher nouveau mot de passe
            $newPwd = generateUnifiedAgentPassword();
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $update = $pdo->prepare("UPDATE agents SET password = ?, updated_at = NOW() WHERE id = ?");
            if ($update->execute([$hash, $id])) {
                apiLog("Mot de passe agent réinitialisé: ID=$id");
                apiResponse(true, ['password' => $newPwd], 'Mot de passe réinitialisé');
            } else {
                apiResponse(false, null, 'Erreur lors de la réinitialisation', 500);
            }
            break;

        // =====================================
        // RÉINITIALISATION MOT DE PASSE BUSINESS CLIENT
        // =====================================
        case 'business_client_reset_password':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            if ($role !== 'admin') {
                apiResponse(false, null, 'Accès refusé', 403);
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                apiResponse(false, null, 'ID client invalide', 400);
            }
            // Vérifier existence
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_clients WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() === 0) {
                apiResponse(false, null, 'Client non trouvé', 404);
            }
            $newPwd = generateUnifiedAgentPassword();
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE business_clients SET password = ? WHERE id = ?");
            if ($upd->execute([$hash, $id])) {
                apiLog("Mot de passe business client réinitialisé: ID=$id");
                apiResponse(true, ['password' => $newPwd], 'Mot de passe réinitialisé');
            }
            apiResponse(false, null, 'Erreur lors de la réinitialisation', 500);
            break;

        // =====================================
        // RÉINITIALISATION MOT DE PASSE PARTICULIER ADMIN (ID-based)
        // =====================================
        case 'admin_reset_particulier_password':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Méthode non autorisée', 405);
            }
            $id = (int)($_POST['id'] ?? 0);
            $isOwner = (isset($_POST['username']) && $_POST['username'] === ($_SESSION['username'] ?? ''));
            if ($role !== 'admin' && !$isOwner) {
                apiResponse(false, null, 'Accès refusé', 403);
            }
            if ($id <= 0) {
                apiResponse(false, null, 'ID particulier invalide', 400);
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients_particuliers WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() === 0) {
                apiResponse(false, null, 'Particulier non trouvé', 404);
            }
            $newPwd = generateUnifiedAgentPassword();
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $upd = $pdo->prepare("UPDATE clients_particuliers SET password = ? WHERE id = ?");
            if ($upd->execute([$hash, $id])) {
                apiLog("Mot de passe particulier réinitialisé: ID=$id");
                apiResponse(true, ['password' => $newPwd], 'Mot de passe réinitialisé');
            }
            apiResponse(false, null, 'Erreur lors de la réinitialisation', 500);
            break;

        default:
            // Délai de compatibilité: si payload ressemble à un login, router vers login
            // Délai de compatibilité étendu: tenter login si alias présents
            $identifierAny = firstNonEmpty($_POST, ['identifier','matricule','username','user','telephone','phone']);
            $passwordAny = firstNonEmpty($_POST, ['password','pass','pwd']);
            if ($method === 'POST' && !empty($identifierAny) && !empty($passwordAny)) {
                $_POST['action'] = 'login';
                $_POST['identifier'] = $identifierAny;
                $_POST['password'] = $passwordAny;
                // Relancer le switch en interne
                $action = 'login';
                // Utiliser goto-like simple via une fonction anonyme
                (function() use ($pdo) {
                    $identifier = trim($_POST['identifier'] ?? '');
                    $password = (string)($_POST['password'] ?? '');
                    if ($identifier === '' || $password === '') {
                        apiResponse(false, null, 'Identifiant et mot de passe requis', 400);
                    }
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM agents_suzosky WHERE matricule = ? OR telephone = ? LIMIT 1");
                        $stmt->execute([$identifier, $identifier]);
                        $agent = $stmt->fetch();
                        if (!$agent) { apiResponse(false, null, 'Identifiants incorrects', 401); }
                        $stored = $agent['password'] ?? '';
                        $ok = !empty($stored) && password_verify($password, $stored);
                        if (!$ok && !empty($agent['plain_password'])) {
                            $ok = hash_equals($agent['plain_password'], $password);
                            if ($ok) {
                                try {
                                    $hash = password_hash($password, PASSWORD_DEFAULT);
                                    $upd = $pdo->prepare("UPDATE agents_suzosky SET password = ?, plain_password = NULL, updated_at = NOW() WHERE id = ?");
                                    $upd->execute([$hash, (int)$agent['id']]);
                                } catch (Throwable $ignore) {}
                            }
                        }
                        if (!$ok) { apiResponse(false, null, 'Identifiants incorrects', 401); }
                        if (session_status() === PHP_SESSION_NONE) { session_start(); }
                        $_SESSION['coursier_logged_in'] = true;
                        $_SESSION['coursier_id'] = (int)$agent['id'];
                        $_SESSION['coursier_table'] = 'agents_suzosky';
                        $_SESSION['coursier_matricule'] = $agent['matricule'];
                        $_SESSION['coursier_nom'] = trim(($agent['nom'] ?? '') . ' ' . ($agent['prenoms'] ?? ''));
                        // Politique session unique dans le fallback également
                        try {
                            try { $pdo->exec("ALTER TABLE agents_suzosky ADD COLUMN IF NOT EXISTS current_session_token VARCHAR(100) NULL, ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL"); } catch (Throwable $ignore) {}
                            $newToken = bin2hex(random_bytes(16));
                            $updTok = $pdo->prepare("UPDATE agents_suzosky SET current_session_token = ?, last_login_at = NOW() WHERE id = ?");
                            $updTok->execute([$newToken, (int)$agent['id']]);
                            $_SESSION['coursier_session_token'] = $newToken;
                        } catch (Throwable $ignore) {}
                        apiResponse(true, ['agent' => ['id' => (int)$agent['id']]], 'Connexion réussie');
                    } catch (Throwable $e) {
                        apiResponse(false, null, 'Erreur de connexion: ' . $e->getMessage(), 500);
                    }
                })();
                // ne devrait jamais atteindre ici
                exit;
            }
            // Si une session est active et aucune action fournie, retourner un OK minimal (évite "Action non reconnue" côté V7)
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            if (!empty($_SESSION['coursier_logged_in']) && !empty($_SESSION['coursier_id'])) {
                // Optionnel: faire une auto-sync du token si manquant avant de renvoyer OK
                try {
                    $id = (int)$_SESSION['coursier_id'];
                    $st = $pdo->prepare("SELECT current_session_token FROM agents_suzosky WHERE id = ?");
                    $st->execute([$id]);
                    $serverTok = (string)($st->fetchColumn() ?: '');
                    if ($serverTok !== '' && empty($_SESSION['coursier_session_token'])) {
                        $_SESSION['coursier_session_token'] = $serverTok;
                    }
                } catch (Throwable $ignore) {}
                apiResponse(true, ['session' => 'ok', 'agent_id' => (int)$_SESSION['coursier_id']], 'OK');
            }
            apiResponse(false, null, 'Action non reconnue', 400);
    }
    
} catch (PDOException $e) {
    apiLog("Erreur BDD: " . $e->getMessage(), 'ERROR');
    // Detailed message for debugging
    apiResponse(false, null, 'Erreur de base de données: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    apiLog("Erreur: " . $e->getMessage(), 'ERROR');
    apiResponse(false, null, 'Erreur interne: ' . $e->getMessage(), 500);
}
?>
