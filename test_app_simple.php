<?php
require_once 'config.php';

echo "=== TEST SIMPLIFIÉ NOTIFICATION APP ===\n";

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=coursier_local;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Vérifier les dernières commandes créées
    echo "\n=== DERNIÈRES COMMANDES ===\n";
    $stmt = $pdo->query("SELECT id, code_commande, statut, coursier_id, created_at FROM commandes ORDER BY created_at DESC LIMIT 5");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $statut = $row['statut'];
        $coursier = $row['coursier_id'] ? "Coursier #{$row['coursier_id']}" : "Pas assigné";
        echo "#{$row['id']} - {$row['code_commande']} - Statut: $statut - $coursier - {$row['created_at']}\n";
    }

    // Vérifier les commandes assignées au coursier #5
    echo "\n=== COMMANDES POUR COURSIER #5 ===\n";
    $stmt = $pdo->query("SELECT id, code_commande, statut, client_nom, adresse_depart, adresse_arrivee, prix_total FROM commandes WHERE coursier_id = 5 AND statut IN ('attribuee', 'en_cours') ORDER BY created_at DESC LIMIT 3");
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($commandes)) {
        echo "❌ Aucune commande assignée au coursier #5\n";
    } else {
        foreach ($commandes as $row) {
            echo "✅ #{$row['id']} - {$row['code_commande']} - {$row['statut']} - {$row['client_nom']}\n";
            echo "   📍 {$row['adresse_depart']} → {$row['adresse_arrivee']} - {$row['prix_total']} FCFA\n";
        }
    }

    // Vérifier l'état du coursier #5
    echo "\n=== ÉTAT COURSIER #5 ===\n";
    $stmt = $pdo->query("SELECT nom, statut, disponible FROM coursiers WHERE id = 5");
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($coursier) {
        echo "Nom: {$coursier['nom']}\n";
        echo "Statut: {$coursier['statut']}\n";
        echo "Disponible: " . ($coursier['disponible'] ? 'OUI' : 'NON') . "\n";
    }

    // Vérifier les device tokens actifs
    echo "\n=== DEVICE TOKENS ACTIFS ===\n";
    $stmt = $pdo->query("SELECT coursier_id, LEFT(token, 30) as token_short, last_ping, is_active FROM device_tokens WHERE coursier_id = 5 ORDER BY last_ping DESC LIMIT 2");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ping = $row['last_ping'] ?? 'Jamais';
        $actif = $row['is_active'] ? 'ACTIF' : 'INACTIF';
        echo "Coursier #{$row['coursier_id']} - Token: {$row['token_short']}... - Ping: $ping - $actif\n";
    }

    // Instructions utilisateur
    echo "\n=== INSTRUCTIONS UTILISATEUR ===\n";
    echo "1. 📱 Ouvrez l'app Suzosky Coursier sur votre téléphone\n";
    echo "2. 🔍 Vérifiez si vous êtes connecté comme 'ZALLE Ismael'\n";
    echo "3. 📋 Allez dans l'onglet 'Courses' en bas\n";
    echo "4. 👀 Regardez s'il y a des commandes en attente\n";
    echo "5. 🎵 Écoutez si une sonnerie se joue\n";
    echo "6. 🔔 Vérifiez la barre de notifications Android\n\n";
    
    echo "Si rien ne s'affiche, tapez 'ok' pour envoyer une nouvelle notification...\n";

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}
?>