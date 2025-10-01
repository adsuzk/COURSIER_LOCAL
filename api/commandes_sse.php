<?php
/**
 * SSE (Server-Sent Events) pour mises à jour temps réel des commandes
 * Utilisé par admin_commandes_enhanced.php
 */

require_once __DIR__ . '/../config.php';

// Headers SSE obligatoires
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Pour Nginx

// Éviter timeout
set_time_limit(0);
ignore_user_abort(true);

$pdo = getDBConnection();
$lastCheck = time();

while (true) {
    // Vérifier si client toujours connecté
    if (connection_aborted()) {
        break;
    }
    
    try {
        // Récupérer les commandes des dernières 24h
        $stmt = $pdo->query("
            SELECT 
                c.id, c.code_commande, c.statut, c.mode_paiement,
                c.heure_acceptation, c.heure_debut, c.heure_retrait, 
                c.heure_livraison, c.cash_recupere, c.prix_total,
                c.adresse_depart, c.adresse_retrait,
                c.adresse_arrivee, c.adresse_livraison,
                c.created_at, c.updated_at,
                a.nom as coursier_nom, 
                a.prenoms as coursier_prenoms,
                a.telephone as coursier_telephone
            FROM commandes c
            LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
            WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY c.created_at DESC
            LIMIT 100
        ");
        
        $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Générer hash pour détecter changements
        $hash = md5(json_encode($commandes));
        
        // Envoyer les données au format SSE
        echo "data: " . json_encode([
            'timestamp' => time(),
            'hash' => $hash,
            'count' => count($commandes),
            'commandes' => $commandes
        ]) . "\n\n";
        
        // Forcer l'envoi immédiat
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        
    } catch (Exception $e) {
        // Envoyer erreur
        echo "data: " . json_encode([
            'timestamp' => time(),
            'error' => $e->getMessage()
        ]) . "\n\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    // Attendre 2 secondes avant prochaine vérification
    sleep(2);
    
    $lastCheck = time();
}
