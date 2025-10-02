<?php
// Gestion de la section finances - Interface d'administration financière Suzosky
// Inclut gestion des comptes coursiers, transactions, recharges et tarification

require_once __DIR__ . '/../config.php';

// Désactiver le cache (en contexte web uniquement) pour éviter l'affichage d'une ancienne version de la page
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Initialisation de la connexion PDO
$pdo = getPDO();

// Synchronisation automatique: créer un compte financier pour chaque agent coursier manquant
try {
    $pdo->exec("INSERT INTO comptes_coursiers (coursier_id, solde, statut)
        SELECT a.id, 0, 'actif'
        FROM agents_suzosky a
        LEFT JOIN comptes_coursiers cc ON cc.coursier_id = a.id
        WHERE (a.type_poste IN ('coursier','coursier_moto','coursier_velo'))
          AND cc.coursier_id IS NULL");
} catch (Exception $e) {
    // Ne pas bloquer l'affichage en cas d'erreur
}

// Gestion des actions POST (uniquement si serveur web)
if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')) {
    // Action: Valider une recharge
    if (isset($_POST['action'], $_POST['recharge_id']) && $_POST['action'] === 'validate_recharge') {
        $rechargeId = (int)$_POST['recharge_id'];
        $comment = trim($_POST['comment'] ?? '');
        
        try {
            $pdo->beginTransaction();
            
            // Récupérer les détails de la recharge
            $stmt = $pdo->prepare("SELECT * FROM recharges_coursiers WHERE id = ? AND statut = 'en_attente'");
            $stmt->execute([$rechargeId]);
            $recharge = $stmt->fetch();
            
            if ($recharge) {
                // Mettre à jour la recharge
                $stmt = $pdo->prepare("UPDATE recharges_coursiers SET statut = 'validee', date_validation = NOW(), commentaire_admin = ? WHERE id = ?");
                $stmt->execute([$comment, $rechargeId]);
                
                // Créditer le compte coursier
                $stmt = $pdo->prepare("UPDATE comptes_coursiers SET solde = solde + ? WHERE coursier_id = ?");
                $stmt->execute([$recharge['montant'], $recharge['coursier_id']]);
                
                // Enregistrer la transaction
                $stmt = $pdo->prepare("INSERT INTO transactions_financieres (type, montant, compte_type, compte_id, reference, description, statut) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    'credit',
                    $recharge['montant'],
                    'coursier',
                    $recharge['coursier_id'],
                    'RECH_' . $rechargeId,
                    'Recharge validée' . ($comment ? ' - ' . $comment : ''),
                    'reussi'
                ]);
                
                $pdo->commit();
                
                // Journalisation
                getJournal()->logMaxDetail(
                    'RECHARGE_VALIDEE',
                    "Recharge #{$rechargeId} validée pour coursier #{$recharge['coursier_id']}",
                    ['recharge_id' => $rechargeId, 'montant' => $recharge['montant']]
                );
                
                $_SESSION['success_message'] = 'Recharge validée avec succès !';
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            $_SESSION['error_message'] = 'Erreur lors de la validation : ' . $e->getMessage();
        }
        
    $redirectUrl = 'admin.php?section=finances&tab=recharges';
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '"></noscript>';
    exit;
    }
    
    // Action: Rejeter une recharge
    if (isset($_POST['action'], $_POST['recharge_id']) && $_POST['action'] === 'reject_recharge') {
        $rechargeId = (int)$_POST['recharge_id'];
        $comment = trim($_POST['comment'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE recharges_coursiers SET statut = 'refusee', date_validation = NOW(), commentaire_admin = ? WHERE id = ? AND statut = 'en_attente'");
            $stmt->execute([$comment, $rechargeId]);
            
            getJournal()->logMaxDetail(
                'RECHARGE_REFUSEE',
                "Recharge #{$rechargeId} refusée",
                ['recharge_id' => $rechargeId, 'raison' => $comment]
            );
            
            $_SESSION['success_message'] = 'Recharge refusée.';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erreur lors du refus : ' . $e->getMessage();
        }
        
    $redirectUrl = 'admin.php?section=finances&tab=recharges';
    echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '"></noscript>';
    exit;
    }
    
    // Action: Modifier les paramètres de tarification
    if (isset($_POST['action']) && $_POST['action'] === 'update_pricing') {
        $prixKm = (float)$_POST['prix_km'];
        $fraisBase = (float)$_POST['frais_base'];
        // Nouveau modèle: km supplémentaires après la destination
        $suppKmRate = (int)($_POST['supp_km_rate'] ?? ($tarification['supp_km_rate'] ?? 100));
        $suppKmFree = (float)($_POST['supp_km_free'] ?? ($tarification['supp_km_free_allowance'] ?? 1));
    // Ancien modèle de majoration supprimé
        $commissionSuzosky = (float)($_POST['commission_suzosky'] ?? $tarification['commission_suzosky'] ?? 15);
    $fraisPlateforme = (float)($_POST['frais_plateforme'] ?? ($tarification['frais_plateforme'] ?? 5));
    $fraisPublicitaires = (float)($_POST['frais_publicitaires'] ?? ($tarification['frais_publicitaires'] ?? 0));
        // Normalisation (bornes)
        if (!is_nan($commissionSuzosky)) {
            $commissionSuzosky = max(1.0, min(50.0, $commissionSuzosky));
        }
        if (!is_nan($fraisPlateforme)) {
            $fraisPlateforme = max(0.0, min(50.0, $fraisPlateforme));
        }
        if (!is_nan($fraisPublicitaires)) {
            $fraisPublicitaires = max(0.0, min(50.0, $fraisPublicitaires));
        }
        
        try {
            // Mettre à jour ou créer les paramètres
            $parametres = [
                'prix_kilometre' => $prixKm,
                'frais_base' => $fraisBase,
                'commission_suzosky' => $commissionSuzosky,
                'frais_plateforme' => $fraisPlateforme,
                'frais_publicitaires' => $fraisPublicitaires,
                'supp_km_rate' => $suppKmRate,
                'supp_km_free_allowance' => $suppKmFree
            ];
            
            foreach ($parametres as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
                $stmt->execute([$key, $value]);
            }
            
            getJournal()->logMaxDetail(
                'TARIFICATION_MODIFIEE',
                'Paramètres de tarification mis à jour',
                $parametres
            );
            
            $_SESSION['success_message'] = 'Paramètres de tarification mis à jour !';
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Erreur lors de la mise à jour : ' . $e->getMessage();
        }
        
        $redirectUrl = 'admin.php?section=finances&tab=pricing';
        // Toujours rediriger côté client pour éviter les conflits d'en-têtes
        echo '<script>window.location.href=' . json_encode($redirectUrl) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redirectUrl, ENT_QUOTES) . '"></noscript>';
        exit;
    }
    
    // Action: Mise à jour en temps réel via AJAX
    if (isset($_POST['action']) && $_POST['action'] === 'update_pricing_ajax') {
        $prixKm = (float)($_POST['prix_km'] ?? 300);
        $commissionSuzosky = (float)($_POST['commission_suzosky'] ?? 15);
        $fraisBase = (float)($_POST['frais_base'] ?? ($tarification['frais_base'] ?? 500));
    $suppKmRate = (int)($_POST['supp_km_rate'] ?? ($tarification['supp_km_rate'] ?? 100));
        $suppKmFree = (float)($_POST['supp_km_free'] ?? ($tarification['supp_km_free_allowance'] ?? 1));
    $fraisPublicitaires = (float)($_POST['frais_publicitaires'] ?? ($tarification['frais_publicitaires'] ?? 0));
        
        try {
            $parametres = [
                'prix_kilometre' => $prixKm,
                'commission_suzosky' => $commissionSuzosky,
                'frais_base' => $fraisBase,
                'frais_publicitaires' => $fraisPublicitaires,
                'supp_km_rate' => $suppKmRate,
                'supp_km_free_allowance' => $suppKmFree
            ];
            
            foreach ($parametres as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO parametres_tarification (parametre, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)");
                $stmt->execute([$key, $value]);
            }
            
            getJournal()->logMaxDetail(
                'TARIFICATION_MODIFIEE_AJAX',
                'Paramètres de tarification mis à jour via AJAX',
                $parametres
            );
            
            // Purger buffers (si présents) et renvoyer JSON
            while (ob_get_level()) { ob_end_clean(); }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => true, 'message' => 'Paramètres mis à jour', 'data' => $parametres]);
            exit;
        } catch (Exception $e) {
            while (ob_get_level()) { ob_end_clean(); }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Récupération des données globales
$tab = $_GET['tab'] ?? 'dashboard';

// Export CSV des transactions agrégées si demandé
if ($tab === 'transactions' && isset($_GET['export'])) {
    // XLSX export if format=xlsx else CSV
    try {
        $pdo = getPDO();
        $orderFilter = trim($_GET['order'] ?? '');
        $coursierFilter = isset($_GET['coursier_id']) && $_GET['coursier_id'] !== '' ? (int)$_GET['coursier_id'] : null;
        $limit = isset($_GET['limit']) ? max(10, min(5000, (int)$_GET['limit'])) : 1000;

        $sql = "
            SELECT t.orderNumber,
                   MAX(CASE WHEN t.type='credit' THEN t.montant END) AS commission,
                   MAX(CASE WHEN t.type='debit' THEN t.montant END) AS fee,
                   MAX(t.compte_id) AS coursier_id,
                   MIN(t.date_creation) AS date_creation
            FROM (
                SELECT reference, type, montant, compte_id, date_creation,
                       CASE
                         WHEN reference LIKE 'DELIV\\_%\\_FEE' THEN SUBSTRING(reference, 7, CHAR_LENGTH(reference)-11)
                         ELSE SUBSTRING(reference, 7)
                       END AS orderNumber
                FROM transactions_financieres
                WHERE reference LIKE 'DELIV_%'
            ) t
            WHERE 1=1
        ";
        $params = [];
        if ($orderFilter !== '') { $sql .= " AND t.orderNumber LIKE ?"; $params[] = "%$orderFilter%"; }
        if ($coursierFilter !== null) { $sql .= " AND t.compte_id = ?"; $params[] = $coursierFilter; }
        $sql .= " GROUP BY t.orderNumber ORDER BY date_creation DESC LIMIT $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $format = strtolower($_GET['format'] ?? 'csv');
        if ($format === 'xlsx') {
            // Générer un XLSX minimal via ZipArchive
            $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xlsx_' . uniqid();
            @mkdir($tmpDir, 0700, true);
            $relsDir = $tmpDir . '/_rels';
            $xlDir = $tmpDir . '/xl';
            $xlRelsDir = $xlDir . '/_rels';
            $worksheetsDir = $xlDir . '/worksheets';
            @mkdir($relsDir, 0700, true);
            @mkdir($xlDir, 0700, true);
            @mkdir($xlRelsDir, 0700, true);
            @mkdir($worksheetsDir, 0700, true);

            // Fichiers obligatoires XLSX
            file_put_contents($tmpDir . '/[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>'
            );
            file_put_contents($relsDir . '/.rels',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="/xl/workbook.xml"/>'
                .'</Relationships>'
            );
            file_put_contents($xlDir . '/workbook.xml',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Transactions" sheetId="1" r:id="rId1"/></sheets></workbook>'
            );
            file_put_contents($xlRelsDir . '/workbook.xml.rels',
                '<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>'
            );

            // Construire sheet1.xml (format inlineStr pour éviter sharedStrings)
            $rowsXml = '';
            $headers = ['Date','Commande','TotalClient','Commission','FraisPlateforme','GainNetCoursier','CoursierID'];
            $rowsXml .= '<row r="1">';
            $c = 1; foreach ($headers as $h) { $rowsXml .= '<c t="inlineStr" r="'.chr(64+$c).'1"><is><t>'.htmlspecialchars($h).'</t></is></c>'; $c++; }
            $rowsXml .= '</row>';
            $rIndex = 2;
            foreach ($rows as $r) {
                $orderNum = $r['orderNumber'];
                $totalCmd = '';
                try {
                    $stc = $pdo->prepare("SELECT COALESCE(prix_estime, cash_amount) AS total_cmd FROM commandes_classiques WHERE order_number = ? OR numero_commande = ? OR code_commande = ? LIMIT 1");
                    $stc->execute([$orderNum, $orderNum, $orderNum]);
                    $t = $stc->fetch(PDO::FETCH_ASSOC);
                    if ($t && $t['total_cmd'] !== null) { $totalCmd = (float)$t['total_cmd']; }
                } catch (Throwable $e) { $totalCmd = ''; }
                $commission = (float)($r['commission'] ?? 0);
                $fee = (float)($r['fee'] ?? 0);
                $net = ($totalCmd !== '') ? ((float)$totalCmd - $commission - $fee) : '';
                $cols = [ $r['date_creation'], $orderNum, $totalCmd, $commission, $fee, $net, (int)($r['coursier_id'] ?? 0) ];
                $rowsXml .= '<row r="'.$rIndex.'">';
                $colIdx = 1;
                foreach ($cols as $val) {
                    $rowsXml .= '<c t="inlineStr" r="'.chr(64+$colIdx).$rIndex.'"><is><t>'.htmlspecialchars((string)$val).'</t></is></c>';
                    $colIdx++;
                }
                $rowsXml .= '</row>';
                $rIndex++;
            }
            $sheetXml = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetData>'.$rowsXml.'</sheetData></worksheet>';
            file_put_contents($worksheetsDir . '/sheet1.xml', $sheetXml);

            // Zip en .xlsx
            $xlsxFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('transactions_suzosky_'.date('Ymd_His').'.xlsx');
            $zip = new ZipArchive();
            if ($zip->open($xlsxFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $file) {
                    $localName = substr($file->getPathname(), strlen($tmpDir) + 1);
                    $zip->addFile($file->getPathname(), str_replace('\\', '/', $localName));
                }
                $zip->close();
            }
            // Envoi
            if (!headers_sent()) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename=' . basename($xlsxFile));
                header('Content-Length: ' . filesize($xlsxFile));
            }
            readfile($xlsxFile);
            // Nettoyage best-effort
            @unlink($xlsxFile);
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
            @rmdir($tmpDir);
        } else {
            // CSV
            $filename = 'transactions_suzosky_' . date('Ymd_His') . '.csv';
            if (!headers_sent()) {
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filename);
            }
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Commande', 'TotalClient', 'Commission', 'FraisPlateforme', 'GainNetCoursier', 'CoursierID']);
            foreach ($rows as $r) {
                $orderNum = $r['orderNumber'];
                $totalCmd = '';
                try {
                    $stc = $pdo->prepare("SELECT COALESCE(prix_estime, cash_amount) AS total_cmd FROM commandes_classiques WHERE order_number = ? OR numero_commande = ? OR code_commande = ? LIMIT 1");
                    $stc->execute([$orderNum, $orderNum, $orderNum]);
                    $t = $stc->fetch(PDO::FETCH_ASSOC);
                    if ($t && $t['total_cmd'] !== null) { $totalCmd = (float)$t['total_cmd']; }
                } catch (Throwable $e) { $totalCmd = ''; }
                $commission = (float)($r['commission'] ?? 0);
                $fee = (float)($r['fee'] ?? 0);
                $net = ($totalCmd !== '') ? ((float)$totalCmd - $commission - $fee) : '';
                fputcsv($out, [
                    $r['date_creation'],
                    $orderNum,
                    $totalCmd,
                    $commission,
                    $fee,
                    $net,
                    (int)($r['coursier_id'] ?? 0)
                ]);
            }
            fclose($out);
        }
        exit;
    } catch (Throwable $e) {
        // Silencieux: en cas d'erreur on affiche l'onglet normalement
    }
}

