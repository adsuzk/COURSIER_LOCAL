<?php
/**
 * Admin commandes avancées (version locale)
 * Mise en forme premium + tracking temps réel aligné production
 */

$__start = microtime(true);
register_shutdown_function(function() use ($__start) {
    // Small non-intrusive fixed-position badge showing total PHP time in ms
    echo '<div id="php-timer" style="position:fixed;top:8px;right:8px;background:#fff;color:#000;z-index:9999;font-size:13px;padding:6px 10px;border:1px solid #ccc;box-shadow:0 1px 3px rgba(0,0,0,0.2)">Chargement PHP : ' . round((microtime(true)-$__start)*1000,1) . ' ms</div>';
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/SystemSync.php';
require_once __DIR__ . '/lib/db_maintenance.php';
require_once __DIR__ . '/lib/coursier_presence.php';

// Fonction de secours si db_maintenance.php ne se charge pas correctement
if (!function_exists('ensureLegacyClientsTable')) {
    function ensureLegacyClientsTable(PDO $pdo): array {
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
            'columns' => []
        ];

        try {
            // Vérifier si la table clients existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'clients'");
            $tableExists = $stmt->rowCount() > 0;
            
            if ($tableExists) {
                $result['exists'] = true;
                
                // Obtenir les colonnes de la table
                $stmt = $pdo->query("DESCRIBE clients");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as $col) {
                    $result['columns'][$col['Field']] = $col;
                }
            }
        } catch (Exception $e) {
            $result['errors'][] = 'Erreur lors de la vérification de la table clients: ' . $e->getMessage();
        }

        $cache = $result;
        return $cache;
    }
}

// Handle manual termination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'terminate_order') {
    $commandeId = isset($_POST['commande_id']) ? (int) $_POST['commande_id'] : 0;

    if ($commandeId > 0) {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare("SELECT id, code_commande, statut, coursier_id, prix_estime FROM commandes WHERE id = ?");
            $stmt->execute([$commandeId]);
            $commande = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$commande) {
                throw new RuntimeException("Commande introuvable");
            }

            if (in_array($commande['statut'], ['livree', 'annulee'], true)) {
                throw new RuntimeException("Commande déjà terminée");
            }

            $pdo->beginTransaction();

            // Mettre à jour la commande : statut = livree + statut_paiement = paye
            $update = $pdo->prepare("UPDATE commandes SET statut = 'livree', statut_paiement = 'paye', updated_at = NOW() WHERE id = ?");
            $update->execute([$commandeId]);

            if (!empty($commande['coursier_id'])) {
                // Vérifier si une transaction existe déjà pour cette commande
                $checkTransaction = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE commande_id = ?");
                $checkTransaction->execute([$commandeId]);

                if ($checkTransaction->fetchColumn() == 0) {
                    // Créer une transaction de paiement pour la course terminée
                    $refTransaction = 'TRX-' . strtoupper(uniqid());
                    $insert = $pdo->prepare("
                        INSERT INTO transactions (
                            commande_id, reference_transaction, montant, type_transaction,
                            methode_paiement, statut, created_at
                        ) VALUES (?, ?, ?, 'paiement', 'especes', 'success', NOW())
                    ");
                    $insert->execute([
                        $commandeId,
                        $refTransaction,
                        $commande['prix_estime'] ?? 0,
                    ]);
                }
            }

            $pdo->commit();
            $_SESSION['admin_message'] = "Commande #{$commande['code_commande']} terminée avec succès.";
            $_SESSION['admin_message_type'] = 'success';
        } catch (Throwable $e) {
            if (!empty($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['admin_message'] = 'Erreur : ' . $e->getMessage();
            $_SESSION['admin_message_type'] = 'error';
        }
    }

    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '#'));
    exit;
}

// Filters
$filtreStatut = $_GET['statut'] ?? '';
$filtreCoursier = $_GET['coursier'] ?? '';
$filtreDate = $_GET['date'] ?? '';
$filtrePriorite = $_GET['priorite'] ?? '';
$filtreTransaction = trim($_GET['transaction'] ?? '');

