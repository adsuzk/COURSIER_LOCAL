<?php
/**
 * NETTOYAGE AUTOMATIQUE DES STATUTS COURSIERS
 * À exécuter périodiquement pour maintenir la cohérence
 * Intégrer dans les scripts d'admin ou en cron job
 */

function cleanupCoursierStatuses(?PDO $pdo = null): array {
    if (!$pdo) {
        require_once __DIR__ . '/config.php';
        $pdo = getDBConnection();
    }
    
    $report = [
        'timestamp' => date('Y-m-d H:i:s'),
        'cleaned_count' => 0,
        'errors' => []
    ];
    
    try {
        // Nettoyer tous les coursiers inactifs depuis plus de 2 minutes
        $stmt = $pdo->prepare("
            UPDATE agents_suzosky 
            SET statut_connexion = 'hors_ligne',
                current_session_token = NULL
            WHERE statut_connexion = 'en_ligne' 
            AND (
                last_login_at IS NULL 
                OR TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) > 2
            )
        ");
        
        $result = $stmt->execute();
        $report['cleaned_count'] = $stmt->rowCount();
        
        // Log dans un fichier pour audit
        $logEntry = json_encode($report) . PHP_EOL;
        file_put_contents(__DIR__ . '/logs/coursier_cleanup.log', $logEntry, FILE_APPEND | LOCK_EX);
        
    } catch (Exception $e) {
        $report['errors'][] = $e->getMessage();
    }
    
    return $report;
}

// Si appelé directement, exécuter le nettoyage
if (basename($_SERVER['SCRIPT_NAME']) === 'coursier_status_cleanup.php') {
    $report = cleanupCoursierStatuses();
    
    echo "=== NETTOYAGE AUTOMATIQUE COURSIERS ===\n";
    echo "Timestamp: {$report['timestamp']}\n";
    echo "Coursiers nettoyés: {$report['cleaned_count']}\n";
    
    if (!empty($report['errors'])) {
        echo "Erreurs:\n";
        foreach ($report['errors'] as $error) {
            echo "  - {$error}\n";
        }
    } else {
        echo "✅ Nettoyage terminé sans erreur\n";
    }
}
?>