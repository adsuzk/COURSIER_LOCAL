<?php
require_once __DIR__ . '/config.php';

$pdo = getPDO();

// Récupérer la commande assignée au coursier (si coursier_id fourni)
if ($argc > 1 && is_numeric($argv[1])) {
    $coursierId = (int)$argv[1];
    
    echo "=== COMMANDES POUR LE COURSIER ID: $coursierId ===\n\n";
    
    $stmt = $pdo->prepare("
        SELECT id, code_commande, order_number, statut, client_nom,
               adresse_retrait, adresse_livraison, prix_estime, created_at, updated_at
        FROM commandes 
        WHERE coursier_id = ?
        AND statut NOT IN ('livree', 'annulee')
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$coursierId]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($commandes)) {
        echo "Aucune commande active pour ce coursier.\n\n";
        
        // Vérifier les dernières commandes du coursier
        echo "=== DERNIÈRES COMMANDES (même terminées) ===\n\n";
        $stmt2 = $pdo->prepare("
            SELECT id, code_commande, order_number, statut, client_nom, created_at
            FROM commandes 
            WHERE coursier_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt2->execute([$coursierId]);
        $last = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($last as $c) {
            echo "ID: " . $c['id'] . " | Code: " . $c['code_commande'] . " | Statut: " . $c['statut'] . " | Date: " . $c['created_at'] . "\n";
        }
    } else {
        foreach ($commandes as $cmd) {
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            echo "ID COMMANDE: " . $cmd['id'] . "\n";
            echo "CODE: " . $cmd['code_commande'] . "\n";
            echo "ORDER NUMBER: " . $cmd['order_number'] . "\n";
            echo "STATUT: " . $cmd['statut'] . "\n";
            echo "CLIENT: " . $cmd['client_nom'] . "\n";
            echo "RETRAIT: " . $cmd['adresse_retrait'] . "\n";
            echo "LIVRAISON: " . $cmd['adresse_livraison'] . "\n";
            echo "PRIX: " . $cmd['prix_estime'] . " FCFA\n";
            echo "CRÉÉE: " . $cmd['created_at'] . "\n";
            echo "MISE À JOUR: " . $cmd['updated_at'] . "\n";
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        }
    }
    
    // Informations sur le coursier
    $courierStmt = $pdo->prepare("SELECT id, nom, prenom, telephone, statut, disponibilite FROM coursiers WHERE id = ?");
    $courierStmt->execute([$coursierId]);
    $courier = $courierStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($courier) {
        echo "\n=== INFO COURSIER ===\n";
        echo "Nom: " . $courier['nom'] . " " . $courier['prenom'] . "\n";
        echo "Téléphone: " . $courier['telephone'] . "\n";
        echo "Statut: " . $courier['statut'] . "\n";
        echo "Disponibilité: " . $courier['disponibilite'] . "\n";
    }
} else {
    echo "Usage: php " . basename(__FILE__) . " <COURSIER_ID>\n";
    echo "Exemple: php " . basename(__FILE__) . " 5\n\n";
    
    // Afficher les coursiers disponibles
    echo "=== COURSIERS DISPONIBLES ===\n\n";
    $couriersList = $pdo->query("SELECT id, nom, prenom, telephone, statut, disponibilite FROM coursiers ORDER BY id");
    
    while ($c = $couriersList->fetch(PDO::FETCH_ASSOC)) {
        echo "ID " . $c['id'] . ": " . $c['nom'] . " " . $c['prenom'] . " (" . $c['telephone'] . ") - Statut: " . $c['statut'] . " - Dispo: " . $c['disponibilite'] . "\n";
    }
}
