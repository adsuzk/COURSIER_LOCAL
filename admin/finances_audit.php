<?php
// admin/finances_audit.php - Audit lecture seule des écritures financières à la livraison
require_once __DIR__ . '/../config.php';

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    echo '<div style="color:#E94560; padding:16px;">Erreur connexion base de données: ' . htmlspecialchars($e->getMessage()) . '</div>';
    return;
}

// Détection colonnes dynamiques pour commandes_classiques
$hasTable = false;
try { $hasTable = $pdo->query("SHOW TABLES LIKE 'commandes_classiques'")->rowCount() > 0; } catch (Throwable $e) { $hasTable = false; }
if (!$hasTable) {
    echo '<div style="padding:16px;">Table commandes_classiques introuvable. Aucune donnée à afficher.</div>';
    return;
}

$hasOrderNumber = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'order_number'")->rowCount() > 0;
$hasNumero      = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'numero_commande'")->rowCount() > 0;
$hasCode        = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'code_commande'")->rowCount() > 0;
$orderNumCol    = $hasOrderNumber ? 'order_number' : ($hasNumero ? 'numero_commande' : ($hasCode ? 'code_commande' : null));

$hasDeliveredTime = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'delivered_time'")->rowCount() > 0;
$hasCashAmount    = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'cash_amount'")->rowCount() > 0;
$hasCashCollected = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'cash_collected'")->rowCount() > 0;
$hasModePaiement  = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'mode_paiement'")->rowCount() > 0;
$hasPrixEstime    = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'prix_estime'")->rowCount() > 0;
$hasCoursierId    = $pdo->query("SHOW COLUMNS FROM commandes_classiques LIKE 'coursier_id'")->rowCount() > 0;

// Filtres
$today     = new DateTime('now');
$defaultTo = $today->format('Y-m-d');
$defaultFrom = $today->modify('-7 days')->format('Y-m-d');

$from = isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from']) ? $_GET['from'] : $defaultFrom;
$to   = isset($_GET['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']) ? $_GET['to'] : $defaultTo;
$q    = trim((string)($_GET['q'] ?? ''));

// Construction requête
$fields = [
    'id',
    'statut'
];
if ($orderNumCol) $fields[] = $orderNumCol;
if ($hasDeliveredTime) $fields[] = 'delivered_time';
if ($hasModePaiement) $fields[] = 'mode_paiement';
if ($hasPrixEstime) $fields[] = 'prix_estime';
if ($hasCashAmount) $fields[] = 'cash_amount';
if ($hasCashCollected) $fields[] = 'cash_collected';
if ($hasCoursierId) $fields[] = 'coursier_id';

// Charger paramètres dynamiques (commission, frais plateforme)
$commissionRate = 15.0;
$feeRate = 5.0;
try {
    $stp = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
    $paramsTarif = [];
    foreach ($stp->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $paramsTarif[$row['parametre']] = $row['valeur'];
    }
    if (isset($paramsTarif['commission_suzosky'])) { $commissionRate = max(0.0, min(50.0, (float)$paramsTarif['commission_suzosky'])); }
    if (isset($paramsTarif['frais_plateforme'])) { $feeRate = max(0.0, min(50.0, (float)$paramsTarif['frais_plateforme'])); }
} catch (Throwable $e) { /* valeurs par défaut conservées */ }

$sql = 'SELECT ' . implode(', ', array_map(fn($c) => $c, $fields)) . " FROM commandes_classiques WHERE statut = 'livree'";
// Filtre dates
$params = [];
if ($hasDeliveredTime) {
    $sql .= ' AND DATE(delivered_time) BETWEEN ? AND ?';
    $params[] = $from; $params[] = $to;
}
// Filtre order number
if ($q !== '' && $orderNumCol) {
    $sql .= ' AND ' . $orderNumCol . ' LIKE ?';
    $params[] = '%' . $q . '%';
}
$sql .= ' ORDER BY ' . ($hasDeliveredTime ? 'delivered_time' : 'id') . ' DESC LIMIT 200';

$rows = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    echo '<div style="color:#E94560; padding:16px;">Erreur SQL: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Helper: vérifier existence transaction par référence
$hasTx = function(PDO $pdo, string $ref): bool {
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM transactions_financieres WHERE reference = ?');
        $st->execute([$ref]);
        return (int)$st->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
};

