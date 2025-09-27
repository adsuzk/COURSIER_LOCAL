<?php
/**
 * Détermine la table primaire des commandes de façon cohérente entre les scripts.
 * Heuristique:
 *  1. Si une seule table existe parmi (commandes_classiques, commandes) -> celle-là.
 *  2. Si les deux existent:
 *     - Priorité à celle qui contient le plus de commandes "actives" récentes (24h) parmi statuts
 *       ('assignee','acceptee','en_cours','picked_up','nouvelle').
 *     - Sinon, fallback à celle qui a le plus de lignes totales.
 */
function resolvePrimaryOrdersTable(PDO $pdo): string {
    static $cache = null; if ($cache) return $cache;
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasNew = in_array('commandes_classiques',$tables,true);
    $hasLegacy = in_array('commandes',$tables,true);
    if ($hasNew && !$hasLegacy) return $cache = 'commandes_classiques';
    if ($hasLegacy && !$hasNew) return $cache = 'commandes';
    if (!$hasNew && !$hasLegacy) throw new RuntimeException('Aucune table de commandes trouvée');

    // Les deux existent – on essaie une heuristique robuste sans supposer les noms de colonnes de date.
    $activeSet = ["assignee","acceptee","en_cours","picked_up","nouvelle"];

    $recentNew = 0; $recentLegacy = 0;
    try { $recentNew = (int)countActiveRecent($pdo, 'commandes_classiques', $activeSet); } catch (Throwable $e) { $recentNew = 0; }
    try { $recentLegacy = (int)countActiveRecent($pdo, 'commandes', $activeSet); } catch (Throwable $e) { $recentLegacy = 0; }
    if ($recentNew !== $recentLegacy) {
        return $cache = ($recentNew > $recentLegacy ? 'commandes_classiques':'commandes');
    }
    // Fallback: total rows
    $totNew = 0; $totLegacy = 0;
    try { $totNew = (int)$pdo->query("SELECT COUNT(*) FROM commandes_classiques")->fetchColumn(); } catch (Throwable $e) {}
    try { $totLegacy = (int)$pdo->query("SELECT COUNT(*) FROM commandes")->fetchColumn(); } catch (Throwable $e) {}
    return $cache = ($totNew >= $totLegacy ? 'commandes_classiques':'commandes');
}

/**
 * Compte le nombre de commandes "actives" récentes pour une table donnée en détectant dynamiquement une colonne de date.
 */
function countActiveRecent(PDO $pdo, string $table, array $activeStatuses): int {
    // Détecter la colonne date: priorités
    $candidates = ['date_creation','created_at','date_commande','created','timestamp_creation'];
    $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $dateCol = null;
    foreach ($candidates as $c) { if (in_array($c,$cols,true)) { $dateCol = $c; break; } }
    if ($dateCol === null) {
        // Pas de colonne date détectée, compter juste sur les statuts sans fenêtre temporelle
        $in = "('".implode("','", array_map('addslashes',$activeStatuses))."')";
        $sql = "SELECT COUNT(*) FROM `$table` WHERE statut IN $in";
        return (int)$pdo->query($sql)->fetchColumn();
    }
    $in = "('".implode("','", array_map('addslashes',$activeStatuses))."')";
    $sql = "SELECT COUNT(*) FROM `$table` WHERE statut IN $in AND `$dateCol` >= (NOW()-INTERVAL 1 DAY)";
    return (int)$pdo->query($sql)->fetchColumn();
}

/**
 * Retourne la liste des statuts considérés "actifs" pour le polling.
 */
function getActiveOrderStatuses(): array {
    return ['assignee','acceptee','en_cours','picked_up','nouvelle'];
}

?>
