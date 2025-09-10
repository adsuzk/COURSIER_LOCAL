<?php
/**
 * SUZOSKY ADMIN - GESTION DES COMMANDES EN TEMPS RÉEL
 * Interface d'administration pour suivi des commandes et coursiers
 * UI/UX conforme au design system Suzosky (colors de coursier.php)
 */

// Vérifier l'authentification admin
if (!checkAdminAuth()) {
    header('Location: admin.php');
    exit;
}

require_once __DIR__ . '/../config.php';

// Récupérer les commandes avec les informations des coursiers
function getCommandesWithCouriers() {
    $pdo = getDBConnection();
    try {
        // Détecter les colonnes de la table commandes
        $cols = $pdo->query("SHOW COLUMNS FROM commandes")->fetchAll(PDO::FETCH_COLUMN);
        // Numéro de commande si existant
        $orderNumCol = in_array('numero_commande', $cols) ? 'numero_commande' : 
                       (in_array('order_number', $cols) ? 'order_number' : 'code_commande');
        // Choisir le champ pour trier chronologiquement
        $orderBy = in_array('created_at', $cols) ? 'c.created_at DESC, c.id DESC' : 'c.id DESC';
        
        $sql = "
        SELECT 
            c.*,
            a.nom as coursier_nom,
            a.prenoms as coursier_prenoms,
            a.telephone as coursier_telephone,
            a.statut_connexion,
            a.latitude as coursier_lat,
            a.longitude as coursier_lng,
            cl.nom as client_nom,
            cl.telephone as client_telephone
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id_coursier
        LEFT JOIN clients cl ON c.client_id = cl.id
        ORDER BY $orderBy
        LIMIT 50
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback si agents_suzosky manquante : commandes sans coursier
        $orderByFb = in_array('created_at', $cols) ? 'c.created_at DESC, c.id DESC' : 'c.id DESC';
        $sqlFallback = "
        SELECT 
            c.*,
            '' as coursier_nom,
            '' as coursier_prenoms,
            '' as coursier_telephone,
            '' as statut_connexion,
            0 as coursier_lat,
            0 as coursier_lng,
            cl.nom as client_nom,
            cl.telephone as client_telephone
        FROM commandes c
        LEFT JOIN clients cl ON c.client_id = cl.id
        ORDER BY $orderByFb
        LIMIT 50
        ";
        $stmt = $pdo->prepare($sqlFallback);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Récupérer les coursiers actifs
function getActiveCouriers() {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id_coursier, nom, prenoms, telephone, statut_connexion, 
               latitude, longitude, derniere_position
        FROM agents_suzosky 
        WHERE statut = 'actif' 
        ORDER BY statut_connexion DESC, derniere_position DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$commandes = getCommandesWithCouriers();
$coursiers = getActiveCouriers();
?>

<style>
/* DESIGN SYSTEM SUZOSKY - ADMIN INTERFACE */
:root {
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
    --warning-color: #ffc107;
    --danger-color: #E94560;
}

.commandes-container {
    background: var(--glass-bg);
    backdrop-filter: blur(15px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 30px;
    margin: 20px 0;
    box-shadow: 0 10px 50px rgba(0,0,0,0.3);
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--primary-gold);
}

.section-title {
    font-size: 28px;
    font-weight: 800;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: flex;
    align-items: center;
    gap: 15px;
}

.real-time-badge {
    background: var(--success-color);
    color: white;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    animation: pulse-dot 1s infinite;
}

@keyframes pulse-dot {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(212,168,83,0.2);
}

.stat-number {
    font-size: 32px;
    font-weight: 900;
    color: var(--primary-gold);
    display: block;
    margin-bottom: 8px;
}

.stat-label {
    color: rgba(255,255,255,0.8);
    font-size: 14px;
    font-weight: 600;
}

.commandes-grid {
    display: grid;
    gap: 20px;
    margin-bottom: 30px;
}

.commande-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 25px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.commande-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-gold);
}

.commande-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.2);
}

.commande-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.commande-number {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-gold);
}

