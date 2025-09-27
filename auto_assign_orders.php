<?php
// Processus d'attribution automatique simple
require_once 'config.php';

$pdo = getDBConnection();

// Vérifier s'il y a des commandes non attribuées (statuts initial ou legacy)
$stmt = $pdo->query("SELECT COUNT(*) FROM commandes WHERE statut IN ('nouvelle','en_attente') AND (coursier_id IS NULL OR coursier_id = 0)");
$commandes_en_attente = (int)$stmt->fetchColumn();

if ($commandes_en_attente > 0) {
    echo "🔄 Attribution automatique de {$commandes_en_attente} commandes...\n";
    
    // Sélection d'un agent actif disponible (fallback vers l'ancienne table si besoin)
    $coursier = null;
    try {
        $stmt_coursier = $pdo->query("SELECT id FROM agents_suzosky WHERE status='actif' ORDER BY id ASC LIMIT 1");
        $coursier = $stmt_coursier->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $stmt_coursier = $pdo->query("SELECT id FROM coursiers WHERE statut='actif' AND disponible=1 LIMIT 1");
        $coursier = $stmt_coursier->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($coursier) {
        $coursier_id = $coursier['id'];
        
        // Attribution de masse
    $stmt_assign = $pdo->prepare("UPDATE commandes SET coursier_id = ?, statut = 'assignee', assigned_at = NOW(), updated_at = NOW() WHERE statut IN ('nouvelle','en_attente') AND (coursier_id IS NULL OR coursier_id = 0)");
    $result = $stmt_assign->execute([$coursier_id]);
        
        $nb_assignees = $stmt_assign->rowCount();
        
        if ($nb_assignees > 0) {
            echo "✅ {$nb_assignees} commandes attribuées au coursier {$coursier_id}\n";
            
            // Notification FCM
            require_once 'api/lib/fcm_enhanced.php';
            
            // Assurer la colonne agent_id dans device_tokens
            $cols = $pdo->query("SHOW COLUMNS FROM device_tokens")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('agent_id', $cols)) {
                try { $pdo->exec("ALTER TABLE device_tokens ADD COLUMN agent_id INT NULL"); } catch (Throwable $e) {}
            }
            $token_stmt = $pdo->prepare('SELECT token FROM device_tokens WHERE agent_id = ? OR coursier_id = ? ORDER BY updated_at DESC LIMIT 1');
            $token_stmt->execute([$coursier_id, $coursier_id]);
            $token_row = $token_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($token_row) {
                $message = $nb_assignees == 1 ? "1 nouvelle commande!" : "{$nb_assignees} nouvelles commandes!";
                
                fcm_send_with_log([
                    $token_row['token']
                ], "🚨 " . strtoupper($message), "Ouvrez l'app pour voir vos commandes", [
                    'type' => 'new_orders',
                    'count' => $nb_assignees,
                    '_data_only' => true,
                    'bulk_assign' => '1'
                ], $coursier_id, 'AUTO_ASSIGN');
                
                echo "✅ Notification envoyée\n";
            }
            
            echo "🎉 Attribution automatique terminée avec succès!\n";
        } else {
            echo "❌ Aucune commande n'a pu être attribuée\n";
        }
    } else {
        echo "❌ Aucun coursier disponible\n";
    }
} else {
    echo "✅ Aucune commande en attente\n";
}
?>
