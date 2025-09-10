<?php
// clients.php - Gestion des clients selon charte Suzosky
require_once __DIR__ . '/../config.php';
$pdo = getPDO();

// Récupérer filtres
$filter_from     = $_GET['filter_from']     ?? '';
$filter_to       = $_GET['filter_to']       ?? '';
$filter_email    = $_GET['filter_email']    ?? '';
$filter_status   = $_GET['filter_status']   ?? '';
$filter_min_cons = $_GET['filter_min_cons'] ?? '';
$filter_max_cons = $_GET['filter_max_cons'] ?? '';

// Construct clients particuliers query with consumption
$whereP = []; $paramsP = [];
$havingP = []; $paramsH = [];
if ($filter_status !== '') { $whereP[] = 'cp.statut = ?'; $paramsP[] = $filter_status; }
if ($filter_email !== '') {
    if ($filter_email === '1') { $whereP[] = 'cp.email IS NOT NULL AND cp.email <> ""'; }
    else { $whereP[] = '(cp.email IS NULL OR cp.email = "")'; }
}
if ($filter_from !== '') { $whereP[] = 'cp.date_derniere_commande >= ?'; $paramsP[] = $filter_from.' 00:00:00'; }
if ($filter_to !== '')   { $whereP[] = 'cp.date_derniere_commande <= ?'; $paramsP[] = $filter_to.' 23:59:59'; }
if ($filter_min_cons !== '') { $havingP[] = 'consommation >= ?'; $paramsH[] = $filter_min_cons; }
if ($filter_max_cons !== '') { $havingP[] = 'consommation <= ?'; $paramsH[] = $filter_max_cons; }

        $sqlP = 'SELECT cp.*, COALESCE(SUM(c.prix_estime),0) AS consommation'
              . ' FROM clients_particuliers cp'
              . ' LEFT JOIN commandes c ON c.client_id = cp.id';
        if ($whereP)   { $sqlP .= ' WHERE ' . implode(' AND ', $whereP); }
        $sqlP .= ' GROUP BY cp.id';
        if ($havingP)  { $sqlP .= ' HAVING ' . implode(' AND ', $havingP); }
        $sqlP .= ' ORDER BY cp.id DESC';
        try {
            $stmtP = $pdo->prepare($sqlP);
            $stmtP->execute(array_merge($paramsP, $paramsH));
            $privateClients = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback without consumption if column missing
            $privateClients = $pdo->query("SELECT *, 0 AS consommation FROM clients_particuliers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        }

// Récupérer clients business (sans consommation) avec fallback
try {
    $whereB = []; $paramsB = [];
    if ($filter_status !== '') { $whereB[] = 'statut = ?'; $paramsB[] = $filter_status; }
    if ($filter_email !== '') {
        if ($filter_email === '1') { $whereB[] = 'contact_email IS NOT NULL AND contact_email <> ""'; }
        else { $whereB[] = '(contact_email IS NULL OR contact_email = "")'; }
    }
    if ($filter_from !== '') { $whereB[] = 'date_creation >= ?'; $paramsB[] = $filter_from.' 00:00:00'; }
    if ($filter_to !== '')   { $whereB[] = 'date_creation <= ?'; $paramsB[] = $filter_to.' 23:59:59'; }

    $sqlB = 'SELECT * FROM business_clients' . (count($whereB) ? ' WHERE ' . implode(' AND ', $whereB) : '') . ' ORDER BY id DESC';
    $stmtB = $pdo->prepare($sqlB);
    $stmtB->execute($paramsB);
    $businessClients = $stmtB->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table business_clients absente ou autre erreur
    $businessClients = [];
}

// Fin de la récupération des données (filtres et consommation appliqués)
?>
    <!-- Filtres clients -->
    <form class="clients-filters" action="" method="get">
        <input type="hidden" name="section" value="clients">
        <label>De: <input type="date" name="filter_from" value="<?= htmlspecialchars($filter_from ?? '') ?>"></label>
        <label>À: <input type="date" name="filter_to" value="<?= htmlspecialchars($filter_to ?? '') ?>"></label>
        <label>Email:
            <select name="filter_email">
                <option value=""<?= $filter_email === '' ? ' selected' : '' ?>>Tous</option>
                <option value="1"<?= $filter_email === '1' ? ' selected' : '' ?>>Avec</option>
                <option value="0"<?= $filter_email === '0' ? ' selected' : '' ?>>Sans</option>
            </select>
        </label>
    <label>Statut:
            <select name="filter_status">
                <option value=""<?= $filter_status === '' ? ' selected' : '' ?>>Tous</option>
                <option value="actif"<?= $filter_status === 'actif' ? ' selected' : '' ?>>Actif</option>
                <option value="inactif"<?= $filter_status === 'inactif' ? ' selected' : '' ?>>Inactif</option>
            </select>
    </label>
    <label>Consommation min (CFA): <input type="number" name="filter_min_cons" value="<?= htmlspecialchars($filter_min_cons ?? '') ?>"></label>
    <label>Consommation max (CFA): <input type="number" name="filter_max_cons" value="<?= htmlspecialchars($filter_max_cons ?? '') ?>"></label>
    <button type="submit">Filtrer</button>
</form>
<?php
// Statistiques
$totalClients = count($privateClients) + count($businessClients);
$clientsWithEmail = count(array_filter($privateClients, fn($c) => !empty($c['email']))) + count(array_filter($businessClients, fn($c) => !empty($c['contact_email'])));
$newClientsThisMonth = count(array_filter($privateClients, fn($c) => isset($c['date_creation']) && date('Y-m', strtotime($c['date_creation'])) === date('Y-m')));
// AJAX endpoint for clients particuliers update
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($privateClients);
    exit;
}
?>
<?php
// Affichage du profil client si demandé
if (isset($_GET['view_client'])) {
    $id = (int) $_GET['view_client'];
    $client = null; $type = '';
    foreach ($privateClients as $c) if ($c['id'] == $id) { $client = $c; $type = 'Particulier'; }
    foreach ($businessClients as $c) if ($c['id'] == $id) { $client = $c; $type = 'Business'; }
    ?>
    <div class="header fade-in">
        <h1><i class="fas fa-user-circle"></i> Profil client</h1>
        <p>Type: <?= htmlspecialchars($type) ?></p>
    </div>
    <div class="glass-card fade-in" style="padding: var(--space-6);">
        <h2><?= htmlspecialchars(($client['prenoms'] ?? '') . ' ' . ($client['nom'] ?? '')) ?></h2>
        <div class="detail-row"><i class="fas fa-phone"></i> <?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></div>
        <div class="detail-row"><i class="fas fa-envelope"></i> <?= !empty($client['email']) ? htmlspecialchars($client['email']) : 'Non renseigné' ?></div>
        <?php if ($type === 'Business'): ?>
            <div class="detail-row"><i class="fas fa-building"></i> <?= htmlspecialchars($client['nom_entreprise'] ?? '') ?></div>
            <div class="detail-row"><i class="fas fa-user-tie"></i> Contact: <?= htmlspecialchars($client['contact_nom'] ?? '') ?></div>
        <?php endif; ?>
        <div class="detail-row"><i class="fas fa-calendar-alt"></i> Inscription: <?= date('d/m/Y', strtotime($client['date_creation'] ?? 'now')) ?></div>
        <!-- Place for order history if available -->
        <div class="client-actions" style="margin-top: var(--space-4);">
            <a href="?section=clients" class="btn-suzosky"><i class="fas fa-arrow-left"></i> Retour</a>
        </div>
    </div>
    <?php
    return;
}
?>
<style>
/* === DESIGN SYSTEM SUZOSKY - GESTION CLIENTS === */
:root {
    /* Variables identiques à coursier.php et chat.php */
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --warning-color: #ffc107;
    --danger-color: #E94560;
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
}
/* === FILTRES CLIENTS === */
.clients-filters {
    margin-bottom: var(--space-6);
    display: flex;
    gap: var(--space-4);
    align-items: center;
    flex-wrap: wrap;
}
.clients-filters label {
    display: flex;
    align-items: center;
    gap: 4px;
}
.clients-filters input,
.clients-filters select {
    padding: 6px 8px;
    border-radius: 6px;
    border: 1px solid var(--glass-border);
    background: var(--glass-bg);
    color: #fff;
}
                        <th>Statut</th>
    background: var(--gradient-gold);
    color: var(--primary-dark);
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

/* === HERO SECTION CLIENTS === */
.clients-hero {
                        <td>
                            <select onchange="updateStatus('business', <?= $client['id'] ?>, this.value)">
                                <option value="actif"<?= $client['statut'] === 'actif' ? ' selected' : '' ?>>Actif</option>
                                <option value="inactif"<?= $client['statut'] === 'inactif' ? ' selected' : '' ?>>Inactif</option>
                            </select>
                        </td>
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.clients-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-gold);
}

