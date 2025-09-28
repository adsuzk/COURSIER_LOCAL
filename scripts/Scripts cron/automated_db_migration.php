<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('memory_limit', '512M');

require_once dirname(__DIR__, 2) . '/config.php';
$migrationsFile = dirname(__DIR__) . '/db_schema_migrations.php';
if (!file_exists($migrationsFile)) {
    fwrite(STDERR, "Fichier de migrations introuvable: {$migrationsFile}\n");
    exit(1);
}

/** @var array<int,array<string,mixed>> $migrations */
$migrations = require $migrationsFile;
if (!is_array($migrations)) {
    fwrite(STDERR, "Le fichier de migrations doit retourner un tableau.\n");
    exit(1);
}

$logDir = dirname(__DIR__, 2) . '/diagnostic_logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/db_migrations.log';

/**
 * Écrit une ligne dans le journal et en sortie standard.
 */
function migrationLog(string $message, string $level = 'INFO'): void
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $line = sprintf('[%s] [%s] %s', $timestamp, strtoupper($level), $message);
    echo $line . PHP_EOL;
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
}

/**
 * Retourne une connexion PDO sécurisée.
 */
function migrationGetPdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    return $pdo;
}

/**
 * Vérifie l'existence de la table de suivi des migrations et la crée le cas échéant.
 */
function ensureMigrationsTable(PDO $pdo): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `id` VARCHAR(191) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('success','failed') NOT NULL DEFAULT 'success',
    `applied_at` DATETIME NOT NULL,
    `execution_time_ms` INT UNSIGNED NULL DEFAULT NULL,
    `details` TEXT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $pdo->exec($sql);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE ?');
    $stmt->execute([$column]);
    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare('SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?');
    $stmt->execute([$index]);
    return (bool) $stmt->fetchColumn();
}

function getAppliedStatus(PDO $pdo, string $id): ?string
{
    $stmt = $pdo->prepare('SELECT status FROM schema_migrations WHERE id = ?');
    $stmt->execute([$id]);
    $status = $stmt->fetchColumn();
    if ($status === false) {
        return null;
    }
    return (string) $status;
}

function recordMigration(PDO $pdo, string $id, string $description, string $status, int $executionTimeMs, ?string $details = null): void
{
    $stmt = $pdo->prepare('INSERT INTO schema_migrations (id, description, status, applied_at, execution_time_ms, details) VALUES (?, ?, ?, NOW(), ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), applied_at = VALUES(applied_at), execution_time_ms = VALUES(execution_time_ms), details = VALUES(details)');
    $stmt->execute([$id, $description, $status, $executionTimeMs, $details]);
}

/**
 * Évalue une condition de skip/onlyIf.
 *
 * @param array<string,mixed> $condition
 */
function evaluateCondition(PDO $pdo, array $condition): bool
{
    if (isset($condition['tableExists'])) {
        return tableExists($pdo, (string) $condition['tableExists']);
    }
    if (isset($condition['columnExists'])) {
        $cfg = $condition['columnExists'];
        $table = (string) ($cfg['table'] ?? '');
        $column = (string) ($cfg['column'] ?? '');
        if ($table === '' || $column === '') {
            return false;
        }
        return columnExists($pdo, $table, $column);
    }
    if (isset($condition['indexExists'])) {
        $cfg = $condition['indexExists'];
        $table = (string) ($cfg['table'] ?? '');
        $index = (string) ($cfg['index'] ?? '');
        if ($table === '' || $index === '') {
            return false;
        }
        return indexExists($pdo, $table, $index);
    }
    return false;
}

/**
 * Exécute une étape de migration et retourne son statut.
 *
 * @param array<string,mixed> $step
 * @return array{status:string,message:string}
 */
