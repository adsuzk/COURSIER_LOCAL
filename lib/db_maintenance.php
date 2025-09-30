<?php
declare(strict_types=1);

if (!function_exists('dbMaintenanceLog')) {
    /**
     * Écrit un message dans le journal de maintenance BD.
     */
    function dbMaintenanceLog(string $level, string $message): void
    {
        $line = sprintf('[%s] %s', strtoupper($level), $message);

        if (function_exists('logMessage')) {
            logMessage('diagnostics_db.log', $line);
            return;
        }

        try {
            $logDir = dirname(__DIR__) . '/diagnostic_logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            @file_put_contents($logDir . '/db_maintenance.log', date('c') . ' ' . $line . PHP_EOL, FILE_APPEND);
        } catch (Throwable $e) {
            // Ignorer les erreurs de journalisation
        }
    }
}

if (!function_exists('dbMaintenanceTableExists')) {
    /**
     * Vérifie l'existence d'une table via SHOW TABLES LIKE.
     */
    function dbMaintenanceTableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            dbMaintenanceLog('error', "SHOW TABLES LIKE '$table' a échoué: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('dbMaintenanceFetchColumns')) {
    /**
     * Retourne la description des colonnes pour une table donnée.
     *
     * @return array<string, array<string, mixed>>
     */
    function dbMaintenanceFetchColumns(PDO $pdo, string $table): array
    {
        $columns = [];
        $tableSafe = str_replace('`', '``', $table);
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableSafe}`");
            if (!$stmt) {
                return [];
            }
            foreach ($stmt as $row) {
                if (!empty($row['Field'])) {
                    $columns[$row['Field']] = $row;
                }
            }
        } catch (Throwable $e) {
            dbMaintenanceLog('error', "SHOW COLUMNS FROM {$table} a échoué: " . $e->getMessage());
            return [];
        }
        return $columns;
    }
}

if (!function_exists('syncLegacyClientsTable')) {
    /**
     * Synchronise la table legacy `clients` avec `clients_particuliers` lorsque possible.
     */
    function syncLegacyClientsTable(PDO $pdo, array $clientsColumns): bool
    {
        if (!dbMaintenanceTableExists($pdo, 'clients_particuliers')) {
            dbMaintenanceLog('warning', 'Synchronisation clients ignorée: table clients_particuliers absente.');
            return false;
        }

        $partColumns = dbMaintenanceFetchColumns($pdo, 'clients_particuliers');
        if (empty($partColumns)) {
            dbMaintenanceLog('warning', 'Synchronisation clients ignorée: impossible de lire les colonnes de clients_particuliers.');
            return false;
        }

        $clientColNames = array_keys($clientsColumns);
        $partColNames = array_keys($partColumns);
        $common = array_values(array_intersect($partColNames, $clientColNames));
        if (empty($common)) {
            dbMaintenanceLog('warning', 'Synchronisation clients ignorée: aucune colonne commune trouvée.');
            return false;
        }

        $quoted = array_map(static fn(string $col): string => '`' . str_replace('`', '``', $col) . '`', $common);
        $colList = implode(', ', $quoted);
        $selectList = implode(', ', $quoted);

        try {
            $pdo->exec("INSERT IGNORE INTO `clients` ({$colList}) SELECT {$selectList} FROM `clients_particuliers`");
            dbMaintenanceLog('info', 'Synchronisation clients effectuée sur colonnes: ' . implode(', ', $common));
            return true;
        } catch (Throwable $e) {
            dbMaintenanceLog('error', 'Synchronisation clients a échoué: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ensureLegacyClientsTable')) {
    /**
     * Garantit l'existence de la table `clients` pour compatibilité legacy.
     *
     * @return array{exists:bool,created:bool,synchronized:bool,warnings:array<int,string>,errors:array<int,string>,columns:array<string,array<string,mixed>>}
     */
    function ensureLegacyClientsTable(PDO $pdo): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $result = [
            'exists' => false,
            'created' => false,
            'synchronized' => false,
            'warnings' => [],
            'errors' => [],
            'columns' => [],
        ];

        if (!dbMaintenanceTableExists($pdo, 'clients')) {
            if (!dbMaintenanceTableExists($pdo, 'clients_particuliers')) {
                $result['warnings'][] = 'clients_particuliers_missing';
                dbMaintenanceLog('warning', 'Table clients introuvable et clients_particuliers absente: impossible de recréer.');
                return $cache = $result;
            }

            try {
                $pdo->exec('CREATE TABLE `clients` LIKE `clients_particuliers`');
                $result['created'] = true;
                dbMaintenanceLog('info', 'Table clients recréée via CREATE TABLE ... LIKE clients_particuliers.');
            } catch (Throwable $e) {
                $result['errors'][] = 'create_like_failed:' . $e->getMessage();
                dbMaintenanceLog('error', 'CREATE TABLE clients LIKE clients_particuliers a échoué: ' . $e->getMessage());

                $fallbackDdl = <<<SQL
CREATE TABLE `clients` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(191) NOT NULL,
    `prenoms` VARCHAR(191) NULL,
    `raison_sociale` VARCHAR(191) NULL,
    `telephone` VARCHAR(40) NULL,
    `email` VARCHAR(191) NULL,
    `adresse` VARCHAR(255) NULL,
    `ville` VARCHAR(100) NULL,
    `pays` VARCHAR(150) NULL,
    `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `date_derniere_commande` TIMESTAMP NULL,
    `statut` VARCHAR(50) DEFAULT 'actif',
    INDEX idx_clients_tel (`telephone`),
    INDEX idx_clients_nom (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
                try {
                    $pdo->exec($fallbackDdl);
                    $result['created'] = true;
                    dbMaintenanceLog('info', 'Table clients recréée via DDL de secours.');
                } catch (Throwable $fallbackError) {
                    $result['errors'][] = 'create_fallback_failed:' . $fallbackError->getMessage();
                    dbMaintenanceLog('error', 'Création fallback de la table clients a échoué: ' . $fallbackError->getMessage());
                    return $cache = $result;
                }
            }
        } else {
            $result['exists'] = true;
        }

        $columns = dbMaintenanceFetchColumns($pdo, 'clients');
        if (empty($columns)) {
            $result['errors'][] = 'fetch_columns_failed';
            dbMaintenanceLog('error', 'Impossible de lire les colonnes de la table clients.');
            return $cache = $result;
        }

        $result['columns'] = $columns;
        $result['exists'] = true;

        // S'assurer que les colonnes essentielles existent
        $required = [
            'id' => "ALTER TABLE `clients` ADD COLUMN `id` INT AUTO_INCREMENT PRIMARY KEY FIRST",
            'nom' => "ALTER TABLE `clients` ADD COLUMN `nom` VARCHAR(191) NOT NULL AFTER `id`",
            'prenoms' => "ALTER TABLE `clients` ADD COLUMN `prenoms` VARCHAR(191) NULL AFTER `nom`",
            'telephone' => "ALTER TABLE `clients` ADD COLUMN `telephone` VARCHAR(40) NULL AFTER `prenoms`",
        ];
        foreach ($required as $field => $ddl) {
            if (!isset($columns[$field])) {
                try {
                    $pdo->exec($ddl);
                    dbMaintenanceLog('info', "Colonne {$field} ajoutée à la table clients.");
                } catch (Throwable $e) {
                    $result['warnings'][] = 'missing_column_' . $field;
                    dbMaintenanceLog('warning', "Impossible d'ajouter la colonne {$field} à clients: " . $e->getMessage());
                }
            }
        }

        $columns = dbMaintenanceFetchColumns($pdo, 'clients');
        if (!empty($columns)) {
            $result['columns'] = $columns;
        }

        $synced = syncLegacyClientsTable($pdo, $result['columns']);
        $result['synchronized'] = $synced;
        if (!$synced) {
            $result['warnings'][] = 'sync_skipped';
        }

        return $cache = $result;
    }
}