.hero-content h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    font-family: 'Montserrat', sans-serif;
}

.hero-content p {
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
    margin-bottom: 20px;
    font-weight: 500;
}

.hero-stats {
    display: flex;
    gap: 30px;
}

.hero-stat .stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
}

.hero-stat .stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* === STATISTIQUES CLIENTS (mini cartes) === */
.stats-grid {
    display: flex;
    justify-content: space-between;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
}
.client-stat-card {
    flex: 1;
    background: var(--glass-subtle);
    border-radius: 10px;
    padding: var(--space-3);
    text-align: center;
    border: 1px solid var(--glass-border);
    transition: all var(--duration-normal) var(--ease-standard);
}
.client-stat-card:hover {
    transform: translateY(-2px);
}
.client-stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary-gold);
}
.client-stat-label {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.7);
    text-transform: uppercase;
    margin-top: var(--space-1);
}

.clients-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.client-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 20px;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
    position: relative;
    overflow: hidden;
}

.client-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-gold);
}

.client-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(212, 168, 83, 0.2);
    border-color: var(--primary-gold);
}

.client-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.client-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--gradient-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-dark);
    font-weight: 700;
    font-size: 1.2rem;
}

.client-info h3 {
    color: #FFFFFF;
    font-weight: 700;
    font-size: 1rem;
    margin-bottom: 3px;
    font-family: 'Montserrat', sans-serif;
}

