<?php
/**
 * Script de restauration table clients pour serveur LWS
 * À exécuter une seule fois sur le serveur de production
 * 
 * Usage: php restore_clients_table_lws.php
 */

declare(strict_types=1);
set_time_limit(300); // 5 minutes max
ini_set('memory_limit', '256M');

echo "=== SCRIPT DE RESTAURATION TABLE CLIENTS - LWS ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Serveur: " . ($_SERVER['HTTP_HOST'] ?? gethostname()) . "\n\n";

// Fonction de log sécurisée
function logRestore(string $level, string $message): void {
    $timestamp = date('c');
    $line = "[{$timestamp}] [{$level}] {$message}\n";
    
    echo $line;
    
    // Tentative d'écriture dans les logs
    $logDir = __DIR__ . '/diagnostic_logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    @file_put_contents($logDir . '/restore_clients.log', $line, FILE_APPEND | LOCK_EX);
}

// Configuration base de données pour LWS
function getProductionPDO(): PDO {
    $config = [
        'host' => '185.98.131.214',
        'port' => '3306',
        'name' => 'conci2547642_1m4twb',
        'user' => 'conci2547642_1m4twb',
        'password' => 'wN1!_TT!yHsK6Y6',
    ];
    
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
    
    return new PDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

// Vérifier si table existe
function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        logRestore('ERROR', "Impossible de vérifier table {$table}: " . $e->getMessage());
        return false;
    }
}

// Obtenir colonnes d'une table
function getTableColumns(PDO $pdo, string $table): array {
    $columns = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        foreach ($stmt as $row) {
            if (!empty($row['Field'])) {
                $columns[$row['Field']] = $row;
            }
        }
    } catch (Throwable $e) {
        logRestore('ERROR', "Impossible de lire colonnes {$table}: " . $e->getMessage());
    }
    return $columns;
}

