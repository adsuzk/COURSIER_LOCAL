<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/lib/db_maintenance.php';

$exitCode = 0;

try {
    $pdo = getDBConnection();
    echo "[INFO] Connexion DB réussie" . PHP_EOL;

    $result = ensureLegacyClientsTable($pdo);

    if (!empty($result['errors'])) {
        $exitCode = 2;
    }

    if (!$result['exists']) {
        $exitCode = max($exitCode, 2);
        echo "[ERROR] La table 'clients' n'a pas pu être vérifiée ou recréée." . PHP_EOL;
    } else {
        echo "[OK] La table 'clients' est présente." . PHP_EOL;
        if ($result['created']) {
            echo "[INFO] Table recréée durant l'exécution." . PHP_EOL;
        }
        if ($result['synchronized']) {
            echo "[INFO] Synchronisation avec 'clients_particuliers' effectuée." . PHP_EOL;
        }
    }

    if (!empty($result['warnings'])) {
        echo "[WARN] " . implode(' | ', $result['warnings']) . PHP_EOL;
        $exitCode = max($exitCode, 1);
    }

    if (!empty($result['errors'])) {
        echo "[ERROR] " . implode(' | ', $result['errors']) . PHP_EOL;
    }

} catch (Throwable $e) {
    $exitCode = 1;
    $message = '[EXCEPTION] ' . $e->getMessage();
    echo $message . PHP_EOL;
    if (function_exists('logMessage')) {
        logMessage('diagnostics_errors.log', $message);
    }
}

echo "[DONE] Statut sortie: {$exitCode}" . PHP_EOL;
exit($exitCode);