.client-type {
    color: var(--primary-gold);
    font-size: 0.8rem;
    font-weight: 600;
    padding: 2px 8px;
    background: rgba(212, 168, 83, 0.2);
    border-radius: 8px;
}

.client-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px;
    background: rgba(255,255,255,0.03);
    border-radius: 6px;
}

.detail-icon {
    color: var(--primary-gold);
    width: 16px;
    font-size: 0.85rem;
}

.detail-text {
    color: rgba(255,255,255,0.8);
    font-size: 0.85rem;
    font-weight: 500;
}

.client-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 15px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.75rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.btn-view {
    background: rgba(59, 130, 246, 0.2);
    color: #3B82F6;
    border: 1px solid #3B82F6;
}

.btn-view:hover {
    background: #3B82F6;
    color: white;
    transform: translateY(-2px);
}

.btn-contact {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.btn-contact:hover {
    background: var(--success-color);
    color: white;
    transform: translateY(-2px);
}

.btn-delete {
    background: rgba(233, 69, 96, 0.2);
    color: var(--accent-red);
    border: 1px solid var(--accent-red);
}

.btn-delete:hover {
    background: var(--accent-red);
    color: white;
    transform: translateY(-2px);
}

/* === BOUTONS PRINCIPAUX === */
.btn-suzosky {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border: none;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.btn-suzosky:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
    text-decoration: none;
    color: var(--primary-dark);
}

/* === TABS SUZOSKY === */
.tabs-container {
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border-radius: 20px;
    padding: var(--space-6);
    border: 1px solid var(--glass-border);
    box-shadow: var(--glass-shadow);
    margin-bottom: var(--space-8);
}
.tab-buttons {
    display: flex;
    gap: var(--space-2);
    margin-bottom: var(--space-8);
    background: rgba(255, 255, 255, 0.05);
    padding: var(--space-2);
    border-radius: 16px;
    backdrop-filter: blur(10px);
}
.tab-button {
    flex: 1;
    background: transparent;
    border: none;
    padding: var(--space-4) var(--space-6);
    border-radius: 12px;
    color: rgba(255, 255, 255, 0.8);
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all var(--duration-normal) var(--ease-standard);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
}
.tab-button:hover {
    color: var(--primary-gold);
    background: rgba(212, 168, 83, 0.1);
    transform: translateY(-2px);
}
.tab-button.active {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    box-shadow: var(--shadow-gold);
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .clients-hero {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 15px;
    }
    
    .clients-grid {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .clients-tabs {
        flex-direction: column;
    }
}

/* === HERO SECTION CLIENTS === */
.clients-hero {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.clients-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-gold);
}

.hero-content h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    font-family: 'Montserrat', sans-serif;
}