function getCommandesWithFilters(string $statut, string $coursier, string $date, string $priorite, string $transaction): array
{
    $pdo = getDBConnection();
    $agentsInfo = getAgentsSchemaInfo($pdo);
    $clientsInfo = ensureLegacyClientsTable($pdo);
    $hasLegacyClients = $clientsInfo['exists'] ?? false;

    $select = [
        'c.*',
        'a.nom AS coursier_nom',
        'a.prenoms AS coursier_prenoms',
        'a.telephone AS coursier_telephone',
        $agentsInfo['status_expression'] . ' AS coursier_status',
        $agentsInfo['online_expression'] . ' AS coursier_is_online',
        $agentsInfo['latitude_expression'] . ' AS coursier_lat',
        $agentsInfo['longitude_expression'] . ' AS coursier_lng',
        'a.' . $agentsInfo['join_column'] . ' AS coursier_reference_id',
    ];

    if ($hasLegacyClients) {
        $select[] = 'cl.nom AS client_nom';
        $select[] = 'cl.telephone AS client_telephone';
    } else {
        $select[] = "COALESCE(c.client_nom, 'Client') AS client_nom";
        $select[] = "COALESCE(c.client_telephone, c.telephone_expediteur) AS client_telephone";
    }

    $sql = 'SELECT ' . implode(',', $select) . ' FROM commandes c'
        . ' LEFT JOIN agents_suzosky a ON c.coursier_id = a.' . $agentsInfo['join_column'];

    if ($hasLegacyClients) {
        $sql .= ' LEFT JOIN clients cl ON cl.id = c.client_id';
    }

    $sql .= ' WHERE 1=1';
    $params = [];

    if ($statut !== '') {
        $sql .= ' AND c.statut = ?';
        $params[] = $statut;
    }
    if ($coursier !== '') {
        $sql .= ' AND c.coursier_id = ?';
        $params[] = $coursier;
    }
    if ($date !== '') {
        $sql .= ' AND DATE(c.created_at) = ?';
        $params[] = $date;
    }
    if ($priorite !== '') {
        $sql .= ' AND c.priorite = ?';
        $params[] = $priorite;
    }
    if ($transaction !== '') {
        $sql .= ' AND (c.code_commande LIKE ? OR c.order_number LIKE ?)';
        $search = "%{$transaction}%";
        $params[] = $search;
        $params[] = $search;
    }

    $sql .= ' ORDER BY c.created_at DESC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatistics(): array
{
    $pdo = getDBConnection();
    $stats = [
        'total' => 0,
        'nouvelle' => 0,
        'en_attente' => 0,
        'attribuee' => 0,
        'acceptee' => 0,
        'en_cours' => 0,
        'livree' => 0,
        'annulee' => 0,
    ];

    try {
        $stats['total'] = (int) $pdo->query('SELECT COUNT(*) FROM commandes')->fetchColumn();
        $byStatus = $pdo->query('SELECT statut, COUNT(*) AS total FROM commandes GROUP BY statut');
        while ($row = $byStatus->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['statut'] ?? '';
            if ($key !== '' && isset($stats[$key])) {
                $stats[$key] = (int) $row['total'];
            }
        }
    } catch (PDOException $e) {
        // ignore
    }

    return $stats;
}

function renderStatsContent(array $stats): string
{
    ob_start();
    ?>
        <div class="stat-card">
            <h3>Total</h3>
            <strong><?= (int) $stats['total'] ?></strong>
        </div>
        <div class="stat-card">
            <h3>Nouvelles</h3>
            <strong><?= (int) $stats['nouvelle'] ?></strong>
        </div>
        <div class="stat-card">
            <h3>En attente</h3>
            <strong><?= (int) ($stats['en_attente'] ?? 0) ?></strong>
        </div>
        <div class="stat-card">
            <h3>Attribuées</h3>
            <strong><?= (int) ($stats['attribuee'] ?? 0) ?></strong>
        </div>
        <div class="stat-card">
            <h3>Acceptées</h3>
            <strong><?= (int) ($stats['acceptee'] ?? 0) ?></strong>
        </div>
        <div class="stat-card">
            <h3>En cours</h3>
            <strong><?= (int) $stats['en_cours'] ?></strong>
        </div>
        <div class="stat-card">
            <h3>Livrées</h3>
            <strong><?= (int) $stats['livree'] ?></strong>
        </div>
        <div class="stat-card">
            <h3>Annulées</h3>
            <strong><?= (int) $stats['annulee'] ?></strong>
        </div>
    <?php
    return ob_get_clean();
}

function renderCoursiersStatusContent(array $coursiers): string
{
    ob_start();
    ?>
    
    <div class="suzosky-coursiers-panel" data-connectivity-panel>
        <div class="panel-header">
            <div class="panel-title">
                <i class="fas fa-motorcycle"></i>
                <span>Coursiers Connectés</span>
                <span class="badge-total" data-connected-total>--</span>
                
                <!-- Indicateur FCM Global -->
                <div class="fcm-status-indicator neutral" data-fcm-indicator title="FCM : synchronisation en cours">
                    <i class="fas fa-bell"></i>
                    <span data-fcm-rate>--%</span>
                </div>
            </div>
            <div class="lights-summary">
                <div class="light-indicator green" title="Disponibles" data-count-green>0</div>
                <div class="light-indicator orange" title="Limités" data-count-orange>0</div>
                <div class="light-indicator red" title="Indisponibles" data-count-red>0</div>
            </div>
        </div>
        
        <div class="coursiers-scrollable" data-coursiers-list>
            <div class="empty-state" data-empty-state>
                <i class="fas fa-spinner fa-spin"></i>
                <div>Chargement des coursiers...</div>
            </div>
        </div>
    </div>

    <!-- Modal détails coursier -->
    <div id="coursierModal" class="suzosky-modal" onclick="closeCoursierModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h5 id="modalCoursierName">Détails Coursier</h5>
                <button onclick="closeCoursierModal()" class="close-btn">&times;</button>
            </div>
            <div id="modalCoursierContent" class="modal-body">
                <!-- Contenu dynamique -->
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

function renderCommandesContent(array $commandes): string
{
    ob_start();
    ?>
        <?php if (count($commandes) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-inbox" style="font-size: 36px; margin-bottom: 10px;"></i>
                <div>Aucune commande trouvée avec ces filtres.</div>
            </div>
        <?php endif; ?>

        <?php foreach ($commandes as $commande): ?>
            <?php
            $statut = $commande['statut'] ?? 'nouvelle';
            $statusClass = 'status-' . preg_replace('/[^a-z0-9_]/i', '_', $statut);
            $hasCoursier = !empty($commande['coursier_id']);
            $isActive = in_array($statut, ['attribuee', 'acceptee', 'en_cours'], true);
            $isCompleted = in_array($statut, ['livree', 'annulee'], true);

            // Détermination du statut pour affichage simple
            $infoLabel = '';
            $infoClass = '';
            $infoIcon = '';
            
            if (!$hasCoursier) {
                $infoLabel = 'Pas de coursier';
                $infoClass = 'status-warning';
                $infoIcon = 'exclamation-circle';
            } elseif ($isActive) {
                $infoLabel = 'En cours';
                $infoClass = 'status-active';
                $infoIcon = 'spinner fa-spin';
            } elseif ($isCompleted) {
                $infoLabel = 'Terminée';
                $infoClass = 'status-completed';
                $infoIcon = 'check-circle';
            } else {
                $infoLabel = 'En attente';
                $infoClass = 'status-pending';
                $infoIcon = 'clock';
            }

            $rawStatus = $commande['coursier_status'] ?? '';
            if ($rawStatus === '' && isset($commande['coursier_is_online'])) {
                $rawStatus = ((int) $commande['coursier_is_online']) === 1 ? 'en_ligne' : 'hors_ligne';
            }
            $rawStatus = $rawStatus ?: 'inconnu';
            $courierStatusClass = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $rawStatus));
            ?>

            <div class="commande-card">
                <div class="commande-header">
                    <div class="commande-number">#<?= htmlspecialchars($commande['code_commande'] ?? $commande['order_number'] ?? 'N/A', ENT_QUOTES) ?></div>
                    <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($statut, ENT_QUOTES) ?></span>
                </div>

                <div class="commande-content">
                    <div class="commande-section">
                        <h4><i class="fas fa-route"></i> Itinéraire</h4>
                        <p><strong>Départ :</strong> <?= htmlspecialchars($commande['adresse_depart'] ?? $commande['adresse_retrait'] ?? 'N/A', ENT_QUOTES) ?></p>
                        <p><strong>Arrivée :</strong> <?= htmlspecialchars($commande['adresse_arrivee'] ?? $commande['adresse_livraison'] ?? 'N/A', ENT_QUOTES) ?></p>
                        <p><strong>Prix estimé :</strong> <?= number_format($commande['prix_estime'] ?? 0, 0, ',', ' ') ?> FCFA</p>
                    </div>
                    <div class="commande-section">
                        <h4><i class="fas fa-user"></i> Client</h4>
                        <p><strong>Nom :</strong> <?= htmlspecialchars($commande['client_nom'] ?? 'N/A', ENT_QUOTES) ?></p>
                        <p><strong>Téléphone :</strong> <?= htmlspecialchars($commande['client_telephone'] ?? 'N/A', ENT_QUOTES) ?></p>
                        <?php if (!empty($commande['created_at'])): ?>
                            <p><strong>📅 Créée :</strong> <?= date('d/m/Y à H:i:s', strtotime($commande['created_at'])) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($isCompleted && !empty($commande['updated_at'])): ?>
                            <?php
                            $debut = strtotime($commande['created_at']);
                            $fin = strtotime($commande['updated_at']);
                            $duree_secondes = $fin - $debut;
                            $duree_minutes = floor($duree_secondes / 60);
                            $duree_heures = floor($duree_minutes / 60);
                            $duree_min_restant = $duree_minutes % 60;
                            $duree_formatted = '';
                            if ($duree_heures > 0) {
                                $duree_formatted = "{$duree_heures}h {$duree_min_restant}min";
                            } else {
                                $duree_formatted = "{$duree_minutes} min";
                            }
                            ?>
                            <p><strong>⏱️ Durée :</strong> <?= $duree_formatted ?></p>
                            <p><strong>✅ Terminée :</strong> <?= date('d/m/Y à H:i:s', $fin) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="commande-section">
                        <h4><i class="fas fa-motorcycle"></i> Coursier</h4>
                        <?php if ($hasCoursier && !empty($commande['coursier_nom'])): ?>
                            <div class="coursier-info">
                                <strong><?= htmlspecialchars(trim(($commande['coursier_prenoms'] ?? '') . ' ' . ($commande['coursier_nom'] ?? '')), ENT_QUOTES) ?></strong>
                                <div class="connexion-status <?= htmlspecialchars($courierStatusClass, ENT_QUOTES) ?>">
                                    <?= htmlspecialchars($rawStatus, ENT_QUOTES) ?>
                                </div>
                                <p><i class="fas fa-phone"></i> <?= htmlspecialchars($commande['coursier_telephone'] ?? 'N/A', ENT_QUOTES) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="coursier-info">
                                <strong style="color: var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Non assigné</strong>
                                <p>Aucun coursier n'est encore affecté.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="commande-actions">
                    <?php if ($hasCoursier && $isActive): ?>
                        <!-- Bouton Tracking Live pour commandes en cours -->
                        <button class="btn-track live" onclick="openTrackingPopup(<?= (int) $commande['id'] ?>, 'live')" title="Suivi en temps réel">
                            <i class="fas fa-satellite"></i> Tracking Live
                        </button>
                    <?php elseif ($hasCoursier && $isCompleted): ?>
                        <!-- Bouton Historique pour commandes terminées -->
                        <button class="btn-track history" onclick="openTrackingPopup(<?= (int) $commande['id'] ?>, 'history')" title="Voir l'historique de la course">
                            <i class="fas fa-history"></i> Historique
                        </button>
                    <?php else: ?>
                        <!-- Badge info pour commandes sans coursier -->
                        <div class="info-badge status-warning">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Pas de coursier</span>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isCompleted && $hasCoursier): ?>
                        <form method="POST" onsubmit="return confirm('⚠️ Êtes-vous sûr de vouloir terminer cette commande maintenant ?\n\nCette action est irréversible.');" style="display: inline-block;">
                            <input type="hidden" name="action" value="terminate_order">
                            <input type="hidden" name="commande_id" value="<?= (int) $commande['id'] ?>">
                            <button class="btn-terminate" type="submit" title="Marquer comme terminée">
                                <i class="fas fa-check-double"></i> Terminer la course
                            </button>
                        </form>
                    <?php elseif ($isCompleted): ?>
                        <div class="badge-completed">
                            <i class="fas fa-check-circle"></i> <strong>Course terminée</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php
    return ob_get_clean();
}

