<?php
/**
 * DIAGNOSTIC - POURQUOI YAPO EMMANUEL RESTE "en_ligne" ?
 * Le système doit automatiquement mettre à jour les statuts !
 */

require_once 'config.php';

$pdo = getDBConnection();

echo "=== DIAGNOSTIC STATUT YAPO EMMANUEL ===\n\n";

// 1. État actuel de YAPO Emmanuel
$stmt = $pdo->prepare("
    SELECT id, nom, prenoms, statut_connexion, current_session_token, 
           last_login_at, TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) AS minutes_inactif
    FROM agents_suzosky 
    WHERE nom = 'YAPO' AND prenoms = 'Emmanuel'
");
$stmt->execute();
$yapo = $stmt->fetch(PDO::FETCH_ASSOC);

if ($yapo) {
    echo "1. ÉTAT ACTUEL YAPO EMMANUEL:\n";
    echo "   ID: {$yapo['id']}\n";
    echo "   Statut DB: {$yapo['statut_connexion']}\n"; 
    echo "   Session token: " . ($yapo['current_session_token'] ? 'OUI' : 'NON') . "\n";
    echo "   Dernière activité: {$yapo['last_login_at']}\n";
    echo "   Minutes d'inactivité: {$yapo['minutes_inactif']}\n\n";
    
    if ($yapo['minutes_inactif'] > 30 && $yapo['statut_connexion'] === 'en_ligne') {
        echo "🚨 PROBLÈME DÉTECTÉ:\n";
        echo "   - Inactif depuis {$yapo['minutes_inactif']} minutes (> 30 min)\n";
        echo "   - Mais toujours marqué 'en_ligne' dans la base\n";
        echo "   - Le système de nettoyage automatique ne fonctionne pas !\n\n";
        
        echo "2. CORRECTION AUTOMATIQUE EN COURS...\n";
        
        // Mettre à jour automatiquement le statut
        $stmt = $pdo->prepare("
            UPDATE agents_suzosky 
            SET statut_connexion = 'hors_ligne',
                current_session_token = NULL
            WHERE id = ? AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) > 30
        ");
        $result = $stmt->execute([$yapo['id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo "   ✅ YAPO Emmanuel mis à jour: statut_connexion = 'hors_ligne'\n";
            echo "   ✅ Session token supprimé\n";
        } else {
            echo "   ❌ Échec de la mise à jour\n";
        }
        
        // Vérification
        echo "\n3. VÉRIFICATION POST-CORRECTION:\n";
        $stmt = $pdo->prepare("SELECT statut_connexion FROM agents_suzosky WHERE id = ?");
        $stmt->execute([$yapo['id']]);
        $nouveauStatut = $stmt->fetchColumn();
        echo "   Nouveau statut: {$nouveauStatut}\n";
        
    } else {
        echo "✅ Aucun problème détecté avec YAPO Emmanuel\n";
    }
    
} else {
    echo "❌ YAPO Emmanuel introuvable dans la base\n";
}

echo "\n4. NETTOYAGE GLOBAL AUTOMATIQUE:\n";
echo "   Mise à jour de TOUS les coursiers inactifs > 30 min...\n";

$stmt = $pdo->prepare("
    UPDATE agents_suzosky 
    SET statut_connexion = 'hors_ligne',
        current_session_token = NULL
    WHERE statut_connexion = 'en_ligne' 
    AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) > 30
");
$result = $stmt->execute();
$affected = $stmt->rowCount();

echo "   📊 Coursiers mis à jour: {$affected}\n";
echo "   🔧 Base de données nettoyée automatiquement\n";

echo "\n✅ MAINTENANT LA BASE ET L'AFFICHAGE SONT COHÉRENTS !\n";
?>