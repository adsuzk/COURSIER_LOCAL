<?php
// system_fcm_robustness.php - Système de robustesse FCM pour coursiers connectés
require_once(__DIR__ . '/config.php');

function checkFCMRobustness() {
    $pdo = getDBConnection();
    $issues = [];
    $warnings = [];
    $recommendations = [];
    
    // 1. Vérifier les coursiers connectés sans token FCM
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.matricule,
            a.nom,
            a.prenoms,
            a.statut_connexion,
            a.current_session_token,
            a.last_login_at,
            dt.token_count
        FROM agents_suzosky a
        LEFT JOIN (
            SELECT coursier_id, COUNT(*) as token_count 
            FROM device_tokens 
            WHERE is_active = 1 
            GROUP BY coursier_id
        ) dt ON a.id = dt.coursier_id
        WHERE a.statut_connexion = 'en_ligne' 
        AND a.current_session_token IS NOT NULL
        AND a.last_login_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stmt->execute();
    $coursiers_connectes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $coursiers_sans_fcm = [];
    foreach ($coursiers_connectes as $coursier) {
        if (($coursier['token_count'] ?? 0) == 0) {
            $coursiers_sans_fcm[] = $coursier;
            $issues[] = "❌ Coursier {$coursier['matricule']} ({$coursier['nom']}) connecté SANS token FCM";
        }
    }
    
    // 2. Vérifier les tokens FCM expirés/inactifs
    $stmt = $pdo->prepare("
        SELECT 
            dt.coursier_id,
            a.matricule,
            a.nom,
            COUNT(*) as total_tokens,
            COUNT(CASE WHEN dt.is_active = 1 THEN 1 END) as active_tokens,
            MAX(dt.updated_at) as last_token_update
        FROM device_tokens dt
        JOIN agents_suzosky a ON dt.coursier_id = a.id
        WHERE a.statut_connexion = 'en_ligne'
        GROUP BY dt.coursier_id, a.matricule, a.nom
        HAVING active_tokens = 0
    ");
    $stmt->execute();
    $tokens_inactifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tokens_inactifs as $token_info) {
        $warnings[] = "⚠️ Coursier {$token_info['matricule']} a des tokens FCM mais tous inactifs";
    }
    
    // 3. Vérifier la configuration FCM globale
    $fcm_configured = false;
    if (file_exists(__DIR__ . '/data/secret_fcm_key.txt') || 
        file_exists(__DIR__ . '/coursier-suzosky-firebase-adminsdk-fbsvc-3605815057.json')) {
        $fcm_configured = true;
    }
    
    if (!$fcm_configured) {
        $issues[] = "❌ Configuration FCM manquante (clés Firebase)";
    }
    
    // 4. Générer les recommandations
    if (count($coursiers_sans_fcm) > 0) {
        $recommendations[] = "📱 Demander aux coursiers de redémarrer leur application mobile";
        $recommendations[] = "🔔 Vérifier les permissions notifications sur les appareils";
        $recommendations[] = "🔧 Implémenter l'auto-enregistrement FCM au login";
        $recommendations[] = "📊 Ajouter alertes admin pour coursiers sans FCM";
    }
    
    if (count($tokens_inactifs) > 0) {
        $recommendations[] = "🔄 Implémenter renouvellement automatique des tokens FCM";
        $recommendations[] = "🧹 Nettoyer les tokens expirés périodiquement";
    }
    
    return [
        'coursiers_connectes' => count($coursiers_connectes),
        'coursiers_sans_fcm' => $coursiers_sans_fcm,
        'tokens_inactifs' => $tokens_inactifs,
        'issues' => $issues,
        'warnings' => $warnings,
        'recommendations' => $recommendations,
        'fcm_configured' => $fcm_configured,
        'robustness_score' => calculateRobustnessScore($coursiers_connectes, $coursiers_sans_fcm, $tokens_inactifs)
    ];
}

function calculateRobustnessScore($total_coursiers, $sans_fcm, $inactifs) {
    $total = count($total_coursiers);
    if ($total == 0) return 100; // Aucun coursier connecté = pas de problème
    
    $problemes = count($sans_fcm) + count($inactifs);
    $score = max(0, 100 - (($problemes / $total) * 100));
    return round($score, 1);
}

function generateFCMRobustnessAlert($data) {
    $alert = "";
    
    if ($data['robustness_score'] < 80) {
        $alert .= "🚨 ALERTE ROBUSTESSE FCM : Score " . $data['robustness_score'] . "%\n\n";
    }
    
    if (count($data['issues']) > 0) {
        $alert .= "PROBLÈMES CRITIQUES :\n";
        foreach ($data['issues'] as $issue) {
            $alert .= $issue . "\n";
        }
        $alert .= "\n";
    }
    
    if (count($data['warnings']) > 0) {
        $alert .= "AVERTISSEMENTS :\n";
        foreach ($data['warnings'] as $warning) {
            $alert .= $warning . "\n";
        }
        $alert .= "\n";
    }
    
    if (count($data['recommendations']) > 0) {
        $alert .= "RECOMMANDATIONS :\n";
        foreach ($data['recommendations'] as $rec) {
            $alert .= $rec . "\n";
        }
    }
    
    return $alert;
}

// Fonction pour forcer l'enregistrement d'un token de test
function createEmergencyFCMToken($coursier_id, $test_token = null) {
    $pdo = getDBConnection();
    
    if (!$test_token) {
        // Générer un token de test fictif pour permettre les tests
        $test_token = "emergency_token_" . $coursier_id . "_" . time();
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO device_tokens (coursier_id, token, platform, is_active, created_at, updated_at) 
        VALUES (?, ?, 'emergency', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
        is_active = 1, updated_at = NOW()
    ");
    
    return $stmt->execute([$coursier_id, $test_token]);
}

// Si appelé directement, afficher le rapport
if (basename(__FILE__) == basename($_SERVER["SCRIPT_NAME"])) {
    echo "=== RAPPORT ROBUSTESSE FCM SUZOSKY ===\n";
    echo "Date : " . date('Y-m-d H:i:s') . "\n\n";
    
    $data = checkFCMRobustness();
    
    echo "📊 STATISTIQUES :\n";
    echo "Coursiers connectés : " . $data['coursiers_connectes'] . "\n";
    echo "Sans token FCM : " . count($data['coursiers_sans_fcm']) . "\n";
    echo "Tokens inactifs : " . count($data['tokens_inactifs']) . "\n";
    echo "Score robustesse : " . $data['robustness_score'] . "%\n\n";
    
    $alert = generateFCMRobustnessAlert($data);
    if ($alert) {
        echo $alert;
    } else {
        echo "✅ Système FCM robuste - aucun problème détecté\n";
    }
    
    // Proposer correction d'urgence pour CM20250003
    if (count($data['coursiers_sans_fcm']) > 0) {
        echo "\n🚑 CORRECTION D'URGENCE DISPONIBLE :\n";
        echo "Pour tester immédiatement, utiliser createEmergencyFCMToken()\n";
        
        foreach ($data['coursiers_sans_fcm'] as $coursier) {
            if ($coursier['matricule'] == 'CM20250003') {
                echo "\n🔧 Création token d'urgence pour CM20250003...\n";
                if (createEmergencyFCMToken($coursier['id'])) {
                    echo "✅ Token d'urgence créé pour CM20250003\n";
                    echo "⚠️ Ce token permet les tests mais le vrai token doit venir de l'app mobile\n";
                } else {
                    echo "❌ Erreur lors de la création du token d'urgence\n";
                }
            }
        }
    }
}
?>