$commandes = getCommandesWithFilters($filtreStatut, $filtreCoursier, $filtreDate, $filtrePriorite, $filtreTransaction);
$coursiers = getAllCouriers();
$stats = getStatistics();

SystemSync::record('admin_commandes', 'ok', [
    'filters' => [
        'statut' => $filtreStatut,
        'coursier' => $filtreCoursier,
        'date' => $filtreDate,
        'priorite' => $filtrePriorite,
        'transaction' => $filtreTransaction,
    ],
    'commandes_count' => count($commandes),
    'stats' => $stats,
]);

$statsHtml = renderStatsContent($stats);
$coursiersHtml = renderCoursiersStatusContent($coursiers);
$commandesHtml = renderCommandesContent($commandes);

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => $statsHtml,
        'coursiers' => $coursiersHtml,
        'commandes' => $commandesHtml,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = $_SESSION['admin_message'] ?? '';
$messageType = $_SESSION['admin_message_type'] ?? '';
unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);

$baseScript = $_SERVER['PHP_SELF'] ?? 'admin.php';
$formAction = htmlspecialchars($baseScript, ENT_QUOTES);
$resetUrl = htmlspecialchars($baseScript . '?section=commandes', ENT_QUOTES);
$ajaxEndpoint = htmlspecialchars(basename(__FILE__), ENT_QUOTES);
?>

<!-- ✅ SCRIPTS SIMPLIFIÉS : Focus sur les fonctionnalités essentielles -->
<script>
// Gestion du modal coursier (assignation)
window.closeCoursierModal = function(event) {
    const modal = document.getElementById('coursierModal');
    if (modal && (!event || event.target === modal)) {
        modal.style.display = 'none';
    }
};

console.log('✅ Scripts admin simplifiés chargés');
</script>

<style>
:root {
    /* COULEURS OFFICIELLES SUZOSKY */
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --warning-color: #FFC107;
    --danger-color: #E94560;
    --info-color: #3B82F6;
    
    /* GLASS MORPHISM SUZOSKY */
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    
    /* DÉGRADÉS SUZOSKY */
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
    --gradient-deep: linear-gradient(135deg, #0F3460 0%, #1A1A2E 100%);
    
    --text-muted: rgba(255,255,255,0.7);
    --sidebar-width: 300px;
}

body.admin-commandes-page {
    background: var(--gradient-dark);
    color: #f9fafb;
    min-height: 100vh;
    padding: 20px;
    font-family: 'Montserrat', sans-serif;
}

.admin-commandes-wrapper {
    max-width: 1200px;
    margin: 0 auto 80px;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.sync-status-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    padding: 18px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
}

.sync-status-card .status-indicator {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: var(--success-color);
    box-shadow: 0 0 18px rgba(39, 174, 96, 0.55);
    transition: background 0.25s ease, box-shadow 0.25s ease;
}

.sync-status-card.degraded .status-indicator {
    background: var(--warning-color);
    box-shadow: 0 0 18px rgba(255, 193, 7, 0.5);
}

.sync-status-card.critical .status-indicator {
    background: var(--danger-color);
    box-shadow: 0 0 18px rgba(233, 69, 96, 0.6);
}

.sync-status-card h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
}

.sync-status-card .sync-metadata {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: var(--text-muted);
    font-size: 13px;
}

.sync-status-card .sync-metadata strong {
    color: #f8fafc;
}

.admin-header h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-shadow: 0 0 20px rgba(212, 168, 83, 0.3);
}

.message-banner {
    margin-bottom: 20px;
    padding: 16px 20px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
}

.message-banner.success {
    background: rgba(39, 174, 96, 0.15);
    border: 1px solid rgba(39, 174, 96, 0.35);
    color: var(--success-color);
    backdrop-filter: blur(10px);
}

.message-banner.error {
    background: rgba(233, 69, 96, 0.15);
    border: 1px solid rgba(233, 69, 96, 0.35);
    color: var(--danger-color);
    backdrop-filter: blur(10px);
}

.filters-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    backdrop-filter: blur(20px);
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
}

.filters-card form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.filters-card label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 6px;
}

.filters-card input,
.filters-card select {
    width: 100%;
    border-radius: 12px;
    border: 1px solid var(--glass-border);
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    padding: 10px 14px;
    font-size: 14px;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.filters-card input:focus,
.filters-card select:focus {
    border-color: var(--primary-gold);
    outline: none;
    box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.2);
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-2px);
}

.filters-card form.is-loading {
    opacity: 0.65;
    pointer-events: none;
}

.filters-actions {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: flex-end;
    grid-column: 1 / -1;
}

.button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.button-primary {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    box-shadow: 0 8px 25px rgba(212, 168, 83, 0.3);
    font-weight: 700;
}

.button-secondary {
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid var(--primary-gold);
    color: var(--primary-gold);
    backdrop-filter: blur(10px);
}

.button:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
    transition: opacity 0.2s ease;
}

#statsContainer.is-dimmed {
    opacity: 0.45;
}

.stat-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 16px 18px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(20px);
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    transition: all 0.3s ease;
}

.stat-card h3 {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    margin: 0 0 6px 0;
}

.stat-card strong {
    font-size: 26px;
    font-weight: 800;
}

.commandes-list {
    display: grid;
    gap: 18px;
}

.commande-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    padding: 22px 24px;
    display: flex;
    flex-direction: column;
    gap: 18px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(20px);
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    transition: all 0.3s ease;
}

.commande-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    border: 1px solid transparent;
    pointer-events: none;
    transition: border-color 0.2s ease;
}

.commande-card:hover::after {
    border-color: rgba(212,168,83,0.25);
}

.commande-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.commande-number {
    font-weight: 700;
    font-size: 18px;
}

.status-pill {
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status-nouvelle { background: rgba(59, 130, 246, 0.15); color: var(--info-color); border: 1px solid rgba(59, 130, 246, 0.3); }
.status-assignee { background: rgba(255, 193, 7, 0.15); color: var(--warning-color); border: 1px solid rgba(255, 193, 7, 0.3); }
.status-en_cours { background: rgba(39, 174, 96, 0.15); color: var(--success-color); border: 1px solid rgba(39, 174, 96, 0.3); }
.status-livree { background: rgba(39, 174, 96, 0.2); color: var(--success-color); border: 1px solid rgba(39, 174, 96, 0.4); }
.status-annulee { background: rgba(233, 69, 96, 0.15); color: var(--danger-color); border: 1px solid rgba(233, 69, 96, 0.3); }
.status-pending { background: rgba(15, 52, 96, 0.15); color: var(--accent-blue); border: 1px solid rgba(15, 52, 96, 0.3); }

.commande-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
}

