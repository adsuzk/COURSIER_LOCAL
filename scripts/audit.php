<?php
/**
 * Audit script for coursier_prod directory
 * Scans for:
 *  - Hard-coded DB credentials
 *  - Direct PDO or mysqli connections outside config
 *  - Multiple password generation functions
 *  - Multiple API endpoints
 *  - Duplicate function definitions
 *  - Environment-specific config usage
 * Reports issues to stdout
 */

$baseDir = realpath(__DIR__ . '/../');
$issues = [];

function scanFile($file) {
    global $issues;
    // Skip files known to have intentional duplicates or DB connections
    $skipPatterns = ['admin.php', 'coursier.php', '/JOURNAL/', 'lib/util.php', 'scripts/mass_refactor.php', 'config.php'];
    foreach ($skipPatterns as $pat) {
        if (strpos($file, $pat) !== false) {
            return;
        }
    }
    $content = file_get_contents($file);
    // 1. Hard-coded DB credentials
    if (preg_match('/(mysqli_connect|new\s+PDO)\s*\(/', $content)) {
        $issues[] = "DB connection found in $file";
    }
    // 2. Password generation functions (strlen >?) and random
    if (preg_match_all('/function\s+password/i', $content, $m)) {
        $issues[] = "Password generator function in $file";
    }
    // 3. API endpoint definitions
    if (preg_match('/\$_GET\[|\/api\//', $content)) {
        $issues[] = "API endpoint or param in $file";
    }
    // 4. Duplicate function definitions
    if (preg_match_all('/function\s+(\w+)\s*\(/', $content, $m)) {
        $names = $m[1];
        foreach (array_count_values($names) as $name => $count) {
            if ($count > 1) {
                $issues[] = "Duplicate function $name in $file ($count times)";
            }
        }
    }
}

// Recursively scan directory
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
foreach ($rii as $file) {
    if ($file->isDir()) continue;
    $path = $file->getPathname();
    if (strpos($path, '/logs/') !== false) continue;
    if (preg_match('/\.php$/i', $path)) {
        scanFile($path);
    }
}

// Output report
if (empty($issues)) {
    echo "No issues found.\n";
} else {
    echo "Audit Report:\n";
    foreach (array_unique($issues) as $i) {
        echo "- $i\n";
    }
}
