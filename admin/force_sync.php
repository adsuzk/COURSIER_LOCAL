<?php
/**
 * SUZOSKY ADMIN - SYNCHRONISATION FORCÉE DES COMMANDES
 * Script pour corriger les désynchronisations entre admin et application mobile
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// Vérifier l'authentification admin
session_start();
if (!checkAdminAuth()) {
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$action = $_POST['action'] ?? '';
$commandeId = (int)($_POST['commande_id'] ?? 0);

if (!$commandeId) {
    echo json_encode(['error' => 'ID commande manquant']);
    exit;
}

try {
    $pdo = getDBConnection();
    $results = [];
    
    switch ($action) {
        case 'sync_all_tables':
            // Synchroniser entre commandes et commandes_classiques
            
            // 1. Récupérer l'état de référence (table principale)
            $stmt = $pdo->prepare("SELECT * FROM commandes WHERE id = ?");
            $stmt->execute([$commandeId]);
            $mainRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($mainRecord) {
                // Mettre à jour commandes_classiques
                $updateClassic = $pdo->prepare("
                    UPDATE commandes_classiques 
                    SET statut = ?, 
                        coursier_id = ?, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateClassic->execute([
                    $mainRecord['statut'],
                    $mainRecord['coursier_id'],
                    $commandeId
                ]);
                $results[] = "Table commandes_classiques synchronisée";
            } else {
                // Vérifier dans commandes_classiques
                $stmt = $pdo->prepare("SELECT * FROM commandes_classiques WHERE id = ?");
                $stmt->execute([$commandeId]);
                $classicRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($classicRecord) {
                    // Créer ou mettre à jour dans commandes principale
                    $insertMain = $pdo->prepare("
                        INSERT INTO commandes (
                            id, code_commande, statut, coursier_id, client_id,
                            adresse_depart, adresse_arrivee, prix_estime,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            statut = VALUES(statut),
                            coursier_id = VALUES(coursier_id),
                            updated_at = NOW()
                    ");
                    $insertMain->execute([
                        $classicRecord['id'],
                        $classicRecord['code_commande'],
                        $classicRecord['statut'],
                        $classicRecord['coursier_id'],
                        $classicRecord['client_id'],
                        $classicRecord['adresse_depart'],
                        $classicRecord['adresse_arrivee'],
                        $classicRecord['prix_estime'],
                        $classicRecord['created_at']
                    ]);
                    $results[] = "Table commandes principale synchronisée";
                }
            }
            break;
            
        case 'refresh_coursier_status':
            // Forcer la mise à jour du statut du coursier
            $stmt = $pdo->prepare("
                SELECT coursier_id FROM commandes 
                WHERE id = ? AND coursier_id IS NOT NULL
            ");
            $stmt->execute([$commandeId]);
            $coursierId = $stmt->fetchColumn();
            
            if ($coursierId) {
                // Vérifier les tokens FCM récents
                $tokenCheck = $pdo->prepare("
                    SELECT COUNT(*) as active_count,
                           MAX(last_used_at) as last_activity
                    FROM fcm_tokens 
                    WHERE user_id = ? 
                    AND user_type = 'coursier' 
                    AND is_active = 1
                ");
                $tokenCheck->execute([$coursierId]);
                $tokenInfo = $tokenCheck->fetch(PDO::FETCH_ASSOC);
                
                $lastActivity = strtotime($tokenInfo['last_activity'] ?? '1970-01-01');
                $isRecentlyActive = (time() - $lastActivity) < 1800; // 30 minutes
                
                $newStatus = ($isRecentlyActive && $tokenInfo['active_count'] > 0) 
                    ? 'en_ligne' 
                    : 'hors_ligne';
                
                // Mettre à jour le statut du coursier
                $updateStatus = $pdo->prepare("
                    UPDATE agents_suzosky 
                    SET statut_connexion = ?,
                        derniere_position = CASE 
                            WHEN ? = 'en_ligne' THEN NOW() 
                            ELSE derniere_position 
                        END
                    WHERE id_coursier = ?
                ");
                $updateStatus->execute([$newStatus, $newStatus, $coursierId]);
                
                $results[] = "Statut coursier mis à jour: $newStatus";
            }
            break;
            
        case 'send_push_notification':
            // Envoyer une notification push pour synchroniser l'app
            require_once __DIR__ . '/../lib/firebase_push.php';
            
            $stmt = $pdo->prepare("
                SELECT c.*, a.nom, a.prenoms 
                FROM commandes c
                LEFT JOIN agents_suzosky a ON c.coursier_id = a.id_coursier
                WHERE c.id = ?
            ");
            $stmt->execute([$commandeId]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($commande && !empty($commande['coursier_id'])) {
                $notification = [
                    'title' => 'Synchronisation Suzosky',
                    'body' => "Mise à jour commande #{$commande['code_commande']}",
                    'data' => [
                        'type' => 'order_sync',
                        'order_id' => $commandeId,
                        'status' => $commande['statut'],
                        'action' => 'refresh_order_status'
                    ]
                ];
                
                $pushResult = sendPushNotificationToCoursier(
                    $commande['coursier_id'], 
                    $notification
                );
                
                if ($pushResult['success']) {
                    $results[] = "Notification push envoyée avec succès";
                } else {
                    $results[] = "Erreur notification push: " . $pushResult['error'];
                }
            }
            break;
            
        case 'force_status_update':
            // Forcer la mise à jour du statut avec journalisation
            $newStatus = $_POST['new_status'] ?? '';
            $validStatuses = ['nouvelle', 'assignee', 'en_cours', 'livree', 'annulee'];
            
            if (!in_array($newStatus, $validStatuses)) {
                throw new Exception("Statut invalide: $newStatus");
            }
            
            // Mise à jour dans toutes les tables
            $updateMain = $pdo->prepare("
                UPDATE commandes 
                SET statut = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateMain->execute([$newStatus, $commandeId]);
            
            $updateClassic = $pdo->prepare("
                UPDATE commandes_classiques 
                SET statut = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $updateClassic->execute([$newStatus, $commandeId]);
            
            // Journaliser l'action
            $logStmt = $pdo->prepare("
                INSERT INTO admin_actions_log (
                    admin_user, action_type, target_id, target_type, 
                    details, created_at
                ) VALUES (?, 'force_status_update', ?, 'commande', ?, NOW())
            ");
            $logStmt->execute([
                $_SESSION['admin_username'] ?? 'admin',
                $commandeId,
                json_encode(['new_status' => $newStatus, 'reason' => 'admin_sync'])
            ]);
            
            $results[] = "Statut forcé vers: $newStatus";
            break;
            
        default:
            throw new Exception("Action non reconnue: $action");
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Erreur sync forcée commande #{$commandeId}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>