.commande-section h4 {
    margin: 0 0 8px 0;
    font-size: 15px;
    color: var(--primary-gold);
    font-weight: 600;
    text-shadow: 0 0 10px rgba(212, 168, 83, 0.3);
}

.commande-section p {
    margin: 4px 0;
    font-size: 14px;
    color: var(--text-muted);
}

.coursier-info {
    border-radius: 14px;
    padding: 14px 16px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.05);
}

.coursier-info strong {
    display: block;
    margin-bottom: 6px;
    color: #fff;
}

.connexion-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 999px;
}

.connexion-status.en_ligne { background: rgba(39, 174, 96, 0.2); color: var(--success-color); border: 1px solid rgba(39, 174, 96, 0.3); }
.connexion-status.hors_ligne { background: rgba(233, 69, 96, 0.18); color: var(--danger-color); border: 1px solid rgba(233, 69, 96, 0.3); }
.connexion-status.inconnu { background: rgba(148, 163, 184, 0.18); color: rgba(255, 255, 255, 0.6); border: 1px solid rgba(148, 163, 184, 0.3); }

/* Système de feux pour coursiers */
.coursier-status-light {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 8px 0;
}

.status-light {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    position: relative;
    box-shadow: 0 0 8px rgba(0, 0, 0, 0.3);
    transition: all 0.3s ease;
}

.status-light.green {
    background: var(--success-color);
    box-shadow: 0 0 12px rgba(39, 174, 96, 0.6);
    animation: pulse-green 2s infinite;
}

.status-light.orange {
    background: var(--warning-color);
    box-shadow: 0 0 12px rgba(255, 193, 7, 0.6);
    animation: pulse-orange 2s infinite;
}

.status-light.red {
    background: var(--danger-color);
    box-shadow: 0 0 12px rgba(233, 69, 96, 0.6);
    animation: pulse-red 2s infinite;
}

.status-light::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 6px;
    height: 6px;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    transform: translate(-50%, -50%);
}

.status-light-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: capitalize;
}

.status-light-label.green { color: var(--success-color); }
.status-light-label.orange { color: var(--warning-color); }
.status-light-label.red { color: var(--danger-color); }

.coursier-token-info {
    font-size: 0.75rem;
    color: rgba(148, 163, 184, 0.7);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.token-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.token-dot.active { background: var(--success-color); }
.token-dot.inactive { background: rgba(255, 255, 255, 0.3); }

@keyframes pulse-green {
    0% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(39, 174, 96, 0); }
    100% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0); }
}

@keyframes pulse-orange {
    0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(255, 193, 7, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
}

@keyframes pulse-red {
    0% { box-shadow: 0 0 0 0 rgba(233, 69, 96, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(233, 69, 96, 0); }
    100% { box-shadow: 0 0 0 0 rgba(233, 69, 96, 0); }
}

/* Panel Coursiers Style Suzosky */
.suzosky-coursiers-panel {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid var(--glass-border);
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    margin: 15px 0;
    max-width: 100%;
    overflow: hidden;
    transition: all 0.3s ease;
}

.panel-header {
    background: var(--gradient-deep);
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--primary-gold);
    position: relative;
}

.panel-header::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--gradient-gold);
}

.panel-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #e2e8f0;
    font-weight: 600;
    font-size: 0.95em;
}