function executeMigrationStep(PDO $pdo, array $step): array
{
    $type = $step['type'] ?? 'runSql';
    $label = $step['label'] ?? ($step['type'] ?? 'step');

    if (isset($step['skipIf']) && evaluateCondition($pdo, (array) $step['skipIf'])) {
        return ['status' => 'skipped', 'message' => "Ignoré (condition skipIf satisfaite) : {$label}"];
    }

    if (isset($step['onlyIf']) && !evaluateCondition($pdo, (array) $step['onlyIf'])) {
        return ['status' => 'skipped', 'message' => "Ignoré (condition onlyIf non satisfaite) : {$label}"];
    }

    switch ($type) {
        case 'ensureTable':
            $table = (string) ($step['table'] ?? '');
            $create = (string) ($step['createStatement'] ?? '');
            if ($table === '' || $create === '') {
                throw new RuntimeException('ensureTable requiert "table" et "createStatement".');
            }
            if (tableExists($pdo, $table)) {
                return ['status' => 'skipped', 'message' => "Table {$table} déjà existante"];
            }
            $pdo->exec($create);
            return ['status' => 'success', 'message' => "Table {$table} créée"];

        case 'ensureColumn':
            $table = (string) ($step['table'] ?? '');
            $column = (string) ($step['column'] ?? '');
            $definition = (string) ($step['definition'] ?? '');
            if ($table === '' || $column === '' || $definition === '') {
                throw new RuntimeException('ensureColumn requiert "table", "column" et "definition".');
            }
            if (columnExists($pdo, $table, $column)) {
                return ['status' => 'skipped', 'message' => "Colonne {$table}.{$column} déjà existante"];
            }
            $sql = sprintf('ALTER TABLE `%s` ADD COLUMN `%s` %s', str_replace('`', '``', $table), str_replace('`', '``', $column), $definition);
            $pdo->exec($sql);
            return ['status' => 'success', 'message' => "Colonne {$table}.{$column} ajoutée"];

        case 'ensureIndex':
            $table = (string) ($step['table'] ?? '');
            $index = (string) ($step['index'] ?? '');
            $columns = $step['columns'] ?? [];
            $unique = (bool) ($step['unique'] ?? false);
            if ($table === '' || $index === '' || empty($columns)) {
                throw new RuntimeException('ensureIndex requiert "table", "index" et "columns".');
            }
            if (indexExists($pdo, $table, $index)) {
                return ['status' => 'skipped', 'message' => "Index {$table}.{$index} déjà existant"];
            }
            $quotedCols = array_map(static fn($col) => '`' . str_replace('`', '``', (string) $col) . '`', (array) $columns);
            $sql = sprintf('ALTER TABLE `%s` ADD %s `%s` (%s)',
                str_replace('`', '``', $table),
                $unique ? 'UNIQUE INDEX' : 'INDEX',
                str_replace('`', '``', $index),
                implode(', ', $quotedCols)
            );
            $pdo->exec($sql);
            return ['status' => 'success', 'message' => "Index {$table}.{$index} créé"];

        case 'runSql':
        default:
            $sql = (string) ($step['sql'] ?? '');
            if ($sql === '') {
                throw new RuntimeException('runSql requiert "sql".');
            }
            $affected = $pdo->exec($sql);
            return ['status' => 'success', 'message' => "SQL exécuté ({$affected} lignes affectées) : {$label}"];
    }
}

$pdo = migrationGetPdo();
ensureMigrationsTable($pdo);

// Serrure applicative pour éviter les exécutions simultanées.
try {
    $lockStmt = $pdo->query("SELECT GET_LOCK('automated_schema_migration', 5)");
    $lockAcquired = (int) $lockStmt->fetchColumn() === 1;
} catch (Throwable $e) {
    $lockAcquired = false;
    migrationLog('Impossible de créer le verrou de migration: ' . $e->getMessage(), 'WARNING');
}

if (!$lockAcquired) {
    migrationLog("Migration déjà en cours ou verrou indisponible.", 'WARNING');
    exit(0);
}

$results = [
    'applied' => [],
    'skipped' => [],
    'failed' => [],
];

foreach ($migrations as $migration) {
    $id = (string) ($migration['id'] ?? '');
    $description = (string) ($migration['description'] ?? '');
    $steps = $migration['steps'] ?? [];

    if ($id === '' || !is_array($steps)) {
        migrationLog('Migration invalide (id ou steps manquants).', 'ERROR');
        continue;
    }

    if (getAppliedStatus($pdo, $id) === 'success') {
        migrationLog("➡️  Migration {$id} déjà appliquée - ignorée.", 'INFO');
        $results['skipped'][] = $id;
        continue;
    }

    migrationLog("➡️  Début migration {$id}: {$description}", 'INFO');
    $start = microtime(true);
    $details = [];
    $success = true;

    foreach ($steps as $step) {
        try {
            $outcome = executeMigrationStep($pdo, (array) $step);
            $details[] = $outcome['message'];
            migrationLog('   • ' . $outcome['message'], $outcome['status'] === 'success' ? 'INFO' : 'DEBUG');
        } catch (Throwable $stepError) {
            $success = false;
            $details[] = 'ERREUR: ' . $stepError->getMessage();
            migrationLog('   ✖ ' . $stepError->getMessage(), 'ERROR');
            break;
        }
    }

    $durationMs = (int) round((microtime(true) - $start) * 1000);

    if ($success) {
        recordMigration($pdo, $id, $description, 'success', $durationMs, implode("\n", $details));
        $results['applied'][] = $id;
        migrationLog("✅ Migration {$id} terminée en {$durationMs}ms.", 'SUCCESS');
    } else {
        recordMigration($pdo, $id, $description, 'failed', $durationMs, implode("\n", $details));
        $results['failed'][] = $id;
        migrationLog("❌ Migration {$id} échouée (durée {$durationMs}ms).", 'ERROR');
        break; // Arrêt sur erreur pour éviter cascade
    }
}

// Libération du verrou
try {
    $pdo->query("SELECT RELEASE_LOCK('automated_schema_migration')");
} catch (Throwable $e) {
    migrationLog('Impossible de libérer le verrou de migration: ' . $e->getMessage(), 'WARNING');
}

migrationLog('---- RÉCAPITULATIF ----', 'INFO');
migrationLog('Appliquées : ' . implode(', ', $results['applied'] ?: ['aucune']), 'INFO');
migrationLog('Ignorées  : ' . implode(', ', $results['skipped'] ?: ['aucune']), 'INFO');
migrationLog('Échecs    : ' . implode(', ', $results['failed'] ?: ['aucun']), empty($results['failed']) ? 'INFO' : 'ERROR');

exit(empty($results['failed']) ? 0 : 1);
