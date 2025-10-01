<?php
/**
 * Script d'optimisation et diagnostic de performance
 * Corrige les problèmes de lenteur après redémarrage
 */

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 DIAGNOSTIC DE PERFORMANCE SYSTÈME\n";
echo str_repeat("=", 70) . "\n\n";

$start_time = microtime(true);

// 1. Test connexion base de données
echo "1. TEST CONNEXION BASE DE DONNÉES\n";
echo str_repeat("-", 70) . "\n";

try {
    require_once __DIR__ . '/config.php';
    $pdo = getDBConnection();
    
    $start_query = microtime(true);
    $result = $pdo->query("SELECT 1 as test")->fetch();
    $query_time = (microtime(true) - $start_query) * 1000;
    
    echo "✅ Connexion MySQL: OK\n";
    echo "⏱️  Temps de requête: " . round($query_time, 2) . " ms\n";
    
    if ($query_time > 100) {
        echo "⚠️  LENT: La requête prend plus de 100ms!\n";
    } elseif ($query_time > 50) {
        echo "⚡ MOYEN: Performance acceptable\n";
    } else {
        echo "🚀 RAPIDE: Excellente performance\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur connexion: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// 2. Vérifier les tables
echo "2. VÉRIFICATION TABLES PRINCIPALES\n";
echo str_repeat("-", 70) . "\n";

$tables = ['commandes', 'agents_suzosky', 'device_tokens', 'clients'];
foreach ($tables as $table) {
    $start_query = microtime(true);
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $query_time = (microtime(true) - $start_query) * 1000;
        
        $status = $query_time < 50 ? "🚀" : ($query_time < 100 ? "⚡" : "⚠️ ");
        echo sprintf("%s Table %-20s: %6d lignes (%6.2f ms)\n", 
                    $status, $table, $count, $query_time);
                    
    } catch (Exception $e) {
        echo "❌ Table $table: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 3. Optimiser les tables
echo "3. OPTIMISATION DES TABLES\n";
echo str_repeat("-", 70) . "\n";

foreach ($tables as $table) {
    try {
        $start_opt = microtime(true);
        $pdo->exec("OPTIMIZE TABLE `$table`");
        $opt_time = (microtime(true) - $start_opt) * 1000;
        echo "✅ Table $table optimisée en " . round($opt_time, 2) . " ms\n";
    } catch (Exception $e) {
        echo "⚠️  Table $table: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 4. Vérifier les index
echo "4. VÉRIFICATION DES INDEX\n";
echo str_repeat("-", 70) . "\n";

$index_checks = [
    'commandes' => ['coursier_id', 'statut', 'created_at'],
    'agents_suzosky' => ['statut_connexion', 'matricule'],
    'device_tokens' => ['coursier_id', 'is_active']
];

foreach ($index_checks as $table => $columns) {
    try {
        $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $indexed_columns = array_column($indexes, 'Column_name');
        
        foreach ($columns as $col) {
            if (in_array($col, $indexed_columns)) {
                echo "✅ Index sur $table.$col existe\n";
            } else {
                echo "⚠️  MANQUANT: Index sur $table.$col\n";
                // Créer l'index
                try {
                    $pdo->exec("ALTER TABLE `$table` ADD INDEX idx_$col (`$col`)");
                    echo "   ✅ Index créé!\n";
                } catch (Exception $e) {
                    echo "   ❌ Erreur création: " . $e->getMessage() . "\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "❌ Erreur vérification $table: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// 5. Statistiques de performance
echo "5. STATISTIQUES MYSQL\n";
echo str_repeat("-", 70) . "\n";

try {
    $stats = $pdo->query("SHOW STATUS LIKE '%slow%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($stats as $key => $value) {
        echo "$key: $value\n";
    }
    
    echo "\nConnexions:\n";
    $connections = $pdo->query("SHOW STATUS LIKE '%connect%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($connections as $key => $value) {
        if (stripos($key, 'connection') !== false || stripos($key, 'connect') !== false) {
            echo "$key: $value\n";
        }
    }
} catch (Exception $e) {
    echo "⚠️  " . $e->getMessage() . "\n";
}

echo "\n";

// 6. Cache OPcache
echo "6. CONFIGURATION PHP\n";
echo str_repeat("-", 70) . "\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "OPcache activé: " . (ini_get('opcache.enable') ? "✅ OUI" : "❌ NON") . "\n";
echo "Mémoire limite: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "s\n";
echo "Upload max size: " . ini_get('upload_max_filesize') . "\n";

if (function_exists('opcache_get_status')) {
    $opcache = opcache_get_status();
    if ($opcache && isset($opcache['opcache_enabled'])) {
        echo "OPcache hit rate: " . round($opcache['opcache_statistics']['opcache_hit_rate'], 2) . "%\n";
        echo "OPcache mémoire utilisée: " . round($opcache['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
    }
}

echo "\n";

// 7. Nettoyage sessions
echo "7. NETTOYAGE SESSIONS\n";
echo str_repeat("-", 70) . "\n";

$session_path = session_save_path();
if (empty($session_path)) {
    $session_path = sys_get_temp_dir();
}

echo "Chemin sessions: $session_path\n";

if (is_dir($session_path)) {
    $sessions = glob($session_path . '/sess_*');
    $old_sessions = 0;
    $now = time();
    
    foreach ($sessions as $session_file) {
        $age = $now - filemtime($session_file);
        // Supprimer sessions > 24h
        if ($age > 86400) {
            @unlink($session_file);
            $old_sessions++;
        }
    }
    
    echo "Sessions nettoyées: $old_sessions\n";
    echo "Sessions actives: " . (count($sessions) - $old_sessions) . "\n";
}

echo "\n";

// 8. Test de chargement de page
echo "8. TEST CHARGEMENT PAGE\n";
echo str_repeat("-", 70) . "\n";

$pages_to_test = [
    'index.php' => 'Page d\'accueil',
    'admin.php' => 'Page admin',
];

foreach ($pages_to_test as $page => $name) {
    if (file_exists(__DIR__ . '/' . $page)) {
        $start_load = microtime(true);
        ob_start();
        try {
            include __DIR__ . '/' . $page;
            ob_end_clean();
            $load_time = (microtime(true) - $start_load) * 1000;
            
            $status = $load_time < 100 ? "🚀 RAPIDE" : ($load_time < 500 ? "⚡ MOYEN" : "⚠️  LENT");
            echo sprintf("%-20s: %s (%6.2f ms)\n", $name, $status, $load_time);
        } catch (Exception $e) {
            ob_end_clean();
            echo sprintf("%-20s: ❌ ERREUR\n", $name);
        }
    }
}

echo "\n";

// Temps total
$total_time = (microtime(true) - $start_time) * 1000;
echo str_repeat("=", 70) . "\n";
echo "⏱️  TEMPS TOTAL DU DIAGNOSTIC: " . round($total_time, 2) . " ms\n";

if ($total_time < 1000) {
    echo "🚀 Système performant!\n";
} elseif ($total_time < 3000) {
    echo "⚡ Système acceptable\n";
} else {
    echo "⚠️  Système lent - Optimisation nécessaire\n";
}

echo "\n✅ DIAGNOSTIC TERMINÉ\n";
?>