// Statistiques générales
$stats = [];

// Solde total des coursiers
$stmt = $pdo->query("SELECT COALESCE(SUM(solde), 0) as total_solde FROM comptes_coursiers");
$stats['total_solde_coursiers'] = $stmt->fetch()['total_solde'];

// Total des recharges
$stmt = $pdo->query("SELECT COALESCE(SUM(montant), 0) as total_recharges FROM recharges_coursiers WHERE statut = 'validee'");
$stats['total_recharges'] = $stmt->fetch()['total_recharges'];

// Total des courses effectuées
$stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM commandes WHERE statut IN ('livree', 'terminee')");
$stats['total_courses'] = $stmt->fetch()['total_courses'];

// Recharges en attente
$stmt = $pdo->query("SELECT COUNT(*) as recharges_attente FROM recharges_coursiers WHERE statut = 'en_attente'");
$stats['recharges_attente'] = $stmt->fetch()['recharges_attente'];

// Paramètres de tarification actuels
$stmt = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
$tarification = [];
while ($row = $stmt->fetch()) {
    $tarification[$row['parametre']] = $row['valeur'];
}

// Nettoyage: supprimer les anciens paramètres pour éviter toute confusion
try {
    $pdo->exec("DELETE FROM parametres_tarification WHERE parametre IN ('seuil_majoration_km','taux_majoration')");
} catch (Throwable $e) { /* ignorer si droits restreints */ }

// Valeurs par défaut si pas encore configurés
$tarification = array_merge([
    'prix_kilometre' => 300,
    'frais_base' => 500,
    'commission_suzosky' => 15.0,
    'frais_plateforme' => 5.0,
    'frais_publicitaires' => 0.0,
    'supp_km_rate' => 100, // FCFA par km supplémentaire au-delà de la destination
    'supp_km_free_allowance' => 1 // km gratuits après destination (ex: 1 km), majoration à partir du 2e km
], $tarification);

?>

<style>
/* === STYLES SPÉCIFIQUES FINANCES === */
.finances-container {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border-radius: 20px;
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
    margin-bottom: var(--space-8);
    overflow: hidden;
}

.finances-tabs {
    display: flex;
    background: rgba(212, 168, 83, 0.05);
    border-bottom: 1px solid var(--glass-border);
    overflow-x: auto;
}

.tab-btn {
    padding: var(--space-4) var(--space-6);
    background: transparent;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all var(--duration-normal) var(--ease-standard);
    border-bottom: 3px solid transparent;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.tab-btn:hover {
    color: var(--primary-gold);
    background: rgba(212, 168, 83, 0.1);
}

.tab-btn.active {
    color: var(--primary-gold);
    border-bottom-color: var(--primary-gold);
    background: rgba(212, 168, 83, 0.15);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--space-6);
    margin-bottom: var(--space-8);
}

.stat-card {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border-radius: 16px;
    border: 1px solid var(--glass-border);
    padding: var(--space-6);
    box-shadow: var(--glass-shadow);
    transition: all var(--duration-normal) var(--ease-standard);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    margin-bottom: var(--space-4);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--primary-gold);
    margin-bottom: var(--space-2);
}

.stat-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pricing-simulator {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.2);
    border-radius: 12px;
    padding: var(--space-6);
    margin-top: var(--space-6);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-5);
}

.form-group {
    margin-bottom: var(--space-5);
}

.form-group label {
    display: block;
    color: var(--primary-gold);
    font-weight: 600;
    margin-bottom: var(--space-2);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-group input, .form-group textarea {
    width: 100%;
    padding: var(--space-3) var(--space-4);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    color: #FFFFFF;
    font-family: inherit;
    transition: all var(--duration-normal) var(--ease-standard);
}

.form-group input:focus, .form-group textarea:focus {
    outline: none;
    border-color: var(--primary-gold);
    box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.2);
}

.alert {
    padding: var(--space-4);
    border-radius: 8px;
    margin-bottom: var(--space-5);
    display: flex;
    align-items: center;
    gap: var(--space-3);
}

.alert-success {
    background: rgba(39, 174, 96, 0.15);
    border: 1px solid rgba(39, 174, 96, 0.3);
    color: #27AE60;
}

.alert-error {
    background: rgba(233, 69, 96, 0.15);
    border: 1px solid rgba(233, 69, 96, 0.3);
    color: #E94560;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.15);
    border: 1px solid rgba(255, 193, 7, 0.3);
    color: #FFC107;
}

