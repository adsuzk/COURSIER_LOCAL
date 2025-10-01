<?php
/**
 * Vérifier les commandes du coursier CM20250003
 */
require_once __DIR__ . '/config.php';

echo "🔍 VÉRIFICATION COMMANDES COURSIER CM20250003\n";
echo str_repeat("=", 70) . "\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. Trouver l'ID du coursier
    echo "👤 1. RECHERCHE COURSIER\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->prepare("SELECT id, nom, prenoms, matricule, statut_connexion FROM agents_suzosky WHERE matricule = ?");
    $stmt->execute(['CM20250003']);
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coursier) {
        echo "❌ Coursier CM20250003 introuvable!\n";
        exit(1);
    }
    
    echo "✅ Coursier trouvé: {$coursier['nom']} {$coursier['prenoms']}\n";
    echo "   ID: {$coursier['id']}\n";
    echo "   Statut: {$coursier['statut_connexion']}\n\n";
    
    // 2. Toutes les commandes de ce coursier
    echo "📦 2. TOUTES LES COMMANDES DU COURSIER\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            id, code_commande, order_number, statut,
            adresse_depart, adresse_arrivee,
            prix_estime, prix_total,
            created_at, updated_at
        FROM commandes 
        WHERE coursier_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$coursier['id']]);
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($commandes)) {
        echo "❌ Aucune commande trouvée pour ce coursier!\n\n";
    } else {
        echo "✅ " . count($commandes) . " commande(s) trouvée(s):\n\n";
        
        foreach ($commandes as $cmd) {
            $prix = $cmd['prix_total'] ?? $cmd['prix_estime'] ?? 0;
            echo "📋 Commande #{$cmd['id']}\n";
            echo "   Code: {$cmd['code_commande']}\n";
            echo "   Order Number: {$cmd['order_number']}\n";
            echo "   Statut: {$cmd['statut']}\n";
            echo "   De: {$cmd['adresse_depart']}\n";
            echo "   Vers: {$cmd['adresse_arrivee']}\n";
            echo "   Prix: {$prix} FCFA\n";
            echo "   Créée: {$cmd['created_at']}\n";
            echo "   Mise à jour: {$cmd['updated_at']}\n\n";
        }
    }
    
    // 3. Vérifier la requête utilisée par admin_commandes_enhanced.php
    echo "🔍 3. SIMULATION REQUÊTE PAGE ADMIN\n";
    echo str_repeat("-", 70) . "\n";
    
    // Requête similaire à celle de admin_commandes_enhanced.php
    $stmt = $pdo->query("
        SELECT 
            c.*,
            a.nom AS coursier_nom,
            a.prenoms AS coursier_prenoms,
            a.matricule AS coursier_matricule
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.statut IN ('nouvelle', 'en_attente', 'attribuee', 'acceptee', 'en_cours')
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    
    $commandesAdmin = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Commandes actives dans la vue admin: " . count($commandesAdmin) . "\n\n";
    
    // Filtrer pour notre coursier
    $commandesCoursier = array_filter($commandesAdmin, function($cmd) use ($coursier) {
        return $cmd['coursier_id'] == $coursier['id'];
    });
    
    if (empty($commandesCoursier)) {
        echo "⚠️  AUCUNE commande du coursier CM20250003 dans les résultats admin!\n\n";
        echo "🔍 Raisons possibles:\n";
        echo "   1. Statut de la commande non inclus dans le filtre\n";
        echo "   2. La commande a été créée mais pas avec le bon statut\n";
        echo "   3. Problème de jointure LEFT JOIN\n\n";
    } else {
        echo "✅ " . count($commandesCoursier) . " commande(s) du coursier dans les résultats admin:\n\n";
        foreach ($commandesCoursier as $cmd) {
            echo "   • #{$cmd['id']} - {$cmd['code_commande']} - {$cmd['statut']}\n";
        }
        echo "\n";
    }
    
    // 4. Vérifier les filtres appliqués
    echo "🎯 4. ANALYSE STATUTS\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM commandes GROUP BY statut ORDER BY count DESC");
    $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Répartition des statuts dans la base:\n\n";
    foreach ($statuts as $s) {
        echo "   {$s['statut']}: {$s['count']} commande(s)\n";
    }
    echo "\n";
    
    echo "🔍 Statuts AFFICHÉS dans admin:\n";
    echo "   ✅ nouvelle, en_attente, attribuee, acceptee, en_cours\n\n";
    
    echo "🔍 Statuts NON AFFICHÉS:\n";
    echo "   ❌ livree, annulee, terminee, etc.\n\n";
    
    // 5. Vérifier la dernière commande test
    echo "🧪 5. DERNIÈRE COMMANDE TEST\n";
    echo str_repeat("-", 70) . "\n";
    
    $stmt = $pdo->query("SELECT * FROM commandes ORDER BY id DESC LIMIT 1");
    $derniere = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($derniere) {
        echo "Dernière commande créée:\n";
        echo "   ID: {$derniere['id']}\n";
        echo "   Code: {$derniere['code_commande']}\n";
        echo "   Coursier ID: {$derniere['coursier_id']}\n";
        echo "   Statut: {$derniere['statut']}\n";
        echo "   Créée: {$derniere['created_at']}\n";
        
        if ($derniere['coursier_id'] == $coursier['id']) {
            echo "   ✅ C'est bien une commande du coursier CM20250003\n";
            
            $statutsValides = ['nouvelle', 'en_attente', 'attribuee', 'acceptee', 'en_cours'];
            if (in_array($derniere['statut'], $statutsValides)) {
                echo "   ✅ Le statut '{$derniere['statut']}' DEVRAIT apparaître dans l'admin\n";
            } else {
                echo "   ⚠️  Le statut '{$derniere['statut']}' N'apparaît PAS dans l'admin (filtre)\n";
            }
        }
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "✅ DIAGNOSTIC TERMINÉ\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "📍 Fichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n";
}
?>