.hero-content p {
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
    margin-bottom: 20px;
    font-weight: 500;
}

.hero-stats {
    display: flex;
    gap: 30px;
}

.hero-stat {
    text-align: center;
}

.hero-stat .stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
}

.hero-stat .stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero-decoration {
    font-size: 4rem;
    color: var(--primary-gold);
    opacity: 0.3;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* === ONGLETS CLIENTS === */
.clients-tabs {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    margin-bottom: 30px;
}

.clients-tabs-header {
    display: flex;
    background: rgba(255,255,255,0.05);
    border-bottom: 1px solid var(--glass-border);
}

.client-tab-button {
    flex: 1;
    padding: 15px 20px;
    background: none;
    border: none;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    font-family: 'Montserrat', sans-serif;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.client-tab-button.active {
    color: var(--primary-gold);
    background: rgba(212, 168, 83, 0.1);
}

.client-tab-button.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-gold);
}

.client-tab-button:hover:not(.active) {
    color: rgba(255,255,255,0.9);
    background: rgba(255,255,255,0.03);
}

.clients-tab-content {
    padding: 30px;
}

.client-tab-pane {
    display: none;
}

.client-tab-pane.active {
    display: block;
}

/* === STATISTIQUES CLIENTS === */
.clients-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.client-stat-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.client-stat-card:hover {
    background: rgba(255,255,255,0.08);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
}

.client-stat-icon {
    width: 50px;
    height: 50px;
    margin: 0 auto 15px;
    background: var(--gradient-gold);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-dark);
    font-size: 1.5rem;
}

.client-stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    margin-bottom: 5px;
    font-family: 'Montserrat', sans-serif;
}

.client-stat-label {
    color: rgba(255,255,255,0.8);
    font-size: 0.9rem;
    font-weight: 600;
}

/* === HEADER ACTIONS === */
.clients-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.clients-header h3 {
    color: var(--primary-gold);
    font-size: 1.3rem;
    font-weight: 700;
    font-family: 'Montserrat', sans-serif;
}

.clients-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}

.search-box {
    position: relative;
}

.search-input {
    background: var(--glass-bg);
    border: 2px solid var(--glass-border);
    border-radius: 12px;
    padding: 10px 15px 10px 40px;
    color: #FFFFFF;
    font-family: 'Montserrat', sans-serif;
    font-size: 0.9rem;
    width: 250px;
    transition: all 0.3s ease;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-gold);
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.2);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary-gold);
}

.btn-primary {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border: none;
    border-radius: 12px;
    padding: 10px 20px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Montserrat', sans-serif;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(212, 168, 83, 0.4);
}

/* === TABLEAU CLIENTS === */
.clients-table-container {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.clients-table {
    width: 100%;
    border-collapse: collapse;
}

.clients-table th {
    background: rgba(255,255,255,0.08);
    color: var(--primary-gold);
    padding: 15px;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--glass-border);
}

.clients-table td {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.9);
    font-weight: 500;
}

.clients-table tr:hover {
    background: rgba(255,255,255,0.05);
}

.client-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--gradient-gold);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-dark);
    font-weight: 700;
    margin-right: 10px;
}

.client-name {
    display: flex;
    align-items: center;
}

.client-info {
    display: flex;
    flex-direction: column;
}

.client-email {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.6);
}

.status-badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-active {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
}

.status-inactive {
    background: rgba(255, 193, 7, 0.2);
    color: var(--warning-color);
}

.actions-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 12px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.btn-view {
    background: rgba(59, 130, 246, 0.2);
    color: #3B82F6;
}

.btn-edit {
    background: rgba(255, 193, 7, 0.2);
    color: var(--warning-color);
}

.btn-delete {
    background: rgba(233, 69, 96, 0.2);
    color: var(--danger-color);
}

.btn-action:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .clients-hero {
        flex-direction: column;
        text-align: center;
        gap: 20px;
    }
    
    .hero-stats {
        flex-direction: column;
        gap: 15px;
    }
    
    .clients-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .clients-actions {
        flex-direction: column;
    }
    
    .search-input {
        width: 100%;
    }
    
    .clients-table {
        font-size: 0.8rem;
    }
    
    .clients-table th,
    .clients-table td {
        padding: 10px;
    }
}
</style>

