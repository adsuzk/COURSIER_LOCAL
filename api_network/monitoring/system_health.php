<?php
/**
 * API NETWORK - MONITORING SERVICE  
 * Service de monitoring global du système
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/coursier_presence.php';

header('Content-Type: application/json; charset=utf-8');

function getSystemHealth() {
    try {
        $pdo = getDBConnection();
        
        // === TESTS CORE SERVICES ===
        
        // 1. Base de données
        $dbHealth = testDatabaseHealth($pdo);
        
        // 2. Système présence coursiers  
        $presenceHealth = testPresenceSystem($pdo);
        
        // 3. APIs principales
        $apiHealth = testMainAPIs();
        
        // 4. Services FCM
        $fcmHealth = testFCMHealth($pdo);
        
        // === CALCUL SCORE GLOBAL ===
        $services = [$dbHealth, $presenceHealth, $apiHealth, $fcmHealth];
        $totalScore = 0;
        $maxScore = 0;
        
        foreach ($services as $service) {
            $totalScore += $service['score'];
            $maxScore += 100;
        }
        
        $globalScore = round(($totalScore / $maxScore) * 100);
        $globalStatus = $globalScore >= 90 ? 'excellent' : ($globalScore >= 70 ? 'good' : 'warning');
        
        return [
            'success' => true,
            'system_health' => [
                'global_score' => $globalScore,
                'global_status' => $globalStatus,
                'services' => $services,
                'summary' => [
                    'online_services' => count(array_filter($services, fn($s) => $s['status'] === 'online')),
                    'total_services' => count($services),
                    'critical_issues' => count(array_filter($services, fn($s) => $s['score'] < 50))
                ]
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}

function testDatabaseHealth($pdo) {
    $tests = [];
    $score = 0;
    
    try {
        // Test connexion
        $pdo->query('SELECT 1');
        $tests['connection'] = true;
        $score += 25;
        
        // Test tables principales
        $tables = ['agents_suzosky', 'commandes', 'device_tokens'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $tests["table_{$table}"] = true;
                $score += 25;
            } catch (Exception $e) {
                $tests["table_{$table}"] = false;
            }
        }
        
        return [
            'service' => 'database',
            'status' => $score >= 75 ? 'online' : 'warning',
            'score' => $score,
            'tests' => $tests
        ];
        
    } catch (Exception $e) {
        return [
            'service' => 'database',
            'status' => 'offline',
            'score' => 0,
            'error' => $e->getMessage()
        ];
    }
}

function testPresenceSystem($pdo) {
    try {
        // Test fonction principale
        $coursiers = getConnectedCouriers($pdo);
        $score = 50;
        
        // Test auto-nettoyage
        $stmt = $pdo->query("SELECT COUNT(*) FROM agents_suzosky WHERE statut_connexion = 'en_ligne'");
        $baseCount = $stmt->fetchColumn();
        $logicCount = count($coursiers);
        
        if ($baseCount == $logicCount) {
            $score += 50; // Cohérence parfaite
        } elseif ($logicCount <= $baseCount) {
            $score += 25; // Auto-nettoyage fonctionne
        }
        
        return [
            'service' => 'presence_system',
            'status' => $score >= 75 ? 'online' : 'warning',
            'score' => $score,
            'data' => [
                'connected_couriers' => $logicCount,
                'base_online_count' => $baseCount,
                'coherence' => $baseCount == $logicCount
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'service' => 'presence_system',
            'status' => 'offline',
            'score' => 0,
            'error' => $e->getMessage()
        ];
    }
}

function testMainAPIs() {
    $apis = [
        'https://localhost/COURSIER_LOCAL/api/get_coursier_data.php?coursier_id=3',
        'https://localhost/COURSIER_LOCAL/admin.php?section=dashboard',
        'https://localhost/COURSIER_LOCAL/admin.php?section=commandes'
    ];
    
    $onlineAPIs = 0;
    $totalAPIs = count($apis);
    
    foreach ($apis as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $onlineAPIs++;
        }
    }
    
    $score = round(($onlineAPIs / $totalAPIs) * 100);
    
    return [
        'service' => 'main_apis',
        'status' => $score >= 80 ? 'online' : ($score >= 50 ? 'warning' : 'offline'),
        'score' => $score,
        'data' => [
            'online_apis' => $onlineAPIs,
            'total_apis' => $totalAPIs
        ]
    ];
}

function testFCMHealth($pdo) {
    try {
        // Vérifier tokens actifs
        $stmt = $pdo->query('SELECT COUNT(*) FROM device_tokens WHERE is_active = 1');
        $activeTokens = $stmt->fetchColumn();
        
        // Vérifier classe FCM
        require_once __DIR__ . '/../../fcm_manager.php';
        $fcmLoaded = class_exists('FCMManager');
        
        $score = 0;
        if ($fcmLoaded) $score += 50;
        if ($activeTokens > 0) $score += 50;
        
        return [
            'service' => 'fcm_notifications',
            'status' => $score >= 75 ? 'online' : 'warning',
            'score' => $score,
            'data' => [
                'fcm_manager_loaded' => $fcmLoaded,
                'active_tokens' => $activeTokens
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'service' => 'fcm_notifications',
            'status' => 'offline',
            'score' => 0,
            'error' => $e->getMessage()
        ];
    }
}

echo json_encode(getSystemHealth(), JSON_PRETTY_PRINT);
?>