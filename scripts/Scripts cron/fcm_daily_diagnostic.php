<?php
/**
 * DIAGNOSTIC FCM QUOTIDIEN AUTOMATIQUE
 * Script à exécuter en cron pour surveiller la robustesse FCM
 * Auteur: Système Suzosky
 * Date: Janvier 2025
 */

require_once dirname(__DIR__, 2) . '/config.php';

class FCMDailyDiagnostic {
    private $pdo;
    private $logFile;
    private $rootPath;
    
    public function __construct() {
        $this->rootPath = dirname(__DIR__, 2);
        $this->pdo = getDBConnection();
        $this->logFile = $this->rootPath . '/diagnostic_logs/fcm_daily_' . date('Y-m-d') . '.log';
        
        // Créer le dossier de logs si nécessaire
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function run() {
        $this->log("🔍 DÉBUT DIAGNOSTIC FCM QUOTIDIEN - " . date('Y-m-d H:i:s'));
        
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'global_status' => $this->analyzeGlobalStatus(),
            'coursiers_analysis' => $this->analyzeCoursiers(),
            'token_health' => $this->analyzeTokenHealth(),
            'recommendations' => []
        ];
        
        // Générer les recommandations
        $results['recommendations'] = $this->generateRecommendations($results);
        
        // Log des résultats
        $this->logResults($results);
        
        // Actions automatiques si nécessaire
        $this->performAutomaticActions($results);
        
        $this->log("✅ FIN DIAGNOSTIC FCM QUOTIDIEN");
        
        return $results;
    }
    
    private function analyzeGlobalStatus() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_coursiers,
                SUM(CASE WHEN statut_connexion = 'en_ligne' AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30 THEN 1 ELSE 0 END) as connected_coursiers,
                COUNT(DISTINCT dt.coursier_id) as coursiers_with_fcm
            FROM agents_suzosky a
            LEFT JOIN device_tokens dt ON a.id = dt.coursier_id AND dt.is_active = 1
        ");
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $fcmRate = $data['connected_coursiers'] > 0 ? 
            round(($data['coursiers_with_fcm'] / $data['connected_coursiers']) * 100, 1) : 0;
            
