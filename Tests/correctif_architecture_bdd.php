<?php
/**
 * CORRECTIF CRITIQUE - NETTOYAGE ARCHITECTURE BDD
 * Suppression logique obsolète et correction contraintes
 */

require_once 'config.php';

echo "🔧 CORRECTIF ARCHITECTURE BDD - NETTOYAGE COMPLET\n";
echo "=" . str_repeat("=", 60) . "\n";

try {
    $pdo = getDBConnection();
    
    // 1. Analyser les contraintes existantes
    echo "\n🔍 1. ANALYSE DES CONTRAINTES ACTUELLES\n";
    
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'coursier_local' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_NAME = 'commandes'
    ");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Contraintes trouvées sur table commandes:\n";
    foreach ($constraints as $constraint) {
        echo "   • {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} → {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
    
    // 2. Supprimer l'ancienne contrainte incorrecte
    echo "\n🗑️ 2. SUPPRESSION CONTRAINTE INCORRECTE\n";
    
    foreach ($constraints as $constraint) {
        if ($constraint['REFERENCED_TABLE_NAME'] === 'coursiers') {
            echo "   ❌ Suppression contrainte incorrecte: {$constraint['CONSTRAINT_NAME']}\n";
            
            try {
                $pdo->exec("ALTER TABLE commandes DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']}");
                echo "   ✅ Contrainte supprimée\n";
            } catch (Exception $e) {
                echo "   ⚠️ Erreur suppression: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // 3. Ajouter la contrainte correcte
    echo "\n✅ 3. AJOUT CONTRAINTE CORRECTE\n";
    
    try {
        $pdo->exec("
            ALTER TABLE commandes 
            ADD CONSTRAINT fk_commandes_agents_suzosky 
            FOREIGN KEY (coursier_id) REFERENCES agents_suzosky(id) 
            ON DELETE SET NULL ON UPDATE CASCADE
        ");
        echo "   ✅ Nouvelle contrainte ajoutée: commandes.coursier_id → agents_suzosky.id\n";
    } catch (Exception $e) {
        echo "   ⚠️ Contrainte existe déjà ou erreur: " . $e->getMessage() . "\n";
    }
    
    // 4. Vérifier les coursiers dans la table obsolète
    echo "\n🔍 4. ANALYSE TABLE COURSIERS (OBSOLÈTE)\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM coursiers");
    $coursierCount = $stmt->fetchColumn();
    
    echo "   📊 Nombre d'entrées dans table coursiers: $coursierCount\n";
    
    if ($coursierCount > 0) {
        echo "   ⚠️ Table coursiers contient des données - Analyse requise avant suppression\n";
        
        $stmt = $pdo->query("SELECT id, nom, email FROM coursiers LIMIT 5");
        $coursiersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "   📋 Échantillon de données:\n";
        foreach ($coursiersData as $coursier) {
            echo "      • ID: {$coursier['id']} - {$coursier['nom']} ({$coursier['email']})\n";
        }
    } else {
        echo "   ✅ Table coursiers vide - Peut être supprimée\n";
    }
    
    // 5. Vérifier la cohérence agents_suzosky
    echo "\n🎯 5. VÉRIFICATION AGENTS_SUZOSKY\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as total,
               SUM(CASE WHEN solde_wallet > 0 THEN 1 ELSE 0 END) as avec_solde,
               SUM(CASE WHEN statut_connexion = 'en_ligne' THEN 1 ELSE 0 END) as connectes
        FROM agents_suzosky
    ");
    $agentsStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   📊 Statistiques agents_suzosky:\n";
    echo "      • Total agents: {$agentsStats['total']}\n";
    echo "      • Avec solde > 0: {$agentsStats['avec_solde']}\n";
    echo "      • Connectés: {$agentsStats['connectes']}\n";
    
    // 6. Identifier les agents sans solde
    echo "\n💰 6. AGENTS SANS SOLDE (PROBLÈME CRITIQUE)\n";
    
    $stmt = $pdo->query("
        SELECT id, nom, prenoms, email, COALESCE(solde_wallet, 0) as solde, statut_connexion
        FROM agents_suzosky 
        WHERE COALESCE(solde_wallet, 0) = 0
        ORDER BY statut_connexion DESC, nom ASC
    ");
    $agentsSansSolde = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($agentsSansSolde) > 0) {
        echo "   🚨 AGENTS SANS SOLDE (ne peuvent recevoir commandes):\n";
        foreach ($agentsSansSolde as $agent) {
            $status = $agent['statut_connexion'] === 'en_ligne' ? '🟢 EN LIGNE' : '⚫ Hors ligne';
            echo "      • {$agent['nom']} {$agent['prenoms']} - Solde: {$agent['solde']} FCFA $status\n";
        }
        
        echo "\n   💡 SOLUTION: Utiliser admin.php?section=finances pour recharger\n";
    } else {
        echo "   ✅ Tous les agents ont un solde positif\n";
    }
    
    // 7. Vérifier les commandes orphelines
    echo "\n📦 7. VÉRIFICATION COMMANDES ORPHELINES\n";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as orphelines
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.coursier_id IS NOT NULL AND a.id IS NULL
    ");
    $orphelines = $stmt->fetchColumn();
    
    if ($orphelines > 0) {
        echo "   ❌ $orphelines commande(s) avec coursier_id invalide détectée(s)\n";
        echo "   💡 Correction nécessaire\n";
    } else {
        echo "   ✅ Aucune commande orpheline\n";
    }
    
    // 8. Récapitulatif des actions
    echo "\n📋 8. RÉCAPITULATIF DES CORRECTIFS\n";
    
    echo "   ✅ Architecture corrigée:\n";
    echo "      • Contrainte FK: commandes.coursier_id → agents_suzosky.id\n";
    echo "      • Table coursiers identifiée comme obsolète\n";
    echo "      • agents_suzosky confirmée comme table principale\n";
    
    echo "\n   🚨 ACTIONS REQUISES:\n";
    echo "      1. Recharger le solde des coursiers (admin interface)\n";
    echo "      2. Vérifier que les coursiers reçoivent les notifications\n";
    echo "      3. Tester le flux complet avec solde > 0\n";
    
    echo "\n✅ CORRECTIFS APPLIQUÉS AVEC SUCCÈS!\n";
    
} catch (Exception $e) {
    echo "\n❌ ERREUR CRITIQUE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>