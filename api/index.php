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

apiLog("Requête reçue: $method $action (role=$role)");

try {
    $pdo = getDBConnection();
    
    switch ($action) {
        // =====================================
        // INSCRIPTION CLIENT PARTICULIER
        // =====================================
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
        // RÉINITIALISATION MOT DE PASSE PARTICULIER
        // =====================================
        case 'particulier_reset_password':
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
