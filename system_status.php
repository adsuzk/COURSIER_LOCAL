<?php
// system_status.php - Statut en temps réel du système
require_once __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== STATUT TEMPS RÉEL SYSTÈME SUZOSKY ===\n";
echo "Horodatage: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Vérifier si le système automatique est activé
    echo "1. 🤖 SYSTÈME AUTOMATIQUE:\n";
    $systemActiveFile = __DIR__ . '/diagnostic_logs/system_auto_active.flag';
    if (file_exists($systemActiveFile)) {
        echo "   ✅ ACTIVÉ\n";
        $content = file_get_contents($systemActiveFile);
        $lines = explode("\n", trim($content));
        if (count($lines) > 0) {
            echo "   📅 " . $lines[0] . "\n";
        }
    } else {
        echo "   ❌ NON ACTIVÉ\n";
        echo "   💡 Utilisez setup_cron_system.php pour l'activer\n";
    }
    echo "\n";

    // 2. Dernière exécution automatique
    echo "2. ⏰ DERNIÈRE EXÉCUTION:\n";
    $lastRunFile = __DIR__ . '/diagnostic_logs/system_last_run.txt';
    if (file_exists($lastRunFile)) {
        $lastRun = (int)file_get_contents($lastRunFile);
        $lastRunDate = date('Y-m-d H:i:s', $lastRun);
        $minutesAgo = floor((time() - $lastRun) / 60);
        echo "   📅 $lastRunDate (il y a $minutesAgo minutes)\n";
        
        if ($minutesAgo > 120) {
            echo "   ⚠️ Pas d'exécution récente (besoin de trafic)\n";
        } else {
            echo "   ✅ Exécution récente\n";
        }
    } else {
        echo "   ❌ Aucune exécution enregistrée\n";
    }
    echo "\n";

    // 3. Logs récents
    echo "3. 📄 LOGS RÉCENTS:\n";
    $logFiles = [
        'Système auto' => __DIR__ . '/diagnostic_logs/system_auto.log',
        'Migrations' => __DIR__ . '/diagnostic_logs/db_migrations.log',
        'FCM Operations' => __DIR__ . '/diagnostic_logs/fcm_operations.log'
    ];
    
    foreach ($logFiles as $name => $path) {
        if (file_exists($path)) {
            $size = filesize($path);
            $modified = date('Y-m-d H:i:s', filemtime($path));
            echo "   ✅ $name: $size bytes (maj: $modified)\n";
            
            // Afficher les 3 dernières lignes
            $lines = file($path, FILE_IGNORE_NEW_LINES);
            $recentLines = array_slice($lines, -3);
            foreach ($recentLines as $line) {
                if (!empty(trim($line))) {
                    echo "      └─ " . trim($line) . "\n";
                }
            }
        } else {
            echo "   ⚪ $name: Pas encore créé\n";
        }
    }
    echo "\n";

    // 4. État coursiers en temps réel
    echo "4. 👥 COURSIERS TEMPS RÉEL:\n";
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN statut_connexion = 'en_ligne' THEN 1 ELSE 0 END) as en_ligne,
            SUM(CASE WHEN statut_connexion = 'en_ligne' AND TIMESTAMPDIFF(MINUTE, last_login_at, NOW()) <= 30 THEN 1 ELSE 0 END) as actifs_recents
        FROM agents_suzosky
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   👥 Total: {$stats['total']}\n";
    echo "   🟢 En ligne: {$stats['en_ligne']}\n";
    echo "   ⚡ Actifs (<30min): {$stats['actifs_recents']}\n";
    
    if ($stats['actifs_recents'] > 0) {
        echo "   ✅ Service disponible\n";
    } else {
        echo "   🔴 Service temporairement indisponible\n";
    }
    echo "\n";

    // 5. Performance base de données
    echo "5. 💾 PERFORMANCE DB:\n";
    $start = microtime(true);
    $stmt = $pdo->query("SELECT COUNT(*) FROM agents_suzosky");
    $count = $stmt->fetchColumn();
    $queryTime = round((microtime(true) - $start) * 1000, 2);
    echo "   ⚡ Requête test: {$queryTime}ms\n";
    echo "   📊 Agents dans la base: $count\n";
    
    if ($queryTime < 100) {
        echo "   ✅ Performance excellente\n";
    } elseif ($queryTime < 500) {
        echo "   ⚠️ Performance correcte\n";
    } else {
        echo "   🔴 Performance dégradée\n";
    }
    echo "\n";

    // 6. Résumé global
    echo "6. 🎯 RÉSUMÉ GLOBAL:\n";
    $systemActive = file_exists($systemActiveFile);
    $hasRecentRun = file_exists($lastRunFile) && (time() - (int)file_get_contents($lastRunFile)) < 7200; // 2 heures
    $hasAvailableCouriers = $stats['actifs_recents'] > 0;
    $goodPerformance = $queryTime < 500;
    
    $score = ($systemActive ? 1 : 0) + ($hasRecentRun ? 1 : 0) + ($hasAvailableCouriers ? 1 : 0) + ($goodPerformance ? 1 : 0);
    
    if ($score >= 3) {
        echo "   🟢 SYSTÈME OPÉRATIONNEL ($score/4)\n";
        echo "   ✅ Plateforme prête pour production\n";
    } elseif ($score >= 2) {
        echo "   🟡 SYSTÈME PARTIELLEMENT OPÉRATIONNEL ($score/4)\n";
        echo "   ⚠️ Quelques optimisations possibles\n";
    } else {
        echo "   🔴 SYSTÈME NÉCESSITE ATTENTION ($score/4)\n";
        echo "   ❌ Configuration requise\n";
    }

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "\nTrace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN STATUT ===\n";
?>