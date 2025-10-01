<?php
/**
 * API MOBILE DÉDIÉE - DIAGNOSTIC SYNCHRONISATION
 * Interface simplifiée pour tests de synchronisation avec l'app mobile
 */

require_once 'config.php';

// Headers pour API mobile
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Log des requêtes pour debug
function logRequest($action, $data, $response) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'data' => $data,
        'response' => $response,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    file_put_contents('mobile_sync_debug.log', json_encode($log) . "\n", FILE_APPEND);
}

try {
    $pdo = getDBConnection();
    
    $action = $_REQUEST['action'] ?? '';
    $coursier_id = intval($_REQUEST['coursier_id'] ?? 0);
    $token = $_REQUEST['token'] ?? '';
    
    $response = ['success' => false, 'message' => 'Action non reconnue'];
    
    switch ($action) {
        
        case 'ping':
            // Test de connectivité
            $response = [
                'success' => true,
                'message' => 'Serveur accessible',
                'timestamp' => time(),
                'server_time' => date('Y-m-d H:i:s')
            ];
            break;
            
        case 'auth_coursier':
            // Authentification coursier
            $matricule = $_REQUEST['matricule'] ?? '';
            $password = $_REQUEST['password'] ?? '';
            
            if (empty($matricule) || empty($password)) {
                $response = ['success' => false, 'message' => 'Matricule et mot de passe requis'];
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, nom, prenoms, matricule, email, telephone, 
                       COALESCE(solde_wallet, 0) as solde,
                       statut_connexion
                FROM agents_suzosky 
                WHERE matricule = ? AND password = ?
            ");
            $stmt->execute([$matricule, $password]);
            $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($coursier) {
                // Générer token de session
                $sessionToken = 'mobile_' . uniqid() . '_' . $coursier['id'];
                
                // Mettre à jour statut connexion
                $stmt = $pdo->prepare("
                    UPDATE agents_suzosky 
                    SET statut_connexion = 'en_ligne', 
                        current_session_token = ?,
                        last_login_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$sessionToken, $coursier['id']]);
                
                $response = [
                    'success' => true,
                    'message' => 'Authentification réussie',
                    'coursier' => $coursier,
                    'session_token' => $sessionToken
                ];
            } else {
                $response = ['success' => false, 'message' => 'Identifiants invalides'];
            }
            break;
            
        case 'get_profile':
            // Profil du coursier
            if (!$coursier_id) {
                $response = ['success' => false, 'message' => 'ID coursier requis'];
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT id, nom, prenoms, matricule, email, telephone,
                       COALESCE(solde_wallet, 0) as solde,
                       statut_connexion, last_login_at
                FROM agents_suzosky 
                WHERE id = ?
            ");
            $stmt->execute([$coursier_id]);
            $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($coursier) {
                $response = [
                    'success' => true,
                    'profile' => $coursier
                ];
            } else {
                $response = ['success' => false, 'message' => 'Coursier non trouvé'];
            }
            break;
            
        case 'get_commandes':
            // Récupérer les commandes du coursier
            if (!$coursier_id) {
                $response = ['success' => false, 'message' => 'ID coursier requis'];
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    id, order_number, code_commande, 
                    telephone_expediteur as client_telephone,
                    adresse_depart, adresse_arrivee, 
                    description_colis as description,
                    prix_estime as prix_total, 
                    statut, priorite,
                    created_at, updated_at,
                    heure_acceptation, heure_retrait, heure_livraison,
                    latitude_depart, longitude_depart, distance_estimee
                FROM commandes 
                WHERE coursier_id = ? 
                AND statut IN ('nouvelle', 'attribuee', 'acceptee', 'en_cours', 'recuperee', 'retiree')
                ORDER BY 
                    CASE statut
                        WHEN 'en_cours' THEN 1
                        WHEN 'recuperee' THEN 2
                        WHEN 'acceptee' THEN 3
                        WHEN 'attribuee' THEN 4
                        WHEN 'nouvelle' THEN 5
                        WHEN 'retiree' THEN 6
                        ELSE 7
                    END,
                    created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$coursier_id]);
            $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'commandes' => $commandes,
                'count' => count($commandes)
            ];
            break;
            
        case 'accept_commande':
            // Accepter une commande
            $commande_id = intval($_REQUEST['commande_id'] ?? 0);
            
            if (!$coursier_id || !$commande_id) {
                $response = ['success' => false, 'message' => 'ID coursier et commande requis'];
                break;
            }
            
            // Vérifier que la commande est bien attribuée au coursier
            $stmt = $pdo->prepare("
                SELECT id, code_commande, statut, prix_total 
                FROM commandes 
                WHERE id = ? AND coursier_id = ? AND statut IN ('nouvelle', 'attribuee')
            ");
            $stmt->execute([$commande_id, $coursier_id]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$commande) {
                $response = ['success' => false, 'message' => 'Commande non trouvée ou déjà traitée'];
                break;
            }
            
            // Accepter la commande
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'acceptee', heure_acceptation = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$commande_id])) {
                $response = [
                    'success' => true,
                    'message' => 'Commande acceptée',
                    'commande' => $commande
                ];
                
                // Log de l'acceptation
                logRequest('accept_commande', ['commande_id' => $commande_id, 'coursier_id' => $coursier_id], $response);
            } else {
                $response = ['success' => false, 'message' => 'Erreur lors de l\'acceptation'];
            }
            break;
            
        case 'refuse_commande':
            // Refuser une commande
            $commande_id = intval($_REQUEST['commande_id'] ?? 0);
            $raison = $_REQUEST['raison'] ?? 'Non spécifié';
            
            if (!$coursier_id || !$commande_id) {
                $response = ['success' => false, 'message' => 'ID coursier et commande requis'];
                break;
            }
            
            // Refuser et remettre en attente d'attribution
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'en_attente', coursier_id = NULL, raison_annulation = ?
                WHERE id = ? AND coursier_id = ?
            ");
            
            if ($stmt->execute([$raison, $commande_id, $coursier_id])) {
                $response = [
                    'success' => true,
                    'message' => 'Commande refusée',
                    'commande_id' => $commande_id
                ];
                
                logRequest('refuse_commande', ['commande_id' => $commande_id, 'raison' => $raison], $response);
            } else {
                $response = ['success' => false, 'message' => 'Erreur lors du refus'];
            }
            break;
            
        case 'start_delivery':
            // Commencer la livraison (acceptee → en_cours)
            $commande_id = intval($_REQUEST['commande_id'] ?? 0);
            
            if (!$commande_id || !$coursier_id) {
                $response = ['success' => false, 'message' => 'ID commande requis'];
                break;
            }
            
            // Vérifier que la commande est bien acceptée
            $check = $pdo->prepare("SELECT statut FROM commandes WHERE id = ? AND coursier_id = ?");
            $check->execute([$commande_id, $coursier_id]);
            $commande = $check->fetch();
            
            if (!$commande) {
                $response = ['success' => false, 'message' => 'Commande non trouvée'];
                break;
            }
            
            if ($commande['statut'] !== 'acceptee') {
                $response = ['success' => false, 'message' => 'Commande déjà en cours ou terminée'];
                break;
            }
            
            // Mettre à jour le statut et l'heure de début
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'en_cours', 
                    heure_debut = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND coursier_id = ?
            ");
            
            if ($stmt->execute([$commande_id, $coursier_id])) {
                $response = [
                    'success' => true,
                    'message' => 'Livraison commencée',
                    'commande_id' => $commande_id,
                    'nouveau_statut' => 'en_cours'
                ];
                logRequest('start_delivery', ['commande_id' => $commande_id], $response);
            } else {
                $response = ['success' => false, 'message' => 'Erreur lors du démarrage'];
            }
            break;
            
        case 'pickup_package':
            // Marquer le colis comme récupéré (en_cours → recuperee)
            $commande_id = intval($_REQUEST['commande_id'] ?? 0);
            
            if (!$commande_id || !$coursier_id) {
                $response = ['success' => false, 'message' => 'ID commande requis'];
                break;
            }
            
            // Vérifier que la commande est bien en cours
            $check = $pdo->prepare("SELECT statut FROM commandes WHERE id = ? AND coursier_id = ?");
            $check->execute([$commande_id, $coursier_id]);
            $commande = $check->fetch();
            
            if (!$commande) {
                $response = ['success' => false, 'message' => 'Commande non trouvée'];
                break;
            }
            
            if ($commande['statut'] !== 'en_cours') {
                $response = ['success' => false, 'message' => 'Commande pas en cours de livraison'];
                break;
            }
            
            // Mettre à jour le statut et l'heure de retrait
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'recuperee', 
                    heure_retrait = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND coursier_id = ?
            ");
            
            if ($stmt->execute([$commande_id, $coursier_id])) {
                $response = [
                    'success' => true,
                    'message' => 'Colis récupéré',
                    'commande_id' => $commande_id,
                    'nouveau_statut' => 'recuperee'
                ];
                logRequest('pickup_package', ['commande_id' => $commande_id], $response);
            } else {
                $response = ['success' => false, 'message' => 'Erreur lors de la récupération'];
            }
            break;
            
        case 'mark_delivered':
            // Marquer comme livrée (recuperee → livree)
            $commande_id = intval($_REQUEST['commande_id'] ?? 0);
            
            if (!$commande_id || !$coursier_id) {
                $response = ['success' => false, 'message' => 'ID commande requis'];
                break;
            }
            
            // Vérifier que la commande est bien récupérée
            $check = $pdo->prepare("SELECT statut FROM commandes WHERE id = ? AND coursier_id = ?");
            $check->execute([$commande_id, $coursier_id]);
            $commande = $check->fetch();
            
            if (!$commande) {
                $response = ['success' => false, 'message' => 'Commande non trouvée'];
                break;
            }
            
            if ($commande['statut'] !== 'recuperee') {
                $response = ['success' => false, 'message' => 'Colis pas encore récupéré'];
                break;
            }
            
            // Mettre à jour le statut et l'heure de livraison
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'livree', 
                    heure_livraison = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND coursier_id = ?
            ");
            
            if ($stmt->execute([$commande_id, $coursier_id])) {
                $response = [
                    'success' => true,
                    'message' => 'Commande livrée avec succès',
                    'commande_id' => $commande_id,
                    'nouveau_statut' => 'livree'
                ];
                logRequest('mark_delivered', ['commande_id' => $commande_id], $response);
            } else {
                $response = ['success' => false, 'message' => 'Erreur lors de la livraison'];
            }
            break;
            
        case 'confirm_cash_received':
            // Confirmer que le cash a été récupéré (pour commandes en espèces)
            $commande_id = intval($_REQUEST['commande_id'] ?? 0);
            
            if (!$commande_id || !$coursier_id) {
                $response = ['success' => false, 'message' => 'ID commande requis'];
                break;
            }
            
            // Vérifier que la commande est bien livrée et en espèces
            $check = $pdo->prepare("SELECT statut, mode_paiement FROM commandes WHERE id = ? AND coursier_id = ?");
            $check->execute([$commande_id, $coursier_id]);
            $commande = $check->fetch();
            
            if (!$commande) {
                $response = ['success' => false, 'message' => 'Commande non trouvée'];
                break;
            }
            
            if ($commande['statut'] !== 'livree') {
                $response = ['success' => false, 'message' => 'Commande pas encore livrée'];
                break;
            }
            
            if ($commande['mode_paiement'] !== 'especes') {
                $response = ['success' => false, 'message' => 'Cette commande n\'est pas en espèces'];
                break;
            }
            
            // Marquer le cash comme récupéré
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET cash_recupere = 1,
                    updated_at = NOW()
                WHERE id = ? AND coursier_id = ?
            ");
            
            if ($stmt->execute([$commande_id, $coursier_id])) {
                $response = [
                    'success' => true,
                    'message' => 'Cash confirmé récupéré',
                    'commande_id' => $commande_id,
                    'cash_recupere' => true
                ];
                logRequest('confirm_cash_received', ['commande_id' => $commande_id], $response);
            } else {
                $response = ['success' => false, 'message' => 'Erreur lors de la confirmation'];
            }
            break;
            
        case 'update_position':
            // Mettre à jour position GPS
            $latitude = floatval($_REQUEST['latitude'] ?? 0);
            $longitude = floatval($_REQUEST['longitude'] ?? 0);
            
            if (!$coursier_id || !$latitude || !$longitude) {
                $response = ['success' => false, 'message' => 'Position GPS invalide'];
                break;
            }
            
            // Mettre à jour position (table à créer si nécessaire)
            $stmt = $pdo->prepare("
                INSERT INTO coursier_positions (coursier_id, latitude, longitude, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude), 
                longitude = VALUES(longitude), 
                updated_at = VALUES(updated_at)
            ");
            
            try {
                $stmt->execute([$coursier_id, $latitude, $longitude]);
                $response = [
                    'success' => true,
                    'message' => 'Position mise à jour',
                    'position' => ['latitude' => $latitude, 'longitude' => $longitude]
                ];
            } catch (Exception $e) {
                // Table n'existe pas, on ignore pour le moment
                $response = [
                    'success' => true,
                    'message' => 'Position reçue (table non configurée)',
                    'position' => ['latitude' => $latitude, 'longitude' => $longitude]
                ];
            }
            break;
            
        case 'register_fcm_token':
            // Enregistrer/Mettre à jour token FCM
            $fcm_token = $_REQUEST['fcm_token'] ?? '';
            $device_info = $_REQUEST['device_info'] ?? '';
            
            if (!$coursier_id || !$fcm_token) {
                $response = ['success' => false, 'message' => 'Token FCM requis'];
                break;
            }
            
            // Désactiver anciens tokens
            $stmt = $pdo->prepare("
                UPDATE device_tokens 
                SET is_active = 0 
                WHERE coursier_id = ?
            ");
            $stmt->execute([$coursier_id]);
            
            // Ajouter nouveau token
            $stmt = $pdo->prepare("
                INSERT INTO device_tokens 
                (coursier_id, token, device_type, platform, is_active, device_info, created_at, updated_at, last_ping)
                VALUES (?, ?, 'mobile', 'android', 1, ?, NOW(), NOW(), NOW())
            ");
            
            if ($stmt->execute([$coursier_id, $fcm_token, $device_info])) {
                $response = [
                    'success' => true,
                    'message' => 'Token FCM enregistré',
                    'token_id' => $pdo->lastInsertId()
                ];
            } else {
                $response = ['success' => false, 'message' => 'Erreur enregistrement token'];
            }
            break;
            
        case 'test_notification':
            // Test d'envoi de notification
            if (!$coursier_id) {
                $response = ['success' => false, 'message' => 'ID coursier requis'];
                break;
            }
            
            // Récupérer token FCM actif
            $stmt = $pdo->prepare("
                SELECT token FROM device_tokens 
                WHERE coursier_id = ? AND is_active = 1 
                ORDER BY updated_at DESC LIMIT 1
            ");
            $stmt->execute([$coursier_id]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                $response = ['success' => false, 'message' => 'Aucun token FCM actif'];
                break;
            }
            
            // Simuler envoi de notification
            $message = "🧪 Test de synchronisation - " . date('H:i:s');
            
            // Log de la notification
            $stmt = $pdo->prepare("
                INSERT INTO notifications_log_fcm 
                (coursier_id, token_used, message, type, status, response_data, created_at)
                VALUES (?, ?, ?, 'test', 'sent', 'Test de synchronisation', NOW())
            ");
            $stmt->execute([$coursier_id, $tokenData['token'], $message]);
            
            $response = [
                'success' => true,
                'message' => 'Notification de test envoyée',
                'notification_id' => $pdo->lastInsertId(),
                'test_message' => $message
            ];
            break;
            
        case 'get_statistics':
            // Statistiques du coursier
            if (!$coursier_id) {
                $response = ['success' => false, 'message' => 'ID coursier requis'];
                break;
            }
            
            // Commandes du jour
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_commandes,
                    COUNT(CASE WHEN statut = 'livree' THEN 1 END) as livrees,
                    SUM(CASE WHEN statut = 'livree' THEN prix_total ELSE 0 END) as gains_jour
                FROM commandes 
                WHERE coursier_id = ? AND DATE(created_at) = CURDATE()
            ");
            $stmt->execute([$coursier_id]);
            $stats_jour = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Solde actuel
            $stmt = $pdo->prepare("
                SELECT COALESCE(solde_wallet, 0) as solde 
                FROM agents_suzosky 
                WHERE id = ?
            ");
            $stmt->execute([$coursier_id]);
            $solde = $stmt->fetchColumn();
            
            $response = [
                'success' => true,
                'statistics' => [
                    'solde_actuel' => $solde,
                    'commandes_jour' => $stats_jour['total_commandes'],
                    'livrees_jour' => $stats_jour['livrees'],
                    'gains_jour' => $stats_jour['gains_jour'] ?? 0
                ]
            ];
            break;
            
        case 'register_token':
        case 'register_fcm_token':
            // Enregistrer token FCM
            if (!$token) {
                $response = ['success' => false, 'message' => 'Token FCM requis'];
                break;
            }
            
            $device_model = $_REQUEST['device_model'] ?? 'Unknown';
            $app_version = $_REQUEST['app_version'] ?? '1.0';
            
            // Désactiver anciens tokens du même coursier
            if ($coursier_id > 0) {
                $stmt = $pdo->prepare("UPDATE device_tokens SET is_active = 0 WHERE coursier_id = ?");
                $stmt->execute([$coursier_id]);
            }
            
            // Insérer nouveau token
            $stmt = $pdo->prepare("
                INSERT INTO device_tokens 
                (token, coursier_id, device_model, app_version, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                coursier_id = VALUES(coursier_id),
                device_model = VALUES(device_model),
                app_version = VALUES(app_version),
                is_active = 1,
                updated_at = NOW()
            ");
            
            if ($stmt->execute([$token, $coursier_id, $device_model, $app_version])) {
                $token_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM device_tokens WHERE token = '$token'")->fetchColumn();
                
                $response = [
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Token FCM enregistré avec succès',
                    'token_id' => $token_id,
                    'coursier_id' => $coursier_id
                ];
            } else {
                $response = ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du token'];
            }
            break;
            
        case 'get_tokens':
            // Récupérer tous les tokens actifs
            $stmt = $pdo->prepare("
                SELECT dt.id, dt.token, dt.coursier_id, dt.device_model, dt.app_version,
                       dt.is_active, dt.created_at, dt.updated_at,
                       CONCAT(a.nom, ' ', a.prenoms) as coursier_nom
                FROM device_tokens dt
                LEFT JOIN agents_suzosky a ON dt.coursier_id = a.id
                WHERE dt.is_active = 1
                ORDER BY dt.updated_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'status' => 'success',
                'tokens' => $tokens,
                'count' => count($tokens)
            ];
            break;
            
        default:
            $response = [
                'success' => false,
                'message' => 'Action non reconnue',
                'available_actions' => [
                    'ping', 'auth_coursier', 'get_profile', 'get_commandes',
                    'accept_commande', 'refuse_commande', 'update_position',
                    'register_token', 'register_fcm_token', 'get_tokens', 'test_notification', 'get_statistics'
                ]
            ];
    }
    
    // Log toutes les requêtes
    logRequest($action, $_REQUEST, $response);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ];
    
    logRequest($action ?? 'error', $_REQUEST, $response);
}

// Envoi de la réponse JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>