.commande-status {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-nouvelle { background: var(--warning-color); color: var(--primary-dark); }
.status-assignee { background: var(--accent-blue); color: white; }
.status-en_cours { background: var(--success-color); color: white; }
.status-livree { background: var(--primary-gold); color: var(--primary-dark); }
.status-annulee { background: var(--danger-color); color: white; }

.commande-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.detail-section h4 {
    color: var(--primary-gold);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
    text-transform: uppercase;
}

.detail-section p {
    color: rgba(255,255,255,0.9);
    font-size: 13px;
    line-height: 1.4;
    margin-bottom: 5px;
}

.coursier-info {
    background: rgba(212,168,83,0.1);
    border: 1px solid var(--primary-gold);
    border-radius: 10px;
    padding: 15px;
    margin-top: 15px;
}

.coursier-info.non-assigne {
    background: rgba(233,69,96,0.1);
    border-color: var(--danger-color);
}

.coursier-name {
    font-weight: 700;
    color: var(--primary-gold);
    font-size: 16px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.connexion-status {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.en_ligne { background: var(--success-color); color: white; }
.hors_ligne { background: var(--danger-color); color: white; }

.coursiers-actifs {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 20px;
    margin-top: 30px;
}

.coursiers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.coursier-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
}

.coursier-card.actif {
    border-color: var(--success-color);
    box-shadow: 0 0 15px rgba(39,174,96,0.2);
}

.refresh-btn {
    background: var(--gradient-gold);
    color: var(--primary-dark);
    border: none;
    padding: 12px 24px;
    border-radius: 25px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.refresh-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(212,168,83,0.3);
}

.timeline-container {
    background: rgba(255,255,255,0.03);
    border-radius: 10px;
    padding: 15px;
    margin-top: 15px;
}

.timeline-step {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
    border-left: 2px solid var(--glass-border);
    padding-left: 15px;
    margin-left: 8px;
    position: relative;
}

.timeline-step.active {
    border-left-color: var(--primary-gold);
}

.timeline-step::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 50%;
    transform: translateY(-50%);
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--glass-border);
}

.timeline-step.active::before {
    background: var(--primary-gold);
    box-shadow: 0 0 10px var(--primary-gold);
}

.timeline-text {
    font-size: 12px;
    color: rgba(255,255,255,0.7);
}

.timeline-step.active .timeline-text {
    color: var(--primary-gold);
    font-weight: 600;
}

.map-container {
    height: 200px;
    border-radius: 10px;
    background: var(--primary-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.5);
    margin-top: 15px;
}
</style>

