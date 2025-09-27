<?php
/**
 * SUZOSKY ADMIN - VÉRIFICATION SYNCHRONISATION COMMANDES
 * Vérification en temps réel du statut de synchronisation entre l'admin et l'application mobile
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../config.php';

if (!isset($_GET['commande_id']) || !is_numeric($_GET['commande_id'])) {
    echo json_encode(['error' => 'ID commande manquant']);
    exit;
}

$commandeId = (int)$_GET['commande_id'];

try {
    $pdo = getDBConnection();
    
    // Récupérer les informations de la commande depuis les différentes sources
    $sources = [];
    
    // 1. Table commandes principale
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            statut, 
            coursier_id, 
            updated_at,
            'commandes' as source_table
        FROM commandes 
        WHERE id = ?
    ");
    $stmt->execute([$commandeId]);
    $mainRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mainRecord) {
        $sources['commandes'] = $mainRecord;
    }
    
    // 2. Table commandes_classiques (fallback)
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            statut, 
            coursier_id, 
            updated_at,
            'commandes_classiques' as source_table
        FROM commandes_classiques 
        WHERE id = ?
    ");
    $stmt->execute([$commandeId]);
    $classicRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($classicRecord) {
        $sources['commandes_classiques'] = $classicRecord;
    }
    
    // 3. Vérifier les tokens actifs du coursier (si assigné)
    $coursierSyncStatus = 'unknown';
    $coursierLastSeen = null;
    
    if (!empty($mainRecord['coursier_id'] ?? $classicRecord['coursier_id'])) {
        $coursierId = $mainRecord['coursier_id'] ?? $classicRecord['coursier_id'];
        
        // Vérifier le statut du coursier dans agents_suzosky
        $stmt = $pdo->prepare("
            SELECT 
                statut_connexion, 
                derniere_position,
                latitude,
                longitude
            FROM agents_suzosky 
            WHERE id_coursier = ?
        ");
        $stmt->execute([$coursierId]);
        $coursierInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coursierInfo) {
            $coursierSyncStatus = $coursierInfo['statut_connexion'] ?? 'hors_ligne';
            $coursierLastSeen = $coursierInfo['derniere_position'];
        }
        
        // Vérifier les tokens FCM actifs
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as active_tokens,
                MAX(last_used_at) as last_token_activity
            FROM fcm_tokens 
            WHERE user_id = ? 
            AND user_type = 'coursier' 
            AND is_active = 1
            AND last_used_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$coursierId]);
        $tokenInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // 4. Analyser la cohérence
    $syncStatus = 'synchronized';
    $issues = [];
    
    // Vérifier si les statuts sont cohérents entre les tables
    if (count($sources) > 1) {
        $statuts = array_unique(array_column($sources, 'statut'));
        if (count($statuts) > 1) {
            $syncStatus = 'out_of_sync';
            $issues[] = 'Statuts différents entre les tables: ' . implode(', ', $statuts);
        }
    }
    
    // Vérifier si le coursier est vraiment connecté
    if (!empty($coursierId)) {
        $lastActivity = max(
            strtotime($coursierLastSeen ?? '1970-01-01'),
            strtotime($tokenInfo['last_token_activity'] ?? '1970-01-01')
        );
        
        $inactivityMinutes = (time() - $lastActivity) / 60;
        
        if ($inactivityMinutes > 30 && $coursierSyncStatus === 'en_ligne') {
            $syncStatus = 'out_of_sync';
            $issues[] = 'Coursier marqué en ligne mais inactif depuis ' . round($inactivityMinutes) . ' minutes';
        }
        
        if (($tokenInfo['active_tokens'] ?? 0) === 0 && $coursierSyncStatus === 'en_ligne') {
            $syncStatus = 'out_of_sync';
            $issues[] = 'Coursier marqué en ligne mais aucun token FCM actif';
        }
    }
    
    // 5. Vérifier la cohérence avec les notifications push
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as notification_count,
            MAX(created_at) as last_notification
        FROM notifications 
        WHERE related_id = ? 
        AND related_type = 'commande'
        AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$commandeId]);
    $notifInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Préparer la réponse
    $response = [
        'commande_id' => $commandeId,
        'sync_status' => $syncStatus,
        'issues' => $issues,
        'sources' => $sources,
        'coursier_sync' => [
            'status' => $coursierSyncStatus,
            'last_seen' => $coursierLastSeen,
            'active_tokens' => $tokenInfo['active_tokens'] ?? 0,
            'last_token_activity' => $tokenInfo['last_token_activity'] ?? null
        ],
        'notifications' => [
            'count_last_hour' => $notifInfo['notification_count'] ?? 0,
            'last_sent' => $notifInfo['last_notification'] ?? null
        ],
        'check_timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Log pour debugging si nécessaire
    if ($syncStatus === 'out_of_sync') {
        error_log("SYNC ISSUE - Commande #{$commandeId}: " . implode('; ', $issues));
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Erreur sync check commande #{$commandeId}: " . $e->getMessage());
    echo json_encode([
        'error' => 'Erreur base de données',
        'sync_status' => 'error',
        'commande_id' => $commandeId
    ]);
} catch (Exception $e) {
    error_log("Erreur générale sync check commande #{$commandeId}: " . $e->getMessage());
    echo json_encode([
        'error' => 'Erreur système',
        'sync_status' => 'error',
        'commande_id' => $commandeId
    ]);
}
?>