<?php
/**
 * TRACKING DES EMAILS
 * Suivi des ouvertures et clics
 */

require_once __DIR__ . '/../config.php';

// Connexion DB
try {
    $pdo = getDBConnection();
    
    $trackingId = $_GET['t'] ?? '';
    $event = $_GET['e'] ?? '';
    
    if ($trackingId && $event) {
        if ($event === 'open') {
            // Marquer comme ouvert
            $stmt = $pdo->prepare("
                UPDATE email_logs 
                SET status = 'opened', opened_at = NOW() 
                WHERE tracking_id = ? AND opened_at IS NULL
            ");
            $stmt->execute([$trackingId]);
            
            // Retourner un pixel transparent
            header('Content-Type: image/gif');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // GIF 1x1 transparent
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            
        } elseif ($event === 'click') {
            // Marquer comme cliqué
            $stmt = $pdo->prepare("
                UPDATE email_logs 
                SET status = 'clicked', clicked_at = NOW() 
                WHERE tracking_id = ?
            ");
            $stmt->execute([$trackingId]);
            
            // Logger le clic
            $logFile = __DIR__ . '/logs/clicks_' . date('Y-m-d') . '.log';
            $logMessage = date('Y-m-d H:i:s') . " - Clic sur email: $trackingId\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            
            echo "Clic enregistré";
        }
    }
    
} catch (Exception $e) {
    // Erreur silencieuse pour ne pas casser l'affichage
    error_log("Erreur tracking email: " . $e->getMessage());
}
?>