.recharge-card {
    background: var(--glass-bg);
    border-radius: 12px;
    border: 1px solid var(--glass-border);
    padding: var(--space-5);
    margin-bottom: var(--space-4);
    transition: all var(--duration-normal) var(--ease-standard);
}

.recharge-card:hover {
    background: rgba(255, 255, 255, 0.08);
}

.recharge-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: var(--space-4);
}

.recharge-actions {
    display: flex;
    gap: var(--space-3);
    margin-top: var(--space-4);
}

.status-badge {
    padding: var(--space-1) var(--space-3);
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-en-attente {
    background: rgba(255, 193, 7, 0.2);
    color: #FFC107;
}

.status-validee {
    background: rgba(39, 174, 96, 0.2);
    color: #27AE60;
}

.status-refusee {
    background: rgba(233, 69, 96, 0.2);
    color: #E94560;
}

.pricing-control-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: var(--space-6);
    transition: all var(--duration-normal) var(--ease-standard);
}

.pricing-control-card:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(212, 168, 83, 0.3);
    transform: translateY(-2px);
}

.pricing-control-header {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-5);
    color: var(--primary-gold);
    font-weight: 700;
    font-size: 1.1rem;
}

.pricing-control-body {
    text-align: center;
}

.pricing-slider {
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    outline: none;
    margin-bottom: var(--space-4);
    -webkit-appearance: none;
}

.pricing-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #D4A853, #E8C468);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(212, 168, 83, 0.4);
    transition: all var(--duration-normal) var(--ease-standard);
}

.pricing-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.6);
}

.pricing-slider::-moz-range-thumb {
    width: 24px;
    height: 24px;
    background: linear-gradient(135deg, #D4A853, #E8C468);
    border-radius: 50%;
    cursor: pointer;
    border: none;
    box-shadow: 0 4px 12px rgba(212, 168, 83, 0.4);
}

.pricing-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    margin-bottom: var(--space-2);
    text-shadow: 0 2px 8px rgba(212, 168, 83, 0.3);
}

.pricing-info {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    line-height: 1.4;
}

.sim-result {
    text-align: center;
    background: rgba(212, 168, 83, 0.1);
    border: 1px solid rgba(212, 168, 83, 0.3);
    border-radius: 12px;
    padding: var(--space-4);
}

.sim-label {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: var(--space-1);
}

.sim-value {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--primary-gold);
}

.sync-status {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-5);
    background: rgba(39, 174, 96, 0.2);
    border: 1px solid rgba(39, 174, 96, 0.4);
    border-radius: 20px;
    color: #27AE60;
    font-weight: 600;
    font-size: 0.9rem;
}

.sync-status.syncing {
    background: rgba(255, 193, 7, 0.2);
    border-color: rgba(255, 193, 7, 0.4);
    color: #FFC107;
}

