<?php
/**
 * Audit complet du dossier coursier_prod
 * - Détection de doublons de fichiers par hash (SHA256)
 * - Détection de doublons de code PHP (fonctions/méthodes identiques textuellement)
 * - Rapport JSON + HTML
 * - Option de mise en quarantaine des doublons
 *
 * Usage CLI: php tools/audit_repo.php [--quarantine]
 * Usage Web: tools/audit_repo.php
 */

$ROOT = realpath(dirname(__DIR__));
chdir($ROOT);

$OUTPUT_DIR = __DIR__ . '/audit_output';
if (!is_dir($OUTPUT_DIR)) {
    @mkdir($OUTPUT_DIR, 0775, true);
}

$report = [
    'timestamp' => date('c'),
    'root' => $ROOT,
    'duplicates' => [
        'files_by_hash' => [],
        'php_functions' => [],
    ],
    'stats' => [
        'total_files' => 0,
        'total_size' => 0,
        'by_ext' => [],
    ],
];

$excludeDirs = [
    '.git', 'vendor', 'node_modules', 'tmp', 'temp', 'logs', 'log', 'backup', 'backups'
];

function iterFiles($root, $excludeDirs) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        if (!$file->isFile()) continue;
        $path = $file->getPathname();
        $rel = substr($path, strlen($root) + 1);
        $parts = explode(DIRECTORY_SEPARATOR, $rel);
        if (count($parts) > 0 && in_array($parts[0], $GLOBALS['excludeDirs'])) {
            continue;
        }
        yield $path;
    }
}

function hashFileSafe($path) {
    try {
        return hash_file('sha256', $path);
    } catch (Throwable $e) {
        return null;
    }
}

function collectStats(&$report, $path) {
    $size = @filesize($path) ?: 0;
    $report['stats']['total_files']++;
    $report['stats']['total_size'] += $size;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!isset($report['stats']['by_ext'][$ext])) $report['stats']['by_ext'][$ext] = ['count' => 0, 'size' => 0];
    $report['stats']['by_ext'][$ext]['count']++;
    $report['stats']['by_ext'][$ext]['size'] += $size;
}

function extractPhpFunctions($code) {
    $functions = [];
    // Normalisation simple: supprimer espaces multiples et commentaires simples
    $normalized = preg_replace('/\/\*.*?\*\//s', '', $code); // comments /* */
    $normalized = preg_replace('/\/\/.*$/m', '', $normalized); // // comments
    $normalized = preg_replace('/#.*$/m', '', $normalized); // # comments
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    
    // Capture des fonctions globales
    if (preg_match_all('/function\s+([a-zA-Z0-9_]+)\s*\((.*?)\)\s*\{(.*?)\}/s', $normalized, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $name = $match[1];
            $signature = $match[0];
            $hash = hash('sha256', $signature);
            $functions[] = [
                'name' => $name,
                'hash' => $hash,
                'signature_preview' => substr($signature, 0, 200) . (strlen($signature) > 200 ? '...' : '')
            ];
        }
    }
    return $functions;
}

$hashGroups = [];
$phpFunctionGroups = [];

foreach (iterFiles($ROOT, $excludeDirs) as $path) {
    collectStats($report, $path);

    // Groupement par hash de fichier
    $hash = hashFileSafe($path);
    if ($hash) {
        $hashGroups[$hash] = $hashGroups[$hash] ?? [];
        $hashGroups[$hash][] = $path;
    }

    // Analyse PHP
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
        $code = @file_get_contents($path);
        if ($code !== false) {
            $funcs = extractPhpFunctions($code);
            foreach ($funcs as $fn) {
                $phpFunctionGroups[$fn['hash']] = $phpFunctionGroups[$fn['hash']] ?? [
                    'name' => $fn['name'], 'signature_preview' => $fn['signature_preview'], 'files' => []
                ];
                $phpFunctionGroups[$fn['hash']]['files'][] = $path;
            }
        }
    }
}

// Filtrer les doublons (groupes de taille > 1)
foreach ($hashGroups as $h => $files) {
    if (count($files) > 1) {
        $report['duplicates']['files_by_hash'][] = [
            'hash' => $h,
            'count' => count($files),
            'files' => $files,
        ];
    }
}

foreach ($phpFunctionGroups as $h => $entry) {
    $files = array_unique($entry['files']);
    if (count($files) > 1) {
        $report['duplicates']['php_functions'][] = [
            'hash' => $h,
            'name' => $entry['name'],
            'signature_preview' => $entry['signature_preview'],
            'count' => count($files),
            'files' => $files,
        ];
    }
}

// Enregistrer le rapport JSON
$jsonPath = $OUTPUT_DIR . '/audit_report.json';
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

