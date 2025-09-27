<?php
// lib/finances_sync.php - utilitaires de synchronisation finances
require_once __DIR__ . '/../config.php';

/**
 * Assure qu'un compte financier existe pour un coursier (agents_suzosky.id)
 * Crée un compte à 0 si absent.
 */
function ensureCourierAccount(PDO $pdo, int $coursierId): void {
    $stmt = $pdo->prepare("SELECT id FROM comptes_coursiers WHERE coursier_id = ?");
    $stmt->execute([$coursierId]);
    if (!$stmt->fetchColumn()) {
        $ins = $pdo->prepare("INSERT INTO comptes_coursiers (coursier_id, solde, statut) VALUES (?, 0, 'actif')");
        $ins->execute([$coursierId]);
    }
}

/**
 * Retourne la liste des colonnes disponibles dans coursier_accounts (mise en cache statique).
 */
function getCoursierAccountsColumns(PDO $pdo): array {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    try {
        $exists = $pdo->query("SHOW TABLES LIKE 'coursier_accounts'");
        if (!$exists || $exists->rowCount() === 0) {
            return $cached = [];
        }
    } catch (Throwable $e) {
        return $cached = [];
    }

    $columns = [];
    try {
        $res = $pdo->query('SHOW COLUMNS FROM coursier_accounts');
        foreach ($res as $row) {
            if (!empty($row['Field'])) {
                $columns[$row['Field']] = true;
            }
        }
    } catch (Throwable $e) {
        return $cached = [];
    }

    return $cached = $columns;
}

/**
 * Ajuste le solde de recharge hérité (coursier_accounts.solde_disponible) en maintenant les indicateurs utiles.
 * $delta > 0 pour une recharge, $delta < 0 pour un prélèvement (acceptation, pénalité, ...).
 */
function adjustCoursierRechargeBalance(PDO $pdo, int $coursierId, float $delta, array $options = []): void {
    if ($delta == 0.0) {
        return;
    }

    $columns = getCoursierAccountsColumns($pdo);
    if (empty($columns) || !isset($columns['solde_disponible'])) {
        return;
    }

    try {
        $sets = [];
        $params = [
            ':delta' => $delta,
            ':id' => $coursierId,
        ];

        $sets[] = 'solde_disponible = GREATEST(COALESCE(solde_disponible,0) + :delta, 0)';

        if (!empty($options['affect_total']) && isset($columns['solde_total'])) {
            $sets[] = 'solde_total = GREATEST(COALESCE(solde_total,0) + :delta_total, 0)';
            $params[':delta_total'] = $delta;
        }

        if ($delta < 0 && isset($columns['total_preleve'])) {
            $sets[] = 'total_preleve = COALESCE(total_preleve,0) + :preleve';
            $params[':preleve'] = abs($delta);
        }

        if ($delta > 0 && isset($columns['total_recharge'])) {
            $sets[] = 'total_recharge = COALESCE(total_recharge,0) + :recharge';
            $params[':recharge'] = $delta;
        }

        if ($delta > 0 && isset($columns['last_recharge'])) {
            $sets[] = 'last_recharge = NOW()';
        }

        if (isset($columns['updated_at'])) {
            $sets[] = 'updated_at = NOW()';
        }

        if (isset($columns['can_receive_orders'])) {
            $sets[] = 'can_receive_orders = CASE WHEN (COALESCE(solde_disponible,0) + :delta_check) > 0 THEN TRUE ELSE FALSE END';
            $params[':delta_check'] = $delta;
        }

        if (!$sets) {
            return;
        }

        $sql = 'UPDATE coursier_accounts SET ' . implode(', ', $sets) . ' WHERE coursier_id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (Throwable $e) {
        // Ne bloque pas le flux principal si l'ancien schéma n'est pas présent.
    }
}

/**
 * Backfill automatique: crée les comptes manquants pour tous les coursiers existants.
 * Retourne le nombre de comptes créés.
 */
function backfillCourierAccounts(PDO $pdo): int {
    $sql = "INSERT INTO comptes_coursiers (coursier_id, solde, statut)
            SELECT a.id, 0, 'actif'
            FROM agents_suzosky a
            LEFT JOIN comptes_coursiers cc ON cc.coursier_id = a.id
            WHERE a.type_poste IN ('coursier','coursier_moto','coursier_velo')
              AND cc.coursier_id IS NULL";
    return $pdo->exec($sql) ?: 0;
}

/**
 * Idempotent credit: crédite un solde et insère une transaction si la référence est nouvelle.
 */
function creditCourierIfNewRef(PDO $pdo, int $coursierId, float $amount, string $reference, string $description = ''): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions_financieres WHERE reference = ?");
    $stmt->execute([$reference]);
    if ((int)$stmt->fetchColumn() > 0) {
        return false; // déjà traité
    }
    $upd = $pdo->prepare("UPDATE comptes_coursiers SET solde = solde + ? WHERE coursier_id = ?");
    $upd->execute([$amount, $coursierId]);
    $ins = $pdo->prepare("INSERT INTO transactions_financieres (type, montant, compte_type, compte_id, reference, description, statut, date_creation) VALUES ('credit', ?, 'coursier', ?, ?, ?, 'reussi', NOW())");
    $ins->execute([$amount, $coursierId, $reference, $description ?: 'Crédit automatique']);
    adjustCoursierRechargeBalance($pdo, $coursierId, $amount, ['affect_total' => true]);
    return true;
}