// Rendu UI
?>
<div style="padding: 20px;">
    <h2 style="color:#D4A853; margin-bottom: 12px;">Audit Financier – Livraisons</h2>
    <p style="color:#ccc; margin-bottom: 16px;">Vue lecture seule des commandes livrées et des écritures attendues (commission <?php echo htmlspecialchars($commissionRate); ?>% / frais plateforme <?php echo htmlspecialchars($feeRate); ?>%).</p>
    <p style="margin-bottom:12px;">
        <a href="<?php echo htmlspecialchars('../Test/healthcheck.php'); ?>" target="_blank" style="color:#D4A853; text-decoration:none; border:1px solid rgba(212,168,83,0.5); padding:6px 10px; border-radius:6px;">🔎 Ouvrir Healthcheck</a>
    </p>

    <form method="get" action="admin.php" style="display:flex; gap:10px; align-items: flex-end; flex-wrap: wrap; background: rgba(255,255,255,0.06); padding:12px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
        <input type="hidden" name="section" value="finances_audit" />
        <div>
            <label style="display:block; color:#D4A853; font-size:12px; margin-bottom:4px;">Du</label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>" style="padding:6px 8px; border-radius:6px; border:1px solid #444; background:#16213E; color:#fff;" />
        </div>
        <div>
            <label style="display:block; color:#D4A853; font-size:12px; margin-bottom:4px;">Au</label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>" style="padding:6px 8px; border-radius:6px; border:1px solid #444; background:#16213E; color:#fff;" />
        </div>
        <div>
            <label style="display:block; color:#D4A853; font-size:12px; margin-bottom:4px;">N° commande</label>
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Rechercher..." style="padding:6px 8px; border-radius:6px; border:1px solid #444; background:#16213E; color:#fff; min-width:200px;" />
        </div>
        <div>
            <button type="submit" style="padding:8px 12px; background:#D4A853; color:#1A1A2E; border:none; border-radius:6px; font-weight:700; cursor:pointer;">Filtrer</button>
        </div>
    </form>

    <div style="margin-top:16px; overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#0F3460; color:#fff;">
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #333;">Date</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #333;">Commande</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #333;">Coursier</th>
                    <th style="text-align:left; padding:8px; border-bottom:1px solid #333;">Paiement</th>
                    <th style="text-align:right; padding:8px; border-bottom:1px solid #333;">Montant</th>
                    <th style="text-align:right; padding:8px; border-bottom:1px solid #333;">Commission (<?php echo htmlspecialchars($commissionRate); ?>%)</th>
                    <th style="text-align:right; padding:8px; border-bottom:1px solid #333;">Frais (<?php echo htmlspecialchars($feeRate); ?>%)</th>
                    <th style="text-align:center; padding:8px; border-bottom:1px solid #333;">Tx Commission (<?php echo htmlspecialchars($commissionRate); ?>%)</th>
                    <th style="text-align:center; padding:8px; border-bottom:1px solid #333;">Tx Frais (<?php echo htmlspecialchars($feeRate); ?>%)</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" style="padding:12px; color:#ccc;">Aucune commande livrée pour cette période.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <?php
                        $orderNum = $orderNumCol ? ($r[$orderNumCol] ?? '') : '';
                        $mode = $hasModePaiement ? strtolower((string)($r['mode_paiement'] ?? '')) : '';
                        $isCash = ($mode === 'cash');
                        $prix = $hasPrixEstime ? (float)($r['prix_estime'] ?? 0) : 0;
                        $cashAmt = $hasCashAmount ? (float)($r['cash_amount'] ?? 0) : 0;
                        $amountBase = $prix > 0 ? $prix : ($isCash ? $cashAmt : $prix);
                        $commission = round($amountBase * ($commissionRate/100.0), 2);
                        $fee = round($amountBase * ($feeRate/100.0), 2);
                        $refCommission = $orderNum ? ('DELIV_' . $orderNum) : '';
                        $refFee        = $orderNum ? ('DELIV_' . $orderNum . '_FEE') : '';
                        $txC = $orderNum ? $hasTx($pdo, $refCommission) : false;
                        $txF = $orderNum ? $hasTx($pdo, $refFee) : false;
                        $dateStr = $hasDeliveredTime && !empty($r['delivered_time']) ? date('Y-m-d H:i', strtotime($r['delivered_time'])) : '-';
                        $coursier = $hasCoursierId ? (int)($r['coursier_id'] ?? 0) : 0;
                    ?>
                    <tr style="background:rgba(255,255,255,0.03); color:#fff;">
                        <td style="padding:8px; border-bottom:1px solid #222;"><?php echo htmlspecialchars($dateStr); ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222; font-weight:600;">#<?php echo htmlspecialchars($orderNum ?: (string)$r['id']); ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222;"><?php echo $coursier ? ('ID ' . (int)$coursier) : '-'; ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222; text-transform:capitalize;"><?php echo htmlspecialchars($mode ?: 'n/a'); ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222; text-align:right;"><?php echo number_format($amountBase, 2, ',', ' '); ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222; text-align:right;"><?php echo number_format($commission, 2, ',', ' '); ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222; text-align:right;"><?php echo number_format($fee, 2, ',', ' '); ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222; text-align:center;"><?php echo $txC ? '✅' : '❌'; ?></td>
                        <td style="padding:8px; border-bottom:1px solid #222; text-align:center;"><?php echo $txF ? '✅' : '❌'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p style="margin-top:10px; color:#aaa; font-size:12px;">Limite d'affichage: 200 résultats. Utilisez les filtres pour affiner.</p>
</div>
<?php
// Fin de page
?>