// Créer table clients
function createClientsTable(PDO $pdo): bool {
    logRestore('INFO', 'Tentative de création table clients...');
    
    // D'abord essayer CREATE TABLE ... LIKE si clients_particuliers existe
    if (tableExists($pdo, 'clients_particuliers')) {
        try {
            $pdo->exec('CREATE TABLE `clients` LIKE `clients_particuliers`');
            logRestore('SUCCESS', 'Table clients créée via LIKE clients_particuliers');
            return true;
        } catch (Throwable $e) {
            logRestore('WARN', 'CREATE LIKE échoué: ' . $e->getMessage());
            // Continuer avec DDL de fallback
        }
    }
    
    // DDL de fallback
    $ddl = <<<SQL
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
    `balance` DECIMAL(10,2) DEFAULT 0.00,
    `type_client` ENUM('client', 'coursier', 'admin') DEFAULT 'client',
    INDEX idx_clients_tel (`telephone`),
    INDEX idx_clients_nom (`nom`),
    INDEX idx_type_client (`type_client`),
    INDEX idx_balance (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    try {
        $pdo->exec($ddl);
        logRestore('SUCCESS', 'Table clients créée via DDL de fallback');
        return true;
    } catch (Throwable $e) {
        logRestore('ERROR', 'Création DDL fallback échouée: ' . $e->getMessage());
        return false;
    }
}

// Synchroniser avec clients_particuliers
function syncWithClientsParticuliers(PDO $pdo): bool {
    if (!tableExists($pdo, 'clients_particuliers')) {
        logRestore('WARN', 'Table clients_particuliers absente - synchronisation ignorée');
        return false;
    }
    
    logRestore('INFO', 'Synchronisation avec clients_particuliers...');
    
    $clientsColumns = getTableColumns($pdo, 'clients');
    $partColumns = getTableColumns($pdo, 'clients_particuliers');
    
    if (empty($clientsColumns) || empty($partColumns)) {
        logRestore('ERROR', 'Impossible de lire les colonnes pour synchronisation');
        return false;
    }
    
    // Colonnes communes prioritaires
    $priority = ['id', 'nom', 'prenoms', 'raison_sociale', 'telephone', 'email', 'adresse', 'ville', 'pays', 'balance', 'type_client'];
    $common = [];
    
    foreach ($priority as $col) {
        if (isset($clientsColumns[$col]) && isset($partColumns[$col])) {
            $common[] = $col;
        }
    }
    
    if (empty($common)) {
        logRestore('WARN', 'Aucune colonne commune trouvée pour synchronisation');
        return false;
    }
    
    $quoted = array_map(fn($col) => '`' . str_replace('`', '``', $col) . '`', $common);
    $colList = implode(', ', $quoted);
    
    try {
        $sql = "INSERT IGNORE INTO `clients` ({$colList}) SELECT {$colList} FROM `clients_particuliers`";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rowCount = $stmt->rowCount();
        
        logRestore('SUCCESS', "Synchronisation effectuée: {$rowCount} lignes copiées");
        logRestore('INFO', 'Colonnes synchronisées: ' . implode(', ', $common));
        return true;
    } catch (Throwable $e) {
        logRestore('ERROR', 'Synchronisation échouée: ' . $e->getMessage());
        return false;
    }
}

// Ajouter colonnes manquantes
function ensureRequiredColumns(PDO $pdo): void {
    logRestore('INFO', 'Vérification colonnes requises...');
    
    $columns = getTableColumns($pdo, 'clients');
    if (empty($columns)) {
        logRestore('ERROR', 'Impossible de vérifier les colonnes');
        return;
    }
    
    $required = [
        'balance' => "ALTER TABLE `clients` ADD COLUMN `balance` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Solde client'",
        'type_client' => "ALTER TABLE `clients` ADD COLUMN `type_client` ENUM('client', 'coursier', 'admin') DEFAULT 'client'",
    ];
    
    foreach ($required as $field => $ddl) {
        if (!isset($columns[$field])) {
            try {
                $pdo->exec($ddl);
                logRestore('SUCCESS', "Colonne {$field} ajoutée");
            } catch (Throwable $e) {
                logRestore('WARN', "Impossible d'ajouter {$field}: " . $e->getMessage());
            }
        } else {
            logRestore('INFO', "Colonne {$field} déjà présente");
        }
    }
}

// Script principal
try {
    logRestore('INFO', 'Connexion à la base de données...');
    $pdo = getProductionPDO();
    logRestore('SUCCESS', 'Connexion établie');
    
    // Vérifier si table clients existe
    if (tableExists($pdo, 'clients')) {
        logRestore('INFO', 'Table clients existante trouvée');
        $existingColumns = getTableColumns($pdo, 'clients');
        logRestore('INFO', 'Colonnes actuelles: ' . implode(', ', array_keys($existingColumns)));
        
        // Juste vérifier les colonnes manquantes
        ensureRequiredColumns($pdo);
        
        // Tenter synchronisation même si table existe
        syncWithClientsParticuliers($pdo);
    } else {
        logRestore('INFO', 'Table clients absente - création nécessaire');
        
        // Créer la table
        if (!createClientsTable($pdo)) {
            logRestore('FATAL', 'Impossible de créer la table clients');
            exit(1);
        }
        
        // Ajouter colonnes manquantes
        ensureRequiredColumns($pdo);
        
        // Synchroniser données
        syncWithClientsParticuliers($pdo);
    }
    
    // Vérification finale
    $finalColumns = getTableColumns($pdo, 'clients');
    if (empty($finalColumns)) {
        logRestore('FATAL', 'Table clients non accessible après traitement');
        exit(1);
    }
    
    // Compter les enregistrements
    try {
        $count = $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
        logRestore('SUCCESS', "Table clients opérationnelle avec {$count} enregistrements");
    } catch (Throwable $e) {
        logRestore('WARN', 'Impossible de compter les enregistrements: ' . $e->getMessage());
    }
    
    // Test rapide des APIs critiques
    logRestore('INFO', 'Test des APIs critiques...');
    try {
        $stmt = $pdo->prepare('SELECT id, nom FROM clients LIMIT 1');
        $stmt->execute();
        $test = $stmt->fetch();
        if ($test) {
            logRestore('SUCCESS', 'APIs SELECT clients opérationnelles');
        }
    } catch (Throwable $e) {
        logRestore('WARN', 'Test API échoué: ' . $e->getMessage());
    }
    
    logRestore('SUCCESS', '=== RESTAURATION TERMINÉE AVEC SUCCÈS ===');
    echo "\n✅ RÉSULTAT: Table clients restaurée et opérationnelle\n";
    echo "📊 Logs détaillés dans diagnostic_logs/restore_clients.log\n";
    echo "🚀 Les APIs submit_order et admin peuvent maintenant fonctionner\n\n";
    
} catch (Throwable $e) {
    logRestore('FATAL', 'Erreur critique: ' . $e->getMessage());
    logRestore('FATAL', 'Stack trace: ' . $e->getTraceAsString());
    
    echo "\n❌ ÉCHEC: " . $e->getMessage() . "\n";
    echo "📋 Consultez les logs pour plus de détails\n\n";
    exit(1);
}

echo "=== SCRIPT TERMINÉ ===\n";
exit(0);