.badge-total {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.lights-summary {
    display: flex;
    gap: 8px;
}

/* Indicateur FCM Global */
.fcm-status-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: bold;
    margin-left: 8px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.fcm-status-indicator.excellent {
    background: linear-gradient(135deg, var(--success-color), #2dd981);
    color: white;
    box-shadow: 0 0 6px rgba(39, 174, 96, 0.4);
}

.fcm-status-indicator.correct {
    background: linear-gradient(135deg, var(--warning-color), #ffd54f);
    color: #1A1A2E;
    box-shadow: 0 0 6px rgba(255, 193, 7, 0.4);
}

.fcm-status-indicator.critique {
    background: linear-gradient(135deg, var(--danger-color), #ff6b93);
    color: white;
    box-shadow: 0 0 6px rgba(233, 69, 96, 0.4);
}

.fcm-status-indicator.erreur {
    background: linear-gradient(135deg, #666, #999);
    color: white;
}

.fcm-status-indicator.neutral {
    background: rgba(255, 255, 255, 0.12);
    color: var(--text-muted);
}

.light-indicator {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75em;
    font-weight: bold;
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    position: relative;
}

.light-indicator.green {
    background: linear-gradient(135deg, var(--success-color), #2dd981);
    box-shadow: 0 0 8px rgba(39, 174, 96, 0.5);
}

.light-indicator.orange {
    background: linear-gradient(135deg, var(--warning-color), #ffd54f);
    box-shadow: 0 0 8px rgba(255, 193, 7, 0.5);
}

.light-indicator.red {
    background: linear-gradient(135deg, var(--danger-color), #ff6b93);
    box-shadow: 0 0 8px rgba(233, 69, 96, 0.5);
}

.coursiers-scrollable {
    max-height: 280px;
    overflow-y: auto;
    background: rgba(26, 26, 46, 0.8);
    backdrop-filter: blur(10px);
}

.coursiers-scrollable::-webkit-scrollbar {
    width: 6px;
}

.coursiers-scrollable::-webkit-scrollbar-track {
    background: var(--secondary-blue);
}

.coursiers-scrollable::-webkit-scrollbar-thumb {
    background: var(--primary-gold);
    border-radius: 3px;
}

.coursiers-scrollable::-webkit-scrollbar-thumb:hover {
    background: #E8C468;
}

.coursier-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
}

.coursier-item:hover {
    background: rgba(212, 168, 83, 0.1);
    transform: translateX(5px);
    border-left: 3px solid var(--primary-gold);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 12px;
    position: relative;
}

.status-dot.green {
    background: #48bb78;
    box-shadow: 0 0 6px rgba(72, 187, 120, 0.7);
    animation: pulse-green 2s infinite;
}

.status-dot.orange {
    background: #ed8936;
    box-shadow: 0 0 6px rgba(237, 137, 54, 0.7);
    animation: pulse-orange 2s infinite;
}

.status-dot.red {
    background: #e53e3e;
    box-shadow: 0 0 6px rgba(229, 62, 62, 0.7);
    animation: pulse-red 2s infinite;
}

.coursier-info {
    flex: 1;
    min-width: 0;
}

.coursier-name {
    color: #e2e8f0;
    font-weight: 500;
    font-size: 0.9em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.coursier-status {
    color: #a0aec0;
    font-size: 0.75em;
    margin-top: 2px;
}

.coursier-meta {
    color: #718096;
    font-size: 0.7em;
    margin-top: 2px;
}

.coursier-badges {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #68d391;
}

.app-badge {
    font-size: 0.8em;
}

.arrow-icon {
    color: #4a5568;
    font-size: 0.7em;
    transition: transform 0.2s ease;
}

.coursier-item:hover .arrow-icon {
    transform: translateX(2px);
    color: #68d391;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #718096;
}

.empty-state i {
    font-size: 2.5em;
    margin-bottom: 10px;
    opacity: 0.5;
}

/* Modal Suzosky Style */
.suzosky-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: linear-gradient(145deg, #1a202c, #2d3748);
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    border: 1px solid #4a5568;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from { transform: translateY(-50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    background: linear-gradient(90deg, #2b6cb0, #3182ce);
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #4299e1;
}

.modal-header h5 {
    color: #e2e8f0;
    margin: 0;
    font-weight: 600;
}

.close-btn {
    background: none;
    border: none;
    color: #e2e8f0;
    font-size: 1.5em;
    cursor: pointer;
    transition: color 0.2s ease;
}

.close-btn:hover {
    color: #fc8181;
}

.modal-body {
    padding: 20px;
    color: #e2e8f0;
}

.coursier-details {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.detail-section h6 {
    color: #4299e1;
    margin-bottom: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.token-status, .last-activity {
    font-size: 0.85em;
    color: #a0aec0;
    margin-bottom: 5px;
}

.token-status.offline {
    color: #fc8181;
}

.commandes-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: rgba(66, 153, 225, 0.1);
    border-radius: 6px;
    border: 1px solid #4299e1;
}

.stat-number {
    display: block;
    font-size: 1.3em;
    font-weight: bold;
    color: #4299e1;
}

.stat-label {
    font-size: 0.8em;
    color: #a0aec0;
}

.performance-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.perf-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    background: rgba(74, 85, 104, 0.3);
    border-radius: 4px;
}

.perf-item strong {
    color: #68d391;
}

.commande-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

/* ✅ BOUTONS DE TRACKING */
.btn-track {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-track i {
    font-size: 16px;
}

.btn-track.live {
    background: linear-gradient(135deg, #2563eb, #60a5fa);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-track.live:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.5);
}

.btn-track.history {
    background: rgba(37, 99, 235, 0.18);
    color: #93c5fd;
    border: 1px solid rgba(37, 99, 235, 0.3);
}

.btn-track.history:hover {
    background: rgba(37, 99, 235, 0.25);
    transform: translateY(-2px);
}

/* ✅ BADGES D'INFORMATION SIMPLIFIÉS */
.info-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
}

.info-badge i {
    font-size: 16px;
}

.info-badge.status-warning {
    background: rgba(234, 179, 8, 0.15);
    color: #facc15;
    border: 1px solid rgba(234, 179, 8, 0.3);
}

.info-badge.status-active {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.info-badge.status-completed {
    background: rgba(37, 99, 235, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(37, 99, 235, 0.3);
}

.info-badge.status-pending {
    background: rgba(148, 163, 184, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.3);
}

/* ✅ BOUTON TERMINER AMÉLIORÉ */
.btn-terminate {
    border: 2px solid rgba(34, 197, 94, 0.5);
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.15), rgba(34, 197, 94, 0.05));
    color: #4ade80;
    padding: 11px 20px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-terminate i {
    font-size: 16px;
}

.btn-terminate:hover {
    background: linear-gradient(135deg, rgba(34, 197, 94, 0.25), rgba(34, 197, 94, 0.15));
    border-color: #4ade80;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(34, 197, 94, 0.3);
}

.btn-terminate:active {
    transform: translateY(0);
}

/* ✅ BADGE TERMINÉ */
.badge-completed {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    background: rgba(37, 99, 235, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(37, 99, 235, 0.3);
    font-weight: 600;
    font-size: 14px;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
    border-radius: 18px;
    background: rgba(17, 24, 39, 0.6);
    border: 1px dashed rgba(148,163,184,0.3);
    color: rgba(148,163,184,0.7);
}

.loading-state {
    padding: 50px 20px;
    text-align: center;
    color: rgba(226,232,240,0.75);
    font-weight: 600;
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
    justify-content: center;
}

.loading-state i {
    font-size: 24px;
    color: var(--accent-gold);
}

.tracking-modal {
    position: fixed;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: flex-end;
    background: rgba(5, 10, 25, 0.75);
    backdrop-filter: blur(8px);
    z-index: 1200;
    padding: 24px;
    padding-left: calc(var(--sidebar-width) + 24px);
}

.tracking-modal.visible { display: flex; }

.modal-card {
    width: min(1100px, calc(100vw - var(--sidebar-width) - 48px));
    height: min(90vh, 760px);
    background: #0f172a;
    border-radius: 22px;
    box-shadow: 0 40px 90px rgba(0,0,0,0.45);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid rgba(212,168,83,0.25);
}

.modal-card header {
    background: linear-gradient(135deg, var(--accent-gold), #fde6a4);
    color: #1f2937;
    padding: 22px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-card header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
}

.modal-card header small {
    display: block;
    font-size: 13px;
    opacity: 0.7;
    font-weight: 600;
}

.modal-close-btn {
    border: none;
    background: rgba(15,23,42,0.12);
    color: inherit;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    cursor: pointer;
}

.modal-tabs {
    display: flex;
    gap: 10px;
    padding: 14px 24px;
    background: rgba(15,23,42,0.92);
    border-bottom: 1px solid rgba(148,163,184,0.18);
}

.modal-tabs button {
    flex: none;
    padding: 10px 16px;
    border-radius: 12px;
    border: 1px solid transparent;
    background: rgba(148,163,184,0.12);
    color: rgba(226,232,240,0.8);
    font-weight: 600;
    cursor: pointer;
}

.modal-tabs button.active {
    background: linear-gradient(135deg, #2563eb, #60a5fa);
    color: #0b1120;
}

.modal-body {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.modal-tab {
    position: absolute;
    inset: 0;
    overflow-y: auto;
    padding: 24px 26px;
    display: none;
}

.modal-tab.active { display: block; }

.overview-grid {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.overview-card {
    background: rgba(148,163,184,0.07);
    border: 1px solid rgba(148,163,184,0.14);
    border-radius: 14px;
    padding: 18px;
}

.overview-card h5 {
    margin: 0 0 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-size: 12px;
    color: rgba(226,232,240,0.8);
}

.map-container {
    position: relative;
    width: 100%;
    height: 100%;
    min-height: 400px;
}

#trackingMap {
    width: 100%;
    height: 100%;
    border-radius: 16px;
}

.timeline {
    border-left: 2px solid rgba(148,163,184,0.25);
    padding-left: 16px;
}

.timeline-item {
    margin-bottom: 18px;
    position: relative;
}

.timeline-item::before {
    content: '';
    position: absolute;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: #2563eb;
    left: -21px;
    top: 4px;
}

.timeline-item.completed::before {
    background: #22c55e;
}

.timeline-item.active::before {
    background: #facc15;
    animation: pulse 1.4s infinite;
}

.timeline-item.pending::before {
    background: rgba(148,163,184,0.35);
}

.timeline-item.cancelled::before {
    background: #f87171;
}

.timeline-item .timeline-content {
    background: rgba(15,23,42,0.6);
    border: 1px solid rgba(148,163,184,0.12);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.timeline-item .timeline-icon {
    font-size: 18px;
    margin-right: 8px;
}

.timeline-item .timeline-content strong {
    display: flex;
    align-items: center;
    font-size: 14px;
    letter-spacing: 0.01em;
    color: rgba(226,232,240,0.95);
}

.timeline-item .timeline-content p {
    margin: 0;
    color: rgba(226,232,240,0.7);
    font-size: 13px;
}

.timeline-item .timeline-meta {
    font-size: 12px;
    color: rgba(148,163,184,0.65);
}

.timeline-item.cancelled .timeline-content {
    border-color: rgba(248,113,113,0.35);
}

.timeline-item.cancelled .timeline-meta {
    color: rgba(248,113,113,0.75);
}

.sync-row {
    border-top: 1px solid rgba(148,163,184,0.14);
    padding: 14px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(15,23,42,0.9);
}

.sync-indicator {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: rgba(226,232,240,0.75);
}

.sync-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #22d3ee;
    animation: pulse 1.8s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.3; transform: scale(0.9); }
    50% { opacity: 1; transform: scale(1.1); }
}

/* ✅ MODAL DE TRACKING */
.tracking-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

.tracking-modal-overlay.active {
    display: flex;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.tracking-modal-card {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border-radius: 20px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
    max-width: 900px;
    width: 100%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.3s ease;
    border: 1px solid rgba(148, 163, 184, 0.2);
}

@keyframes slideUp {
    from { 
        opacity: 0;
        transform: translateY(30px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}

.tracking-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 28px;
    border-bottom: 1px solid rgba(148, 163, 184, 0.2);
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.1), rgba(30, 41, 59, 0.3));
}

.tracking-modal-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #f9fafb;
}

.tracking-modal-close {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.6);
    font-size: 24px;
    cursor: pointer;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.tracking-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.tracking-modal-body {
    padding: 28px;
    overflow-y: auto;
    flex: 1;
}

.tracking-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.tracking-info-card {
    background: rgba(30, 41, 59, 0.6);
    border-radius: 14px;
    padding: 18px;
    border: 1px solid rgba(148, 163, 184, 0.15);
}

.tracking-info-card h3 {
    margin: 0 0 12px 0;
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(226, 232, 240, 0.7);
    display: flex;
    align-items: center;
    gap: 8px;
}

.tracking-info-card h3 i {
    color: #60a5fa;
}

.tracking-info-card p {
    margin: 8px 0;
    font-size: 14px;
    color: rgba(226, 232, 240, 0.9);
    line-height: 1.6;
}

.tracking-info-card strong {
    color: #f9fafb;
    font-weight: 600;
}

.tracking-map-container {
    margin-top: 24px;
    border-radius: 14px;
    overflow: hidden;
    height: 350px;
    background: rgba(30, 41, 59, 0.6);
    border: 1px solid rgba(148, 163, 184, 0.15);
}

#trackingMap {
    width: 100%;
    height: 100%;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
}

.status-badge.online {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-badge.offline {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

@media (max-width: 780px) {
    .admin-header { flex-direction: column; gap: 12px; align-items: flex-start; }
    .filters-actions { justify-content: flex-start; }
    .commande-actions { flex-direction: column; align-items: stretch; }
    
    .tracking-modal-card {
        max-width: 95vw;
        max-height: 90vh;
    }
    
    .tracking-info-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 1080px) {
    .tracking-modal {
        padding: 16px;
        padding-left: 16px;
        justify-content: center;
    }

    .modal-card {
        width: min(1000px, 96vw);
    }
}
</style>

<div class="admin-commandes-wrapper">
    <div class="admin-header">
        <h1>Gestion avancée des commandes</h1>
        <div style="color: var(--text-muted); font-size: 14px;">
            Dernière mise à jour : <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="message-banner <?= htmlspecialchars($messageType, ENT_QUOTES) ?>">
            <i class="fas fa-info-circle"></i>
            <span><?= htmlspecialchars($message, ENT_QUOTES) ?></span>
        </div>
    <?php endif; ?>

    <div id="syncStatusCard" class="sync-status-card">
        <span class="status-indicator"></span>
        <div>
            <h3>Synchronisation du réseau Suzosky</h3>
            <div id="syncStatusContent" class="sync-metadata">
                <span>Chargement des métriques...</span>
            </div>
        </div>
    </div>

    <div class="filters-card">
    <form method="GET" action="<?= $formAction ?>" id="commandesFilterForm" data-ajax="1" data-ajax-endpoint="<?= $ajaxEndpoint ?>">
            <input type="hidden" name="section" value="commandes">
            <div>
                <label for="statut">Statut</label>
                <select id="statut" name="statut">
                    <option value="">Tous</option>
                    <?php foreach (['nouvelle' => 'Nouvelles', 'en_attente' => 'En attente', 'attribuee' => 'Attribuées', 'acceptee' => 'Acceptées', 'en_cours' => 'En cours', 'livree' => 'Livrées', 'annulee' => 'Annulées'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $filtreStatut === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="coursier">Coursier</label>
                <select id="coursier" name="coursier">
                    <option value="">Tous</option>
                    <?php foreach ($coursiers as $coursier): ?>
                        <option value="<?= htmlspecialchars($coursier['id'], ENT_QUOTES) ?>" <?= $filtreCoursier === (string) $coursier['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim(($coursier['prenoms'] ?? '') . ' ' . ($coursier['nom'] ?? '')), ENT_QUOTES) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="date">Date</label>
                <input type="date" id="date" name="date" value="<?= htmlspecialchars($filtreDate, ENT_QUOTES) ?>">
            </div>
            <div>
                <label for="priorite">Priorité</label>
                <select id="priorite" name="priorite">
                    <option value="">Toutes</option>
                    <?php foreach (['basse' => 'Basse', 'normale' => 'Normale', 'haute' => 'Haute', 'urgente' => 'Urgente'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $filtrePriorite === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="transaction">N° commande</label>
                <input type="text" id="transaction" name="transaction" placeholder="CM20250001" value="<?= htmlspecialchars($filtreTransaction, ENT_QUOTES) ?>">
            </div>
            <div class="filters-actions">
                <button type="submit" class="button button-primary"><i class="fas fa-filter"></i> Filtrer</button>
                <a class="button button-secondary" href="<?= $resetUrl ?>">
                    <i class="fas fa-undo"></i> Réinitialiser
                </a>
            </div>
        </form>
    </div>

    <div id="statsContainer" class="stats-grid">
        <?= $statsHtml ?>
    </div>

    <div id="coursiersContainer" class="coursiers-container">
        <?= $coursiersHtml ?>
    </div>

    <div id="commandesList" class="commandes-list">
        <?= $commandesHtml ?>
    </div>
</div>

<!-- ✅ MODAL DE TRACKING SIMPLE ET FONCTIONNEL -->
<div id="trackingModal" class="tracking-modal-overlay" onclick="closeTrackingModal(event)">
    <div class="tracking-modal-card" onclick="event.stopPropagation()">
        <div class="tracking-modal-header">
            <div>
                <h2 id="trackingModalTitle">Suivi de commande</h2>
                <p id="trackingModalSubtitle" style="color: rgba(255,255,255,0.7); font-size: 14px; margin: 5px 0 0 0;"></p>
            </div>
            <button class="tracking-modal-close" onclick="closeTrackingModal()" aria-label="Fermer">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="tracking-modal-body" id="trackingModalContent">
            <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 15px;"></i>
                <p>Chargement des données...</p>
            </div>
        </div>
    </div>
</div>

<script>
// ✅ SYSTÈME SIMPLIFIÉ - Focus sur synchronisation et actions essentielles
console.log('✅ Admin commandes - Initialisation système simplifié');

// ========== FONCTIONS MODAL TRACKING ==========
function openTrackingPopup(commandeId, mode) {
    console.log('🔍 Ouverture modal tracking:', { commandeId, mode });
    
    const modal = document.getElementById('trackingModal');
    const modalTitle = document.getElementById('trackingModalTitle');
    const modalSubtitle = document.getElementById('trackingModalSubtitle');
    const modalContent = document.getElementById('trackingModalContent');
    
    if (!modal || !modalContent) {
        console.error('❌ Modal introuvable !');
        return;
    }
    
    // Afficher le modal
    modal.classList.add('active');
    
    // Mettre à jour le titre
    modalTitle.textContent = mode === 'live' ? '📡 Tracking Live' : '📊 Historique de course';
    modalSubtitle.textContent = `Commande #${commandeId}`;
    
    // Loader
    modalContent.innerHTML = `
        <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
            <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 15px;"></i>
            <p>Chargement des données de tracking...</p>
        </div>
    `;
    
    // Charger les données via API
    fetch(`api/tracking_simple.php?commande_id=${commandeId}&mode=${mode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderTrackingData(data, mode);
            } else {
                modalContent.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #ef4444;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 15px;"></i>
                        <p>${data.error || 'Erreur lors du chargement des données'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('❌ Erreur tracking:', error);
            modalContent.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #ef4444;">
                    <i class="fas fa-wifi" style="font-size: 32px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>Erreur de connexion au serveur</p>
                </div>
            `;
        });
}

function closeTrackingModal(event) {
    const modal = document.getElementById('trackingModal');
    if (event && event.target !== modal && event.type !== 'click') return;
    
    if (modal) {
        modal.classList.remove('active');
    }
}

function renderTrackingData(data, mode) {
    const modalContent = document.getElementById('trackingModalContent');
    const commande = data.commande || {};
    const coursier = data.coursier || {};
    const duree = data.duree || {};
    
    let html = '<div class="tracking-info-grid">';
    
    // Carte Coursier
    html += `
        <div class="tracking-info-card">
            <h3><i class="fas fa-motorcycle"></i> Coursier</h3>
            <p><strong>${coursier.nom || 'N/A'}</strong></p>
            <p><i class="fas fa-phone"></i> ${coursier.telephone || 'N/A'}</p>
            ${coursier.statut ? `<p><span class="status-badge ${coursier.statut === 'en_ligne' ? 'online' : 'offline'}">${coursier.statut === 'en_ligne' ? '🟢 En ligne' : '🔴 Hors ligne'}</span></p>` : ''}
        </div>
    `;
    
    // Carte Itinéraire
    html += `
        <div class="tracking-info-card">
            <h3><i class="fas fa-route"></i> Itinéraire</h3>
            <p><strong>Départ :</strong><br>${commande.adresse_depart || 'N/A'}</p>
            <p><strong>Arrivée :</strong><br>${commande.adresse_arrivee || 'N/A'}</p>
        </div>
    `;
    
    // Carte Temps & Prix
    html += `
        <div class="tracking-info-card">
            <h3><i class="fas fa-clock"></i> Temps & Prix</h3>
            ${duree.debut ? `<p><strong>⏰ Début :</strong> ${duree.debut}</p>` : ''}
            ${duree.fin ? `<p><strong>✅ Fin :</strong> ${duree.fin}</p>` : ''}
            ${duree.duree_formatted ? `<p><strong>⏱️ Durée :</strong> ${duree.duree_formatted}</p>` : ''}
            <p><strong>💰 Prix :</strong> ${commande.prix_estime ? Number(commande.prix_estime).toLocaleString('fr-FR') + ' FCFA' : 'N/A'}</p>
        </div>
    `;
    
    html += '</div>';
    
    // Carte si disponible
    if (data.position || (data.positions && data.positions.length > 0)) {
        html += `
            <div class="tracking-map-container">
                <div id="trackingMap" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
                    <div style="text-align: center;">
                        <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>Carte Google Maps bientôt disponible</p>
                        <p style="font-size: 12px; margin-top: 10px;">Position: ${data.position ? `Lat ${data.position.lat}, Lng ${data.position.lng}` : 'Non disponible'}</p>
                    </div>
                </div>
            </div>
        `;
    } else {
        html += `
            <div style="text-align: center; padding: 30px; background: rgba(30, 41, 59, 0.6); border-radius: 14px; color: rgba(226, 232, 240, 0.6);">
                <i class="fas fa-map-marked-alt" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
                <p>Aucune donnée de géolocalisation disponible</p>
            </div>
        `;
    }
    
    modalContent.innerHTML = html;
}

// Fermer le modal avec Echap
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeTrackingModal({ type: 'click' });
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('commandesFilterForm');
    const statsContainer = document.getElementById('statsContainer');
    const coursiersContainer = document.getElementById('coursiersContainer');
    const commandesContainer = document.getElementById('commandesList');
    const syncCard = document.getElementById('syncStatusCard');
    const syncContent = document.getElementById('syncStatusContent');

    // ⚡ SYNCHRONISATION TEMPS RÉEL - Rechargement automatique toutes les 30 secondes
    console.log('🔄 Activation synchronisation temps réel admin commandes');
    setInterval(() => {
        console.log('🔄 Rechargement auto page commandes...');
        window.location.reload();
    }, 30000); // 30 secondes

    const formatAgo = (seconds) => {
        if (Number.isNaN(seconds) || seconds === null) return 'inconnue';
        if (seconds < 60) return 'il y a quelques secondes';
        if (seconds < 3600) return 'il y a ' + Math.round(seconds / 60) + ' min';
        if (seconds < 86400) return 'il y a ' + Math.round(seconds / 3600) + ' h';
        return 'il y a ' + Math.round(seconds / 86400) + ' j';
    };

    const applySyncHealth = (health) => {
        if (!syncCard) return;
        syncCard.classList.remove('degraded', 'critical');
        if (health === 'warning' || health === 'degraded') {
            syncCard.classList.add('degraded');
        } else if (health === 'critical') {
            syncCard.classList.add('critical');
        }
    };

    const refreshSyncStatus = () => {
        if (!syncCard || !syncContent) return;
        fetch('api/system_sync_status.php', { credentials: 'same-origin' })
            .then(resp => resp.json())
            .then(data => {
                if (!data) return;
                applySyncHealth(data.health || 'healthy');

                const metrics = data.metrics || {};
                const components = data.components || {};
                const indexAge = components.frontend_index ? components.frontend_index.age_seconds : null;
                const adminAge = components.admin_commandes ? components.admin_commandes.age_seconds : null;
                const fcmAge = components.fcm_sync ? components.fcm_sync.age_seconds : null;

                const commandesMetrics = metrics.commandes || {};
                const fcm = metrics.fcm_tokens || {};
                const chat = metrics.chat || {};

                syncContent.innerHTML = `
                    <span><strong>Etat global :</strong> ${String(data.health || 'N/A').toUpperCase()}</span>
                    <span><strong>Commandes :</strong> ${commandesMetrics.total ?? 0} (MAJ ${formatAgo(adminAge)})</span>
                    <span><strong>Index :</strong> heartbeat ${formatAgo(indexAge)}</span>
                    <span><strong>Tokens actifs :</strong> ${fcm.active_tokens ?? 0} (stale ${fcm.stale_tokens ?? 0})</span>
                    <span><strong>Chat en attente :</strong> ${chat.waiting_messages ?? 0}</span>
                    <span><strong>FCM :</strong> heartbeat ${formatAgo(fcmAge)}</span>
                `;
            })
            .catch(error => {
                console.error('Sync status error', error);
                syncContent.innerHTML = '<span>Impossible de charger le statut de synchronisation.</span>';
                applySyncHealth('warning');
            });
    };

    if (syncCard && syncContent) {
        refreshSyncStatus();
        setInterval(refreshSyncStatus, 45000);
    }

    const connectivityApiUrl = 'api/coursiers_connectes.php';

    const formatRelativeTime = (value) => {
        if (!value) {
            return 'Dernière activité inconnue';
        }

        const normalized = String(value).replace(' ', 'T');
        const timestamp = Date.parse(normalized);
        if (Number.isNaN(timestamp)) {
            return 'Dernière activité inconnue';
        }

        const diffMs = Date.now() - timestamp;
        if (diffMs <= 0) {
            return "Dernière activité : à l'instant";
        }

        const diffSec = Math.floor(diffMs / 1000);
        if (diffSec < 60) {
            return "Dernière activité : à l'instant";
        }
        if (diffSec < 3600) {
            const minutes = Math.floor(diffSec / 60);
            return `Dernière activité : il y a ${minutes} minute${minutes > 1 ? 's' : ''}`;
        }
        if (diffSec < 86400) {
            const hours = Math.floor(diffSec / 3600);
            return `Dernière activité : il y a ${hours} heure${hours > 1 ? 's' : ''}`;
        }
        const days = Math.floor(diffSec / 86400);
        return `Dernière activité : il y a ${days} jour${days > 1 ? 's' : ''}`;
    };

    async function refreshConnectivityPanel() {
        if (!coursiersContainer) {
            return;
        }

        const panel = coursiersContainer.querySelector('[data-connectivity-panel]');
        if (!panel) {
            return;
        }

        const listEl = panel.querySelector('[data-coursiers-list]');
        const totalEl = panel.querySelector('[data-connected-total]');
        const greenEl = panel.querySelector('[data-count-green]');
        const orangeEl = panel.querySelector('[data-count-orange]');
        const redEl = panel.querySelector('[data-count-red]');
        const fcmIndicator = panel.querySelector('[data-fcm-indicator]');
        const fcmRateEl = panel.querySelector('[data-fcm-rate]');

        try {
            const response = await fetch(connectivityApiUrl, { cache: 'no-store' });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const payload = await response.json();
            const couriers = Array.isArray(payload.data) ? payload.data : [];

            if (totalEl) {
                const total = payload.meta && typeof payload.meta.total === 'number'
                    ? payload.meta.total
                    : couriers.length;
                totalEl.textContent = total;
            }

            const counts = { green: 0, orange: 0, red: 0 };
            const fragment = document.createDocumentFragment();

            couriers.forEach((coursier) => {
                const id = Number.parseInt(coursier.id, 10) || 0;
                const statusLight = coursier.status_light || {};
                let color = String(statusLight.color || '').toLowerCase();
                if (!['green', 'orange', 'red'].includes(color)) {
                    color = 'red';
                }
                counts[color] += 1;

                const nameParts = [coursier.nom || '', coursier.prenoms || '']
                    .map(part => part ? String(part).trim() : '')
                    .filter(Boolean);
                const displayName = nameParts.join(' ') || `Coursier #${id}`;
                const statusLabel = statusLight.label || 'Statut inconnu';
                const lastSeenRaw = coursier.last_seen_at || coursier.last_login_at || null;
                const lastSeenText = formatRelativeTime(lastSeenRaw);
                const fcmTokens = Number.parseInt(coursier.fcm_tokens, 10) || 0;

                const item = document.createElement('div');
                item.className = 'coursier-item';
                item.dataset.coursierId = String(id);
                item.innerHTML = `
                    <div class="status-dot ${color}"></div>
                    <div class="coursier-info">
                        <div class="coursier-name">${escapeHtml(displayName)}</div>
                        <div class="coursier-status">${escapeHtml(statusLabel)}</div>
                        <div class="coursier-meta">${escapeHtml(lastSeenText)}</div>
                    </div>
                    <div class="coursier-badges">
                        ${fcmTokens > 0 ? '<i class="fas fa-mobile-alt app-badge" title="Token FCM actif"></i>' : ''}
                        <i class="fas fa-chevron-right arrow-icon"></i>
                    </div>
                `;
                item.addEventListener('click', () => showCoursierDetails(id));
                fragment.appendChild(item);
            });

            if (listEl) {
                listEl.innerHTML = '';
                if (couriers.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'empty-state';
                    empty.innerHTML = `
                        <i class="fas fa-motorcycle"></i>
                        <div>Aucun coursier connecté</div>
                    `;
                    listEl.appendChild(empty);
                } else {
                    listEl.appendChild(fragment);
                }
            }

            if (greenEl) greenEl.textContent = counts.green;
            if (orangeEl) orangeEl.textContent = counts.orange;
            if (redEl) redEl.textContent = counts.red;

            if (fcmIndicator && fcmRateEl) {
                const summary = payload.meta && payload.meta.fcm_summary ? payload.meta.fcm_summary : null;
                fcmIndicator.classList.remove('excellent', 'correct', 'critique', 'erreur', 'neutral');

                if (summary && summary.status) {
                    fcmIndicator.classList.add(summary.status);
                    const rate = typeof summary.fcm_rate === 'number' ? summary.fcm_rate : 0;
                    fcmRateEl.textContent = `${rate}%`;
                    const tooltip = `FCM : ${(summary.with_fcm ?? 0)}/${(summary.total_connected ?? 0)} (${rate}%)`;
                    fcmIndicator.setAttribute('title', tooltip);
                } else {
                    fcmIndicator.classList.add('neutral');
                    fcmRateEl.textContent = '--%';
                    fcmIndicator.setAttribute('title', 'FCM : données indisponibles');
                }
            }
        } catch (error) {
            console.warn('Coursiers connectivity refresh failed:', error);

            if (totalEl) totalEl.textContent = '??';
            if (greenEl) greenEl.textContent = '0';
            if (orangeEl) orangeEl.textContent = '0';
            if (redEl) redEl.textContent = '0';

            if (listEl) {
                listEl.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-wifi"></i>
                        <div>Impossible de charger les coursiers</div>
                    </div>
                `;
            }

            if (fcmIndicator && fcmRateEl) {
                fcmIndicator.classList.remove('excellent', 'correct', 'critique', 'neutral');
                fcmIndicator.classList.add('erreur');
                fcmIndicator.setAttribute('title', 'FCM : erreur de chargement');
                fcmRateEl.textContent = '--%';
            }
        }
    }

    if (coursiersContainer) {
        refreshConnectivityPanel();
        setInterval(refreshConnectivityPanel, 30000);
    }

    window.refreshConnectivityPanel = refreshConnectivityPanel;
}

// Fonctions pour le modal coursier
function showCoursierDetails(coursierId) {
    const modal = document.getElementById('coursierModal');
    const modalName = document.getElementById('modalCoursierName');
    const modalContent = document.getElementById('modalCoursierContent');
    
    if (!modal || !modalName || !modalContent) return;
    
    // Afficher le modal avec un loader
    modalContent.innerHTML = `
        <div style="text-align: center; padding: 20px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #4299e1;"></i>
            <div style="margin-top: 10px;">Chargement des détails...</div>
        </div>
    `;
    
    modal.style.display = 'block';
    
    // Récupérer les détails du coursier via AJAX
    fetch(`get_coursier_data.php?id=${coursierId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                modalName.textContent = `${data.coursier.nom} ${data.coursier.prenoms}`;
                modalContent.innerHTML = generateCoursierDetailsHTML(data);
            } else {
                modalContent.innerHTML = `
                    <div style="text-align: center; color: #fc8181;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>Erreur lors du chargement des détails</div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            modalContent.innerHTML = `
                <div style="text-align: center; color: #fc8181;">
                    <i class="fas fa-wifi" style="opacity: 0.5;"></i>
                    <div>Erreur de connexion</div>
                </div>
            `;
        });
}

function generateCoursierDetailsHTML(data) {
    const coursier = data.coursier;
    const commandes = data.commandes || {};
    const statusLight = data.status_light || {};
    
    return `
        <div class="coursier-details">
            <div class="detail-section">
                <h6><i class="fas fa-info-circle"></i> Statut de Connexion</h6>
                <div class="status-info">
                    <div class="status-dot ${statusLight.color}"></div>
                    <span>${statusLight.label || 'Statut inconnu'}</span>
                </div>
                ${coursier.current_session_token ? 
                    '<div class="token-status"><i class="fas fa-mobile-alt"></i> Application connectée</div>' : 
                    '<div class="token-status offline"><i class="fas fa-mobile-alt"></i> Application déconnectée</div>'
                }
                ${coursier.last_login_at ? 
                    `<div class="last-activity"><i class="fas fa-clock"></i> Dernière activité: ${new Date(coursier.last_login_at).toLocaleString('fr-FR')}</div>` : ''
                }
            </div>
            
            <div class="detail-section">
                <h6><i class="fas fa-box"></i> Commandes</h6>
                <div class="commandes-stats">
                    <div class="stat-item">
                        <span class="stat-number">${commandes.en_cours || 0}</span>
                        <span class="stat-label">En cours</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${commandes.en_attente || 0}</span>
                        <span class="stat-label">En attente</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${commandes.refusees || 0}</span>
                        <span class="stat-label">Refusées</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${commandes.livrees_aujourd_hui || 0}</span>
                        <span class="stat-label">Livrées aujourd'hui</span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h6><i class="fas fa-chart-line"></i> Performance</h6>
                <div class="performance-info">
                    <div class="perf-item">
                        <span>Taux de réussite:</span>
                        <strong>${data.performance?.taux_reussite || '0'}%</strong>
                    </div>
                    <div class="perf-item">
                        <span>Temps moyen livraison:</span>
                        <strong>${data.performance?.temps_moyen || 'N/A'}</strong>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function closeCoursierModal(event) {
    const modal = document.getElementById('coursierModal');
    if (modal && (event?.target === modal || event?.type === 'click')) {
        modal.style.display = 'none';
    }
}

// ✅ FIN DU SYSTÈME SIMPLIFIÉ
console.log('✅ Admin commandes - Système simplifié prêt');
});
</script>

</body>
</html>