<div class="commandes-container">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-shipping-fast"></i>
            Gestion des Commandes
        </h2>
        <div class="real-time-badge">
            <div class="pulse-dot"></div>
            Temps Réel
        </div>
    </div>

    <!-- Statistiques rapides -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-number"><?= count($commandes) ?></span>
            <span class="stat-label">Total Commandes</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= count(array_filter($commandes, fn($c) => !empty($c['coursier_id']))) ?></span>
            <span class="stat-label">Assignées</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= count(array_filter($coursiers, fn($c) => $c['statut_connexion'] === 'en_ligne')) ?></span>
            <span class="stat-label">Coursiers Actifs</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= count(array_filter($commandes, fn($c) => ($c['statut'] ?? 'nouvelle') === 'en_cours')) ?></span>
            <span class="stat-label">En Livraison</span>
        </div>
    </div>

    <!-- Actions rapides -->
    <div style="text-align: right; margin-bottom: 20px;">
        <button class="refresh-btn" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i>
            Actualiser
        </button>
    </div>

    <!-- Liste des commandes -->
    <div class="commandes-grid">
        <?php if (empty($commandes)): ?>
            <div style="text-align: center; padding: 40px; color: rgba(255,255,255,0.6);">
                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                <p>Aucune commande pour le moment</p>
            </div>
        <?php else: ?>
            <?php foreach ($commandes as $commande): ?>
                <div class="commande-card">
                    <div class="commande-header">
                        <div class="commande-number">
                            #<?= htmlspecialchars($commande['order_number'] ?? $commande['numero_commande'] ?? $commande['code_commande'] ?? 'N/A') ?>
                        </div>
                        <div class="commande-status status-<?= htmlspecialchars($commande['statut'] ?? 'nouvelle') ?>">
                            <?= htmlspecialchars($commande['statut'] ?? 'nouvelle') ?>
                        </div>
                    </div>

                    <div class="commande-details">
                        <div class="detail-section">
                            <h4><i class="fas fa-map-marker-alt"></i> Itinéraire</h4>
                            <p><strong>Départ:</strong> <?= htmlspecialchars($commande['adresse_depart'] ?? 'N/A') ?></p>
                            <p><strong>Arrivée:</strong> <?= htmlspecialchars($commande['adresse_arrivee'] ?? 'N/A') ?></p>
                            <p><strong>Priorité:</strong> <?= htmlspecialchars($commande['priorite'] ?? 'normale') ?></p>
                            <!-- Estimation détaillée admin -->
                            <div class="admin-price-estimation" data-departure="<?= htmlspecialchars($commande['adresse_depart'] ?? '') ?>" 
                                 data-destination="<?= htmlspecialchars($commande['adresse_arrivee'] ?? '') ?>" 
                                 data-priority="<?= htmlspecialchars($commande['priorite'] ?? 'normale') ?>">
                                <button onclick="loadPriceEstimation(this)" class="btn-estimate" style="
                                    background: var(--primary-gold); 
                                    color: #1A1A2E; 
                                    border: none; 
                                    padding: 6px 12px; 
                                    border-radius: 6px; 
                                    font-size: 0.8rem; 
                                    cursor: pointer;
                                    margin-top: 8px;
                                ">
                                    📊 Voir estimation détaillée
                                </button>
                                <div class="estimation-result"></div>
                            </div>
                        </div>
                        <div class="detail-section">
                            <h4><i class="fas fa-user"></i> Client</h4>
                            <p><strong>Nom:</strong> <?= htmlspecialchars($commande['client_nom'] ?? 'N/A') ?></p>
                            <p><strong>Tél:</strong> <?= htmlspecialchars($commande['client_telephone'] ?? $commande['telephone_expediteur'] ?? 'N/A') ?></p>
                            <p><strong>Prix:</strong> <?= number_format($commande['prix_estime'] ?? 0, 0, ',', ' ') ?> FCFA</p>
                        </div>
                    </div>

                    <!-- Informations coursier -->
                    <?php if (!empty($commande['coursier_id']) && !empty($commande['coursier_nom'])): ?>
                        <div class="coursier-info">
                            <div class="coursier-name">
                                <i class="fas fa-motorcycle"></i>
                                <?= htmlspecialchars($commande['coursier_prenoms'] . ' ' . $commande['coursier_nom']) ?>
                                <span class="connexion-status <?= htmlspecialchars($commande['statut_connexion'] ?? 'hors_ligne') ?>">
                                    <?= htmlspecialchars($commande['statut_connexion'] ?? 'hors_ligne') ?>
                                </span>
                            </div>
                            <p style="color: rgba(255,255,255,0.8); font-size: 13px;">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($commande['coursier_telephone'] ?? 'N/A') ?>
                            </p>
                            
                            <!-- Timeline de livraison -->
                            <div class="timeline-container">
                                <div class="timeline-step active">
                                    <div class="timeline-text">Commande créée</div>
                                </div>
                                <div class="timeline-step <?= !empty($commande['coursier_id']) ? 'active' : '' ?>">
                                    <div class="timeline-text">Coursier assigné</div>
                                </div>
                                <div class="timeline-step <?= ($commande['statut'] ?? '') === 'en_cours' ? 'active' : '' ?>">
                                    <div class="timeline-text">En cours de livraison</div>
                                </div>
                                <div class="timeline-step <?= ($commande['statut'] ?? '') === 'livree' ? 'active' : '' ?>">
                                    <div class="timeline-text">Livraison terminée</div>
                                </div>
                            </div>

                            <!-- Mini carte (placeholder) -->
                            <div class="map-container">
                                <i class="fas fa-map"></i>
                                <span style="margin-left: 8px;">Localisation du coursier</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="coursier-info non-assigne">
                            <div style="color: var(--danger-color); font-weight: 600; text-align: center;">
                                <i class="fas fa-exclamation-triangle"></i>
                                Aucun coursier assigné
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="text-align: right; margin-top: 15px; font-size: 11px; color: rgba(255,255,255,0.5);">
                        Créée le <?= date('d/m/Y à H:i', strtotime($commande['created_at'] ?? 'now')) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Section coursiers actifs -->
