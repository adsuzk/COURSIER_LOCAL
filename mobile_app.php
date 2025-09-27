<?php
/**
 * Interface mobile optimisée pour les coursiers
 * Version PWA légère de l'application coursier
 * Accessible via mobile web et installable
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

// Vérifier si c'est un accès mobile
$is_mobile = preg_match('/(android|iphone|ipad|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

// Headers pour PWA
$serviceWorkerScope = function_exists('getAppBasePath') ? getAppBasePath() : '/';
header('Service-Worker-Allowed: ' . $serviceWorkerScope);

$assetRoute = function (string $path) {
    $normalized = ltrim($path, '/');
    if (function_exists('routePath')) {
        return routePath($normalized);
    }
    return '/' . $normalized;
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Suzosky Coursier - App Mobile</title>
    
    <!-- PWA Meta Tags -->
    <meta name="description" content="Application mobile Suzosky Coursier - Gestion des livraisons">
    <meta name="theme-color" content="#D4A853">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Suzosky Coursier">
    
    <!-- Manifest PWA -->
    <link rel="manifest" href="<?= htmlspecialchars($assetRoute('manifest.json'), ENT_QUOTES) ?>">
    
    <!-- Icons -->
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($assetRoute('assets/favicon.svg'), ENT_QUOTES) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($assetRoute('assets/icon-192.svg'), ENT_QUOTES) ?>">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
    /* Variables CSS Suzosky */
    :root {
        --primary-gold: #D4A853;
        --primary-dark: #1A1A2E;
        --secondary-blue: #16213E;
        --accent-blue: #0F3460;
        --accent-red: #E94560;
        --success: #27AE60;
        --warning: #FFC107;
        --danger: #E94560;
        
        --glass-bg: rgba(255,255,255,0.08);
        --glass-border: rgba(255,255,255,0.2);
        --shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    }
    
    /* Reset et base */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Montserrat', sans-serif;
        background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-blue) 100%);
        min-height: 100vh;
        color: white;
        overflow-x: hidden;
        position: relative;
    }
    
    /* Effet de particules */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 80%, rgba(212, 168, 83, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(212, 168, 83, 0.08) 0%, transparent 50%);
        animation: float 20s ease-in-out infinite;
        pointer-events: none;
        z-index: -1;
    }
    
    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-20px); }
    }
    
    /* Header mobile */
    .mobile-header {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-border);
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 100;
        box-shadow: var(--shadow);
    }
    
    .logo {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--primary-gold);
    }
    
    .status-indicator {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 15px;
        border-radius: 20px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        font-size: 0.9rem;
    }
    
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--success);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    /* Navigation bottom */
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border-top: 1px solid var(--glass-border);
        display: flex;
        justify-content: space-around;
        padding: 12px 0;
        z-index: 100;
        box-shadow: 0 -5px 20px rgba(0,0,0,0.3);
    }
    
    .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        padding: 8px 12px;
        border-radius: 12px;
        transition: all 0.3s ease;
        text-decoration: none;
        color: rgba(255,255,255,0.7);
        min-width: 60px;
    }
    
    .nav-item.active,
    .nav-item:hover {
        background: var(--glass-bg);
        color: var(--primary-gold);
        transform: translateY(-2px);
    }
    
    .nav-item i {
        font-size: 1.2rem;
    }
    
    .nav-item span {
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    /* Contenu principal */
    .main-content {
        padding: 20px;
        padding-bottom: 100px; /* Espace pour la nav bottom */
        max-width: 400px;
        margin: 0 auto;
    }
    
    /* Cards glassmorphism */
    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(15px);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: var(--shadow);
    }
    
    /* Écran de connexion */
    .login-screen {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 200px);
        text-align: center;
    }
    
    .login-form {
        width: 100%;
        max-width: 320px;
    }
    
    .form-group {
        margin-bottom: 20px;
        text-align: left;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--primary-gold);
        font-weight: 600;
    }
    
    .form-group input {
        width: 100%;
        padding: 15px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        color: white;
        font-size: 16px;
        transition: all 0.3s ease;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: var(--primary-gold);
        box-shadow: 0 0 0 3px rgba(212, 168, 83, 0.2);
    }
    
    .btn {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, var(--primary-gold), #FFD700);
        border: none;
        border-radius: 12px;
        color: var(--primary-dark);
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(212, 168, 83, 0.4);
    }
    
    /* Dashboard */
    .stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 15px;
        text-align: center;
    }

    .stat-card.wallet-card {
        grid-column: 1 / -1;
        text-align: left;
        background: linear-gradient(135deg, rgba(212, 168, 83, 0.25), rgba(212, 168, 83, 0.1));
        border: 1px solid rgba(212, 168, 83, 0.6);
        box-shadow: 0 10px 30px rgba(212, 168, 83, 0.15);
    }

    .stat-card.wallet-card .stat-value {
        font-size: 2.2rem;
        color: #fff;
    }

    .stat-card.wallet-card .stat-label {
        color: rgba(255, 255, 255, 0.85);
        margin-bottom: 6px;
    }

    .stat-hint {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-top: 6px;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--primary-gold);
    }
    
    .stat-label {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-top: 5px;
    }
    
    /* Commandes */
    .order-card {
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .order-id {
        font-weight: 700;
        color: var(--primary-gold);
    }
    
    .order-status {
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    
    .status-en_cours { background: rgba(255, 193, 7, 0.2); color: #FFC107; }
    .status-recupere { background: rgba(59, 130, 246, 0.2); color: #3B82F6; }
    .status-livre { background: rgba(34, 197, 94, 0.2); color: #22C55E; }
    .status-termine { background: rgba(34, 197, 94, 0.3); color: #16A34A; }
    
    /* Responsive */
    @media (max-width: 480px) {
        .main-content {
            padding: 15px;
        }
        
        .stats-grid {
            gap: 10px;
        }
        
        .mobile-header {
            padding: 12px 15px;
        }
    }
    
    /* Styles pour écrans cachés */
    .screen {
        display: none;
    }
    
    .screen.active {
        display: block;
    }
    
    /* Boutons d'action rapide */
    .quick-actions {
        display: flex;
        gap: 10px;
        margin: 20px 0;
    }
    
    .quick-btn {
        flex: 1;
        padding: 12px;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        color: white;
        text-decoration: none;
        text-align: center;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .quick-btn:hover {
        background: var(--primary-gold);
        color: var(--primary-dark);
        transform: translateY(-2px);
    }
    </style>
</head>
<body>
    <!-- Header mobile -->
    <div class="mobile-header">
        <div class="logo">
            <i class="fas fa-shipping-fast"></i>
            <span>Suzosky Coursier</span>
        </div>
        <div class="status-indicator" id="statusIndicator">
            <span class="status-dot"></span>
            <span>Hors ligne</span>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <!-- Écran de connexion -->
        <div class="screen active" id="loginScreen">
            <div class="login-screen">
                <div class="glass-card">
                    <h2 style="margin-bottom: 20px; color: var(--primary-gold);">
                        <i class="fas fa-user-circle"></i> Connexion Coursier
                    </h2>
                    
                    <form class="login-form" onsubmit="handleLogin(event)">
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" id="loginPhone" placeholder="+225 XX XX XX XX XX" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" id="loginPassword" placeholder="••••••••" required>
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Dashboard -->
        <div class="screen" id="dashboardScreen">
            <div class="glass-card">
                <h3 style="margin-bottom: 15px; color: var(--primary-gold);">
                    <i class="fas fa-tachometer-alt"></i> Tableau de bord
                </h3>
                
                <div class="stats-grid">
                    <div class="stat-card wallet-card">
                        <div class="stat-label"><i class="fas fa-wallet"></i> Solde wallet</div>
                        <div class="stat-value" id="walletBalance">0 F</div>
                        <div class="stat-hint" id="walletSourceLabel">Synchronisation en cours…</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="todayOrders">0</div>
                        <div class="stat-label">Commandes aujourd'hui</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="todayEarnings">0 F</div>
                        <div class="stat-label">Gains du jour</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="totalOrders">0</div>
                        <div class="stat-label">Total commandes</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="averageRating">0.0</div>
                        <div class="stat-label">Note moyenne</div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <a href="#" class="quick-btn" onclick="showScreen('ordersScreen')">
                        <i class="fas fa-list"></i><br>Commandes
                    </a>
                    <a href="#" class="quick-btn" onclick="showScreen('mapScreen')">
                        <i class="fas fa-map"></i><br>Carte
                    </a>
                    <a href="#" class="quick-btn" onclick="showScreen('profileScreen')">
                        <i class="fas fa-user"></i><br>Profil
                    </a>
                </div>
            </div>
        </div>

        <!-- Commandes -->
        <div class="screen" id="ordersScreen">
            <div class="glass-card">
                <h3 style="margin-bottom: 15px; color: var(--primary-gold);">
                    <i class="fas fa-shipping-fast"></i> Mes Commandes
                </h3>
                
                <div id="ordersList">
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-id">#12345</span>
                            <span class="order-status status-en_cours">En cours</span>
                        </div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">
                            <div><strong>De:</strong> Cocody Riviera</div>
                            <div><strong>Vers:</strong> Yopougon</div>
                            <div><strong>Montant:</strong> 2,500 FCFA</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carte -->
        <div class="screen" id="mapScreen">
            <div class="glass-card">
                <h3 style="margin-bottom: 15px; color: var(--primary-gold);">
                    <i class="fas fa-map-marked-alt"></i> Localisation
                </h3>
                
                <div id="map" style="height: 300px; background: var(--glass-bg); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <div style="text-align: center; opacity: 0.7;">
                        <i class="fas fa-map fa-3x" style="margin-bottom: 10px;"></i><br>
                        Carte en cours de chargement...
                    </div>
                </div>
            </div>
        </div>

        <!-- Profil -->
        <div class="screen" id="profileScreen">
            <div class="glass-card">
                <h3 style="margin-bottom: 15px; color: var(--primary-gold);">
                    <i class="fas fa-user-circle"></i> Mon Profil
                </h3>
                
                <div id="profileInfo">
                    <div style="margin-bottom: 15px;">
                        <strong>Nom:</strong> <span id="coursierName">-</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>Téléphone:</strong> <span id="coursierPhone">-</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>Statut:</strong> <span id="coursierStatus">-</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>Solde wallet:</strong> <span id="profileWallet">0 F</span>
                        <div style="font-size: 0.75rem; opacity: 0.7;" id="profileWalletSource">Synchronisation en cours…</div>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>Matricule:</strong> <span id="coursierMatricule">-</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>Zone de travail:</strong> <span id="workZone">-</span>
                    </div>
                </div>
                
                <button class="btn" onclick="logout()" style="background: linear-gradient(135deg, var(--danger), #ff6b6b);">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation bottom -->
    <div class="bottom-nav">
        <a href="#" class="nav-item active" onclick="showScreen('dashboardScreen')">
            <i class="fas fa-home"></i>
            <span>Accueil</span>
        </a>
        <a href="#" class="nav-item" onclick="showScreen('ordersScreen')">
            <i class="fas fa-list"></i>
            <span>Commandes</span>
        </a>
        <a href="#" class="nav-item" onclick="showScreen('mapScreen')">
            <i class="fas fa-map"></i>
            <span>Carte</span>
        </a>
        <a href="#" class="nav-item" onclick="showScreen('profileScreen')">
            <i class="fas fa-user"></i>
            <span>Profil</span>
        </a>
    </div>

    <script>
    let currentCoursier = null;
    // Base path dynamique pour éviter les 404 si l'app est servie dans un sous-dossier
    const __BASE_PATH = (window.ROOT_PATH || '<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>').replace(/\/$/, '');
    function apiPath(rel){return __BASE_PATH + rel;}
    let watchPositionId = null;

    function formatCurrency(amount) {
        if (amount === null || amount === undefined || isNaN(amount)) {
            return '0 F';
        }
        return Number(amount).toLocaleString('fr-FR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }) + ' F';
    }

    function humanizeWalletSource(source) {
        if (!source) return null;
        const map = {
            'agents_suzosky': 'Fichier agents Suzosky',
            'coursier_accounts.solde_disponible': 'Compte rechargement (solde disponible)',
            'coursier_accounts.solde_total': 'Compte rechargement (solde total)',
            'comptes_coursiers.solde': 'Comptes coursiers (legacy)',
            'clients_particuliers.balance': 'Balance clients (legacy)',
            'default_zero': 'Solde initialisé'
        };
        return map[source] ?? source.replace(/_/g, ' ');
    }

    // Gestion des écrans
    function showScreen(screenId) {
        // Masquer tous les écrans
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
        });
        
        // Afficher l'écran demandé
        document.getElementById(screenId).classList.add('active');
        
        // Mettre à jour la navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        event.target.closest('.nav-item')?.classList.add('active');
    }

    // Connexion
    async function handleLogin(event) {
        event.preventDefault();
        
        const phone = document.getElementById('loginPhone').value;
        const password = document.getElementById('loginPassword').value;
        
        try {
            const response = await fetch(apiPath('/api/auth.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    phone: phone,
                    password: password,
                    type: 'coursier'
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                currentCoursier = data.data;
                localStorage.setItem('coursier_session', JSON.stringify(currentCoursier));
                
                // Passer au dashboard
                showScreen('dashboardScreen');
                loadDashboardData();
                startLocationTracking();
                
                // Mettre à jour le statut
                updateStatusIndicator('En ligne', true);
            } else {
                alert('Erreur de connexion: ' + data.message);
            }
        } catch (error) {
            console.error('Erreur de connexion:', error);
            alert('Erreur de connexion au serveur');
        }
    }

    // Charger les données du dashboard
    async function loadDashboardData() {
        if (!currentCoursier) return;
        
        try {
            const response = await fetch(apiPath(`/api/get_coursier_info.php?coursier_id=${currentCoursier.id}`));
            const data = await response.json();
            
            if (data.success) {
                const stats = data.data.statistiques || {};

                const walletValue = data.data.solde_wallet ?? 0;
                const humanSource = humanizeWalletSource(data.data.solde_wallet_source);
                const walletSource = humanSource ? `Source: ${humanSource}` : 'Solde synchronisé';
                const walletBalanceEl = document.getElementById('walletBalance');
                if (walletBalanceEl) walletBalanceEl.textContent = formatCurrency(walletValue);
                const walletSourceEl = document.getElementById('walletSourceLabel');
                if (walletSourceEl) walletSourceEl.textContent = walletSource;

                const todayOrders = stats.commandes_jour ?? 0;
                document.getElementById('todayOrders').textContent = todayOrders.toString();
                document.getElementById('todayEarnings').textContent = formatCurrency(stats.gains_jour ?? 0);
                const totalOrders = stats.total_commandes ?? 0;
                document.getElementById('totalOrders').textContent = totalOrders.toString();
                const averageRatingRaw = Number(stats.note_moyenne ?? 0);
                document.getElementById('averageRating').textContent = Number.isFinite(averageRatingRaw) ? averageRatingRaw.toFixed(1) : '0.0';

                // Mettre à jour le profil
                document.getElementById('coursierName').textContent = data.data.nom || '-';
                document.getElementById('coursierPhone').textContent = data.data.telephone || '-';
                const statusText = data.data.statut_connexion ? `${data.data.statut || '-' } (${data.data.statut_connexion})` : (data.data.statut || '-');
                document.getElementById('coursierStatus').textContent = statusText;
                document.getElementById('coursierMatricule').textContent = data.data.matricule || '-';
                document.getElementById('workZone').textContent = data.data.zone_travail || 'Non définie';
                updateStatusIndicator(statusText, !!data.data.disponible);
                const profileWallet = document.getElementById('profileWallet');
                if (profileWallet) profileWallet.textContent = formatCurrency(walletValue);
                const profileWalletSource = document.getElementById('profileWalletSource');
                if (profileWalletSource) profileWalletSource.textContent = walletSource;
            }
        } catch (error) {
            console.error('Erreur chargement données:', error);
        }
    }

    // Mise à jour du statut
    function updateStatusIndicator(status, online = false) {
        const indicator = document.getElementById('statusIndicator');
        const dot = indicator.querySelector('.status-dot');
        const text = indicator.querySelector('span:last-child');
        
        text.textContent = status;
        dot.style.background = online ? 'var(--success)' : 'var(--danger)';
    }

    // Tracking GPS
    function startLocationTracking() {
        if ('geolocation' in navigator) {
            watchPositionId = navigator.geolocation.watchPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    updateCoursierPosition(latitude, longitude, position.coords.accuracy);
                },
                (error) => {
                    console.error('Erreur géolocalisation:', error);
                    updateStatusIndicator('GPS indisponible', false);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 30000
                }
            );
        }
    }

    // Mise à jour position
    async function updateCoursierPosition(lat, lng, accuracy = null) {
        if (!currentCoursier) return;
        
        try {
            await fetch(apiPath('/api/update_coursier_position.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    coursier_id: currentCoursier.id,
                    lat: lat,
                    lng: lng,
                    accuracy: accuracy
                })
            });
        } catch (error) {
            console.error('Erreur mise à jour position:', error);
        }
    }

    // Déconnexion
    function logout() {
        if (confirm('Voulez-vous vraiment vous déconnecter ?')) {
            // Arrêter le tracking
            if (watchPositionId) {
                navigator.geolocation.clearWatch(watchPositionId);
                watchPositionId = null;
            }
            
            // Nettoyer les données
            localStorage.removeItem('coursier_session');
            currentCoursier = null;
            
            // Retour à l'écran de connexion
            showScreen('loginScreen');
            updateStatusIndicator('Hors ligne', false);
        }
    }

    // Vérification session au chargement
    document.addEventListener('DOMContentLoaded', () => {
        const savedSession = localStorage.getItem('coursier_session');
        if (savedSession) {
            try {
                currentCoursier = JSON.parse(savedSession);
                showScreen('dashboardScreen');
                loadDashboardData();
                startLocationTracking();
                updateStatusIndicator('En ligne', true);
            } catch (error) {
                localStorage.removeItem('coursier_session');
            }
        }
        
        // Enregistrer le service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(() => console.log('Service Worker enregistré'))
                .catch(err => console.log('Erreur Service Worker:', err));
        }
    });

    // Gestion hors ligne
    window.addEventListener('online', () => {
        updateStatusIndicator('En ligne', true);
    });

    window.addEventListener('offline', () => {
        updateStatusIndicator('Hors ligne', false);
    });
    </script>
</body>
</html>