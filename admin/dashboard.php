<?php
// Ne pas relancer session_start si elle est déjà active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
$pdo = getPDO();
// Statistiques dynamiques : seules connexions coursiers actives
try {
    $onlineCouriers = (int)$pdo->query("SELECT COUNT(*) FROM agents_suzosky WHERE type_poste='coursier' AND status='actif'")->fetchColumn();
} catch (PDOException $e) {
    // Table non trouvée en local ou autre erreur
    $onlineCouriers = 0;
}
// Désactivation des autres statistiques
// $agentCount, $ordersToday, $revenueToday, $clientsCount, $supportMessages

?>
<style>
/* === DESIGN SYSTEM SUZOSKY - DASHBOARD === */
:root {
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
    --space-2: 8px;
    --space-4: 16px;
    --space-6: 24px;
    --space-8: 32px;
}

/* === HERO SECTION DASHBOARD === */
.dashboard-hero {
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

.dashboard-hero::before {
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

.hero-decoration {
    font-size: 4rem;
    color: var(--primary-gold);
    opacity: 0.3;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* === GRILLE STATISTIQUES === */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* === CARTES STATISTIQUES === */
.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-gold);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(212, 168, 83, 0.2);
    border-color: var(--primary-gold);
}

.stat-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: var(--gradient-gold);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-dark);
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
}

.stat-info h3 {
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
}

.stat-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid var(--glass-border);
}

.stat-trend {
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-trend.positive {
    color: var(--success-color);
}

.stat-link {
    color: var(--primary-gold);
    text-decoration: none;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    padding: 5px;
    border-radius: 50%;
}

.stat-link:hover {
    background: rgba(212, 168, 83, 0.1);
    transform: translateX(3px);
}

/* === STATUS INDICATORS === */
.stat-status {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-status.online {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
}

.status-dot {
    width: 6px;
    height: 6px;
    background: var(--success-color);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* === SECTIONS ACTIONS RAPIDES === */
.quick-actions {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    color: var(--primary-gold);
    font-size: 1.3rem;
    font-weight: 700;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 10px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.action-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}

.action-card:hover {
    background: rgba(255,255,255,0.1);
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(212, 168, 83, 0.15);
}

.action-icon {
    font-size: 2rem;
    color: var(--primary-gold);
    margin-bottom: 10px;
}

.action-title {
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.action-desc {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.3;
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .dashboard-hero {
        flex-direction: column;
        text-align: center;
        padding: var(--space-6);
    }
    
    .hero-stats {
        justify-content: center;
        margin-top: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- Hero Section Dashboard Suzosky -->
<div class="dashboard-hero">
    <div class="hero-content">
        <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord Suzosky</h1>
        <p>Vue d'ensemble de votre plateforme de livraison premium</p>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="stat-value"><?= $onlineCouriers ?></span>
                <span class="stat-label">Coursiers Actifs</span>
            </div>
        </div>
    </div>
    <div class="hero-decoration">
        <i class="fas fa-shipping-fast"></i>
    </div>
</div> <!-- /.dashboard-hero -->

<?php return; // Stop further rendering, display only connections metric ?>
