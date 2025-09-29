<?php
// setup_database.php
// Initialize local MySQL database for the project using XAMPP defaults.
// - Creates database if missing
// - Imports the provided SQL dump (configurable)
// Usage examples:
//   php setup_database.php                      # uses env DB_NAME or coursier_local, imports only if empty
//   php setup_database.php --db=conci...        # override target DB name
//   php setup_database.php --db=conci... --force --dump=_sql/conci2547642_1m4twb.sql

error_reporting(E_ALL);
ini_set('display_errors', 1);

$rootDir = __DIR__;
require_once $rootDir . '/config.php';

function cliout($msg) { echo $msg . PHP_EOL; }

// Parse CLI args
$opts = [
    'db'    => null,
    'dump'  => null,
    'force' => false,
];
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (preg_match('/^--db=(.+)$/', $arg, $m)) { $opts['db'] = $m[1]; }
        if (preg_match('/^--dump=(.+)$/', $arg, $m)) { $opts['dump'] = $m[1]; }
        if ($arg === '--force') { $opts['force'] = true; }
    }
}

try {
    // Get intended DB params from env overrides (as used by getDBConnection)
    $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = $opts['db'] ?: (getenv('DB_NAME') ?: 'coursier_local');
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';

    // Resolve dump file
    $dumpFile = $opts['dump']
        ?: ($rootDir . '/_sql/conci2547642_1m4twb.sql');
    if (!is_file($dumpFile)) {
        throw new RuntimeException('SQL dump not found at ' . $dumpFile);
    }

    cliout("Target DB: {$dbHost}:{$dbPort} / {$dbName} (user={$dbUser})");
    cliout("Dump file: {$dumpFile}");

    // Connect without DB to create it if missing
    $dsnNoDb = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdoAdmin = new PDO($dsnNoDb, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database if not exists
    $pdoAdmin->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    cliout("Ensured database exists: {$dbName}");

    // Connect to the target DB
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Determine if the DB is empty (no user tables)
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ?");
    $stmt->execute([$dbName]);
    $cnt = (int)$stmt->fetchColumn();

    if ($cnt > 0 && !$opts['force']) {
        cliout("Database already has {$cnt} tables. Skipping import (use --force to re-import).");
        exit(0);
    }

    cliout('Importing SQL dump (this may take a moment)...');

    // Use mysql CLI if available for speed, else fallback to PHP import
    $mysqlCliCandidates = [
        'C:\\xampp\\mysql\\bin\\mysql.exe',
        'mysql',
    ];
    $usedCliPath = null; // unquoted path or command name
    foreach ($mysqlCliCandidates as $cli) {
        $testCmd = (is_file($cli) ? '"' . $cli . '"' : $cli) . ' --version';
        @exec($testCmd, $out, $code);
        if ($code === 0) { $usedCliPath = $cli; break; }
    }

    if ($usedCliPath) {
        // Import via CLI
        $passPart = $dbPass !== '' ? ' -p"' . $dbPass . '"' : '';
        $inner = '"' . $usedCliPath . '" -h "' . $dbHost . '" -P ' . (int)$dbPort . ' -u "' . $dbUser . '"' . $passPart . ' "' . $dbName . '" < "' . $dumpFile . '"';
        // Use cmd to support input redirection on Windows
        if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
            $cmd = 'cmd /c "' . $inner . '"';
        } else {
            $cmd = $inner;
        }
        cliout('Running MySQL import via CLI...');
        system($cmd, $ret);
        if ($ret !== 0) {
            cliout('CLI import failed, falling back to PHP import...');
            // Fallback: PHP import with simple parsing and DEFINER normalization
            $sqlRaw = file_get_contents($dumpFile);
            if ($sqlRaw === false) {
                throw new RuntimeException('Failed to read SQL dump for fallback import');
            }
            // Normalize DEFINER to current user to avoid privilege issues
            $sqlRaw = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/i', 'DEFINER=CURRENT_USER', $sqlRaw);
            // Split statements on semicolon at end of line
            $statements = preg_split('/;\s*\n/m', $sqlRaw);
            $total = count($statements);
            $done = 0;
            foreach ($statements as $stmt) {
                $s = trim($stmt);
                if ($s === '' || str_starts_with($s, '--') || str_starts_with($s, '/*')) {
                    continue;
                }
                try {
                    $pdo->exec($s);
                } catch (Throwable $ie) {
                    // Log and continue for non-critical failures (e.g., view creation)
                    cliout('[WARN] Statement failed, continuing: ' . substr($s, 0, 120) . '... => ' . $ie->getMessage());
                }
                $done++;
            }
            cliout("PHP import executed statements: {$done}/{$total}");
        }
    } else {
        // Fallback: PHP import (slower, but works without CLI)
        $sql = file_get_contents($dumpFile);
        $pdo->exec($sql);
    }

    cliout('Database import completed successfully.');
    exit(0);
} catch (Throwable $e) {
    cliout('[ERROR] ' . $e->getMessage());
    exit(1);
}