<div class="coursiers-actifs">
    <h3 style="color: var(--primary-gold); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-users"></i>
        Coursiers Actifs (<?= count($coursiers) ?>)
    </h3>
    
    <div class="coursiers-grid">
        <?php foreach ($coursiers as $coursier): ?>
            <div class="coursier-card <?= $coursier['statut_connexion'] === 'en_ligne' ? 'actif' : '' ?>">
                <div style="font-weight: 600; color: var(--primary-gold); margin-bottom: 8px;">
                    <?= htmlspecialchars($coursier['prenoms'] . ' ' . $coursier['nom']) ?>
                </div>
                <div style="font-size: 12px; color: rgba(255,255,255,0.7); margin-bottom: 8px;">
                    <?= htmlspecialchars($coursier['telephone']) ?>
                </div>
                <div class="connexion-status <?= htmlspecialchars($coursier['statut_connexion'] ?? 'hors_ligne') ?>">
                    <?= htmlspecialchars($coursier['statut_connexion'] ?? 'hors_ligne') ?>
                </div>
                <?php if (!empty($coursier['derniere_position'])): ?>
                    <div style="font-size: 10px; color: rgba(255,255,255,0.5); margin-top: 5px;">
                        Dernière position: <?= date('H:i', strtotime($coursier['derniere_position'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// Auto-refresh toutes les 30 secondes
setInterval(() => {
    location.reload();
}, 30000);

// Notification sonore pour nouvelles commandes
let lastCommandCount = <?= count($commandes) ?>;

// Fonction pour charger l'estimation de prix détaillée
function loadPriceEstimation(button) {
    const container = button.closest('.admin-price-estimation');
    const departure = container.dataset.departure;
    const destination = container.dataset.destination;
    const priority = container.dataset.priority;
    const resultDiv = container.querySelector('.estimation-result');
    
    if (!departure || !destination) {
        resultDiv.innerHTML = '<div style="color: #E94560;">Adresses manquantes</div>';
        return;
    }
    
    button.disabled = true;
    button.textContent = '⏳ Calcul...';
    resultDiv.innerHTML = '<div style="color: #D4A853;">Calcul en cours...</div>';
    
    // Utiliser la fonction admin de calcul
    calculatePriceAdmin(departure, destination, priority)
        .then(priceData => {
            resultDiv.innerHTML = generateAdminPriceDisplay(priceData);
            button.style.display = 'none';
        })
        .catch(error => {
            console.error('Erreur calcul prix:', error);
            resultDiv.innerHTML = `<div style="color: #E94560;">Erreur: ${error}</div>`;
            button.disabled = false;
            button.textContent = '📊 Réessayer';
        });
}

// Exposer la fonction globalement
window.loadPriceEstimation = loadPriceEstimation;

function checkNewCommands() {
    fetch('<?= $_SERVER['PHP_SELF'] ?>?section=commandes&ajax=1')
        .then(response => response.json())
        .then(data => {
            if (data.count > lastCommandCount) {
                // Nouvelle commande - notification
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('Nouvelle commande Suzosky!', {
                        body: 'Une nouvelle commande vient d\'être créée',
                        icon: '/assets/logo-suzosky.svg'
                    });
                }
                lastCommandCount = data.count;
            }
        })
        .catch(console.error);
}

// Demander permission notifications
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
}

// Vérifier nouvelles commandes toutes les 10 secondes
setInterval(checkNewCommands, 10000);
</script>