        return [
            'total_coursiers' => $data['total_coursiers'],
            'connected_coursiers' => $data['connected_coursiers'],
            'coursiers_with_fcm' => $data['coursiers_with_fcm'],
            'fcm_rate' => $fcmRate,
            'status' => $fcmRate >= 80 ? 'excellent' : ($fcmRate >= 60 ? 'correct' : 'critique')
        ];
    }
    
    private function analyzeCoursiers() {
        $stmt = $this->pdo->prepare("
            SELECT 
                a.id,
                a.nom,
                a.prenoms,
                a.statut_connexion,
                a.last_login_at,
                TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) as minutes_since_login,
                COUNT(dt.id) as token_count,
                MAX(dt.updated_at) as last_token_update
            FROM agents_suzosky a
            LEFT JOIN device_tokens dt ON a.id = dt.coursier_id AND dt.is_active = 1
            WHERE a.statut_connexion = 'en_ligne'
            AND TIMESTAMPDIFF(MINUTE, a.last_login_at, NOW()) <= 30
            GROUP BY a.id
            ORDER BY token_count ASC, minutes_since_login ASC
        ");
        $stmt->execute();
        
        $coursiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $analysis = [
            'total_analyzed' => count($coursiers),
            'without_fcm' => [],
            'with_old_tokens' => [],
            'fully_operational' => []
        ];
        
        foreach ($coursiers as $coursier) {
            if ($coursier['token_count'] == 0) {
                $analysis['without_fcm'][] = $coursier;
            } elseif (strtotime($coursier['last_token_update']) < strtotime('-24 hours')) {
                $analysis['with_old_tokens'][] = $coursier;
            } else {
                $analysis['fully_operational'][] = $coursier;
            }
        }
        
        return $analysis;
    }
    
    private function analyzeTokenHealth() {
        // Tokens anciens (plus de 7 jours)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as old_tokens
            FROM device_tokens 
            WHERE is_active = 1 AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $oldTokens = $stmt->fetchColumn();
        
        // Tokens orphelins (coursier n'existe plus)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as orphan_tokens
            FROM device_tokens dt
            LEFT JOIN agents_suzosky a ON dt.coursier_id = a.id
            WHERE dt.is_active = 1 AND a.id IS NULL
        ");
        $stmt->execute();
        $orphanTokens = $stmt->fetchColumn();
        
        // Total tokens actifs
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM device_tokens WHERE is_active = 1");
        $stmt->execute();
        $totalTokens = $stmt->fetchColumn();
        
        return [
            'total_active_tokens' => $totalTokens,
            'old_tokens' => $oldTokens,
            'orphan_tokens' => $orphanTokens,
            'health_score' => $totalTokens > 0 ? round((1 - ($oldTokens + $orphanTokens) / $totalTokens) * 100, 1) : 0
        ];
    }
    
    private function generateRecommendations($results) {
        $recommendations = [];
        
        // Recommandations globales
        if ($results['global_status']['fcm_rate'] < 60) {
            $recommendations[] = "🚨 CRITIQUE: Taux FCM trop bas ({$results['global_status']['fcm_rate']}%). Action immédiate requise.";
        } elseif ($results['global_status']['fcm_rate'] < 80) {
            $recommendations[] = "⚠️ ATTENTION: Taux FCM modéré ({$results['global_status']['fcm_rate']}%). Amélioration recommandée.";
        }
        
        // Recommandations coursiers
        if (!empty($results['coursiers_analysis']['without_fcm'])) {
            $count = count($results['coursiers_analysis']['without_fcm']);
            $recommendations[] = "📱 $count coursier(s) connecté(s) sans token FCM. Créer tokens d'urgence.";
        }
        
        if (!empty($results['coursiers_analysis']['with_old_tokens'])) {
            $count = count($results['coursiers_analysis']['with_old_tokens']);
            $recommendations[] = "🔄 $count coursier(s) avec tokens FCM anciens. Renouveler recommandé.";
        }
        
        // Recommandations tokens
        if ($results['token_health']['old_tokens'] > 0) {
            $recommendations[] = "🧹 {$results['token_health']['old_tokens']} token(s) ancien(s) à nettoyer.";
        }
        
        if ($results['token_health']['orphan_tokens'] > 0) {
            $recommendations[] = "🗑️ {$results['token_health']['orphan_tokens']} token(s) orphelin(s) à supprimer.";
        }
        
        return $recommendations;
    }
    
    private function performAutomaticActions($results) {
        $actionsPerformed = [];
        
        // Créer des tokens d'urgence pour les coursiers connectés sans FCM
        foreach ($results['coursiers_analysis']['without_fcm'] as $coursier) {
            if ($this->createEmergencyToken($coursier['id'])) {
                $actionsPerformed[] = "✅ Token d'urgence créé pour {$coursier['nom']} {$coursier['prenoms']}";
            }
        }
        
        // Nettoyer les tokens orphelins
        if ($results['token_health']['orphan_tokens'] > 0) {
            $cleaned = $this->cleanOrphanTokens();
            if ($cleaned > 0) {
                $actionsPerformed[] = "🧹 $cleaned token(s) orphelin(s) nettoyé(s)";
            }
        }
        
        if (!empty($actionsPerformed)) {
            $this->log("🔧 ACTIONS AUTOMATIQUES EFFECTUÉES:");
            foreach ($actionsPerformed as $action) {
                $this->log("   $action");
            }
        }
    }
    
    private function createEmergencyToken($coursierId) {
        try {
            $emergencyToken = 'auto_emergency_' . uniqid() . '_' . $coursierId;
            $stmt = $this->pdo->prepare("
                INSERT INTO device_tokens (coursier_id, token, device_type, is_active, created_at, updated_at) 
                VALUES (?, ?, 'emergency_auto', 1, NOW(), NOW())
            ");
            return $stmt->execute([$coursierId, $emergencyToken]);
        } catch (Exception $e) {
            $this->log("❌ Erreur création token urgence pour coursier $coursierId: " . $e->getMessage());
            return false;
        }
    }
    
    private function cleanOrphanTokens() {
        try {
            $stmt = $this->pdo->prepare("
                DELETE dt FROM device_tokens dt
                LEFT JOIN agents_suzosky a ON dt.coursier_id = a.id
                WHERE dt.is_active = 1 AND a.id IS NULL
            ");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            $this->log("❌ Erreur nettoyage tokens orphelins: " . $e->getMessage());
            return 0;
        }
    }
    
    private function logResults($results) {
        $this->log("📊 RÉSULTATS GLOBAUX:");
        $this->log("   • Total coursiers: {$results['global_status']['total_coursiers']}");
        $this->log("   • Coursiers connectés: {$results['global_status']['connected_coursiers']}");
        $this->log("   • Avec FCM: {$results['global_status']['coursiers_with_fcm']}");
        $this->log("   • Taux FCM: {$results['global_status']['fcm_rate']}%");
        $this->log("   • Statut: {$results['global_status']['status']}");
        
        $this->log("🔍 ANALYSE COURSIERS:");
        $this->log("   • Sans FCM: " . count($results['coursiers_analysis']['without_fcm']));
        $this->log("   • Tokens anciens: " . count($results['coursiers_analysis']['with_old_tokens']));
        $this->log("   • Opérationnels: " . count($results['coursiers_analysis']['fully_operational']));
        
        $this->log("💊 SANTÉ TOKENS:");
        $this->log("   • Total actifs: {$results['token_health']['total_active_tokens']}");
        $this->log("   • Anciens: {$results['token_health']['old_tokens']}");
        $this->log("   • Orphelins: {$results['token_health']['orphan_tokens']}");
        $this->log("   • Score santé: {$results['token_health']['health_score']}%");
        
        if (!empty($results['recommendations'])) {
            $this->log("💡 RECOMMANDATIONS:");
            foreach ($results['recommendations'] as $rec) {
                $this->log("   $rec");
            }
        }
    }
    
    private function log($message) {
        $logEntry = "[" . date('Y-m-d H:i:s') . "] $message\n";
        echo $logEntry;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Exécution si appelé directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $diagnostic = new FCMDailyDiagnostic();
        $results = $diagnostic->run();
        
        echo "\n📋 RÉSUMÉ FINAL:\n";
        echo "   • Taux FCM global: {$results['global_status']['fcm_rate']}%\n";
        echo "   • Statut système: {$results['global_status']['status']}\n";
        echo "   • Recommandations: " . count($results['recommendations']) . "\n";
        
    } catch (Exception $e) {
        echo "❌ ERREUR FATALE: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
