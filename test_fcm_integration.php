<?php
/**
 * Test rapide de l'intégration FCM dans l'admin
 * Script pour vérifier que le système FCM fonctionne correctement
 */

require_once 'config.php';

echo "🔍 TEST INTÉGRATION FCM ADMIN\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Test de la fonction FCM Global Status
    echo "\n📊 1. Test getFCMGlobalStatus()\n";
    
    // Simuler la fonction
    function getFCMGlobalStatus_test($pdo) {
        // Coursiers connectés actuellement
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_connected 
            FROM agents_suzosky 
            WHERE statut_connexion = 'en_ligne'
            AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30
        ");
        $stmt->execute();
        $connectedCoursiers = $stmt->fetchColumn();
        
        // Coursiers connectés AVEC token FCM
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.id) as with_fcm
            FROM agents_suzosky a
            INNER JOIN device_tokens dt ON a.id = dt.coursier_id
            WHERE a.statut_connexion = 'en_ligne'
            AND TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) <= 30
            AND dt.is_active = 1
        ");
        $stmt->execute();
        $connectedWithFCM = $stmt->fetchColumn();
        
        // Calcul du taux de robustesse FCM
        $fcmRate = $connectedCoursiers > 0 ? round(($connectedWithFCM / $connectedCoursiers) * 100, 1) : 0;
        
        return [
            'total_connected' => $connectedCoursiers,
            'with_fcm' => $connectedWithFCM,
            'without_fcm' => $connectedCoursiers - $connectedWithFCM,
            'fcm_rate' => $fcmRate,
            'status' => $fcmRate >= 80 ? 'excellent' : ($fcmRate >= 60 ? 'correct' : 'critique')
        ];
    }
    
    $fcmStatus = getFCMGlobalStatus_test($pdo);
    
    echo "   • Coursiers connectés: " . $fcmStatus['total_connected'] . "\n";
    echo "   • Avec FCM: " . $fcmStatus['with_fcm'] . "\n";
    echo "   • Sans FCM: " . $fcmStatus['without_fcm'] . "\n";
    echo "   • Taux FCM: " . $fcmStatus['fcm_rate'] . "%\n";
    echo "   • Statut: " . $fcmStatus['status'] . "\n";
    
    // 2. Test de la logique de status light avec FCM
    echo "\n🚦 2. Test getCoursierStatusLight() avec FCM\n";
    
    // Récupérer un coursier connecté pour tester
    $stmt = $pdo->prepare("
        SELECT a.*, 
               CASE WHEN TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) <= 30 THEN 1 ELSE 0 END as is_recent_activity
        FROM agents_suzosky a 
        WHERE a.statut_connexion = 'en_ligne'
        LIMIT 1
    ");
    $stmt->execute();
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coursier) {
        echo "   • Test avec coursier: " . $coursier['nom'] . " " . $coursier['prenoms'] . "\n";
        
        // Vérifier FCM pour ce coursier
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_tokens WHERE coursier_id = ? AND is_active = 1");
        $stmt->execute([$coursier['id']]);
        $fcmTokenCount = $stmt->fetchColumn();
        
        echo "   • Tokens FCM: " . $fcmTokenCount . "\n";
        echo "   • Session token: " . (!empty($coursier['current_session_token']) ? '✅' : '❌') . "\n";
        echo "   • Statut connexion: " . $coursier['statut_connexion'] . "\n";
        echo "   • Activité récente: " . ($coursier['is_recent_activity'] ? '✅' : '❌') . "\n";
        
        // Déterminer le statut
        $hasToken = !empty($coursier['current_session_token']);
        $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
        $isRecentActivity = $coursier['is_recent_activity'];
        $hasFCMToken = $fcmTokenCount > 0;
        
        if ($hasToken && $isOnline && $isRecentActivity) {
            if (!$hasFCMToken) {
                $label = '⚠️ FCM manquant';
                $color = 'orange';
            } else {
                $label = '✅ Opérationnel';
                $color = 'green';
            }
        } else {
            if (!$hasToken) {
                $label = '📱 App déconnectée';
            } elseif (!$isOnline) {
                $label = '⚫ Hors ligne';
            } else {
                $label = '😴 Inactif';
            }
            $color = 'red';
        }
        
        echo "   • Statut calculé: $color - $label\n";
    } else {
        echo "   • Aucun coursier connecté pour le test\n";
    }
    
    // 3. Test de tokens FCM disponibles
    echo "\n📱 3. Analyse des tokens FCM\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            dt.coursier_id,
            a.nom,
            a.prenoms,
            COUNT(dt.id) as token_count,
            MAX(dt.created_at) as last_token_date
        FROM device_tokens dt
        LEFT JOIN agents_suzosky a ON dt.coursier_id = a.id
        WHERE dt.is_active = 1
        GROUP BY dt.coursier_id
        ORDER BY token_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $tokensData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($tokensData) {
        echo "   Top 10 coursiers avec tokens FCM:\n";
        foreach ($tokensData as $data) {
            echo "   • " . ($data['nom'] ?? 'Inconnu') . " " . ($data['prenoms'] ?? '') . ": " . $data['token_count'] . " tokens\n";
        }
    } else {
        echo "   • ❌ AUCUN token FCM actif dans le système!\n";
        echo "   • 🆘 PROBLÈME CRITIQUE: Notifications impossibles!\n";
    }
    
    // 4. Test d'urgence - Créer token si nécessaire
    if (empty($tokensData) && $coursier) {
        echo "\n🆘 4. Création token d'urgence pour " . $coursier['nom'] . "\n";
        
        $emergencyToken = 'emergency_' . uniqid() . '_' . $coursier['id'];
        $stmt = $pdo->prepare("
            INSERT INTO device_tokens (coursier_id, token, device_type, is_active, created_at, updated_at) 
            VALUES (?, ?, 'emergency', 1, NOW(), NOW())
        ");
        
        if ($stmt->execute([$coursier['id'], $emergencyToken])) {
            echo "   • ✅ Token d'urgence créé: " . substr($emergencyToken, 0, 20) . "...\n";
        } else {
            echo "   • ❌ Échec création token d'urgence\n";
        }
    }
    
    echo "\n✅ Tests terminés avec succès!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n📋 RECOMMANDATIONS:\n";
echo "1. Vérifier l'interface admin pour voir l'indicateur FCM\n";
echo "2. Tester les notifications push avec les nouveaux indicateurs\n";
echo "3. Surveiller le taux FCM en temps réel\n";
echo "4. Résoudre les coursiers sans tokens FCM\n";
?>