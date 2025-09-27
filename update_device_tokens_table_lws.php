<?php
/**
 * Script de mise à jour table device_tokens pour LWS
 * À exécuter une seule fois sur le serveur de production
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', '1');

echo "=== MISE À JOUR TABLE DEVICE_TOKENS - LWS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Serveur: " . ($_SERVER['HTTP_HOST'] ?? gethostname()) . "\n\n";

function logUpdate(string $level, string $message): void {
    $timestamp = date('c');
    $line = "[{$timestamp}] [{$level}] {$message}\n";
    echo $line;
}

try {
    require_once __DIR__ . '/../config.php';
    $pdo = getDBConnection();
    logUpdate('INFO', 'Connexion DB établie');
    
    // Vérifier la structure actuelle
    logUpdate('INFO', 'Vérification structure actuelle...');
    $stmt = $pdo->query("SHOW COLUMNS FROM device_tokens");
    $currentColumns = [];
    foreach ($stmt as $col) {
        $currentColumns[$col['Field']] = $col['Type'];
        logUpdate('INFO', "Colonne existante: {$col['Field']} ({$col['Type']})");
    }
    
    // Colonnes attendues par l'API
    $expectedColumns = [
        'token_hash' => 'CHAR(64)',
        'agent_id' => 'INT'
    ];
    
    // Modifications nécessaires
    $modifications = [
        // 1. Modifier token en TEXT si nécessaire
        "ALTER TABLE device_tokens MODIFY COLUMN token TEXT NOT NULL",
        // 2. Ajouter token_hash si absent
        "ALTER TABLE device_tokens ADD COLUMN token_hash CHAR(64) NULL AFTER token",
        // 3. Ajouter agent_id si absent  
        "ALTER TABLE device_tokens ADD COLUMN agent_id INT NULL AFTER coursier_id",
        // 4. Remplir token_hash pour les tokens existants
        "UPDATE device_tokens SET token_hash = SHA2(token, 256) WHERE token_hash IS NULL OR token_hash = ''",
        // 5. Rendre token_hash NOT NULL
        "ALTER TABLE device_tokens MODIFY COLUMN token_hash CHAR(64) NOT NULL",
        // 6. Ajouter index unique sur token_hash
        "ALTER TABLE device_tokens ADD UNIQUE KEY uniq_token_hash (token_hash)",
        // 7. Ajouter index sur agent_id
        "ALTER TABLE device_tokens ADD KEY idx_agent_id (agent_id)"
    ];
    
    $successful = 0;
    $skipped = 0;
    
    foreach ($modifications as $i => $sql) {
        $step = $i + 1;
        logUpdate('INFO', "Étape $step: " . substr($sql, 0, 50) . '...');
        
        try {
            $pdo->exec($sql);
            logUpdate('SUCCESS', "Étape $step réussie");
            $successful++;
        } catch (Exception $e) {
            // Certaines modifications peuvent échouer si déjà appliquées
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate') !== false || 
                strpos($msg, 'already exists') !== false ||
                strpos($msg, 'can\'t DROP') !== false) {
                logUpdate('INFO', "Étape $step ignorée (déjà appliquée): " . $msg);
                $skipped++;
            } else {
                logUpdate('WARN', "Étape $step échouée: " . $msg);
            }
        }
    }
    
    // Vérification finale
    logUpdate('INFO', 'Vérification structure finale...');
    $stmt = $pdo->query("SHOW COLUMNS FROM device_tokens");
    $finalColumns = [];
    foreach ($stmt as $col) {
        $finalColumns[] = $col['Field'];
    }
    
    logUpdate('SUCCESS', 'Colonnes finales: ' . implode(', ', $finalColumns));
    
    // Test d'insertion
    logUpdate('INFO', 'Test d\'insertion...');
    $testToken = 'test_migration_' . time();
    $testHash = hash('sha256', $testToken);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO device_tokens (coursier_id, agent_id, token, token_hash) VALUES (?, ?, ?, ?)");
        $stmt->execute([1, 1, $testToken, $testHash]);
        $testId = $pdo->lastInsertId();
        logUpdate('SUCCESS', "Test insertion OK (ID: $testId)");
        
        // Nettoyer le test
        $pdo->prepare("DELETE FROM device_tokens WHERE id = ?")->execute([$testId]);
        logUpdate('INFO', 'Test nettoyé');
    } catch (Exception $e) {
        logUpdate('ERROR', 'Test insertion échoué: ' . $e->getMessage());
    }
    
    logUpdate('SUCCESS', "=== MISE À JOUR TERMINÉE ===");
    logUpdate('SUCCESS', "Modifications réussies: $successful");
    logUpdate('INFO', "Modifications ignorées: $skipped");
    
    echo "\n✅ Table device_tokens mise à jour avec succès\n";
    echo "📊 L'API register_device_token.php devrait maintenant fonctionner\n\n";
    
} catch (Exception $e) {
    logUpdate('FATAL', 'Erreur critique: ' . $e->getMessage());
    echo "\n❌ ÉCHEC: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "=== SCRIPT TERMINÉ ===\n";
?>