.sync-status.syncing i {
    animation: spin 1s linear infinite;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .finances-tabs {
        flex-direction: column;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .pricing-control-card {
        margin-bottom: var(--space-4);
    }
    
    .sim-result {
        margin-bottom: var(--space-3);
    }
}
</style>

<!-- Messages d'alerte -->
<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-triangle"></i>
    <span><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
</div>
<?php endif; ?>

<!-- Conteneur principal finances -->
<div class="finances-container">
    <!-- Navigation par onglets -->
    <div class="finances-tabs">
        <a href="admin.php?section=finances&tab=dashboard" class="tab-btn <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Tableau de bord</span>
        </a>
        <a href="admin.php?section=finances&tab=comptabilite" class="tab-btn <?php echo $tab === 'comptabilite' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>📊 Comptabilité</span>
        </a>
        <a href="admin.php?section=finances&tab=coursiers" class="tab-btn <?php echo $tab === 'coursiers' ? 'active' : ''; ?>">
            <i class="fas fa-motorcycle"></i>
            <span>Comptes coursiers</span>
        </a>
        <a href="admin.php?section=finances&tab=clients_particuliers" class="tab-btn <?php echo $tab === 'clients_particuliers' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Comptes clients particuliers</span>
        </a>
        <a href="admin.php?section=finances&tab=clients_business" class="tab-btn <?php echo $tab === 'clients_business' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Comptes clients business</span>
        </a>
        <a href="admin.php?section=finances&tab=transactions" class="tab-btn <?php echo $tab === 'transactions' ? 'active' : ''; ?>">
            <i class="fas fa-exchange-alt"></i>
            <span>Transactions</span>
        </a>
        <a href="admin.php?section=finances&tab=recharges" class="tab-btn <?php echo $tab === 'recharges' ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            <span>Recharges (<?php echo $stats['recharges_attente']; ?>)</span>
        </a>
        <a href="admin.php?section=finances&tab=rechargement_direct" class="tab-btn <?php echo $tab === 'rechargement_direct' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i>
            <span>💳 Rechargement Direct</span>
        </a>
        <a href="admin.php?section=finances&tab=pricing" class="tab-btn <?php echo $tab === 'pricing' ? 'active' : ''; ?>">
            <i class="fas fa-calculator"></i>
            <span>Calcul des prix</span>
        </a>
        <a href="admin.php?section=finances&tab=reports" class="tab-btn <?php echo $tab === 'reports' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Rapports</span>
        </a>
    </div>
    
    <div class="tab-content" style="padding: var(--space-8);">
        <?php if (in_array($tab, ['dashboard','pricing'], true)): ?>
        <!-- Encart Valeurs actuelles -->
        <div style="margin-bottom: var(--space-6); background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 12px; padding: var(--space-4);">
            <div style="display: flex; flex-wrap: wrap; gap: var(--space-4); align-items: center;">
                <div style="font-weight: 700; color: var(--primary-gold); display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-info-circle"></i> Valeurs actuelles
                </div>
                <div style="display:flex; gap:12px; flex-wrap: wrap; color: rgba(255,255,255,0.85);">
                    <span><strong>Commission:</strong> <?php echo (float)$tarification['commission_suzosky']; ?>%</span>
                    <span><strong>Frais plateforme:</strong> <?php echo isset($tarification['frais_plateforme']) ? (float)$tarification['frais_plateforme'] : 5; ?>%</span>
                    <span><strong>Frais publicitaires:</strong> <?php echo isset($tarification['frais_publicitaires']) ? (float)$tarification['frais_publicitaires'] : 0; ?>%</span>
                    <span><strong>Prix/km:</strong> <?php echo (int)$tarification['prix_kilometre']; ?> FCFA</span>
                    <span><strong>Frais de base:</strong> <?php echo (int)$tarification['frais_base']; ?> FCFA</span>
                    <span><strong>Supplément/km après destination:</strong> <?php echo (int)$tarification['supp_km_rate']; ?> FCFA</span>
                    <span><strong>Km gratuits après destination:</strong> <?php echo (float)$tarification['supp_km_free_allowance']; ?> km</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($tab === 'dashboard'): ?>
        <!-- CONTRÔLES DE TARIFICATION TEMPS RÉEL -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border);">
                <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                    <i class="fas fa-sliders-h"></i>
                    Paramètres de tarification en temps réel
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Ajustez instantanément le taux de commission et le prix par kilomètre</p>
            </div>
            
            <div style="padding: var(--space-6);">
                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr;">
                    <div class="pricing-control-card">
                        <div class="pricing-control-header">
                            <i class="fas fa-percentage"></i>
                            <span>Commission Suzosky</span>
                        </div>
                        <div class="pricing-control-body">
                <input type="range" 
                    id="commission-slider" 
                    min="1" 
                    max="50" 
                    step="0.5"
                                   value="<?php echo $tarification['commission_suzosky']; ?>"
                                   class="pricing-slider">
                            <div class="pricing-value">
                                <span id="commission-value"><?php echo $tarification['commission_suzosky']; ?>%</span>
                            </div>
                            <div class="pricing-info">
                                Taux de commission prélevé sur chaque course
                            </div>
                        </div>
                    </div>
                    <div class="pricing-control-card">
                        <div class="pricing-control-header">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span>Frais plateforme (%)</span>
                        </div>
                        <div class="pricing-control-body">
                            <input type="range" 
                                   id="fee-slider" 
                                   min="0" 
                                   max="50" 
                                   step="0.5"
                                   value="<?php echo isset($tarification['frais_plateforme']) ? (float)$tarification['frais_plateforme'] : 5; ?>"
                                   class="pricing-slider">
                            <div class="pricing-value">
                                <span id="fee-value"><?php echo isset($tarification['frais_plateforme']) ? (float)$tarification['frais_plateforme'] : 5; ?>%</span>
                            </div>
                            <div class="pricing-info">
                                Pourcentage de frais prélevé par la plateforme
                            </div>
                        </div>
                    </div>

                    <div class="pricing-control-card">
                        <div class="pricing-control-header">
                            <i class="fas fa-bullhorn"></i>
                            <span>Frais publicitaires (%)</span>
                        </div>
                        <div class="pricing-control-body">
                            <input type="range" 
                                   id="ad-fee-slider" 
                                   min="0" 
                                   max="50" 
                                   step="0.5"
                                   value="<?php echo isset($tarification['frais_publicitaires']) ? (float)$tarification['frais_publicitaires'] : 0; ?>"
                                   class="pricing-slider">
                            <div class="pricing-value">
                                <span id="ad-fee-value"><?php echo isset($tarification['frais_publicitaires']) ? (float)$tarification['frais_publicitaires'] : 0; ?>%</span>
                            </div>
                            <div class="pricing-info">
                                Part dédiée à la promotion/marketing
                            </div>
                        </div>
                    </div>
                    
                    <div class="pricing-control-card">
                        <div class="pricing-control-header">
                            <i class="fas fa-route"></i>
                            <span>Prix par kilomètre</span>
                        </div>
                        <div class="pricing-control-body">
                            <input type="range" 
                                   id="price-km-slider" 
                                   min="100" 
                                   max="1000" 
                                   step="25"
                                   value="<?php echo $tarification['prix_kilometre']; ?>"
                                   class="pricing-slider">
                            <div class="pricing-value">
                                <span id="price-km-value"><?php echo $tarification['prix_kilometre']; ?></span> FCFA/km
                            </div>
                            <div class="pricing-info">
                                Montant facturé par kilomètre parcouru
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ligne 2: paramètres du nouveau modèle (km après destination) -->
                <div class="form-grid" style="grid-template-columns: 1fr 1fr 1fr; margin-top: var(--space-6);">
                    <div class="pricing-control-card">
                        <div class="pricing-control-header">
                            <i class="fas fa-coins"></i>
                            <span>Frais de base</span>
                        </div>
                        <div class="pricing-control-body">
                            <input type="range"
                                   id="base-fare-slider"
                                   min="0"
                                   max="5000"
                                   step="50"
                                   value="<?php echo (int)$tarification['frais_base']; ?>"
                                   class="pricing-slider">
                            <div class="pricing-value">
                                <span id="base-fare-value"><?php echo (int)$tarification['frais_base']; ?></span> FCFA
                            </div>
                            <div class="pricing-info">
                                Montant fixe ajouté à chaque course
                            </div>
                        </div>
                    </div>

                    <div class="pricing-control-card">
                        <div class="pricing-control-header">
                            <i class="fas fa-plus-square"></i>
                            <span>Supplément/km après destination</span>
                        </div>
                        <div class="pricing-control-body">
                            <input type="range"
                                   id="supp-km-rate-slider"
                                   min="0"
                                   max="2000"
                                   step="25"
                                   value="<?php echo (int)$tarification['supp_km_rate']; ?>"
                                   class="pricing-slider">
                            <div class="pricing-value">
                                <span id="supp-km-rate-value"><?php echo (int)$tarification['supp_km_rate']; ?></span> FCFA/km
                            </div>
                            <div class="pricing-info">
                                Facturation des kilomètres supplémentaires après la destination
                            </div>
                        </div>
                    </div>

                    <div class="pricing-control-card">
                        <div class="pricing-control-header">
                            <i class="fas fa-gift"></i>
                            <span>Km gratuits après destination</span>
                        </div>
                        <div class="pricing-control-body">
                            <input type="range"
                                   id="supp-km-free-slider"
                                   min="0"
                                   max="5"
                                   step="0.5"
                                   value="<?php echo (float)$tarification['supp_km_free_allowance']; ?>"
                                   class="pricing-slider">
                            <div class="pricing-value">
                                <span id="supp-km-free-value"><?php echo (float)$tarification['supp_km_free_allowance']; ?></span> km
                            </div>
                            <div class="pricing-info">
                                Distance gratuite après la destination (ex: 1 km)
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Simulateur de prix en temps réel -->
                <div class="pricing-simulator" id="pricing-simulator">
                    <h4 style="color: var(--primary-gold); margin-bottom: var(--space-4);">
                        <i class="fas fa-calculator"></i>
                        Simulateur de prix
                    </h4>
                    <div class="form-grid" style="grid-template-columns: repeat(4, 1fr); align-items: end;">
                        <div class="form-group">
                            <label for="sim-distance">Distance (km)</label>
                            <input type="number" id="sim-distance" value="5" min="1" max="100" step="0.1">
                        </div>
                        <div class="form-group">
                            <label for="sim-base">Prix de base</label>
                            <input type="number" id="sim-base" value="500" min="0" step="50">
                        </div>
                        <div class="sim-result">
                            <div class="sim-label">Prix total client</div>
                            <div class="sim-value" id="sim-total">2 000 FCFA</div>
                        </div>
                        <div class="sim-result">
                            <div class="sim-label">Commission Suzosky</div>
                            <div class="sim-value" id="sim-commission">300 FCFA</div>
                        </div>
                        <div class="sim-result">
                            <div class="sim-label">Frais plateforme</div>
                            <div class="sim-value" id="sim-fee-amount">100 FCFA</div>
                        </div>
                        <div class="sim-result">
                            <div class="sim-label">Frais publicitaires</div>
                            <div class="sim-value" id="sim-ad-fee-amount">0 FCFA</div>
                        </div>
                        <div class="sim-result">
                            <div class="sim-label">Gain net du coursier</div>
                            <div class="sim-value" id="sim-net">200 FCFA</div>
                        </div>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: var(--space-6);">
                    <div class="sync-status" id="sync-status">
                        <i class="fas fa-sync-alt"></i>
                        <span>Synchronisation automatique activée</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLEAU DE BORD -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #D4A853, #E8C468); color: var(--primary-dark);">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_solde_coursiers'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Solde total coursiers</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #27AE60, #2ECC71); color: white;">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_recharges'], 0, ',', ' '); ?> FCFA</div>
                <div class="stat-label">Total des recharges</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3B82F6, #60A5FA); color: white;">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_courses'], 0, ',', ' '); ?></div>
                <div class="stat-label">Courses effectuées</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #FFC107, #FFD93D); color: var(--primary-dark);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $stats['recharges_attente']; ?></div>
                <div class="stat-label">Recharges en attente</div>
            </div>
        </div>
        
        <!-- Alertes et notifications -->
        <?php if ($stats['recharges_attente'] > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Vous avez <?php echo $stats['recharges_attente']; ?> recharge(s) en attente de validation.</span>
            <a href="admin.php?section=finances&tab=recharges" class="btn btn-warning" style="margin-left: auto;">
                <i class="fas fa-eye"></i>
                Voir les recharges
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Dernières transactions -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border);">
                <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                    <i class="fas fa-history"></i>
                    Dernières transactions
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Aperçu des 10 dernières transactions financières</p>
            </div>
            
            <?php
            // Récupérer les dernières transactions
            $stmt = $pdo->prepare("
                SELECT t.*, c.nom as coursier_nom 
                FROM transactions_financieres t
                LEFT JOIN comptes_coursiers cc ON t.compte_id = cc.coursier_id AND t.compte_type = 'coursier'
                LEFT JOIN coursiers c ON cc.coursier_id = c.id
                ORDER BY t.date_creation DESC 
                LIMIT 10
            ");
            $stmt->execute();
            $dernires_transactions = $stmt->fetchAll();
            ?>
            
            <div style="padding: var(--space-6);">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Compte</th>
                            <th>Référence</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dernires_transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($transaction['date_creation'])); ?></td>
                            <td>
                                <span class="status-badge <?php echo $transaction['type'] === 'credit' ? 'status-validee' : 'status-refusee'; ?>">
                                    <?php echo $transaction['type'] === 'credit' ? 'Crédit' : 'Débit'; ?>
                                </span>
                            </td>
                            <td style="font-weight: 700; color: <?php echo $transaction['type'] === 'credit' ? '#27AE60' : '#E94560'; ?>;">
                                <?php echo ($transaction['type'] === 'credit' ? '+' : '-'); ?><?php echo number_format($transaction['montant'], 0, ',', ' '); ?> FCFA
                            </td>
                            <td><?php echo htmlspecialchars($transaction['coursier_nom'] ?? 'N/A'); ?></td>
                            <td><code><?php echo htmlspecialchars($transaction['reference']); ?></code></td>
                            <td>
                                <span class="status-badge status-<?php echo $transaction['statut']; ?>">
                                    <?php echo ucfirst($transaction['statut']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($tab === 'coursiers'): ?>
        <!-- COMPTES COURSIERS -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border);">
                <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                    <i class="fas fa-motorcycle"></i>
                    Gestion des comptes coursiers
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Vue d'ensemble de tous les comptes coursiers</p>
            </div>
            
            <?php
            // Récupérer tous les comptes coursiers synchronisés avec agents_suzosky
            $stmt = $pdo->prepare("
                SELECT cc.*, a.nom, a.email, a.telephone,
                       (SELECT COUNT(*) FROM commandes WHERE coursier_id = a.id AND statut IN ('livree', 'terminee')) as courses_totales,
                       (SELECT COALESCE(SUM(montant), 0) FROM recharges_coursiers WHERE coursier_id = a.id AND statut = 'validee') as total_recharges
                FROM comptes_coursiers cc
                JOIN agents_suzosky a ON cc.coursier_id = a.id
                WHERE a.type_poste IN ('coursier','coursier_moto','coursier_velo')
                ORDER BY cc.solde DESC
            ");
            $stmt->execute();
            $comptes_coursiers = $stmt->fetchAll();
            ?>
            
            <div style="padding: var(--space-6);">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Coursier</th>
                            <th>Contact</th>
                            <th>Solde actuel</th>
                            <th>Total recharges</th>
                            <th>Courses effectuées</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comptes_coursiers as $compte): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($compte['nom']); ?></strong><br>
                                <small style="color: rgba(255, 255, 255, 0.6);">ID: <?php echo $compte['coursier_id']; ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($compte['email']); ?><br>
                                <small><?php echo htmlspecialchars($compte['telephone']); ?></small>
                            </td>
                            <td style="font-weight: 700; color: <?php echo $compte['solde'] >= 0 ? '#27AE60' : '#E94560'; ?>;">
                                <?php echo number_format($compte['solde'], 0, ',', ' '); ?> FCFA
                            </td>
                            <td><?php echo number_format($compte['total_recharges'], 0, ',', ' '); ?> FCFA</td>
                            <td><?php echo $compte['courses_totales']; ?> courses</td>
                            <td>
                                <span class="status-badge <?php echo $compte['statut'] === 'actif' ? 'status-validee' : 'status-refusee'; ?>">
                                    <?php echo ucfirst($compte['statut']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: var(--space-2);">
                                    <button class="btn btn-info" onclick="viewCoursierDetails(<?php echo $compte['coursier_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-warning" onclick="editCoursierAccount(<?php echo $compte['coursier_id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a class="btn btn-secondary" href="admin.php?section=finances&tab=transactions&coursier_id=<?php echo $compte['coursier_id']; ?>">
                                        <i class="fas fa-exchange-alt"></i> Voir transactions
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($tab === 'clients_particuliers'): ?>
        <!-- COMPTES CLIENTS PARTICULIERS -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border);">
                <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                    <i class="fas fa-user"></i>
                    Gestion des comptes clients particuliers
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Vue d'ensemble de tous les comptes clients particuliers inscrits depuis l'index</p>
            </div>
            
            <?php
            // Récupérer tous les comptes clients particuliers
            // TODO: À implémenter selon la structure de la table clients_particuliers
            // Pour l'instant, structure vide en attendant la création de la table
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS comptes_clients_particuliers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL UNIQUE,
                    nom VARCHAR(100) NOT NULL,
                    prenom VARCHAR(100) NOT NULL,
                    email VARCHAR(150) NOT NULL,
                    telephone VARCHAR(20),
                    solde DECIMAL(10,2) DEFAULT 0.00,
                    statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
                    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP,
                    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $stmt->execute();
            
            $stmt = $pdo->prepare("
                SELECT * FROM comptes_clients_particuliers 
                ORDER BY date_inscription DESC
            ");
            $stmt->execute();
            $comptes_particuliers = $stmt->fetchAll();
            ?>
            
            <div style="padding: var(--space-6);">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Solde actuel</th>
                            <th>Date inscription</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($comptes_particuliers) > 0): ?>
                            <?php foreach ($comptes_particuliers as $compte): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($compte['prenom'] . ' ' . $compte['nom']); ?></strong><br>
                                    <small style="color: rgba(255, 255, 255, 0.6);">ID: <?php echo $compte['client_id']; ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($compte['email']); ?><br>
                                    <small><?php echo htmlspecialchars($compte['telephone'] ?? 'N/A'); ?></small>
                                </td>
                                <td style="font-weight: 700; color: <?php echo $compte['solde'] >= 0 ? '#27AE60' : '#E94560'; ?>;">
                                    <?php echo number_format($compte['solde'], 0, ',', ' '); ?> FCFA
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($compte['date_inscription'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $compte['statut'] === 'actif' ? 'status-validee' : 'status-refusee'; ?>">
                                        <?php echo ucfirst($compte['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: var(--space-2);">
                                        <button class="btn btn-info" onclick="viewClientDetails(<?php echo $compte['client_id']; ?>, 'particulier')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-warning" onclick="editClientAccount(<?php echo $compte['client_id']; ?>, 'particulier')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: var(--space-8);">
                                    <i class="fas fa-user-plus" style="font-size: 3rem; margin-bottom: var(--space-4);"></i><br>
                                    Aucun client particulier inscrit pour le moment
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($tab === 'clients_business'): ?>
        <!-- COMPTES CLIENTS BUSINESS -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border);">
                <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                    <i class="fas fa-building"></i>
                    Gestion des comptes clients business
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Vue d'ensemble de tous les comptes clients business créés par l'admin</p>
            </div>
            
            <?php
            // Récupérer tous les comptes clients business
            // TODO: À implémenter selon la structure de la table clients_business
            // Pour l'instant, structure vide en attendant la création de la table
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS comptes_clients_business (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_id INT NOT NULL UNIQUE,
                    nom_entreprise VARCHAR(150) NOT NULL,
                    nom_contact VARCHAR(100) NOT NULL,
                    prenom_contact VARCHAR(100) NOT NULL,
                    email VARCHAR(150) NOT NULL,
                    telephone VARCHAR(20),
                    secteur_activite VARCHAR(100),
                    adresse TEXT,
                    solde DECIMAL(10,2) DEFAULT 0.00,
                    statut ENUM('actif', 'inactif', 'suspendu') DEFAULT 'actif',
                    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
                    date_modification DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    admin_createur INT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $stmt->execute();
            
            $stmt = $pdo->prepare("
                SELECT * FROM comptes_clients_business 
                ORDER BY date_creation DESC
            ");
            $stmt->execute();
            $comptes_business = $stmt->fetchAll();
            ?>
            
            <div style="padding: var(--space-6);">
                <div style="margin-bottom: var(--space-6);">
                    <button class="btn btn-primary" onclick="showCreateBusinessClientModal()">
                        <i class="fas fa-plus"></i>
                        Créer un nouveau compte business
                    </button>
                </div>
                
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Entreprise</th>
                            <th>Contact</th>
                            <th>Secteur</th>
                            <th>Solde actuel</th>
                            <th>Date création</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($comptes_business) > 0): ?>
                            <?php foreach ($comptes_business as $compte): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($compte['nom_entreprise']); ?></strong><br>
                                    <small style="color: rgba(255, 255, 255, 0.6);">ID: <?php echo $compte['client_id']; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($compte['prenom_contact'] . ' ' . $compte['nom_contact']); ?></strong><br>
                                    <?php echo htmlspecialchars($compte['email']); ?><br>
                                    <small><?php echo htmlspecialchars($compte['telephone'] ?? 'N/A'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($compte['secteur_activite'] ?? 'Non défini'); ?></td>
                                <td style="font-weight: 700; color: <?php echo $compte['solde'] >= 0 ? '#27AE60' : '#E94560'; ?>;">
                                    <?php echo number_format($compte['solde'], 0, ',', ' '); ?> FCFA
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($compte['date_creation'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $compte['statut'] === 'actif' ? 'status-validee' : 'status-refusee'; ?>">
                                        <?php echo ucfirst($compte['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: var(--space-2);">
                                        <button class="btn btn-info" onclick="viewClientDetails(<?php echo $compte['client_id']; ?>, 'business')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-warning" onclick="editClientAccount(<?php echo $compte['client_id']; ?>, 'business')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-danger" onclick="deleteBusinessClient(<?php echo $compte['client_id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: rgba(255, 255, 255, 0.6); padding: var(--space-8);">
                                    <i class="fas fa-building" style="font-size: 3rem; margin-bottom: var(--space-4);"></i><br>
                                    Aucun client business créé pour le moment
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($tab === 'transactions'): ?>
    <!-- TRANSACTIONS (DÉTAILS PAR COMMANDE + EXPORT) -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border); display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                        <i class="fas fa-exchange-alt"></i>
                        Transactions par commande
                    </h3>
                    <p style="color: rgba(255, 255, 255, 0.7); margin:0;">Vue agrégée par numéro de commande avec Commission, Frais plateforme et Gain net du coursier.</p>
                </div>
                <div style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                    <form method="GET" style="display:flex; gap:8px; align-items:end;">
                        <input type="hidden" name="section" value="finances">
                        <input type="hidden" name="tab" value="transactions">
                        <div class="form-group" style="margin:0;">
                            <label for="filter_order">N° commande</label>
                            <input type="text" id="filter_order" name="order" value="<?php echo htmlspecialchars($_GET['order'] ?? ''); ?>" placeholder="Ex: 2025-0001">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label for="filter_coursier">Coursier ID</label>
                            <input type="number" id="filter_coursier" name="coursier_id" value="<?php echo isset($_GET['coursier_id']) ? (int)$_GET['coursier_id'] : ''; ?>" min="1">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label for="limit">Limite</label>
                            <input type="number" id="limit" name="limit" value="<?php echo isset($_GET['limit']) ? (int)$_GET['limit'] : 200; ?>" min="10" max="1000">
                        </div>
                        <button class="btn btn-info" type="submit" style="height:38px;">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <a class="btn btn-success" href="admin.php?section=finances&tab=transactions&export=1<?php
                            $qs = [];
                            if (!empty($_GET['order'])) $qs[] = 'order=' . urlencode($_GET['order']);
                            if (!empty($_GET['coursier_id'])) $qs[] = 'coursier_id=' . urlencode($_GET['coursier_id']);
                            if (!empty($_GET['limit'])) $qs[] = 'limit=' . urlencode($_GET['limit']);
                            echo $qs ? '&' . implode('&', $qs) : '';
                        ?>"><i class="fas fa-file-export"></i> Export CSV</a>
                        <a class="btn btn-secondary" href="admin.php?section=finances&tab=transactions&export=1&format=xlsx<?php
                            $qs = [];
                            if (!empty($_GET['order'])) $qs[] = 'order=' . urlencode($_GET['order']);
                            if (!empty($_GET['coursier_id'])) $qs[] = 'coursier_id=' . urlencode($_GET['coursier_id']);
                            if (!empty($_GET['limit'])) $qs[] = 'limit=' . urlencode($_GET['limit']);
                            echo $qs ? '&' . implode('&', $qs) : '';
                        ?>"><i class="fas fa-file-excel"></i> Export XLSX</a>
                    </form>
                </div>
            </div>

            <?php
            // Préparer données agrégées
            $orderFilter = trim($_GET['order'] ?? '');
            $coursierFilter = isset($_GET['coursier_id']) && $_GET['coursier_id'] !== '' ? (int)$_GET['coursier_id'] : null;
            $limit = isset($_GET['limit']) ? max(10, min(1000, (int)$_GET['limit'])) : 200;

            // Construire SQL dynamique
                        $sql = "
          SELECT t.orderNumber,
              MAX(CASE WHEN t.type='credit' THEN t.montant END) AS commission,
              MAX(CASE WHEN t.type='debit' THEN t.montant END) AS fee,
                       MAX(t.compte_id) AS coursier_id,
                                             MIN(t.date_creation) AS date_creation
                FROM (
                    SELECT reference, type, montant, compte_id, date_creation,
                           CASE
                             WHEN reference LIKE 'DELIV\\_%\\_FEE' THEN SUBSTRING(reference, 7, CHAR_LENGTH(reference)-11)
                             ELSE SUBSTRING(reference, 7)
                           END AS orderNumber
                    FROM transactions_financieres
                    WHERE reference LIKE 'DELIV_%'
                ) t
                WHERE 1=1
            ";
            $params = [];
            if ($orderFilter !== '') {
                $sql .= " AND t.orderNumber LIKE ?";
                $params[] = "%$orderFilter%";
            }
            if ($coursierFilter !== null) {
                $sql .= " AND t.compte_id = ?";
                $params[] = $coursierFilter;
            }
            $sql .= " GROUP BY t.orderNumber ORDER BY date_creation DESC LIMIT $limit";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer noms coursiers et montants commandes (si disponibles)
            $mapCoursiers = [];
            if (!empty($rows)) {
                $ids = array_unique(array_filter(array_map(fn($r)=> (int)($r['coursier_id'] ?? 0), $rows)));
                if ($ids) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    $stn = $pdo->prepare("SELECT id, nom FROM coursiers WHERE id IN ($in)");
                    $stn->execute($ids);
                    foreach ($stn->fetchAll(PDO::FETCH_ASSOC) as $c) { $mapCoursiers[(int)$c['id']] = $c['nom']; }
                }
            }

            // Essayer de récupérer total commande
            $rowsEnrich = [];
            foreach ($rows as $r) {
                $orderNum = $r['orderNumber'];
                $totalCmd = null;
                $modePaiement = null; $isCash = null;
                try {
                    $stc = $pdo->prepare("SELECT COALESCE(prix_estime, cash_amount) AS total_cmd, mode_paiement, cash_collected FROM commandes_classiques WHERE order_number = ? OR numero_commande = ? OR code_commande = ? LIMIT 1");
                    $stc->execute([$orderNum, $orderNum, $orderNum]);
                    $t = $stc->fetch(PDO::FETCH_ASSOC);
                    if ($t && $t['total_cmd'] !== null) { $totalCmd = (float)$t['total_cmd']; }
                    if ($t && isset($t['mode_paiement'])) { $modePaiement = $t['mode_paiement']; }
                    if ($t && isset($t['cash_collected'])) { $isCash = (int)$t['cash_collected']; }
                } catch (Throwable $e) { $totalCmd = null; }
                $r['total_commande'] = $totalCmd;
                $r['mode_paiement'] = $modePaiement;
                $r['cash_flag'] = $isCash;
                $rowsEnrich[] = $r;
            }
            ?>

            <div style="padding: var(--space-6);">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>N° commande</th>
                            <th>Total (client)</th>
                            <th>Commission</th>
                            <th>Frais plateforme</th>
                            <th>Gain net du coursier</th>
                            <th>Coursier</th>
                            <th>Paiement</th>
                            <th>Cash</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rowsEnrich as $row):
                            $commission = (float)($row['commission'] ?? 0);
                            $fee = (float)($row['fee'] ?? 0);
                            $net = isset($row['total_commande']) && $row['total_commande'] !== null
                                ? ((float)$row['total_commande'] - $commission - $fee)
                                : ($commission - $fee);
                            $cid = (int)($row['coursier_id'] ?? 0);
                            $cname = $mapCoursiers[$cid] ?? 'N/A';
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['date_creation'])); ?></td>
                            <td><code><?php echo htmlspecialchars($row['orderNumber']); ?></code></td>
                            <td><?php echo $row['total_commande'] !== null ? number_format($row['total_commande'], 0, ',', ' ') . ' FCFA' : '<span style="color:rgba(255,255,255,0.6)">N/D</span>'; ?></td>
                            <td style="color:#27AE60; font-weight:700;">+<?php echo number_format($commission, 0, ',', ' '); ?> FCFA</td>
                            <td style="color:#E94560; font-weight:700;">-<?php echo number_format($fee, 0, ',', ' '); ?> FCFA</td>
                            <td style="font-weight:800; color: var(--primary-gold);"><?php echo number_format($net, 0, ',', ' '); ?> FCFA</td>
                            <td><?php echo htmlspecialchars($cname) . ' (#' . $cid . ')'; ?></td>
                            <td><?php echo $row['mode_paiement'] ? htmlspecialchars($row['mode_paiement']) : '<span style="color:rgba(255,255,255,0.6)">N/D</span>'; ?></td>
                            <td><?php echo ($row['cash_flag'] !== null) ? (($row['cash_flag'] ? 'Oui' : 'Non')) : '<span style="color:rgba(255,255,255,0.6)">N/D</span>'; ?></td>
                            <td>
                                <a class="btn btn-secondary" href="admin.php?section=finances&tab=transactions&export=1&order=<?php echo urlencode($row['orderNumber']); ?>">
                                    <i class="fas fa-file-export"></i> CSV
                                </a>
                                <a class="btn btn-secondary" href="admin.php?section=finances&tab=transactions&export=1&format=xlsx&order=<?php echo urlencode($row['orderNumber']); ?>">
                                    <i class="fas fa-file-excel"></i>
                                </a>
                                <button class="btn btn-info" onclick="openTxnDetails('<?php echo htmlspecialchars($row['orderNumber']); ?>')">
                                    <i class="fas fa-eye"></i> Voir détails
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modale Détails Transaction -->
        <div id="txn-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
            <div style="background: var(--glass-bg); border:1px solid var(--glass-border); border-radius:12px; width:min(900px, 95vw); max-height:85vh; overflow:auto;">
                <div style="display:flex; align-items:center; justify-content:space-between; padding:16px; border-bottom:1px solid var(--glass-border); gap:12px;">
                    <h4 style="margin:0; color: var(--primary-gold);"><i class="fas fa-file-invoice"></i> Détails transaction <span id="txn-order-title"></span></h4>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <a id="txn-export-csv" class="btn btn-secondary" href="#" target="_blank"><i class="fas fa-file-export"></i> Export CSV</a>
                        <a id="txn-export-xlsx" class="btn btn-secondary" href="#" target="_blank"><i class="fas fa-file-excel"></i> Export XLSX</a>
                        <button class="btn btn-danger" onclick="closeTxnModal()"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <div style="padding:16px;">
                    <div id="txn-modal-content" style="color:rgba(255,255,255,0.85)">Chargement...</div>
                </div>
            </div>
        </div>

        <?php elseif ($tab === 'recharges'): ?>
        <!-- GESTION DES RECHARGES -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border);">
                <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                    <i class="fas fa-credit-card"></i>
                    Gestion des recharges
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Validation et suivi des recharges de comptes coursiers</p>
            </div>
            
            <?php
            // Récupérer les recharges en attente
            $stmt = $pdo->prepare("
                SELECT rc.*, c.nom as coursier_nom, c.telephone
                FROM recharges_coursiers rc
                JOIN coursiers c ON rc.coursier_id = c.id
                WHERE rc.statut = 'en_attente'
                ORDER BY rc.date_demande ASC
            ");
            $stmt->execute();
            $recharges_attente = $stmt->fetchAll();
            ?>
            
            <div style="padding: var(--space-6);">
                <?php if (count($recharges_attente) > 0): ?>
                    <h4 style="color: #FFC107; margin-bottom: var(--space-6);">
                        <i class="fas fa-clock"></i>
                        Recharges en attente (<?php echo count($recharges_attente); ?>)
                    </h4>
                    
                    <?php foreach ($recharges_attente as $recharge): ?>
                    <div class="recharge-card">
                        <div class="recharge-header">
                            <div>
                                <h5 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                                    <?php echo htmlspecialchars($recharge['coursier_nom']); ?>
                                </h5>
                                <p style="color: rgba(255, 255, 255, 0.7); margin: 0;">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($recharge['telephone']); ?>
                                </p>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.8rem; font-weight: 700; color: var(--primary-gold);">
                                    <?php echo number_format($recharge['montant'], 0, ',', ' '); ?> FCFA
                                </div>
                                <small style="color: rgba(255, 255, 255, 0.6);">
                                    <?php echo date('d/m/Y H:i', strtotime($recharge['date_demande'])); ?>
                                </small>
                            </div>
                        </div>
                        
                        <?php if ($recharge['reference_paiement']): ?>
                        <p style="margin-bottom: var(--space-3);">
                            <strong>Référence:</strong> <code><?php echo htmlspecialchars($recharge['reference_paiement']); ?></code>
                        </p>
                        <?php endif; ?>
                        
                        <div class="recharge-actions">
                            <form method="POST" style="display: inline-block; margin-right: var(--space-3);">
                                <input type="hidden" name="action" value="validate_recharge">
                                <input type="hidden" name="recharge_id" value="<?php echo $recharge['id']; ?>">
                                <input type="text" name="comment" placeholder="Commentaire (optionnel)" style="margin-right: var(--space-2); display: inline-block; width: 200px;">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check"></i>
                                    Valider
                                </button>
                            </form>
                            
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="action" value="reject_recharge">
                                <input type="hidden" name="recharge_id" value="<?php echo $recharge['id']; ?>">
                                <input type="text" name="comment" placeholder="Raison du refus" style="margin-right: var(--space-2); display: inline-block; width: 200px;">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-times"></i>
                                    Refuser
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span>Aucune recharge en attente de validation.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php elseif ($tab === 'rechargement_direct'): ?>
        <!-- RECHARGEMENT DIRECT -->
        <?php include __DIR__ . '/sections_finances/rechargement_direct.php'; ?>
        
        <?php elseif ($tab === 'pricing'): ?>
        <!-- CALCUL DES PRIX -->
        <div class="finances-container">
            <div style="padding: var(--space-6); border-bottom: 1px solid var(--glass-border);">
                <h3 style="color: var(--primary-gold); margin-bottom: var(--space-2);">
                    <i class="fas fa-calculator"></i>
                    Paramètres de tarification
                </h3>
                <p style="color: rgba(255, 255, 255, 0.7);">Base + supplément par km au-delà de la destination</p>
            </div>
            
            <div style="padding: var(--space-6);">
                <form method="POST">
                    <input type="hidden" name="action" value="update_pricing">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="prix_km">Prix par kilomètre (FCFA)</label>
                            <input type="number" id="prix_km" name="prix_km" value="<?php echo $tarification['prix_kilometre']; ?>" min="0" step="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="frais_base">Frais de base (FCFA)</label>
                            <input type="number" id="frais_base" name="frais_base" value="<?php echo $tarification['frais_base']; ?>" min="0" step="1">
                        </div>

                        <div class="form-group">
                            <label for="commission_suzosky">Commission Suzosky (%)</label>
                            <input type="number" id="commission_suzosky" name="commission_suzosky" value="<?php echo (float)$tarification['commission_suzosky']; ?>" min="1" max="50" step="0.5">
                            <small style="color: rgba(255,255,255,0.6)">Pourcentage de commission appliqué (1–50%).</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="frais_plateforme">Frais plateforme (%)</label>
                            <input type="number" id="frais_plateforme" name="frais_plateforme" value="<?php echo isset($tarification['frais_plateforme']) ? (float)$tarification['frais_plateforme'] : 5; ?>" min="0" max="50" step="0.5">
                            <small style="color: rgba(255,255,255,0.6)">Pourcentage des frais de plateforme appliqués aux courses.</small>
                        </div>

                        <div class="form-group">
                            <label for="frais_publicitaires">Frais publicitaires (%)</label>
                            <input type="number" id="frais_publicitaires" name="frais_publicitaires" value="<?php echo isset($tarification['frais_publicitaires']) ? (float)$tarification['frais_publicitaires'] : 0; ?>" min="0" max="50" step="0.5">
                            <small style="color: rgba(255,255,255,0.6)">Pourcentage alloué à la publicité/marketing.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="supp_km_rate">Supplément par km après destination (FCFA)</label>
                            <input type="number" id="supp_km_rate" name="supp_km_rate" value="<?php echo (int)$tarification['supp_km_rate']; ?>" min="0" step="1">
                            <small style="color: rgba(255,255,255,0.6)">Chaque km supplémentaire après le point de livraison est facturé à ce tarif.</small>
                        </div>

                        <div class="form-group">
                            <label for="supp_km_free">Km gratuits après destination</label>
                            <input type="number" id="supp_km_free" name="supp_km_free" value="<?php echo (float)$tarification['supp_km_free_allowance']; ?>" min="0" step="0.5">
                            <small style="color: rgba(255,255,255,0.6)">Ex: 1 signifie que le 1er km supplémentaire est offert, facturation à partir du 2e km.</small>
                        </div>

                    </div>
                    
                    <div style="margin-top: var(--space-6);">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
                
                <!-- Simulateur de prix -->
                <div class="pricing-simulator">
                    <h4 style="color: #3B82F6; margin-bottom: var(--space-4);">
                        <i class="fas fa-calculator"></i>
                        Simulateur de prix
                    </h4>
                    
                    <div style="display: flex; gap: var(--space-4); align-items: end; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1;">
                            <label for="sim_distance">Distance standard (km)</label>
                            <input type="number" id="sim_distance" placeholder="Ex: 5.5" step="0.1" min="0">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="sim_extra_km">Km supplémentaires après destination</label>
                            <input type="number" id="sim_extra_km" placeholder="Ex: 2" step="0.1" min="0">
                        </div>
                        <button type="button" class="btn btn-info" onclick="calculatePrice()">
                            <i class="fas fa-calculator"></i>
                            Calculer
                        </button>
                    </div>
                    
                    <div id="price-result" style="margin-top: var(--space-4); padding: var(--space-4); background: rgba(59, 130, 246, 0.1); border-radius: 8px; display: none;">
                        <h5 style="color: #3B82F6;">Résultat du calcul :</h5>
                        <div id="price-details"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- TAB PAR DÉFAUT -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <span>Sélectionnez un onglet pour commencer.</span>
        </div>
        
        <?php endif; ?>
        
    </div>
</div>

<script>
// API interne: renvoyer détails transaction d'une commande
<?php if (($tab ?? '') === 'transactions' && isset($_GET['details']) && isset($_GET['order'])) {
    try {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        $ord = trim($_GET['order']);
        // Ecritures
        $stmt = $pdo->prepare("SELECT id, type, montant, reference, description, statut, date_creation, compte_id FROM transactions_financieres WHERE reference IN (?, ?) ORDER BY id ASC");
        $stmt->execute(['DELIV_' . $ord, 'DELIV_' . $ord . '_FEE']);
        $txns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Snapshot
        $snap = null;
        try {
            $st = $pdo->prepare("SELECT * FROM financial_context_by_order WHERE order_number = ? LIMIT 1");
            $st->execute([$ord]);
            $snap = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) { $snap = null; }
    echo json_encode(['success'=>true, 'transactions'=>$txns, 'snapshot'=>$snap]);
        exit;
    } catch (Throwable $e) {
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
        exit;
    }
} ?>

// Fonction pour calculer le prix en temps réel (modèle: km supplémentaires après destination)
function calculatePrice() {
    const distance = parseFloat(document.getElementById('sim_distance').value);
    const extra = parseFloat(document.getElementById('sim_extra_km')?.value || '0');

    if (isNaN(distance) || distance <= 0) {
        alert('Veuillez entrer une distance valide');
        return;
    }

    const prixKm = <?php echo (int)$tarification['prix_kilometre']; ?>;
    const fraisBase = <?php echo (int)$tarification['frais_base']; ?>;
    const commissionRate = <?php echo (float)$tarification['commission_suzosky']; ?>;
    const feeRate = <?php echo isset($tarification['frais_plateforme']) ? (float)$tarification['frais_plateforme'] : 5; ?>;
    const adFeeRate = <?php echo isset($tarification['frais_publicitaires']) ? (float)$tarification['frais_publicitaires'] : 0; ?>;
    const suppKmRate = <?php echo (int)$tarification['supp_km_rate']; ?>;
    const suppKmFree = <?php echo (float)$tarification['supp_km_free_allowance']; ?>;

    let prixTotal = fraisBase + (distance * prixKm);
    const chargeableExtra = Math.max(0, extra - suppKmFree);
    const supplement = chargeableExtra * suppKmRate;
    prixTotal += supplement;

    const resultDiv = document.getElementById('price-result');
    const detailsDiv = document.getElementById('price-details');

    let detailsHTML = `
        <p><strong>Distance standard:</strong> ${distance} km</p>
        <p><strong>Frais de base:</strong> ${fraisBase.toLocaleString()} FCFA</p>
        <p><strong>Coût distance:</strong> ${distance} × ${prixKm.toLocaleString()} = ${(distance * prixKm).toLocaleString()} FCFA</p>
        <p><strong>Km sup. après destination:</strong> ${extra} km (dont ${suppKmFree} km gratuit)</p>
    `;
    if (supplement > 0) {
        detailsHTML += `<p><strong>Supplément:</strong> ${chargeableExtra.toLocaleString()} × ${suppKmRate.toLocaleString()} = ${supplement.toLocaleString()} FCFA</p>`;
    }
    const commissionAmount = Math.round((prixTotal * commissionRate) / 100);
    const platformFee = Math.round((prixTotal * feeRate) / 100);
    const adFee = Math.round((prixTotal * adFeeRate) / 100);
    const gainNetCoursier = prixTotal - commissionAmount - platformFee - adFee;
    detailsHTML += `
        <hr>
        <p><strong>Commission Suzosky (${commissionRate}%):</strong> ${commissionAmount.toLocaleString()} FCFA</p>
    <p><strong>Frais plateforme (${feeRate}%):</strong> ${platformFee.toLocaleString()} FCFA</p>
    <p><strong>Frais publicitaires (${adFeeRate}%):</strong> ${adFee.toLocaleString()} FCFA</p>
        <p><strong>Gain net du coursier:</strong> <span style="font-weight:700; color: var(--primary-gold);">${gainNetCoursier.toLocaleString()} FCFA</span></p>
        <hr>
        <p style="font-size: 1.2rem; font-weight: 700; color: var(--primary-gold);"><strong>Prix total: ${prixTotal.toLocaleString()} FCFA</strong></p>
    `;

    detailsDiv.innerHTML = detailsHTML;
    resultDiv.style.display = 'block';
}

// Fonction pour voir les détails d'un coursier
function viewCoursierDetails(coursierId) {
    // TODO: Implémenter modal avec détails du coursier
    console.log('Voir détails coursier:', coursierId);
}

// Fonction pour éditer un compte coursier
function editCoursierAccount(coursierId) {
    // TODO: Implémenter modal d'édition
    console.log('Éditer compte coursier:', coursierId);
}

// Fonction pour voir les détails d'un client
function viewClientDetails(clientId, type) {
    // TODO: Implémenter modal avec détails du client
    console.log('Voir détails client:', clientId, 'Type:', type);
}

// Fonction pour éditer un compte client
function editClientAccount(clientId, type) {
    // TODO: Implémenter modal d'édition client
    console.log('Éditer compte client:', clientId, 'Type:', type);
}

// Fonction pour afficher le modal de création de client business
function showCreateBusinessClientModal() {
    // TODO: Implémenter modal de création client business
    alert('Fonctionnalité de création de client business à implémenter');
    console.log('Créer nouveau client business');
}

// Fonction pour supprimer un client business
function deleteBusinessClient(clientId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce client business ?')) {
        // TODO: Implémenter suppression client business
        console.log('Supprimer client business:', clientId);
    }
}

// Auto-calcul en temps réel
document.addEventListener('DOMContentLoaded', function() {
    // Contrôles de tarification en temps réel
    const commissionSlider = document.getElementById('commission-slider');
    const priceKmSlider = document.getElementById('price-km-slider');
    const commissionValue = document.getElementById('commission-value');
    const priceKmValue = document.getElementById('price-km-value');
    const baseFareSlider = document.getElementById('base-fare-slider');
    const baseFareValue = document.getElementById('base-fare-value');
    const suppKmRateSlider = document.getElementById('supp-km-rate-slider');
    const suppKmRateValue = document.getElementById('supp-km-rate-value');
    const suppKmFreeSlider = document.getElementById('supp-km-free-slider');
    const suppKmFreeValue = document.getElementById('supp-km-free-value');
    const syncStatus = document.getElementById('sync-status');
    
    // Éléments du simulateur
    const simDistance = document.getElementById('sim-distance');
    const simBase = document.getElementById('sim-base');
    const simTotal = document.getElementById('sim-total');
    const simCommission = document.getElementById('sim-commission');
    const feeSlider = document.getElementById('fee-slider');
    const simFeeAmount = document.getElementById('sim-fee-amount');
    const simAdFeeAmount = document.getElementById('sim-ad-fee-amount');
    const simNet = document.getElementById('sim-net');
    
    let updateTimeout;
    
    function updatePricingDisplay() {
    if (commissionValue && commissionSlider) commissionValue.textContent = commissionSlider.value + '%';
    if (document.getElementById('fee-value') && feeSlider) document.getElementById('fee-value').textContent = feeSlider.value + '%';
        if (priceKmValue && priceKmSlider) priceKmValue.textContent = priceKmSlider.value + ' FCFA/km';
        if (baseFareValue && baseFareSlider) baseFareValue.textContent = baseFareSlider.value;
        if (suppKmRateValue && suppKmRateSlider) suppKmRateValue.textContent = suppKmRateSlider.value;
        if (suppKmFreeValue && suppKmFreeSlider) suppKmFreeValue.textContent = suppKmFreeSlider.value;
        updateSimulator();
    }
    
    function updateSimulator() {
        if (!simDistance || !simBase || !simTotal || !simCommission) return;
        
    const distance = parseFloat(simDistance.value) || 5;
    const base = baseFareSlider ? parseFloat(baseFareSlider.value) : (parseFloat(simBase.value) || 500);
    const pricePerKm = parseFloat(priceKmSlider ? priceKmSlider.value : 300) || 300;
    const commission = parseFloat(commissionSlider ? commissionSlider.value : 15) || 15;
    const feeRate = parseFloat(feeSlider ? feeSlider.value : (<?php echo isset($tarification['frais_plateforme']) ? (float)$tarification['frais_plateforme'] : 5; ?>)) || 0;
    const adFeeRate = parseFloat(document.getElementById('ad-fee-slider') ? document.getElementById('ad-fee-slider').value : (<?php echo isset($tarification['frais_publicitaires']) ? (float)$tarification['frais_publicitaires'] : 0; ?>)) || 0;
    const suppRate = suppKmRateSlider ? parseFloat(suppKmRateSlider.value) : 0;
    const freeKm = suppKmFreeSlider ? parseFloat(suppKmFreeSlider.value) : 0;
    const extraKm = 0; // dans ce mini simulateur, on ne renseigne pas d'extra km

    const chargeableExtra = Math.max(0, extraKm - freeKm);
    const total = base + (distance * pricePerKm) + (chargeableExtra * suppRate);
        const commissionAmount = (total * commission) / 100;
        const feeAmount = (total * feeRate) / 100;
        const adFeeAmount = (total * adFeeRate) / 100;
    const netAmount = total - commissionAmount - feeAmount - adFeeAmount;
        
        simTotal.textContent = total.toLocaleString('fr-FR') + ' FCFA';
        simCommission.textContent = Math.round(commissionAmount).toLocaleString('fr-FR') + ' FCFA';
    if (simFeeAmount) simFeeAmount.textContent = Math.round(feeAmount).toLocaleString('fr-FR') + ' FCFA';
    if (simAdFeeAmount) simAdFeeAmount.textContent = Math.round(adFeeAmount).toLocaleString('fr-FR') + ' FCFA';
        if (simNet) simNet.textContent = Math.round(netAmount).toLocaleString('fr-FR') + ' FCFA';
    }
    
    function syncPricing() {
        if (!syncStatus || !commissionSlider || !priceKmSlider) return;
        
        syncStatus.className = 'sync-status syncing';
        syncStatus.innerHTML = '<i class="fas fa-sync-alt"></i><span>Synchronisation...</span>';
        
    const formData = new FormData();
        formData.append('action', 'update_pricing_ajax');
    formData.append('prix_km', priceKmSlider.value);
    formData.append('commission_suzosky', commissionSlider.value);
    // Inclure le frais plateforme si disponible
    if (feeSlider) formData.append('frais_plateforme', feeSlider.value);
    if (baseFareSlider) formData.append('frais_base', baseFareSlider.value);
    if (suppKmRateSlider) formData.append('supp_km_rate', suppKmRateSlider.value);
    if (suppKmFreeSlider) formData.append('supp_km_free', suppKmFreeSlider.value);
    if (document.getElementById('ad-fee-slider')) formData.append('frais_publicitaires', document.getElementById('ad-fee-slider').value);
        
        // Unifie la synchronisation via l'API dédiée (single source of truth)
        fetch('api/sync_pricing.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                syncStatus.className = 'sync-status';
                syncStatus.innerHTML = '<i class="fas fa-check"></i><span>Synchronisé</span>';
                setTimeout(() => {
                    syncStatus.className = 'sync-status';
                    syncStatus.innerHTML = '<i class="fas fa-sync-alt"></i><span>Synchronisation automatique activée</span>';
                }, 2000);
            } else {
                throw new Error(data.message || 'Erreur de synchronisation');
            }
        })
        .catch(error => {
            console.error('Erreur sync:', error);
            syncStatus.className = 'sync-status';
            syncStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Erreur de synchronisation</span>';
            syncStatus.style.background = 'rgba(233, 69, 96, 0.2)';
            syncStatus.style.borderColor = 'rgba(233, 69, 96, 0.4)';
            syncStatus.style.color = '#E94560';
        });
    }
    
    function debounceSync() {
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(syncPricing, 800);
    }
    
    // Event listeners
    if (commissionSlider) {
        commissionSlider.addEventListener('input', function() {
            updatePricingDisplay();
            debounceSync();
        });
    }
    
    if (priceKmSlider) {
        priceKmSlider.addEventListener('input', function() {
            updatePricingDisplay();
            debounceSync();
        });
    }

    if (feeSlider) {
        feeSlider.addEventListener('input', function() {
            updatePricingDisplay();
            debounceSync();
        });
    }

    if (baseFareSlider) {
        baseFareSlider.addEventListener('input', function() {
            updatePricingDisplay();
            debounceSync();
        });
    }

    if (suppKmRateSlider) {
        suppKmRateSlider.addEventListener('input', function() {
            updatePricingDisplay();
            debounceSync();
        });
    }

    if (suppKmFreeSlider) {
        suppKmFreeSlider.addEventListener('input', function() {
            updatePricingDisplay();
            debounceSync();
        });
    }
    
    if (simDistance) {
        simDistance.addEventListener('input', updateSimulator);
    }
    
    if (simBase) {
        simBase.addEventListener('input', updateSimulator);
    }
    // Fee slider updates simulator via updatePricingDisplay
    
    // Initialisation
    updatePricingDisplay();
    
    // Legacy code pour compatibilité
    if (document.getElementById('sim_distance')) {
        document.getElementById('sim_distance').addEventListener('input', function() {
            if (this.value) {
                calculatePrice();
            }
        });
    }
});

// Modal transactions: ouverture/fermeture et rendu
function openTxnDetails(orderNumber) {
    const modal = document.getElementById('txn-modal');
    const title = document.getElementById('txn-order-title');
    const content = document.getElementById('txn-modal-content');
    const exportCsv = document.getElementById('txn-export-csv');
    const exportXlsx = document.getElementById('txn-export-xlsx');
    if (!modal || !title || !content) return;
    title.textContent = '#' + orderNumber;
    content.textContent = 'Chargement...';
    modal.style.display = 'flex';

    const url = new URL(window.location.href);
    url.searchParams.set('section', 'finances');
    url.searchParams.set('tab', 'transactions');
    url.searchParams.set('details', '1');
    url.searchParams.set('order', orderNumber);

    if (exportCsv) {
        const csvUrl = new URL(window.location.href);
        csvUrl.searchParams.set('section', 'finances');
        csvUrl.searchParams.set('tab', 'transactions');
        csvUrl.searchParams.set('export', '1');
        csvUrl.searchParams.set('format', 'csv');
        csvUrl.searchParams.set('order', orderNumber);
        exportCsv.href = csvUrl.toString();
    }

    if (exportXlsx) {
        const xlsxUrl = new URL(window.location.href);
        xlsxUrl.searchParams.set('section', 'finances');
        xlsxUrl.searchParams.set('tab', 'transactions');
        xlsxUrl.searchParams.set('export', '1');
        xlsxUrl.searchParams.set('format', 'xlsx');
        xlsxUrl.searchParams.set('order', orderNumber);
        exportXlsx.href = xlsxUrl.toString();
    }

    fetch(url.toString(), { credentials: 'same-origin' })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Erreur');
            const txns = data.transactions || [];
            const snap = data.snapshot || null;
            let html = '';
            html += '<h5 style="margin-top:0">Ecritures</h5>';
            if (txns.length === 0) {
                html += '<p>Aucune écriture trouvée.</p>';
            } else {
                html += '<table class="data-table"><thead><tr><th>Date</th><th>Type</th><th>Montant</th><th>Référence</th><th>Description</th><th>Statut</th></tr></thead><tbody>';
                for (const t of txns) {
                    const sign = t.type === 'credit' ? '+' : '-';
                    const color = t.type === 'credit' ? '#27AE60' : '#E94560';
                    html += `<tr>
                        <td>${new Date(t.date_creation.replace(' ', 'T')).toLocaleString('fr-FR')}</td>
                        <td><span class="status-badge ${t.type==='credit'?'status-validee':'status-refusee'}">${t.type==='credit'?'Crédit':'Débit'}</span></td>
                        <td style="font-weight:700;color:${color}">${sign}${Number(t.montant).toLocaleString('fr-FR')} FCFA</td>
                        <td><code>${t.reference||''}</code></td>
                        <td>${t.description?escapeHtml(t.description):''}</td>
                        <td>${t.statut?escapeHtml(String(t.statut)):'N/A'}</td>
                    </tr>`;
                }
                html += '</tbody></table>';
            }
            html += '<hr>';
            html += '<h5>Paramètres capturés à la livraison</h5>';
            if (!snap) {
                html += '<p style="color:rgba(255,255,255,0.7)">Aucun snapshot trouvé (antérieur à la fonctionnalité ou livraison non standard).</p>';
            } else {
                html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">';
                const fields = [
                    ['Commission (%)', snap.commission_rate],
                    ['Frais plateforme (%)', snap.fee_rate],
                    ['Prix/km', snap.prix_kilometre],
                    ['Frais de base', snap.frais_base],
                    ['Supp./km', snap.supp_km_rate],
                    ['Km gratuits', snap.supp_km_free_allowance],
                    ['Capturé le', snap.captured_at]
                ];
                for (const [k,v] of fields) {
                    html += `<div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);border-radius:8px;padding:8px"><div style="color:var(--primary-gold);font-weight:700">${k}</div><div>${v!==null&&v!==undefined?escapeHtml(String(v)):'N/D'}</div></div>`;
                }
                html += '</div>';
            }
            content.innerHTML = html;
        })
        .catch(err => {
            content.innerHTML = `<div class="alert alert-error"><i class=\"fas fa-exclamation-triangle\"></i><span>${escapeHtml(err.message)}</span></div>`;
        });
}

function closeTxnModal() {
    const modal = document.getElementById('txn-modal');
    if (modal) modal.style.display = 'none';
}

function escapeHtml(str){
    return str.replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s]));
}
</script>