// Générer un HTML simple
$htmlPath = $OUTPUT_DIR . '/audit_report.html';
ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Audit coursier_prod</title>
<style>
body{font-family:Arial, sans-serif;background:#f7f7f7;margin:0;padding:20px;color:#222}
.container{max-width:1200px;margin:0 auto;background:#fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08);padding:20px}
h1{margin-top:0}
pre{white-space:pre-wrap;word-wrap:break-word}
.badge{display:inline-block;padding:4px 8px;border-radius:12px;background:#eee;margin-right:6px}
.badge-ok{background:#e8f5e9;color:#2e7d32}
.badge-warn{background:#fff8e1;color:#f57f17}
.badge-err{background:#ffebee;color:#c62828}
.code{font-family:Consolas, monospace;font-size:12px;background:#fafafa;border:1px solid #eee;border-radius:6px;padding:10px}
.group{border:1px solid #ddd;border-radius:6px;padding:10px;margin:10px 0}
.small{color:#777;font-size:12px}
</style>
</head>
<body>
<div class="container">
  <h1>Audit coursier_prod</h1>
  <p class="small">Généré le <?= htmlspecialchars($report['timestamp']) ?></p>
  <h2>Statistiques</h2>
  <ul>
    <li>Total fichiers: <strong><?= (int)$report['stats']['total_files'] ?></strong></li>
    <li>Taille totale: <strong><?= number_format($report['stats']['total_size']/1024, 2) ?> KB</strong></li>
  </ul>
  <h3>Par extension</h3>
  <div class="code"><pre><?php echo htmlspecialchars(json_encode($report['stats']['by_ext'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); ?></pre></div>

  <h2>Doublons de fichiers (par hash)</h2>
  <?php if (empty($report['duplicates']['files_by_hash'])): ?>
    <p><span class="badge badge-ok">OK</span> Aucun doublon exact trouvé.</p>
  <?php else: ?>
    <?php foreach ($report['duplicates']['files_by_hash'] as $grp): ?>
      <div class="group">
        <div><strong>Hash:</strong> <?= htmlspecialchars($grp['hash']) ?> <span class="badge badge-warn"><?= (int)$grp['count'] ?> fichiers</span></div>
        <ul>
          <?php foreach ($grp['files'] as $f): ?>
            <li><code><?= htmlspecialchars($f) ?></code></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2>Doublons de fonctions PHP</h2>
  <?php if (empty($report['duplicates']['php_functions'])): ?>
    <p><span class="badge badge-ok">OK</span> Aucun doublon de code significatif trouvé.</p>
  <?php else: ?>
    <?php foreach ($report['duplicates']['php_functions'] as $grp): ?>
      <div class="group">
        <div><strong>Fonction:</strong> <?= htmlspecialchars($grp['name']) ?> <span class="badge badge-warn"><?= (int)$grp['count'] ?> occurrences</span></div>
        <div class="small">Aperçu: <code><?= htmlspecialchars($grp['signature_preview']) ?></code></div>
        <ul>
          <?php foreach ($grp['files'] as $f): ?>
            <li><code><?= htmlspecialchars($f) ?></code></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h2>Fichiers rapport</h2>
  <ul>
    <li>JSON: <code><?= htmlspecialchars($jsonPath) ?></code></li>
    <li>HTML: <code><?= htmlspecialchars($htmlPath) ?></code></li>
  </ul>
</div>
</body>
</html>
<?php
$html = ob_get_clean();
file_put_contents($htmlPath, $html);

// Mise en quarantaine facultative
$quarantine = in_array('--quarantine', $argv ?? []);
if ($quarantine && !empty($report['duplicates']['files_by_hash'])) {
    $qdir = $OUTPUT_DIR . '/quarantine_' . date('Ymd_His');
    @mkdir($qdir, 0775, true);
    foreach ($report['duplicates']['files_by_hash'] as $grp) {
        $i = 0;
        foreach ($grp['files'] as $f) {
            // on conserve le premier, on met les suivants en quarantaine
            if ($i === 0) { $i++; continue; }
            $rel = str_replace(['\\', $ROOT . DIRECTORY_SEPARATOR], ['/', ''], $f);
            $target = $qdir . '/' . str_replace('/', '__', $rel);
            @copy($f, $target);
            // ne pas supprimer automatiquement par défaut
        }
    }
}

// Suppression optionnelle des doublons de fichiers (conserve le premier)
$delete = in_array('--delete', $argv ?? []);
$deletedFiles = [];
if ($delete && !empty($report['duplicates']['files_by_hash'])) {
    $qdir = $OUTPUT_DIR . '/quarantine_' . date('Ymd_His');
    @mkdir($qdir, 0775, true);

    // Liste de motifs à ne jamais supprimer
    $neverDelete = [
        '/(^|\\\\|\/)config\.php$/i',
        '/(^|\\\\|\/)index\.php$/i',
        '/(^|\\\\|\/)admin\.php$/i',
        '/(^|\\\\|\/)server_check\.php$/i',
        '/(^|\\\\|\/)tools(\\\\|\/)/i',
    ];

    foreach ($report['duplicates']['files_by_hash'] as $grp) {
        $i = 0;
        foreach ($grp['files'] as $f) {
            $i++;
            if ($i === 1) continue; // conserve le premier

            $skip = false;
            foreach ($neverDelete as $rx) {
                if (preg_match($rx, $f)) { $skip = true; break; }
            }
            if ($skip) continue;

            $rel = str_replace([$ROOT . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $f);
            $backup = $qdir . '/' . str_replace('/', '__', $rel);
            @copy($f, $backup);
            if (@unlink($f)) {
                $deletedFiles[] = $f;
            }
        }
    }
}

$done = [
    'json' => $jsonPath,
    'html' => $htmlPath,
    'quarantine_hint' => $quarantine ? 'Copies en quarantaine effectuées (aucune suppression automatique)' : 'Exécutez avec --quarantine pour copier les doublons',
    'deleted_files' => $deletedFiles,
    'usage' => [
        'php tools/audit_repo.php' => 'génère les rapports sans action',
        'php tools/audit_repo.php --quarantine' => 'copie les doublons (fichiers) en quarantaine',
        'php tools/audit_repo.php --delete' => 'supprime les doublons après sauvegarde en quarantaine',
    ],
];

if (php_sapi_name() === 'cli') {
    echo json_encode($done, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . "\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($done);
}
