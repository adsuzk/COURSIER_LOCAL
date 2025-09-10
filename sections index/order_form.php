<?php
// sections/order_form.php - Formulaire de commande complet
?>
    <!-- Fix mobile pour les cartes de service -->
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
                    <form id="orderForm">
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
                                    placeholder="+225 XX XX XX XX XX"
                                    maxlength="17"
                                    required
                                    value="<?= htmlspecialchars($sessionPhone) ?>"
                                    <?= $sessionPhone ? 'readonly' : '' ?>
                                >
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="receiverPhone"><i class="fas fa-phone"></i> Téléphone Destinataire</label>
                            <div class="input-with-icon phone">
                                <input type="tel" id="receiverPhone" name="receiverPhone" placeholder="+225 XX XX XX XX XX" maxlength="17" required>
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
                    <div class="price-estimate" id="estimatedPrice" style="display:none;">
                        💰 Calcul du prix en cours...
                    </div>
                    
                    <!-- Sélection mode de paiement -->
                    <div class="payment-methods-container" id="paymentMethods" style="display:none;">
                        <h3 class="payment-title">💳 Choisissez votre mode de paiement</h3>
                        
                        <!-- Liste des modes de paiement horizontale -->
                        <div class="payment-options">
                            <!-- Espèces à la livraison -->
                            <div class="payment-option" data-method="cash" data-default="true">
                                <input type="radio" name="paymentMethod" value="cash" id="cash" checked>
                                <label for="cash">
                                    <div class="payment-icon"><img src="assets/img/payment/cash.svg" alt="Espèces" class="payment-logo"></div>
                                    <div class="payment-details">
                                        <span class="payment-name">Espèces à la livraison</span>
                                        <span class="payment-info">Paiement au coursier • Sans frais</span>
                                    </div>
                                </label>
                            </div>
                            <!-- Orange Money -->
                            <div class="payment-option" data-method="orange_money">
                                <input type="radio" name="paymentMethod" value="orange_money" id="orange_money">
                                <label for="orange_money">
                                    <div class="payment-icon"><img src="assets/img/payment/orange-money.svg" alt="Orange Money" class="payment-logo"></div>
                                    <div class="payment-details">
                                        <span class="payment-name">Orange Money</span>
                                        <span class="payment-info">Instantané • Sans frais</span>
                                    </div>
                                </label>
                            </div>
                            <!-- MTN Money -->
                            <div class="payment-option" data-method="mtn_money">
                                <input type="radio" name="paymentMethod" value="mtn_money" id="mtn_money">
                                <label for="mtn_money">
                                    <div class="payment-icon"><img src="assets/img/payment/mtn-money.svg" alt="MTN Money" class="payment-logo"></div>
                                    <div class="payment-details">
                                        <span class="payment-name">MTN Money</span>
                                        <span class="payment-info">Instantané • Sans frais</span>
                                    </div>
                                </label>
                            </div>
                            <!-- Moov Money -->
                            <div class="payment-option" data-method="moov_money">
                                <input type="radio" name="paymentMethod" value="moov_money" id="moov_money">
                                <label for="moov_money">
                                    <div class="payment-icon"><img src="assets/img/payment/moov-money.svg" alt="Moov Money" class="payment-logo"></div>
                                    <div class="payment-details">
                                        <span class="payment-name">Moov Money</span>
                                        <span class="payment-info">Instantané • Sans frais</span>
                                    </div>
                                </label>
                            </div>
                            <!-- Wave -->
                            <div class="payment-option" data-method="wave">
                                <input type="radio" name="paymentMethod" value="wave" id="wave">
                                <label for="wave">
                                    <div class="payment-icon"><img src="assets/img/payment/wave.svg" alt="Wave" class="payment-logo"></div>
                                    <div class="payment-details">
                                        <span class="payment-name">Wave</span>
                                        <span class="payment-info">Instantané • Sans frais</span>
                                    </div>
                                </label>
                            </div>
                            <!-- Carte bancaire -->
                            <div class="payment-option" data-method="card">
                                <input type="radio" name="paymentMethod" value="card" id="card">
                                <label for="card">
                                    <div class="payment-icon"><img src="assets/img/payment/card.svg" alt="Carte bancaire" class="payment-logo"></div>
                                    <div class="payment-details">
                                        <span class="payment-name">Visa / Mastercard</span>
                                        <span class="payment-info">1-3 min • Frais 2.5%</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
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
                    <button type="button" class="submit-btn" onclick="processOrder()">
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
