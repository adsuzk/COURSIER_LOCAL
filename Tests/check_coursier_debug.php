<?php
require_once __DIR__ . '/config.php';

$matricule = 'CM20250003';
$password_test = 'KOrxl';

echo "=== DIAGNOSTIC COURSIER CM20250003 ===\n";

try {
    $pdo = getDBConnection();
    
    // Rechercher le coursier
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE matricule = ?");
    $stmt->execute([$matricule]);
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coursier) {
        echo "✅ COURSIER TROUVÉ\n";
        echo "ID: " . $coursier['id'] . "\n";
        echo "Matricule: " . $coursier['matricule'] . "\n";
        echo "Nom: " . $coursier['nom'] . " " . $coursier['prenoms'] . "\n";
        echo "Téléphone: " . $coursier['telephone'] . "\n";
        echo "Email: " . ($coursier['email'] ?? 'N/A') . "\n";
        echo "Statut: " . $coursier['statut'] . "\n";
        echo "Hash mot de passe: " . $coursier['mot_de_passe'] . "\n";
        echo "Statut connexion: " . ($coursier['statut_connexion'] ?? 'N/A') . "\n";
        echo "Dernière connexion: " . ($coursier['last_login_at'] ?? 'N/A') . "\n";
        echo "Token session: " . ($coursier['current_session_token'] ?? 'N/A') . "\n";
        
        echo "\n=== TEST HACHAGE MOT DE PASSE ===\n";
        echo "Mot de passe testé: '$password_test'\n";
        
        // Test différents types de hachage
        $md5_hash = md5($password_test);
        $sha1_hash = sha1($password_test);
        $password_hash = password_hash($password_test, PASSWORD_DEFAULT);
        
        echo "MD5: $md5_hash\n";
        echo "SHA1: $sha1_hash\n";
        echo "Hash en BD: " . $coursier['mot_de_passe'] . "\n";
        
        // Vérification des correspondances
        if ($coursier['mot_de_passe'] === $md5_hash) {
            echo "✅ CORRESPONDANCE MD5 TROUVÉE!\n";
        } elseif ($coursier['mot_de_passe'] === $sha1_hash) {
            echo "✅ CORRESPONDANCE SHA1 TROUVÉE!\n";
        } elseif ($coursier['mot_de_passe'] === $password_test) {
            echo "⚠️ MOT DE PASSE EN TEXTE CLAIR!\n";
        } elseif (password_verify($password_test, $coursier['mot_de_passe'])) {
            echo "✅ CORRESPONDANCE AVEC password_verify()!\n";
        } else {
            echo "❌ AUCUNE CORRESPONDANCE - PROBLÈME DÉTECTÉ!\n";
            echo "Le hash en BD ne correspond à aucun format connu du mot de passe 'KOrxl'\n";
            
            // Suggestion de correction
            echo "\n=== CORRECTION SUGGÉRÉE ===\n";
            echo "Le hash MD5 correct pour 'KOrxl' devrait être: $md5_hash\n";
            
            // Corriger automatiquement
            echo "Correction automatique du mot de passe...\n";
            $update_stmt = $pdo->prepare("UPDATE agents SET mot_de_passe = ? WHERE matricule = ?");
            if ($update_stmt->execute([$md5_hash, $matricule])) {
                echo "✅ MOT DE PASSE CORRIGÉ AVEC SUCCÈS!\n";
                
                // Réinitialiser la session pour forcer une nouvelle connexion
                $reset_stmt = $pdo->prepare("UPDATE agents SET current_session_token = NULL, statut_connexion = 'hors_ligne' WHERE matricule = ?");
                $reset_stmt->execute([$matricule]);
                echo "✅ SESSION RÉINITIALISÉE\n";
            } else {
                echo "❌ ERREUR LORS DE LA CORRECTION\n";
            }
        }
        
    } else {
        echo "❌ COURSIER CM20250003 INTROUVABLE!\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";
?>