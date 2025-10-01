<?php
/**
 * Script de test pour vérifier les données de tracking dans la base
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();
    
    // Compter les commandes avec coursier
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM commandes 
        WHERE coursier_id IS NOT NULL 
        AND statut IN ('attribuee', 'acceptee', 'en_cours')
    ");
    $commandesCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Compter les coursiers en ligne
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM agents_suzosky 
        WHERE statut_connexion = 'en_ligne'
    ");
    $coursiersCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Récupérer une commande exemple
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.code_commande,
            c.statut,
            c.adresse_depart,
            c.adresse_arrivee,
            a.nom as coursier_nom,
            a.prenoms as coursier_prenoms,
            a.matricule as coursier_matricule,
            a.statut_connexion
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.coursier_id IS NOT NULL
        AND c.statut IN ('attribuee', 'acceptee', 'en_cours')
        ORDER BY c.created_at DESC
        LIMIT 1
    ");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sample) {
        $sample['coursier_nom'] = trim(($sample['coursier_prenoms'] ?? '') . ' ' . ($sample['coursier_nom'] ?? ''));
    }
    
    echo json_encode([
        'success' => true,
        'commandes_count' => (int)$commandesCount,
        'coursiers_count' => (int)$coursiersCount,
        'sample' => $sample ?: null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
