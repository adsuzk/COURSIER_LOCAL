<?php
// sections/order_form.php - Formulaire de commande complet
?>
    <!-- sections/order_form sans styles inline -->

    <!-- HERO SECTION -->
    <div class="hero-section" id="accueil">
        <div class="hero-content">
            <div class="hero-left">
                <div class="hero-text">
                    <h1 class="hero-title">Commandez un coursier<br><span class="hero-accent">en 1 minute</span></h1>
                    <p class="hero-subtitle">Livraison express, sécurisée et suivie en temps réel à Abidjan.<br>Payez à la livraison ou par mobile money.</p>
                </div>
                
                <!-- FORMULAIRE DE COMMANDE -->
                <form id="orderForm" class="order-form">
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
                        <div class="form-map-controls">
                            <button type="button" onclick="clearRoute()" class="clear-route">
                                🔄 Réafficher marqueurs
                            </button>
                            <button type="button" onclick="clearMarkers()" class="clear-markers">
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
        </div>
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
