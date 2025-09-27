<?php
// Diagnostic complet des tokens FCM
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config.php';

echo "=== DIAGNOSTIC TOKENS FCM - COURSIERS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDBConnection();
    
    // 1. Vérifier l'existence de la table device_tokens
    echo "=== VÉRIFICATION TABLE DEVICE_TOKENS ===\n";
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'device_tokens'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Table device_tokens existe\n";
            
            // Structure de la table
            $stmt = $pdo->query("SHOW COLUMNS FROM device_tokens");
            echo "\nStructure:\n";
            foreach ($stmt as $col) {
                echo "- {$col['Field']} ({$col['Type']})\n";
            }
            
            // Compter les tokens
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM device_tokens");
            $total = $stmt->fetchColumn();
            echo "\nNombre total de tokens: $total\n";
            
            if ($total > 0) {
                // Détail des tokens par coursier
                $stmt = $pdo->query("
                    SELECT dt.coursier_id, dt.agent_id, COUNT(*) as nb_tokens, 
                           MAX(dt.updated_at) as dernier_update,
                           c.nom as coursier_nom, c.telephone as coursier_tel
                    FROM device_tokens dt 
                    LEFT JOIN coursiers c ON dt.coursier_id = c.id
                    GROUP BY dt.coursier_id, dt.agent_id 
                    ORDER BY dernier_update DESC
                ");
                
                echo "\n=== TOKENS PAR COURSIER ===\n";
                foreach ($stmt as $row) {
                    echo "Coursier ID: {$row['coursier_id']} | Agent ID: {$row['agent_id']} | ";
                    echo "Nom: " . ($row['coursier_nom'] ?? 'N/A') . " | ";
                    echo "Tél: " . ($row['coursier_tel'] ?? 'N/A') . " | ";
                    echo "Tokens: {$row['nb_tokens']} | ";
                    echo "Dernier: {$row['dernier_update']}\n";
                }
                
                // Derniers tokens enregistrés
                echo "\n=== 5 DERNIERS TOKENS ENREGISTRÉS ===\n";
                $stmt = $pdo->query("
                    SELECT dt.*, c.nom as coursier_nom
                    FROM device_tokens dt 
                    LEFT JOIN coursiers c ON dt.coursier_id = c.id
                    ORDER BY dt.updated_at DESC 
                    LIMIT 5
                ");
                
                foreach ($stmt as $token) {
                    echo "ID: {$token['id']} | ";
                    echo "Coursier: {$token['coursier_id']} ({$token['coursier_nom']}) | ";
                    echo "Token: " . substr($token['token'], 0, 30) . "... | ";
                    echo "Date: {$token['updated_at']}\n";
                }
            }
        } else {
            echo "❌ Table device_tokens n'existe pas\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur vérification table: " . $e->getMessage() . "\n";
    }
    
    // 2. Vérifier les coursiers actifs
    echo "\n=== COURSIERS ACTIFS ===\n";
    try {
        $stmt = $pdo->query("
            SELECT id, nom, telephone, statut, disponible, derniere_connexion
            FROM coursiers 
            WHERE statut = 'actif' 
            ORDER BY derniere_connexion DESC 
            LIMIT 10
        ");
        
        foreach ($stmt as $coursier) {
            $hasToken = false;
            try {
                $tokenStmt = $pdo->prepare("SELECT COUNT(*) FROM device_tokens WHERE coursier_id = ?");
                $tokenStmt->execute([$coursier['id']]);
                $hasToken = $tokenStmt->fetchColumn() > 0;
            } catch (Exception $e) {}
            
            echo "ID: {$coursier['id']} | ";
            echo "Nom: {$coursier['nom']} | ";
            echo "Tél: {$coursier['telephone']} | ";
            echo "Statut: {$coursier['statut']} | ";
            echo "Disponible: " . ($coursier['disponible'] ? 'Oui' : 'Non') . " | ";
            echo "Token FCM: " . ($hasToken ? '✅' : '❌') . " | ";
            echo "Connexion: {$coursier['derniere_connexion']}\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur vérification coursiers: " . $e->getMessage() . "\n";
    }
    
    // 3. Test de l'API register_device_token
    echo "\n=== TEST API REGISTER_DEVICE_TOKEN ===\n";
    
    $testToken = 'diagnostic_test_token_' . time();
    $testCoursierId = 1; // Premier coursier
    
    // Simuler l'envoi via cURL local
    $postData = json_encode([
        'coursier_id' => $testCoursierId,
        'agent_id' => $testCoursierId,
        'token' => $testToken
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'http://localhost/coursier_prod/api/register_device_token.php',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    if ($curlError) {
        echo "Erreur cURL: $curlError\n";
    }
    echo "Réponse API: $response\n";
    
    // Vérifier si le token de test a été créé
    try {
        $stmt = $pdo->prepare("SELECT * FROM device_tokens WHERE token = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$testToken]);
        $testResult = $stmt->fetch();
        
        if ($testResult) {
            echo "✅ Token de test créé avec succès (ID: {$testResult['id']})\n";
            // Nettoyer le token de test
            $pdo->prepare("DELETE FROM device_tokens WHERE id = ?")->execute([$testResult['id']]);
            echo "🧹 Token de test nettoyé\n";
        } else {
            echo "❌ Token de test non trouvé en base\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur vérification token test: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "ERREUR FATALE: " . $e->getMessage() . "\n";
}
?>