<!-- Hero Section Clients Suzosky -->
<div class="clients-hero">
    <div class="hero-content">
        <h1><i class="fas fa-users"></i> Gestion des Clients Suzosky</h1>
        <p>Gérez votre base de clients particuliers et business avec l'interface premium</p>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="stat-value"><?= $totalClients ?></span>
                <span class="stat-label">Total Clients</span>
            </div>
            <div class="hero-stat">
                <span class="stat-value"><?= $newClientsThisMonth ?></span>
                <span class="stat-label">Nouveaux ce mois</span>
            </div>
        </div>
    </div>
    <div class="hero-decoration">
        <i class="fas fa-user-friends"></i>
    </div>
</div>

    <!-- Statistiques clients -->
    <div class="stats-grid">
        <div class="client-stat-card">
            <div class="client-stat-number"><?= count($privateClients) ?></div>
            <div class="client-stat-label">Particuliers</div>
        </div>
        <div class="client-stat-card">
            <div class="client-stat-number"><?= count($businessClients) ?></div>
            <div class="client-stat-label">Business</div>
        </div>
        <div class="client-stat-card">
            <div class="client-stat-number"><?= $clientsWithEmail ?></div>
            <div class="client-stat-label">Avec Email</div>
        </div>
        <div class="client-stat-card">
            <div class="client-stat-number"><?= $newClientsThisMonth ?></div>
            <div class="client-stat-label">Ce Mois</div>
        </div>
    </div>


    <!-- Onglets clients -->
    <div class="tabs-container fade-in">
        <div class="tab-buttons">
            <button class="tab-button active" onclick="showTab('clients-private')">
                <i class="fas fa-user"></i> Clients Particuliers
            </button>
            <button class="tab-button" onclick="showTab('clients-business')">
                <i class="fas fa-building"></i> Clients Business
            </button>
        </div>
            <div id="clients-private" class="tab-content">
            <!-- Table clients Particuliers -->
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Nom, Prénoms</th>
                        <th>Tél</th>
                        <th>Dernière Connexion</th>
                        <th>Email</th>
                        <th>Conso (CFA)</th>
                        <th>Voir</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($privateClients as $client): ?>
                    <tr>
                        <td><?= htmlspecialchars(($client['nom'] ?? '') . ' ' . ($client['prenoms'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($client['telephone'] ?? 'Non renseigné') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($client['date_creation'] ?? 'now')) ?></td>
                        <td><?= !empty($client['email']) ? htmlspecialchars($client['email']) : 'Non renseigné' ?></td>
                        <td><?= number_format($client['consommation'] ?? 0, 0, ',', ' ') ?></td>
                        <td><a href="?section=clients&view_client=<?= $client['id'] ?>" class="btn-secondary"><i class="fas fa-eye"></i> Voir</a></td>
                        <td>
                            <select onchange="updateStatus('private', <?= $client['id'] ?>, this.value)">
                                <option value="actif"<?= $client['statut'] === 'actif' ? ' selected' : '' ?>>Actif</option>
                                <option value="inactif"<?= $client['statut'] === 'inactif' ? ' selected' : '' ?>>Inactif</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div id="clients-business" class="tab-content" style="display:none;">
            <!-- Table clients Business -->
            <table class="clients-table">
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Tél</th>
                        <th>Dernière Connexion</th>
                        <th>Email</th>
                        <th>Conso (CFA)</th>
                        <th>Voir</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($businessClients as $client): ?>
                    <tr>
                        <td><?= htmlspecialchars($client['nom_entreprise'] ?? '') ?></td>
                        <td><?= htmlspecialchars($client['contact_telephone'] ?? 'Non renseigné') ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($client['date_creation'] ?? 'now')) ?></td>
                        <td><?= htmlspecialchars($client['contact_email'] ?? $client['email'] ?? 'Non renseigné') ?></td>
                        <td>-</td>
                        <td><a href="?section=clients&view_client=<?= $client['id'] ?>" class="btn-secondary"><i class="fas fa-eye"></i> Voir</a></td>
                        <td>
                            <select onchange="updateStatus('business', <?= $client['id'] ?>, this.value)">
                                <option value="actif"<?= $client['statut'] === 'actif' ? ' selected' : '' ?>>Actif</option>
                                <option value="inactif"<?= $client['statut'] === 'inactif' ? ' selected' : '' ?>>Inactif</option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // === GESTION DES TABS ===
        function showTab(tabId) {
            // Masquer tous les contenus
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Désactiver tous les boutons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Afficher le contenu sélectionné
            document.getElementById(tabId).style.display = 'block';
            
            // Activer le bouton correspondant
            event.target.classList.add('active');
        }

        // === ANIMATIONS ET INTERACTIONS ===
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des cartes au chargement
            const cards = document.querySelectorAll('.client-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s cubic-bezier(0, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Effet parallax subtil pour les cartes
            window.addEventListener('scroll', function() {
                const scrolled = window.pageYOffset;
                const parallaxElements = document.querySelectorAll('.client-card');
                
                parallaxElements.forEach((element, index) => {
                    const speed = 0.02;
                    const yPos = -(scrolled * speed * (index % 3 + 1));
                    element.style.transform = `translateY(${yPos}px)`;
                });
            });

            // Gestion des hover states avancés
            const clientCards = document.querySelectorAll('.client-card');
            clientCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    // Effet de lueur dorée progressive
                    this.style.boxShadow = '0 8px 32px rgba(212, 168, 83, 0.4), 0 0 0 1px rgba(212, 168, 83, 0.5)';
                    
                    // Animation des éléments internes
                    const avatar = this.querySelector('.client-avatar');
                    if (avatar) {
                        avatar.style.transform = 'scale(1.1) rotate(5deg)';
                        avatar.style.boxShadow = '0 6px 20px rgba(212, 168, 83, 0.6)';
                    }
                    
                    const details = this.querySelectorAll('.detail-row');
                    details.forEach((detail, index) => {
                        setTimeout(() => {
                            detail.style.transform = 'translateX(5px)';
                        }, index * 50);
                    });
                });
                
                card.addEventListener('mouseleave', function() {
                    // Retour à l'état normal
                    this.style.boxShadow = '';
                    
                    const avatar = this.querySelector('.client-avatar');
                    if (avatar) {
                        avatar.style.transform = '';
                        avatar.style.boxShadow = '';
                    }
                    
                    const details = this.querySelectorAll('.detail-row');
                    details.forEach(detail => {
                        detail.style.transform = '';
                    });
                });
            });

            // Effet de particules dorées au survol des stats
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    createGoldenParticles(this);
                });
            });

            function createGoldenParticles(element) {
                for (let i = 0; i < 5; i++) {
                    const particle = document.createElement('div');
                    particle.style.position = 'absolute';
                    particle.style.width = '4px';
                    particle.style.height = '4px';
                    particle.style.background = '#D4A853';
                    particle.style.borderRadius = '50%';
                    particle.style.pointerEvents = 'none';
                    particle.style.zIndex = '1000';
                    
                    const rect = element.getBoundingClientRect();
                    particle.style.left = (rect.left + Math.random() * rect.width) + 'px';
                    particle.style.top = (rect.top + Math.random() * rect.height) + 'px';
                    
                    document.body.appendChild(particle);
                    
                    // Animation des particules
                    particle.animate([
                        { opacity: 1, transform: 'translateY(0px) scale(1)' },
                        { opacity: 0, transform: 'translateY(-50px) scale(0)' }
                    ], {
                        duration: 1000,
                        easing: 'cubic-bezier(0, 0, 0.2, 1)'
                    }).onfinish = () => particle.remove();
                }
            }

            // Gestion du scroll intelligent pour révéler les éléments
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Observer les éléments qui arrivent tard
            document.querySelectorAll('.mini-stat, .badge').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                el.style.transition = 'all 0.6s cubic-bezier(0, 0, 0.2, 1)';
                observer.observe(el);
            });

            // Polling pour actualiser la liste clients particuliers
            setInterval(() => {
                // Appel AJAX vers le script de mise à jour des clients (relative au dossier admin)
                fetch('ajax_clients.php')
                    .then(res => {
                        if (!res.ok) throw new Error('Erreur ' + res.status);
                        return res.text();
                    })
                    .then(html => {
                        const container = document.querySelector('#clients-private .clients-grid');
                        if (container) container.innerHTML = html;
                    })
                    .catch(err => console.error('Échec chargement clients :', err));
            }, 5000);
        });
    </script>
</body>
</html>
