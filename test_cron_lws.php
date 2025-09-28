<?php
// test_cron_lws.php - Test de fonctionnement des CRON sur LWS
header('Content-Type: text/plain; charset=utf-8');

echo "=== TEST CRON LWS - " . date('Y-m-d H:i:s') . " ===\n\n";

// 1. Test de l'existence des fichiers CRON
$cronFiles = [
    'automated_db_migration.php' => __DIR__ . '/Scripts/Scripts cron/automated_db_migration.php',
    'fcm_token_security.php' => __DIR__ . '/Scripts/Scripts cron/fcm_token_security.php',
    'fcm_auto_cleanup.php' => __DIR__ . '/Scripts/Scripts cron/fcm_auto_cleanup.php',
    'fcm_daily_diagnostic.php' => __DIR__ . '/Scripts/Scripts cron/fcm_daily_diagnostic.php'
];

echo "1. VÉRIFICATION FICHIERS CRON:\n";
foreach ($cronFiles as $name => $path) {
    $exists = file_exists($path);
    $readable = $exists && is_readable($path);
    echo "   " . ($exists ? '✅' : '❌') . " $name: " . ($exists ? 'EXISTS' : 'MISSING');
    if ($exists) {
        echo " (readable: " . ($readable ? 'YES' : 'NO') . ")";
        echo " (size: " . filesize($path) . " bytes)";
    }
    echo "\n";
}

echo "\n2. TEST EXÉCUTION SCRIPTS:\n";

// Test FCM Token Security
try {
    echo "   🔐 Test FCM Token Security:\n";
    if (file_exists($cronFiles['fcm_token_security.php'])) {
        // Simuler l'exécution sans vraiment l'exécuter
        $content = file_get_contents($cronFiles['fcm_token_security.php']);
        if (strpos($content, 'class FCMTokenSecurity') !== false) {
            echo "      ✅ Classe FCMTokenSecurity trouvée\n";
        }
        if (strpos($content, 'canAcceptNewOrders') !== false) {
            echo "      ✅ Méthode canAcceptNewOrders trouvée\n";
        }
    }
} catch (Exception $e) {
    echo "      ❌ Erreur: " . $e->getMessage() . "\n";
}

// Test Auto Migration
try {
    echo "   🔄 Test Auto Migration:\n";
    if (file_exists($cronFiles['automated_db_migration.php'])) {
        $content = file_get_contents($cronFiles['automated_db_migration.php']);
        if (strpos($content, 'GET_LOCK') !== false) {
            echo "      ✅ Système de verrous MySQL trouvé\n";
        }
        if (strpos($content, 'db_migrations.log') !== false) {
            echo "      ✅ Système de logging trouvé\n";
        }
    }
} catch (Exception $e) {
    echo "      ❌ Erreur: " . $e->getMessage() . "\n";
}

echo "\n3. TEST LOGS ET RÉPERTOIRES:\n";

// Vérifier les répertoires de logs
$logDirs = [
    'diagnostic_logs' => __DIR__ . '/diagnostic_logs',
    'Scripts' => __DIR__ . '/Scripts',
];

foreach ($logDirs as $name => $path) {
    $exists = file_exists($path) && is_dir($path);
    $writable = $exists && is_writable($path);
    echo "   " . ($exists ? '✅' : '❌') . " Répertoire $name: " . ($exists ? 'EXISTS' : 'MISSING');
    if ($exists) {
        echo " (writable: " . ($writable ? 'YES' : 'NO') . ")";
    }
    echo "\n";
}

// Vérifier les logs existants
$logFiles = [
    'db_migrations.log' => __DIR__ . '/diagnostic_logs/db_migrations.log',
    'db_structure_snapshot.json' => __DIR__ . '/diagnostic_logs/db_structure_snapshot.json',
];

foreach ($logFiles as $name => $path) {
    $exists = file_exists($path);
    echo "   " . ($exists ? '📄' : '⚪') . " Log $name: " . ($exists ? 'EXISTS' : 'NOT CREATED YET');
    if ($exists) {
        $size = filesize($path);
        $modified = date('Y-m-d H:i:s', filemtime($path));
        echo " (size: $size bytes, modified: $modified)";
    }
    echo "\n";
}

echo "\n4. TEST CONNEXION BASE DE DONNÉES:\n";
try {
    require_once __DIR__ . '/config.php';
    echo "   ✅ Config chargée\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total_agents FROM agents_suzosky");
    $total = $stmt->fetchColumn();
    echo "   ✅ Connexion DB OK - $total agents dans la base\n";
    
    // Test table migrations si elle existe
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'db_schema_migrations'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM db_schema_migrations");
            $migrations = $stmt->fetchColumn();
            echo "   ✅ Table migrations existe - $migrations entrées\n";
        } else {
            echo "   ⚪ Table migrations pas encore créée\n";
        }
    } catch (Exception $e) {
        echo "   ⚪ Table migrations: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erreur DB: " . $e->getMessage() . "\n";
}

echo "\n5. RECOMMANDATIONS CRON:\n";
echo "   📅 Configuration CRON recommandée pour LWS:\n";
echo "   0 2 * * * /usr/bin/php " . __DIR__ . "/Scripts/Scripts\\ cron/automated_db_migration.php\n";
echo "   0 1 * * * /usr/bin/php " . __DIR__ . "/Scripts/Scripts\\ cron/fcm_token_security.php\n";
echo "   0 */6 * * * /usr/bin/php " . __DIR__ . "/Scripts/Scripts\\ cron/fcm_auto_cleanup.php\n";
echo "   30 2 * * * /usr/bin/php " . __DIR__ . "/Scripts/Scripts\\ cron/fcm_daily_diagnostic.php\n";

echo "\n6. COMMANDES DE TEST MANUEL:\n";
echo "   🧪 Pour tester manuellement sur LWS:\n";
echo "   php " . __DIR__ . "/Scripts/Scripts\\ cron/fcm_token_security.php\n";
echo "   php " . __DIR__ . "/diagnostic_coursiers_disponibilite.php\n";

echo "\n=== FIN TEST CRON ===\n";
?>