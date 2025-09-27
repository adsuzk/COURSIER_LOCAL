<?php
/**
 * Admin commandes avancées (version locale)
 * Mise en forme premium + tracking temps réel aligné production
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/SystemSync.php';
require_once __DIR__ . '/lib/db_maintenance.php';

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

            $update = $pdo->prepare("UPDATE commandes SET statut = 'livree', updated_at = NOW() WHERE id = ?");
            $update->execute([$commandeId]);

            if (!empty($commande['coursier_id'])) {
                $checkTransaction = $pdo->prepare("SELECT COUNT(*) FROM transactions_financieres WHERE commande_id = ?");
                $checkTransaction->execute([$commandeId]);

                if ($checkTransaction->fetchColumn() == 0) {
                    $insert = $pdo->prepare("
                        INSERT INTO transactions_financieres (
                            commande_id, coursier_id, montant, mode_paiement,
                            type_transaction, statut, created_at
                        ) VALUES (?, ?, ?, 'especes', 'livraison', 'completed', NOW())
                    ");
                    $insert->execute([
                        $commandeId,
                        $commande['coursier_id'],
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

function getAgentsSchemaInfo(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $columns = [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM agents_suzosky');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[$row['Field']] = true;
        }
    } catch (PDOException $e) {
        $columns = [];
    }

    $joinColumn = isset($columns['id_coursier']) ? 'id_coursier' : 'id';
    $statusExpression = isset($columns['statut_connexion']) ? 'a.statut_connexion'
        : (isset($columns['status']) ? 'a.status'
            : (isset($columns['is_online']) ? "CASE WHEN a.is_online = 1 THEN 'en_ligne' ELSE 'hors_ligne' END"
                : "'inconnu'"));

    $onlineExpression = isset($columns['is_online']) ? 'a.is_online' : 'NULL';
    $latitudeExpression = isset($columns['latitude']) ? 'a.latitude' : 'NULL';
    $longitudeExpression = isset($columns['longitude']) ? 'a.longitude' : 'NULL';

    $statusFilterColumn = isset($columns['statut']) ? 'statut' : (isset($columns['status']) ? 'status' : null);

    $cache = [
        'join_column' => $joinColumn,
        'status_expression' => $statusExpression,
        'online_expression' => $onlineExpression,
        'latitude_expression' => $latitudeExpression,
        'longitude_expression' => $longitudeExpression,
        'status_filter_column' => $statusFilterColumn,
    ];

    return $cache;
}

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

function getAllCouriers(): array
{
    $pdo = getDBConnection();
    $info = getAgentsSchemaInfo($pdo);
    $filter = '';
    if ($info['status_filter_column']) {
        $filter = "WHERE LOWER(a." . $info['status_filter_column'] . ") IN ('actif','active')";
    }

    try {
        $stmt = $pdo->query("
            SELECT a." . $info['join_column'] . " AS id,
                   a.nom,
                   a.prenoms,
                   " . $info['status_expression'] . " AS statut_connexion,
                   a.current_session_token,
                   a.last_login_at,
                   (a.last_login_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)) AS is_recent_activity
            FROM agents_suzosky a
            $filter
            ORDER BY a.statut_connexion DESC, a.last_login_at DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function getCoursierStatusLight($coursier): array
{
    // Système de feux : Vert, Orange, Rouge
    $status = [
        'color' => 'red',
        'label' => 'Non disponible',
        'conditions' => []
    ];
    
    // Condition 1: Token présent (connexion technique app)
    $hasToken = !empty($coursier['current_session_token']);
    $status['conditions']['token'] = $hasToken;
    
    // Condition 2: Statut en ligne
    $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
    $status['conditions']['online'] = $isOnline;
    
    // Condition 3: Solde suffisant (à implémenter selon logique métier)
    $hasSufficientBalance = true; // TODO: Vérifier le solde réel
    $status['conditions']['balance'] = $hasSufficientBalance;
    
    // Condition 4: Dernière activité récente
    $lastActivity = strtotime($coursier['last_login_at'] ?? '0');
    $isRecentActivity = $lastActivity > (time() - 300); // 5 minutes
    $status['conditions']['activity'] = $isRecentActivity;
    
    // Déterminer la couleur du feu
    if ($hasToken && $isOnline && $isRecentActivity) {
        if ($hasSufficientBalance) {
            $status['color'] = 'green';
            $status['label'] = 'Disponible pour courses';
        } else {
            $status['color'] = 'orange';
            $status['label'] = 'Solde insuffisant';
        }
    } else {
        $status['color'] = 'red';
        if (!$hasToken) {
            $status['label'] = 'Token app manquant';
        } elseif (!$isOnline) {
            $status['label'] = 'Hors ligne';
        } else {
            $status['label'] = 'Inactif';
        }
    }
    
    return $status;
}

function getStatistics(): array
{
    $pdo = getDBConnection();
    $stats = [
        'total' => 0,
        'nouvelle' => 0,
        'assignee' => 0,
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
        $stats['assignee'] = (int) $pdo->query('SELECT COUNT(*) FROM commandes WHERE coursier_id IS NOT NULL AND coursier_id > 0')->fetchColumn();
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
            <h3>Assignées</h3>
            <strong><?= (int) $stats['assignee'] ?></strong>
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
    
    $vertsCount = 0;
    $orangesCount = 0;
    $rougesCount = 0;
    
    // FILTRER UNIQUEMENT LES COURSIERS CONNECTÉS
    $coursiersConnectes = [];
    foreach ($coursiers as $coursier) {
        $statusLight = getCoursierStatusLight($coursier);
        
        // Un coursier est connecté s'il a un token ET est en ligne ET a une activité récente
        $hasToken = !empty($coursier['current_session_token']);
        $isOnline = ($coursier['statut_connexion'] ?? '') === 'en_ligne';
        // Utiliser les données MySQL pour éviter les problèmes de fuseau horaire
        $isRecentActivity = !empty($coursier['is_recent_activity']);
        
        // Fallback si is_recent_activity n'est pas disponible
        if (!isset($coursier['is_recent_activity'])) {
            $lastActivity = strtotime($coursier['last_login_at'] ?? '0');
            $isRecentActivity = $lastActivity > (time() - 1800); // 30 minutes
        }
        
        $isConnected = $hasToken && $isOnline && $isRecentActivity;
        
        // Ne garder que les coursiers connectés
        if ($isConnected) {
            $coursiersConnectes[] = array_merge($coursier, ['status_light' => $statusLight]);
            
            switch($statusLight['color']) {
                case 'green': $vertsCount++; break;
                case 'orange': $orangesCount++; break;
                case 'red': $rougesCount++; break;
            }
        }
    }
    
    $totalCoursiers = count($coursiersConnectes);
    ?>
    
    <div class="suzosky-coursiers-panel">
        <div class="panel-header">
            <div class="panel-title">
                <i class="fas fa-motorcycle"></i>
                <span>Coursiers Connectés</span>
                <span class="badge-total"><?= $totalCoursiers ?></span>
            </div>
            <div class="lights-summary">
                <div class="light-indicator green" title="Disponibles"><?= $vertsCount ?></div>
                <div class="light-indicator orange" title="Limités"><?= $orangesCount ?></div>
                <div class="light-indicator red" title="Indisponibles"><?= $rougesCount ?></div>
            </div>
        </div>
        
        <div class="coursiers-scrollable">
            <?php foreach ($coursiersConnectes as $coursier): ?>
                <div class="coursier-item" onclick="showCoursierDetails(<?= $coursier['id'] ?>)" data-coursier-id="<?= $coursier['id'] ?>">
                    <div class="status-dot <?= $coursier['status_light']['color'] ?>"></div>
                    <div class="coursier-info">
                        <div class="coursier-name"><?= htmlspecialchars($coursier['nom'] . ' ' . $coursier['prenoms']) ?></div>
                        <div class="coursier-status"><?= htmlspecialchars($coursier['status_light']['label']) ?></div>
                    </div>
                    <div class="coursier-badges">
                        <?php if (!empty($coursier['current_session_token'])): ?>
                            <i class="fas fa-mobile-alt app-badge" title="App connectée"></i>
                        <?php endif; ?>
                        <i class="fas fa-chevron-right arrow-icon"></i>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($coursiersConnectes)): ?>
                <div class="empty-state">
                    <i class="fas fa-motorcycle"></i>
                    <div>Aucun coursier connecté</div>
                </div>
            <?php endif; ?>
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
            $isActive = in_array($statut, ['assignee', 'en_cours'], true);
            $isCompleted = in_array($statut, ['livree', 'annulee'], true);

            if (!$hasCoursier) {
                $trackClass = 'btn-track disabled';
                $trackLabel = 'Pas de coursier';
                $trackIcon = 'ban';
                $trackTitle = "Aucun coursier assigné";
                $trackAction = 'showTrackingUnavailable(); return false;';
                $trackDisabled = 'disabled';
            } elseif ($isActive) {
                $trackClass = 'btn-track live';
                $trackLabel = 'Tracking Live';
                $trackIcon = 'satellite';
                $trackTitle = 'Suivi en temps réel';
                $trackAction = "openTrackingModal({$commande['id']}, {$commande['coursier_id']}, 'live');";
                $trackDisabled = '';
            } elseif ($isCompleted) {
                $trackClass = 'btn-track history';
                $trackLabel = 'Historique';
                $trackIcon = 'history';
                $trackTitle = 'Consulter la course';
                $trackAction = "openTrackingModal({$commande['id']}, {$commande['coursier_id']}, 'history');";
                $trackDisabled = '';
            } else {
                $trackClass = 'btn-track pending';
                $trackLabel = 'En attente';
                $trackIcon = 'clock';
                $trackTitle = 'Commande en attente';
                $trackAction = "openTrackingModal({$commande['id']}, {$commande['coursier_id']}, 'pending');";
                $trackDisabled = '';
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
                            <p><strong>Créée :</strong> <?= date('d/m/Y H:i', strtotime($commande['created_at'])) ?></p>
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
                    <button class="<?= $trackClass ?>" title="<?= htmlspecialchars($trackTitle, ENT_QUOTES) ?>" onclick="<?= $trackAction ?>" <?= $trackDisabled ?>>
                        <i class="fas fa-<?= $trackIcon ?>"></i>
                        <?= $trackLabel ?>
                    </button>

                    <?php if (!$isCompleted): ?>
                        <form method="POST" onsubmit="return confirm('Confirmer la terminaison de cette commande ?');">
                            <input type="hidden" name="action" value="terminate_order">
                            <input type="hidden" name="commande_id" value="<?= (int) $commande['id'] ?>">
                            <button class="btn-terminate" type="submit">
                                <i class="fas fa-check"></i> Terminer manuellement
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="color: var(--success-green); font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-check-circle"></i> Commande terminée
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
}

.btn-track {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

.btn-track i { font-size: 16px; }

.btn-track.live {
    background: linear-gradient(135deg, #2563eb, #60a5fa);
    color: #0b1120;
}

.btn-track.history {
    background: rgba(37, 99, 235, 0.18);
    color: #93c5fd;
}

.btn-track.pending {
    background: rgba(234, 179, 8, 0.12);
    color: #facc15;
}

.btn-track.disabled {
    background: rgba(148,163,184,0.12);
    color: rgba(148,163,184,0.6);
    cursor: not-allowed;
}

.btn-track:not(.disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 15px 25px rgba(0,0,0,0.2);
}

.btn-terminate {
    border: 1px solid rgba(233, 69, 96, 0.4);
    background: transparent;
    color: rgba(233, 69, 96, 0.9);
    padding: 10px 16px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;
}

.btn-terminate:hover {
    background: rgba(233, 69, 96, 0.12);
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

@media (max-width: 780px) {
    .admin-header { flex-direction: column; gap: 12px; align-items: flex-start; }
    .filters-actions { justify-content: flex-start; }
    .commande-actions { flex-direction: column; align-items: stretch; }
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
                    <?php foreach (['nouvelle' => 'Nouvelles', 'assignee' => 'Assignées', 'en_cours' => 'En cours', 'livree' => 'Livrées', 'annulee' => 'Annulées'] as $value => $label): ?>
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

<div id="trackingModal" class="tracking-modal" role="dialog" aria-modal="true" aria-labelledby="trackingTitle">
    <div class="modal-card">
        <header>
            <div>
                <h2 id="trackingTitle">Tracking commande</h2>
                <small id="trackingSubtitle">Initialisation...</small>
            </div>
            <button class="modal-close-btn" type="button" onclick="closeTrackingModal()" aria-label="Fermer le tracking">
                <i class="fas fa-times"></i>
            </button>
        </header>
        <div class="modal-tabs">
            <button type="button" class="active" data-tab="overview" onclick="switchTrackingTab('overview')">Vue d'ensemble</button>
            <button type="button" data-tab="map" onclick="switchTrackingTab('map')">Carte</button>
            <button type="button" data-tab="timeline" onclick="switchTrackingTab('timeline')">Timeline</button>
        </div>
        <div class="modal-body">
            <div id="tab-overview" class="modal-tab active">
                <div class="overview-grid">
                    <div class="overview-card">
                        <h5>Coursier</h5>
                        <div id="trackingCourier">Chargement...</div>
                    </div>
                    <div class="overview-card">
                        <h5>File d'attente</h5>
                        <div id="trackingQueue">-</div>
                    </div>
                    <div class="overview-card">
                        <h5>Estimations</h5>
                        <div id="trackingEstimates">-</div>
                    </div>
                    <div class="overview-card">
                        <h5>Détails commande</h5>
                        <div id="trackingDetails">-</div>
                    </div>
                </div>
                <div style="margin-top: 18px; display: flex; gap: 12px;">
                    <button class="button button-primary" type="button" onclick="refreshTracking()"><i class="fas fa-sync-alt"></i> Actualiser</button>
                    <button class="button button-secondary" type="button" onclick="switchTrackingTab('map')"><i class="fas fa-map"></i> Voir la carte</button>
                </div>
            </div>
            <div id="tab-map" class="modal-tab">
                <div class="map-container">
                    <div id="trackingMap"></div>
                </div>
            </div>
            <div id="tab-timeline" class="modal-tab">
                <div class="timeline" id="trackingTimeline">
                    <div>Aucune donnée disponible.</div>
                </div>
            </div>
        </div>
        <div class="sync-row">
            <div class="sync-indicator">
                <span class="sync-dot"></span>
                <span id="trackingSync">Synchronisation...</span>
            </div>
            <div id="trackingLastUpdate" style="font-size: 13px; color: rgba(226,232,240,0.65);">
                Dernière mise à jour : --:--:--
            </div>
        </div>
    </div>
</div>

<?php $mapsApiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : (getenv('GOOGLE_MAPS_API_KEY') ?: ''); ?>

<script>
const ADMIN_MAPS_API_KEY = <?= json_encode($mapsApiKey) ?>;
window.GOOGLE_MAPS_API_KEY = ADMIN_MAPS_API_KEY || '';
const TRACKING_DEFAULT_CENTER = { lat: 5.359951, lng: -4.008256 };

function escapeHtml(value) {
    if (value === undefined || value === null) {
        return '';
    }
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDateTime(value) {
    if (!value) {
        return '--';
    }
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) {
        return value;
    }
    return dt.toLocaleString('fr-FR', { hour12: false });
}

function humanizeStatus(status) {
    if (!status) return '';
    const normalized = String(status).toLowerCase();
    const map = {
        'nouvelle': 'Nouvelle commande',
        'en_attente': 'En attente',
        'pending': 'En attente',
        'assignee': 'Coursier assigné',
        'acceptee': 'Acceptée',
        'en_cours': 'En cours de livraison',
        'livree': 'Livrée',
        'delivered': 'Livrée',
        'annulee': 'Annulée',
        'annule': 'Annulée'
    };
    return map[normalized] || status.replace(/_/g, ' ');
}

let trackingModal = null;
let currentCommandeId = null;
let trackingTimer = null;
let trackingIntervalMs = 20000;
let trackingMapInstance = null;
let trackingMarker = null;
let trackingPickupMarker = null;
let trackingDropoffMarker = null;
let googleMapsScriptLoading = false;
let googleMapsInitQueue = [];

document.addEventListener('DOMContentLoaded', () => {
    trackingModal = document.getElementById('trackingModal');

    const filterForm = document.getElementById('commandesFilterForm');
    const statsContainer = document.getElementById('statsContainer');
    const coursiersContainer = document.getElementById('coursiersContainer');
    const commandesContainer = document.getElementById('commandesList');
    const syncCard = document.getElementById('syncStatusCard');
    const syncContent = document.getElementById('syncStatusContent');

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

    // Auto-rafraîchissement des feux coursiers toutes les 30 secondes
    async function refreshCoursiersStatus() {
        if (!coursiersContainer) return;
        
        try {
            const params = new URLSearchParams(new FormData(filterForm));
            params.set('ajax', '1');
            
            const response = await fetch('?' + params.toString(), {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.success && data.coursiers) {
                    coursiersContainer.innerHTML = data.coursiers;
                }
            }
        } catch (error) {
            console.log('Coursiers auto-refresh failed:', error);
        }
    }

    if (coursiersContainer) {
        setInterval(refreshCoursiersStatus, 30000); // 30 secondes
    }
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

    if (!filterForm || filterForm.dataset.ajax !== '1' || !statsContainer || !coursiersContainer || !commandesContainer) {
        return;
    }

    const loadingMarkup = `
        <div class="loading-state">
            <i class="fas fa-spinner fa-spin"></i>
            <div>Mise à jour des commandes...</div>
        </div>
    `;

    let currentController = null;
    let transactionDebounce = null;

    const buildParams = () => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams();
        formData.forEach((value, key) => {
            const stringValue = typeof value === 'string' ? value.trim() : value;
            if (key === 'section' || stringValue !== '') {
                params.append(key, value);
            }
        });
        return params;
    };

    const applyFilters = () => {
        const baseParams = buildParams();
        const ajaxParams = new URLSearchParams(baseParams);
        ajaxParams.set('ajax', '1');

        const endpoint = filterForm.dataset.ajaxEndpoint || filterForm.action || window.location.pathname;
        const endpointUrl = new URL(endpoint, window.location.href);
        endpointUrl.search = ajaxParams.toString();

        if (currentController) {
            currentController.abort();
        }
        const controller = new AbortController();
        currentController = controller;

        commandesContainer.innerHTML = loadingMarkup;
        statsContainer.classList.add('is-dimmed');
        filterForm.classList.add('is-loading');

        fetch(endpointUrl.toString(), {
            method: 'GET',
            signal: controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau (' + response.status + ')');
                }
                return response.json();
            })
            .then(data => {
                if (!data || !data.success) {
                    throw new Error(data && data.message ? data.message : 'Réponse invalide');
                }
                statsContainer.innerHTML = data.stats || '';
                coursiersContainer.innerHTML = data.coursiers || '';
                commandesContainer.innerHTML = data.commandes || '';

                const paramsForUrl = buildParams();
                const actionUrl = new URL(filterForm.action || window.location.pathname, window.location.href);
                actionUrl.search = paramsForUrl.toString();
                window.history.replaceState({}, '', actionUrl.toString());
            })
            .catch(error => {
                if (error.name === 'AbortError') {
                    return;
                }
                console.error('Erreur filtrage AJAX', error);
                const safeMessage = escapeHtml(error.message || 'Erreur inconnue');
                commandesContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-triangle" style="font-size: 30px; margin-bottom: 10px;"></i>
                        <div>Impossible de mettre à jour les commandes.</div>
                        <small style="display:block;margin-top:6px;color:rgba(226,232,240,0.6);">${safeMessage}</small>
                    </div>`;
            })
            .finally(() => {
                if (currentController === controller) {
                    currentController = null;
                }
                statsContainer.classList.remove('is-dimmed');
                filterForm.classList.remove('is-loading');
            });
    };

    filterForm.addEventListener('submit', event => {
        event.preventDefault();
        applyFilters();
    });

    filterForm.querySelectorAll('select, input[type="date"]').forEach(el => {
        el.addEventListener('change', applyFilters);
    });

    const transactionInput = filterForm.querySelector('input[name="transaction"]');
    if (transactionInput) {
        transactionInput.addEventListener('input', () => {
            clearTimeout(transactionDebounce);
            transactionDebounce = setTimeout(applyFilters, 500);
        });
        transactionInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                clearTimeout(transactionDebounce);
                applyFilters();
            }
        });
    }
});

function startTrackingInterval(intervalMs) {
    if (trackingTimer) {
        clearInterval(trackingTimer);
    }
    trackingIntervalMs = intervalMs;
    trackingTimer = setInterval(fetchTrackingData, trackingIntervalMs);
}

function applyRefreshInterval(seconds) {
    const numeric = Number(seconds);
    if (!Number.isFinite(numeric) || numeric <= 0) {
        return;
    }
    const intervalMs = Math.max(10000, Math.round(numeric * 1000));
    if (intervalMs !== trackingIntervalMs) {
        startTrackingInterval(intervalMs);
    }
}

function openTrackingModal(commandeId, coursierId, mode) {
    currentCommandeId = commandeId;
    if (!trackingModal) {
        trackingModal = document.getElementById('trackingModal');
    }
    if (!trackingModal) {
        console.warn('Tracking modal introuvable');
        return;
    }

    trackingModal.classList.add('visible');
    document.body.classList.add('modal-open');

    const titleEl = document.getElementById('trackingTitle');
    if (titleEl) {
        titleEl.textContent = 'Commande #' + commandeId;
    }

    const subtitleEl = document.getElementById('trackingSubtitle');
    if (subtitleEl) {
        subtitleEl.textContent = mode === 'history' ? 'Historique de la course' : 'Suivi en temps réel';
    }

    document.getElementById('trackingCourier').textContent = 'Chargement...';
    document.getElementById('trackingQueue').innerHTML = '-';
    document.getElementById('trackingEstimates').innerHTML = '-';
    document.getElementById('trackingDetails').innerHTML = '-';
    document.getElementById('trackingTimeline').innerHTML = '<div>Chargement des événements...</div>';
    document.getElementById('trackingSync').textContent = 'Synchronisation...';
    document.getElementById('trackingLastUpdate').textContent = 'Dernière mise à jour : --:--';

    fetchTrackingData(true);
    startTrackingInterval(trackingIntervalMs);
}

function closeTrackingModal() {
    if (trackingModal) {
        trackingModal.classList.remove('visible');
    }
    document.body.classList.remove('modal-open');
    if (trackingTimer) {
        clearInterval(trackingTimer);
        trackingTimer = null;
    }
}

function switchTrackingTab(tab) {
    document.querySelectorAll('.modal-tabs button').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.querySelectorAll('.modal-tab').forEach(content => {
        content.classList.toggle('active', content.id === 'tab-' + tab);
    });
}

function refreshTracking() {
    fetchTrackingData(true);
}

function updateQueueSummary(queue) {
    const queueEl = document.getElementById('trackingQueue');
    if (!queueEl) return;

    const position = queue && queue.position ? queue.position : 0;
    const total = queue && queue.total ? queue.total : 0;

    let html = `<div>Position : <strong>${position}</strong> / ${total}</div>`;

    if (queue && Array.isArray(queue.orders) && queue.orders.length) {
        const nextOrder = queue.orders.find(order => !order.is_current);
        if (nextOrder) {
            const label = nextOrder.code_commande || ('#' + nextOrder.id);
            html += `<div>Prochaine : ${escapeHtml(label)}</div>`;
        }
        html += `<div style="font-size:12px;color:rgba(148,163,184,0.6);">File active : ${queue.orders.length} commande(s)</div>`;
    }

    queueEl.innerHTML = html;
}

function renderTimeline(steps) {
    const container = document.getElementById('trackingTimeline');
    if (!container) return;

    const timelineSteps = Array.isArray(steps) ? steps : [];
    if (!timelineSteps.length) {
        container.innerHTML = '<div style="color:rgba(148,163,184,0.65);">Aucun événement enregistré.</div>';
        return;
    }

    container.innerHTML = timelineSteps.map(step => {
        const stateClass = String(step.state || step.status || 'pending').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        const label = escapeHtml(step.label || step.title || 'Événement');
        const description = step.description ? `<p>${escapeHtml(step.description)}</p>` : '';
        const timestamp = step.formatted || step.timestamp;
        const meta = timestamp ? `<div class="timeline-meta">${escapeHtml(timestamp)}</div>` : '';
        const icon = step.icon ? `<span class="timeline-icon">${escapeHtml(step.icon)}</span>` : '';
        return `<div class="timeline-item ${stateClass}"><div class="timeline-content">${icon}<strong>${label}</strong>${description}${meta}</div></div>`;
    }).join('');
}

function updateTrackingOverview(data) {
    const commande = data.commande || {};
    const courier = data.coursier || null;
    const queue = data.queue || {};
    const estimations = data.estimations || {};
    const pickup = data.pickup || {};
    const dropoff = data.dropoff || {};

    const titleEl = document.getElementById('trackingTitle');
    if (titleEl) {
        const label = commande.code_commande || commande.order_number || commande.id || currentCommandeId;
        titleEl.textContent = `Commande ${label}`;
    }

    const subtitleEl = document.getElementById('trackingSubtitle');
    if (subtitleEl) {
        const statusLabel = humanizeStatus(commande.statut);
        subtitleEl.textContent = statusLabel ? `Statut : ${statusLabel}` : 'Suivi en temps réel';
    }

    const courierEl = document.getElementById('trackingCourier');
    if (courierEl) {
        if (courier && courier.nom) {
            let statusText = courier.statut_connexion || '';
            if (!statusText && data.position_coursier && data.position_coursier.status) {
                statusText = data.position_coursier.status;
            }
            const statusClass = statusText ? statusText.toLowerCase().replace(/[^a-z0-9_-]/g, '') : '';
            const phoneLine = courier.telephone ? `<div><i class="fas fa-phone"></i> ${escapeHtml(courier.telephone)}</div>` : '';
            const lastSeen = courier.last_seen ? `<div style="font-size:12px;color:rgba(148,163,184,0.65);">Dernière vue ${escapeHtml(formatDateTime(courier.last_seen))}</div>` : '';
            courierEl.innerHTML = `
                <div class="coursier-info">
                    <strong>${escapeHtml(courier.nom)}</strong>
                    ${statusText ? `<div class="connexion-status ${escapeHtml(statusClass)}">${escapeHtml(statusText)}</div>` : ''}
                    ${phoneLine}
                    ${lastSeen}
                </div>`;
        } else {
            courierEl.innerHTML = `
                <div class="coursier-info">
                    <strong style="color: var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Non assigné</strong>
                    <p>En attente d'un coursier disponible.</p>
                </div>`;
        }
    }

    updateQueueSummary(queue);

    const estimatesEl = document.getElementById('trackingEstimates');
    if (estimatesEl) {
        const pickupEta = estimations.pickup_eta_minutes ? `${estimations.pickup_eta_minutes} min` : 'N/A';
        const pickupDistance = estimations.pickup_distance_km ? `${estimations.pickup_distance_km} km` : 'N/A';
        estimatesEl.innerHTML = `
            <div>ETA retrait : <strong>${pickupEta}</strong></div>
            <div>Distance retrait : <strong>${pickupDistance}</strong></div>
            ${data.last_status_update ? `<div style="font-size:12px;color:rgba(148,163,184,0.6);">Statut MAJ ${escapeHtml(formatDateTime(data.last_status_update))}</div>` : ''}
        `;
    }

    const detailsEl = document.getElementById('trackingDetails');
    if (detailsEl) {
        const pickupAddress = pickup && pickup.address ? escapeHtml(pickup.address) : (commande.adresse_retrait ? escapeHtml(commande.adresse_retrait) : 'N/A');
        const dropoffAddress = dropoff && dropoff.address ? escapeHtml(dropoff.address) : (commande.adresse_livraison ? escapeHtml(commande.adresse_livraison) : 'N/A');
        const priceLine = commande.prix_estime ? `<div><strong>Montant estimé :</strong> ${Number(commande.prix_estime).toLocaleString('fr-FR')} FCFA</div>` : '';
        detailsEl.innerHTML = `
            <div><strong>Retrait :</strong> ${pickupAddress}</div>
            <div><strong>Livraison :</strong> ${dropoffAddress}</div>
            ${priceLine}
        `;
    }

    const syncEl = document.getElementById('trackingSync');
    if (syncEl) {
        syncEl.textContent = 'Synchronisé';
    }

    const lastUpdateEl = document.getElementById('trackingLastUpdate');
    if (lastUpdateEl) {
        const stamp = data.timestamp || new Date().toISOString();
        lastUpdateEl.textContent = 'Dernière mise à jour : ' + formatDateTime(stamp);
    }

    renderTimeline(data.timeline || data.historique_positions || []);
}

function loadGoogleMapsScript(callback) {
    if (typeof google !== 'undefined' && google.maps) {
        callback();
        return;
    }
    if (!window.GOOGLE_MAPS_API_KEY) {
        const mapElement = document.getElementById('trackingMap');
        if (mapElement) {
            mapElement.innerHTML = '<div style="padding:40px;text-align:center;color:rgba(255,255,255,0.6);">Google Maps non configuré</div>';
        }
        return;
    }
    googleMapsInitQueue.push(callback);
    if (googleMapsScriptLoading) {
        return;
    }
    googleMapsScriptLoading = true;
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(window.GOOGLE_MAPS_API_KEY)}&v=weekly&libraries=places`;
    script.async = true;
    script.defer = true;
    script.onload = () => {
        googleMapsScriptLoading = false;
        const queue = [...googleMapsInitQueue];
        googleMapsInitQueue = [];
        queue.forEach(cb => {
            try {
                cb();
            } catch (err) {
                console.error('Google Maps callback error', err);
            }
        });
    };
    script.onerror = () => {
        googleMapsScriptLoading = false;
        const mapElement = document.getElementById('trackingMap');
        if (mapElement) {
            mapElement.innerHTML = '<div style="padding:40px;text-align:center;color:rgba(255,255,255,0.6);">Impossible de charger Google Maps</div>';
        }
    };
    document.head.appendChild(script);
}

function ensureTrackingMap(onReady) {
    const mapElement = document.getElementById('trackingMap');
    if (!mapElement) return;

    const initMap = () => {
        if (!trackingMapInstance) {
            trackingMapInstance = new google.maps.Map(mapElement, {
                center: TRACKING_DEFAULT_CENTER,
                zoom: 12,
                mapTypeControl: false,
                fullscreenControl: false,
                streetViewControl: false,
                styles: [
                    { elementType: 'geometry', stylers: [{ color: '#1f2937' }] },
                    { elementType: 'labels.text.fill', stylers: [{ color: '#f9fafb' }] },
                    { featureType: 'water', stylers: [{ color: '#1d4ed8' }] }
                ]
            });
        }
        if (typeof onReady === 'function') {
            onReady();
        }
    };

    loadGoogleMapsScript(initMap);
}

function updateTrackingMap(data) {
    const mapElement = document.getElementById('trackingMap');
    if (!mapElement) return;

    ensureTrackingMap(() => {
        if (!trackingMapInstance) return;

        const bounds = new google.maps.LatLngBounds();
        let hasPoint = false;

        if (data.pickup && data.pickup.lat !== null && data.pickup.lng !== null) {
            if (!trackingPickupMarker) {
                trackingPickupMarker = new google.maps.Marker({
                    map: trackingMapInstance,
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                        scaledSize: new google.maps.Size(32, 32)
                    }
                });
            }
            const pickupPosition = { lat: Number(data.pickup.lat), lng: Number(data.pickup.lng) };
            trackingPickupMarker.setPosition(pickupPosition);
            trackingPickupMarker.setVisible(true);
            bounds.extend(pickupPosition);
            hasPoint = true;
        } else if (trackingPickupMarker) {
            trackingPickupMarker.setVisible(false);
        }

        if (data.dropoff && data.dropoff.lat !== null && data.dropoff.lng !== null) {
            if (!trackingDropoffMarker) {
                trackingDropoffMarker = new google.maps.Marker({
                    map: trackingMapInstance,
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                        scaledSize: new google.maps.Size(32, 32)
                    }
                });
            }
            const dropoffPosition = { lat: Number(data.dropoff.lat), lng: Number(data.dropoff.lng) };
            trackingDropoffMarker.setPosition(dropoffPosition);
            trackingDropoffMarker.setVisible(true);
            bounds.extend(dropoffPosition);
            hasPoint = true;
        } else if (trackingDropoffMarker) {
            trackingDropoffMarker.setVisible(false);
        }

        if (data.position_coursier && data.position_coursier.lat !== undefined && data.position_coursier.lng !== undefined) {
            const courierPosition = { lat: Number(data.position_coursier.lat), lng: Number(data.position_coursier.lng) };
            if (!trackingMarker) {
                trackingMarker = new google.maps.Marker({
                    map: trackingMapInstance,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 8,
                        strokeColor: '#0ea5e9',
                        strokeWeight: 3,
                        fillColor: '#38bdf8',
                        fillOpacity: 0.9,
                    }
                });
            }
            trackingMarker.setPosition(courierPosition);
            trackingMarker.setVisible(true);
            bounds.extend(courierPosition);
            hasPoint = true;
        } else if (trackingMarker) {
            trackingMarker.setVisible(false);
        }

        if (hasPoint) {
            trackingMapInstance.fitBounds(bounds);
            google.maps.event.addListenerOnce(trackingMapInstance, 'bounds_changed', () => {
                if (trackingMapInstance.getZoom() > 16) {
                    trackingMapInstance.setZoom(16);
                }
            });
        } else {
            trackingMapInstance.setCenter(TRACKING_DEFAULT_CENTER);
            trackingMapInstance.setZoom(12);
        }
    });
}

function fetchTrackingData(force = false) {
    if (!currentCommandeId) return;

    const url = new URL('api/tracking_realtime.php', window.location.origin);
    url.searchParams.set('commande_id', currentCommandeId);

    fetch(url.toString(), { credentials: 'same-origin' })
        .then(response => response.json())
        .then(data => {
            if (!data || !data.success) {
                throw new Error(data && data.error ? data.error : 'Réponse API invalide');
            }
            updateTrackingOverview(data);
            updateTrackingMap(data);
            applyRefreshInterval(data.refresh_interval || data.refreshInterval);
        })
        .catch(error => {
            console.error('Tracking error', error);
            const syncEl = document.getElementById('trackingSync');
            if (syncEl) {
                syncEl.textContent = 'Erreur de synchronisation';
            }
        });
}

function showTrackingUnavailable() {
    alert("Aucun coursier n'est encore assigné à cette commande.");
}

window.addEventListener('keydown', evt => {
    if (evt.key === 'Escape' && trackingModal && trackingModal.classList.contains('visible')) {
        closeTrackingModal();
    }
});
</script>
