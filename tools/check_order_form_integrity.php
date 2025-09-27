#!/usr/bin/env php
<?php
/**
 * check_order_form_integrity.php
 *
 * Safeguards the critical `sections_index/order_form.php` template by
 *  - Verifying a stored SHA-256 hash (immutability contract)
 *  - Ensuring key inputs still exist with expected attributes
 *  - Detecting duplicate function declarations inside the template
 *
 * Usage:
 *   php tools/check_order_form_integrity.php            # Run integrity checks
 *   php tools/check_order_form_integrity.php --update   # Recompute and store the current hash
 *   php tools/check_order_form_integrity.php --show     # Display the current stored hash
 */

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$targetFile  = $projectRoot . DIRECTORY_SEPARATOR . 'sections_index' . DIRECTORY_SEPARATOR . 'order_form.php';
$lockFile    = __DIR__ . DIRECTORY_SEPARATOR . 'order_form_integrity.json';

if (!file_exists($targetFile)) {
    fwrite(STDERR, "❌ Target file not found: {$targetFile}\n");
    exit(2);
}

$args        = array_slice($argv, 1);
$shouldUpdate = in_array('--update', $args, true) || in_array('--update-hash', $args, true);
$shouldShow   = in_array('--show', $args, true);

if ($shouldUpdate) {
    updateLockFile($targetFile, $lockFile);
    exit(0);
}

if (!file_exists($lockFile)) {
    fwrite(STDERR, "⚠️  Integrity lock file missing. Run with --update to generate it.\n");
    exit(3);
}

$lockData = json_decode((string) file_get_contents($lockFile), true, 512, JSON_THROW_ON_ERROR);

if ($shouldShow) {
    $storedHash = $lockData['sha256'] ?? 'n/a';
    echo $storedHash . PHP_EOL;
    exit(0);
}

$currentHash = hash_file('sha256', $targetFile);
$storedHash  = $lockData['sha256'] ?? null;

if ($storedHash === null) {
    fwrite(STDERR, "❌ Integrity lock file is invalid. Regenerate it with --update.\n");
    exit(4);
}

if (!hashEquals($storedHash, $currentHash)) {
    fwrite(STDERR, "❌ Hash mismatch detected for order_form.php\n");
    fwrite(STDERR, "   Stored:  {$storedHash}\n");
    fwrite(STDERR, "   Actual:  {$currentHash}\n");
    fwrite(STDERR, "   Run: php tools/check_order_form_integrity.php --update (after verifying the changes)\n");
    exit(1);
}

enforceRequiredInputs($targetFile);
$duplicates = detectDuplicateFunctions($targetFile);

if (!empty($duplicates)) {
    fwrite(STDERR, "❌ Duplicate function declarations detected in order_form.php:\n");
    foreach ($duplicates as $name => $count) {
        fwrite(STDERR, "   - {$name} (x{$count})\n");
    }
    exit(5);
}

echo "✅ order_form.php integrity check passed.\n";
exit(0);

function updateLockFile(string $targetFile, string $lockFile): void
{
    $hash = hash_file('sha256', $targetFile);
    $payload = [
        'file'    => str_replace('\\', '/', realpath($targetFile) ?: $targetFile),
        'sha256'  => $hash,
        'updated' => date(DATE_ATOM),
    ];

    file_put_contents($lockFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "🔐 Integrity hash updated: {$hash}\n";
}

function hashEquals(string $a, string $b): bool
{
    if (function_exists('hash_equals')) {
        return hash_equals($a, $b);
    }
    if (strlen($a) !== strlen($b)) {
        return false;
    }
    $res = 0;
    for ($i = 0, $len = strlen($a); $i < $len; $i++) {
        $res |= ord($a[$i]) ^ ord($b[$i]);
    }
    return $res === 0;
}

function enforceRequiredInputs(string $filePath): void
{
    $content = (string) file_get_contents($filePath);
    $requiredPatterns = [
        'senderPhone input' => '/<input[^>]*id="senderPhone"[^>]*name="senderPhone"[^>]*>/i',
        'receiverPhone input' => '/<input[^>]*id="receiverPhone"[^>]*name="receiverPhone"[^>]*>/i',
    ];

    foreach ($requiredPatterns as $label => $pattern) {
        if (!preg_match($pattern, $content)) {
            fwrite(STDERR, "❌ Missing required element: {$label}\n");
            exit(6);
        }
    }
}

function detectDuplicateFunctions(string $filePath): array
{
    $content    = (string) file_get_contents($filePath);
    $duplicates = [];

    // PHP function detection (avoids anonymous functions)
    $tokens = token_get_all($content);
    $phpFunctions = [];
    $totalTokens = count($tokens);
    for ($i = 0; $i < $totalTokens; $i++) {
        if (!is_array($tokens[$i])) {
            continue;
        }
        [$tokenId, $value] = $tokens[$i];
        if ($tokenId === T_FUNCTION) {
            // Skip anonymous functions
            $j = $i + 1;
            while ($j < $totalTokens && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $j++;
            }
            if ($j < $totalTokens && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $phpFunctions[] = $tokens[$j][1];
            }
        }
    }

    // JS function detection inside script blocks
    if (preg_match_all('/function\s+([A-Za-z0-9_]+)\s*\(/', $content, $jsMatches)) {
        $jsFunctions = $jsMatches[1];
    } else {
        $jsFunctions = [];
    }

    $all = array_merge($phpFunctions, $jsFunctions);
    if (!empty($all)) {
        $counts = array_count_values($all);
        foreach ($counts as $name => $count) {
            if ($count > 1) {
                $duplicates[$name] = $count;
            }
        }
    }

    return $duplicates;
}
