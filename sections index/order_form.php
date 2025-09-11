<?php
// sections/order_form.php - Formulaire de commande complet
?>
    <!-- Fix mobile pour les cartes de service -->
    <style>
        /* Force l'affichage des cartes sur mobile */
        @media (max-width: 768px) {
            .services-section {
                display: block !important;
                visibility: visible !important;
                padding: 40px 0 !important;
            }
            
            .services-container {
                display: block !important;
                padding: 0 15px !important;
            }
            
            .services-grid {
                display: block !important;
                width: 100% !important;
                margin-top: 20px !important;
            }
            
            .service-card {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                background: rgba(255, 255, 255, 0.1) !important;
                border: 1px solid rgba(212,168,83,0.5) !important;
                border-radius: 15px !important;
                padding: 25px 15px !important;
                margin: 15px 0 !important;
                text-align: center !important;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
                backdrop-filter: blur(10px) !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            
            .service-icon {
                display: block !important;
                font-size: 2.5rem !important;
                margin-bottom: 15px !important;
                line-height: 1 !important;
            }
            
            .service-title {
                display: block !important;
                font-size: 1.1rem !important;
                font-weight: 700 !important;
                color: #D4A853 !important;
                margin-bottom: 10px !important;
                background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%) !important;
                -webkit-background-clip: text !important;
                -webkit-text-fill-color: transparent !important;
                background-clip: text !important;
            }
            
            .service-description {
                display: block !important;
                color: rgba(255, 255, 255, 0.85) !important;
                font-size: 0.9rem !important;
                line-height: 1.5 !important;
                margin: 0 !important;
            }
            
            .section-title {
                font-size: 1.8rem !important;
                text-align: center !important;
                margin-bottom: 15px !important;
                background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%) !important;
                -webkit-background-clip: text !important;
                -webkit-text-fill-color: transparent !important;
                background-clip: text !important;
            }
            
            .section-subtitle {
                font-size: 0.95rem !important;
                text-align: center !important;
                color: rgba(255, 255, 255, 0.8) !important;
                margin-bottom: 25px !important;
            }
        }
        
        /* Fix pour très petits écrans */
        @media (max-width: 480px) {
            .service-card {
                padding: 20px 12px !important;
                margin: 12px 0 !important;
            }
            
            .service-icon {
                font-size: 2.2rem !important;
            }
            
            .service-title {
                font-size: 1rem !important;
            }
            
            .service-description {
                font-size: 0.85rem !important;
            }
        }
        
        /* 📱 STYLES PHONE-ROW - SYSTÈME ORIGINAL CONFORME À LA CHARTE */
        .phone-row {
            display: flex;
            gap: 15px;
            margin: 15px 0;
            width: 100%;
        }
        
        .phone-row .form-group {
            flex: 1;
            min-width: 0;
        }

        /* STYLES RESPONSIVES OPTIMISÉS */
        @media (max-width: 768px) {
            .phone-row {
                flex-direction: column;
                gap: 20px;
            }
        }

        /* ESTIMATION DE PRIX - CONFORME CHARTE SUZOSKY */
        .price-calculation-section {
            background: rgba(212, 168, 83, 0.1);
            border: 2px solid rgba(212, 168, 83, 0.3);
            border-radius: 16px;
            padding: 25px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(212, 168, 83, 0.2);
            transition: all 0.3s ease;
        }

        .price-calculation-section:hover {
            background: rgba(212, 168, 83, 0.15);
            border-color: rgba(212, 168, 83, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(212, 168, 83, 0.3);
        }

        .price-header h3 {
            color: #D4A853;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-align: center;
            background: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .price-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .distance-info, .time-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .distance-info .label, .time-info .label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .distance-info .value, .time-info .value {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
        }

        .price-breakdown {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgba(212, 168, 83, 0.3);
        }

        .price-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .price-line .description {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
        }

        .price-line .amount {
            color: #fff;
            font-weight: 600;
        }

        .price-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
            padding: 12px 0;
            border-top: 2px solid #D4A853;
        }

        .price-total .total-label {
            color: #D4A853;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .price-total .total-amount {
            color: #D4A853;
            font-weight: 900;
            font-size: 1.4rem;
            text-shadow: 0 2px 8px rgba(212, 168, 83, 0.4);
        }

        /* Responsive pour prix */
        @media (max-width: 768px) {
            .price-calculation-section {
                padding: 20px;
                margin: 15px 0;
            }
            
            .price-header h3 {
                font-size: 1.1rem;
            }
            
            .price-total .total-amount {
                font-size: 1.2rem;
            }
        }

        /* ANIMATIONS ET ÉTATS DE CHARGEMENT POUR LE CALCUL DE PRIX */
        .price-calculation-section {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .price-calculation-section.price-visible {
            opacity: 1;
            transform: translateY(0);
        }

        .price-calculation-section.price-loading {
            opacity: 0.7;
            background: rgba(212, 168, 83, 0.05);
            border-color: rgba(212, 168, 83, 0.2);
        }

        .price-calculation-section.price-loading * {
            color: rgba(255, 255, 255, 0.6) !important;
            animation: priceLoadingPulse 2s ease-in-out infinite;
        }

        .price-calculation-section.price-error {
            background: rgba(244, 67, 54, 0.1);
            border-color: rgba(244, 67, 54, 0.4);
        }

        .price-calculation-section.price-error .error-message {
            color: #ff5757 !important;
            text-align: center;
            font-weight: 600;
        }

        @keyframes priceLoadingPulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        /* Spinner pour le chargement */
        .price-calculation-section .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Amélioration des détails de prix */
        .distance-info i, .time-info i {
            color: #D4A853;
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        .price-line {
            transition: all 0.2s ease;
        }

        .price-line:hover {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 4px 8px;
        }

        .price-separator {
            height: 1px;
            background: linear-gradient(90deg, transparent, #D4A853, transparent);
            margin: 10px 0;
        }

        /* Responsive pour les nouvelles animations */
        @media (max-width: 768px) {
            .price-calculation-section {
                transform: translateY(10px);
            }
            
            .price-line:hover {
                background: none;
                padding: 0;
            }
        }

        /* ESTIMATION DE PRIX - CONFORME CHARTE SUZOSKY */
        .price-estimate {
            background: rgba(212, 168, 83, 0.1);
            border: 2px solid rgba(212, 168, 83, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
            color: #fff;
            font-weight: 600;
            font-size: 1.1rem;
            line-height: 1.6;
            text-align: center;
            box-shadow: 0 8px 32px rgba(212, 168, 83, 0.2);
            transition: all 0.3s ease;
        }

        .price-estimate:hover {
            background: rgba(212, 168, 83, 0.15);
            border-color: rgba(212, 168, 83, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(212, 168, 83, 0.3);
        }

        .price-estimate strong {
            color: var(--primary-gold);
            font-weight: 800;
            font-size: 1.2em;
            text-shadow: 0 2px 8px rgba(212, 168, 83, 0.4);
        }
    </style>

    <!-- HERO + COMMANDE + MAP -->
    <div class="hero-section" id="accueil">
        <div class="hero-content-wrapper">
            <!-- Conteneur principal avec formulaire à gauche et carte à droite -->
            <div class="order-map-container">
                <!-- Bloc gauche : Hero + Formulaire -->
                <div class="hero-left">
                    <div class="hero-text">
                        <h1 class="hero-title">Commandez un coursier<br><span class="hero-accent">en 1 minute</span></h1>
                        <p class="hero-subtitle">Livraison express, sécurisée et suivie en temps réel à Abidjan.<br>Payez à la livraison ou par mobile money.</p>
                    </div>
                    <div class="order-form">
                        <h2 class="form-title">💫 Commander un coursier</h2>
                        <form id="orderForm" action="/api/submit_order.php" method="POST">
                            <div class="address-row">
                                <div class="form-group">
                                    <label for="departure">Départ (Expéditeur)</label>
                                    <div class="input-with-icon departure">
                                        <input type="text" id="departure" name="departure" placeholder="Adresse de départ..." required autocomplete="off">
                                    </div>
                                    <div class="location-controls">
                                        <button type="button" onclick="getCurrentLocation('departure')" class="gps-btn">
                                            📍 Ma position (A)
                                        </button>
                                    <small style="color: rgba(255,255,255,0.7);">💡 Déplacez le marqueur A pour plus de précision</small>
                                </div>
                            </div>
                            <div class="route-arrow">→</div>
                            <div class="form-group">
                                <label for="destination">Arrivée (Destinataire)</label>
                                <div class="input-with-icon destination">
                                    <input type="text" id="destination" name="destination" placeholder="Adresse de destination..." required autocomplete="off">
                                </div>
                                <div class="location-controls">
                                    <button type="button" onclick="getCurrentLocation('destination')" class="gps-btn">
                                        📍 Ma position (B)
                                    </button>
                                    <small style="color: rgba(255,255,255,0.7);">💡 Déplacez le marqueur B pour plus de précision</small>
                                </div>
                            </div>
                        </div>
                        <div class="phone-row">
                            <div class="form-group">
                                <label for="senderPhone"><i class="fas fa-phone"></i> Téléphone Expéditeur</label>
                                <div class="input-with-icon phone">
                                    <?php $sessionPhone = $_SESSION['client_telephone'] ?? ''; ?>
                                    <input type="tel" id="senderPhone" name="senderPhone"
                                        placeholder="+225 xx xx xx xx xx"
                                        maxlength="19"
                                        required
                                        <?php if (!empty($_SESSION['client_id']) && $sessionPhone): ?>
                                            value="<?= htmlspecialchars($sessionPhone) ?>" readonly
                                        <?php endif; ?>
                                    >
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="receiverPhone"><i class="fas fa-phone"></i> Téléphone Destinataire</label>
                                <div class="input-with-icon phone">
                                    <input type="tel" id="receiverPhone" name="receiverPhone" placeholder="+225 xx xx xx xx xx" maxlength="19" required>
                                </div>
                            </div>
                        </div>
                        <div class="package-details">
                            <div class="form-group">
                                <label for="packageDesc">Description du colis</label>
                                <textarea id="packageDesc" name="packageDesc" placeholder="Documents, vêtements, nourriture..." rows="2"></textarea>
                            </div>
                        </div>
                        <div class="priority-options">
                            <div class="priority-option">
                                <input type="radio" id="normal" name="priority" value="normale" checked>
                                <label for="normal" class="priority-label">🚶 Normal<br><small>1-2h</small></label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" id="urgent" name="priority" value="urgente">
                                <label for="urgent" class="priority-label">⚡ Urgent<br><small>30min</small></label>
                            </div>
                            <div class="priority-option">
                                <input type="radio" id="express" name="priority" value="express">
                                <label for="express" class="priority-label">🚀 Express<br><small>15min</small></label>
                            </div>
                        </div>
                        
                        <!-- Calcul automatique du prix - Version client simplifiée -->
                        <div class="price-calculation-section" id="price-calculation-section" style="display:none;">
                            <div class="price-display">
                                <div class="price-header">
                                    <h3>💰 Estimation de votre course</h3>
                                </div>
                                <div class="price-details">
                                    <div class="distance-info" id="distance-info"></div>
                                    <div class="time-info" id="time-info"></div>
                                    <div class="price-breakdown" id="price-breakdown"></div>
                                    <div class="price-total" id="total-price"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inclusion de la gestion des modes de paiement -->
                        <?php include __DIR__ . '/payment_methods.php'; ?>
                        <div class="map-controls" style="display:flex; gap:10px; margin:10px 0;">
                            <button type="button" onclick="clearRoute()" style="background:rgba(212,168,83,0.2); color:#D4A853; border:1px solid #D4A853; padding:8px 16px; border-radius:8px; cursor:pointer;">
                                🔄 Réafficher marqueurs
                            </button>
                            <button type="button" onclick="clearMarkers()" style="background:rgba(233,69,96,0.2); color:#E94560; border:1px solid #E94560; padding:8px 16px; border-radius:8px; cursor:pointer;">
                                🗑️ Effacer tout
                            </button>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px; margin: 10px 0;">
                            <small style="color: rgba(255,255,255,0.8); font-size: 11px;">
                                💡 <strong>Astuce précision:</strong><br>
                                • Cliquez sur la carte pour placer A ou B<br>
                                • Déplacez les marqueurs vers l'endroit exact<br>
                                • Utilisez votre GPS pour votre position actuelle
                            </small>
                        </div>
                        <button type="button" class="submit-btn">
                            🛵 Commander maintenant
                        </button>
                    </form>
                </div>
                </div>
                
                <!-- Bloc droite : Carte Google Maps avec scroll synchronisé -->
                <div class="map-right">
                    <div class="map-container-sticky" id="mapContainerSticky">
                        <div id="map"></div>
                        <div class="map-info-overlay">
                            <div id="routeInfo" class="route-info" style="display: none;">
                                <div class="distance-info">Distance: <span id="routeDistance">-</span></div>
                                <div class="duration-info">Durée: <span id="routeDuration">-</span></div>
                                <div class="price-info">Prix: <span id="routePrice">-</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            
        <!-- Background assets -->
        <video autoplay loop muted playsinline class="hero-bg">
            <source src="https://player.vimeo.com/external/447487990.sd.mp4?s=f65b5b0c7b41b3e4e7c1b8ba1b8b1b4b4e7c1b8b&profile_id=164&oauth2_token_id=57447761" type="video/mp4">
            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1500&q=80" alt="Ville de nuit" style="width:100vw;height:100vh;object-fit:cover;filter:blur(4px) brightness(0.7);">
        </video>
    </div>

    <style>
    /* CSS pour layout formulaire à gauche, carte à droite avec scroll synchronisé */
    .hero-content-wrapper {
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 100px 20px 40px;
        position: relative;
        z-index: 2;
    }

    .order-map-container {
        display: flex;
        gap: 30px;
        align-items: flex-start;
        min-height: calc(100vh - 200px);
    }

    .hero-left {
        flex: 0 0 50%;
        max-width: 600px;
    }

    .map-right {
        flex: 0 0 45%;
        position: relative;
    }

    .map-container-sticky {
        position: sticky;
        top: 100px;
        height: 70vh;
        min-height: 500px;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        border: 2px solid rgba(212,168,83,0.3);
        transition: all 0.3s ease;
    }

    .map-container-sticky:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 80px rgba(0,0,0,0.5);
        border-color: rgba(212,168,83,0.5);
    }

    #map {
        width: 100%;
        height: 100%;
        border-radius: 18px;
    }

    .map-info-overlay {
        position: absolute;
        top: 20px;
        left: 20px;
        right: 20px;
        z-index: 10;
    }

    .route-info {
        background: rgba(26, 26, 46, 0.95);
        border: 1px solid rgba(212,168,83,0.5);
        border-radius: 15px;
        padding: 15px 20px;
        backdrop-filter: blur(20px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        color: #fff;
        font-weight: 500;
        box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    }

    .route-info > div {
        text-align: center;
        flex: 1;
    }

    .route-info span {
        color: #D4A853;
        font-weight: 700;
        display: block;
        font-size: 1.1em;
        margin-top: 3px;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .order-map-container {
            flex-direction: column;
            gap: 20px;
        }

        .hero-left,
        .map-right {
            flex: none;
            max-width: 100%;
            width: 100%;
        }

        .map-container-sticky {
            position: relative;
            top: auto;
            height: 50vh;
            min-height: 400px;
        }
    }

    @media (max-width: 768px) {
        .hero-content-wrapper {
            padding: 80px 15px 20px;
        }

        .map-container-sticky {
            height: 40vh;
            min-height: 300px;
        }

        .route-info {
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        .route-info > div {
            text-align: center;
        }
    }
    </style>

    <script>
    // Script pour scroll synchronisé de la carte
    document.addEventListener('DOMContentLoaded', function() {
        const mapContainer = document.getElementById('mapContainerSticky');
        const heroLeft = document.querySelector('.hero-left');
        
        if (!mapContainer || !heroLeft) return;

        function updateMapPosition() {
            const heroRect = heroLeft.getBoundingClientRect();
            const heroHeight = heroLeft.scrollHeight;
            const viewportHeight = window.innerHeight;
            const scrollProgress = Math.max(0, Math.min(1, -heroRect.top / (heroHeight - viewportHeight + 200)));
            
            // Calculer la nouvelle position
            const maxScroll = Math.max(0, heroHeight - viewportHeight + 200);
            const newTop = 100 + (scrollProgress * Math.min(maxScroll * 0.3, 150));
            
            // Appliquer la position avec une transition fluide
            if (window.innerWidth > 1024) {
                mapContainer.style.top = `${Math.min(newTop, 250)}px`;
            }
        }

        // Throttle pour optimiser les performances
        let ticking = false;
        function requestTick() {
            if (!ticking) {
                requestAnimationFrame(updateMapPosition);
                ticking = true;
                setTimeout(() => { ticking = false; }, 16);
            }
        }

        // Écouter le scroll
        window.addEventListener('scroll', requestTick);
        window.addEventListener('resize', requestTick);
        
        // Position initiale
        updateMapPosition();
    });
    </script>
