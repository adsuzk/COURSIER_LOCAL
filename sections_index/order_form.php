<?php
// sections/order_form.php - Formulaire de commande complet selon modèle HTML

$sessionSenderPhoneRaw = $_SESSION['client_telephone'] ?? '';
$sessionSenderPhoneDisplay = '';
$sessionSenderPhoneDigits = '';

if ($sessionSenderPhoneRaw !== '') {
    $digits = preg_replace('/\D+/', '', $sessionSenderPhoneRaw);

    if (strpos($digits, '00225') === 0) {
        $digits = substr($digits, 5);
    }

    if (strpos($digits, '225') === 0 && strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }

    if (strlen($digits) >= 10) {
        $core = substr($digits, -10);
        $parts = str_split($core, 2);
        $sessionSenderPhoneDisplay = '+225 ' . implode(' ', $parts);
        $sessionSenderPhoneDigits = $core;
    } else {
        $sessionSenderPhoneDisplay = $sessionSenderPhoneRaw;
        $sessionSenderPhoneDigits = $digits;
    }
}
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

        /* STYLES MODES DE PAIEMENT */
        .payment-methods-container {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }

        .payment-title {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            text-align: center;
        }

        .payment-category {
            margin-bottom: 20px;
        }

        .payment-category-title {
            color: #D4A853;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .payment-option {
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .payment-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .payment-option label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #fff;
        }

        .payment-option input[type="radio"]:checked + label {
            background: rgba(212, 168, 83, 0.15);
            border-color: #D4A853;
            box-shadow: 0 4px 20px rgba(212, 168, 83, 0.2);
        }

        .payment-option label:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Timeline enrichie client */
        .client-timeline {
            margin-top: 12px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 12px;
        }

        .client-timeline-title {
            font-weight: 600;
            color: #D4A853;
            margin-bottom: 8px;
        }

        .client-timeline-steps {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .client-timeline-step {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            min-width: 190px;
            flex: 1 1 220px;
            transition: all 0.25s ease;
        }

        .client-timeline-step .step-icon {
            font-size: 1.1rem;
            line-height: 1;
            margin-top: 2px;
        }

        .client-timeline-step .step-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .client-timeline-step .step-label {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .client-timeline-step small {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .client-timeline-step.is-completed {
            background: rgba(212, 168, 83, 0.18);
            border-color: rgba(212, 168, 83, 0.7);
            box-shadow: 0 6px 18px rgba(212, 168, 83, 0.18);
        }

        .client-timeline-step.is-active {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(212, 168, 83, 0.45);
        }

        .client-timeline-step.is-pending {
            opacity: 0.65;
        }

        .client-timeline-messages {
            margin-top: 10px;
            display: none;
            flex-direction: column;
            gap: 6px;
        }

        .client-timeline-message {
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .timeline-message-text {
            flex: 1 1 auto;
        }

        .timeline-retry-btn {
            background: rgba(212, 168, 83, 0.2);
            border: 1px solid rgba(212, 168, 83, 0.6);
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .timeline-retry-btn:hover {
            background: rgba(212, 168, 83, 0.35);
            border-color: rgba(212, 168, 83, 0.8);
            box-shadow: 0 4px 12px rgba(212, 168, 83, 0.25);
        }

        .timeline-retry-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            box-shadow: none;
        }

        .client-timeline-message[data-type="success"] {
            border-color: rgba(46, 204, 113, 0.6);
            background: rgba(46, 204, 113, 0.12);
        }

        .client-timeline-message[data-type="info"] {
            border-color: rgba(41, 128, 185, 0.6);
            background: rgba(41, 128, 185, 0.12);
        }

        .client-timeline-message[data-type="warning"] {
            border-color: rgba(241, 196, 15, 0.6);
            background: rgba(241, 196, 15, 0.12);
        }

        .client-timeline-message[data-type="error"],
        .client-timeline-message[data-type="danger"] {
            border-color: rgba(231, 76, 60, 0.6);
            background: rgba(231, 76, 60, 0.12);
        }

        .payment-icon {
            /* Icon container for payment logo */
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }
        .payment-logo {
            max-width: 32px;
            max-height: 32px;
            display: block;
        }

        .payment-details {
            flex: 1;
        }

        .payment-name {
            display: block;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 4px;
        }

        .payment-info {
            display: block;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .live-tracking-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.85rem;
            font-weight: 600;
            color: #ffffff;
            transition: all 0.25s ease;
            margin-bottom: 8px;
        }

        .live-tracking-badge[data-mode="pending"] {
            background: rgba(212, 168, 83, 0.18);
            border-color: rgba(212, 168, 83, 0.45);
            color: #fff5dd;
        }

        .live-tracking-badge[data-mode="live"] {
            background: rgba(52, 199, 89, 0.2);
            border-color: rgba(46, 204, 113, 0.55);
            color: #eafff3;
        }

        .live-tracking-badge[data-mode="payment"] {
            background: rgba(41, 128, 185, 0.2);
            border-color: rgba(41, 128, 185, 0.5);
            color: #e7f4ff;
        }

        .live-tracking-badge[data-mode="stopped"] {
            background: rgba(231, 76, 60, 0.18);
            border-color: rgba(231, 76, 60, 0.55);
            color: #ffebe8;
        }

        .live-tracking-badge[data-mode="delivered"] {
            background: rgba(46, 204, 113, 0.24);
            border-color: rgba(39, 174, 96, 0.6);
            color: #e9ffef;
        }

        /* Responsive pour les modes de paiement - disposition verticale sur mobile */
        @media (max-width: 768px) {
            .payment-options {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .payment-option {
                min-width: 100%;
                flex: 1 1 auto;
            }
            .payment-option label {
                width: 100%;
            }
        }
    </style>
    <!-- Polling indicator feature removed per request: original behavior restored -->

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
                        
                        <?php if (!$coursiersDisponibles): ?>
                        <!-- SÉCURITÉ: Aucun coursier disponible -->
                        <div class="service-unavailable-alert">
                            <?php
                            // Ensure a friendly commercial message is shown if $messageIndisponibilite is empty.
                            // Use centralized message provided by index.php when available
                            $commercialText = isset($commercialFallbackMessage) ? $commercialFallbackMessage : (isset($messageIndisponibilite) ? $messageIndisponibilite : '');
                            $displayMessage = trim((string)($messageIndisponibilite ?? '')) !== '' ? $messageIndisponibilite : $commercialText;
                            echo '<div style="padding:12px; border-radius:8px; background: rgba(255,255,255,0.03);">' . $displayMessage . '</div>';
                            ?>
                            <div style="text-align: center; margin-top: 15px;">
                                <button type="button" onclick="location.reload()" class="refresh-btn">
                                    🔄 Actualiser
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        
                        <form id="orderForm" action="/api/submit_order.php" method="post">
                            <div class="address-row">
                                <div class="form-group">
                                    <label for="departure">Départ (Expéditeur)</label>
                                    <div class="input-with-icon departure">
                                        <input type="text" id="departure" name="departure" placeholder="Adresse de départ..." required autocomplete="off">
                                        <!-- Champs cachés pour coordonnées de départ -->
                                        <input type="hidden" id="departure_lat" name="departure_lat" value="">
                                        <input type="hidden" id="departure_lng" name="departure_lng" value="">
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
                                    <input type="hidden" id="destination_lat" name="destination_lat" value="">
                                    <input type="hidden" id="destination_lng" name="destination_lng" value="">
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
                                    <input
                                        type="tel"
                                        id="senderPhone"
                                        name="senderPhone"
                                        placeholder="+225 XX XX XX XX XX"
                                        maxlength="19"
                                        autocomplete="tel"
                                        <?= $sessionSenderPhoneDisplay !== '' ? ' readonly data-origin="session"' : '' ?>
                                        <?= $sessionSenderPhoneDigits !== '' ? ' data-raw-value="' . htmlspecialchars($sessionSenderPhoneDigits, ENT_QUOTES) . '"' : '' ?>
                                        value="<?= htmlspecialchars($sessionSenderPhoneDisplay, ENT_QUOTES) ?>"
                                        required
                                    >
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="receiverPhone"><i class="fas fa-phone"></i> Téléphone Destinataire</label>
                                <div class="input-with-icon phone">
                                    <input type="tel" id="receiverPhone" name="receiverPhone" placeholder="+225 XX XX XX XX XX" maxlength="19" required>
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
                        <div class="price-estimate" id="price-calculation-section" style="display:none;">
                            <div class="distance-info" id="distance-info">Distance: -</div>
                            <div class="time-info" id="time-info">⏱️ -</div>
                            <div class="price-breakdown" id="price-breakdown" style="display:none;"></div>
                            <div class="total-price" id="total-price">💰 - FCFA</div>
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
                        <button type="submit" class="submit-btn">
                            🛵 Commander maintenant
                        </button>
                        <!-- Timeline utilisateur après soumission -->
                        <div id="client-timeline" class="client-timeline" style="display:none;">
                            <div class="client-timeline-title">Suivi de votre commande</div>
                            <div id="live-tracking-status" class="live-tracking-badge" style="display:none;">⌛ En attente d'un coursier</div>
                            <ul id="client-timeline-steps" class="client-timeline-steps"></ul>
                            <div id="client-timeline-messages" class="client-timeline-messages"></div>
                        </div>
                    </form>
                    
                    <?php endif; // Fin du contrôle de disponibilité des coursiers ?>
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
    /* Spinner/indicator removed - original styles retained elsewhere */
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
    // Timeline client & suivi amélioré (espèces + paiements électroniques)
    (function(){
        // Affichage du contact du coursier dans la timeline
        function renderCourierContact(coursier) {
            let contactBox = document.getElementById('courier-contact-info');
            if (!contactBox) {
                contactBox = document.createElement('div');
                contactBox.id = 'courier-contact-info';
                contactBox.className = 'courier-contact-info';
                // Insérer juste après la timeline
                const timeline = document.getElementById('client-timeline');
                if (timeline) {
                    timeline.parentNode.insertBefore(contactBox, timeline.nextSibling);
                } else {
                    document.body.appendChild(contactBox);
                }
            }
            if (!coursier || (!coursier.nom && !coursier.telephone)) {
                contactBox.style.display = 'none';
                contactBox.innerHTML = '';
                return;
            }
            contactBox.innerHTML = `
                <div class="courier-contact-title">Votre coursier</div>
                <div class="courier-contact-row">
                    <span class="courier-contact-label">Nom :</span>
                    <span class="courier-contact-value">${coursier.nom ? coursier.nom : 'Non renseigné'}</span>
                </div>
                <div class="courier-contact-row">
                    <span class="courier-contact-label">Téléphone :</span>
                    <span class="courier-contact-value"><a href="tel:${coursier.telephone}">${coursier.telephone ? coursier.telephone : 'Non renseigné'}</a></span>
                </div>
            `;
            contactBox.style.display = 'block';
        }

        // Style pour le bloc contact coursier
        const style = document.createElement('style');
        style.innerHTML = `
            .courier-contact-info {
                margin: 18px 0 0 0;
                background: rgba(46,204,113,0.10);
                border: 1.5px solid rgba(46,204,113,0.35);
                border-radius: 10px;
                padding: 14px 18px;
                color: #1a1a2e;
                font-size: 1rem;
                box-shadow: 0 2px 12px rgba(46,204,113,0.08);
                max-width: 420px;
            }
            .courier-contact-title {
                font-weight: 700;
                color: #27ae60;
                margin-bottom: 8px;
                font-size: 1.08em;
            }
            .courier-contact-row {
                display: flex;
                gap: 10px;
                margin-bottom: 4px;
                align-items: center;
            }
            .courier-contact-label {
                font-weight: 600;
                color: #145a32;
                min-width: 80px;
            }
            .courier-contact-value {
                color: #222;
                font-weight: 500;
            }
            .courier-contact-value a {
                color: #27ae60;
                text-decoration: underline;
            }
        `;
        document.head.appendChild(style);
        const form = document.getElementById('orderForm');
        if (!form) return;

        const timelineBox = document.getElementById('client-timeline');
        const stepsList = document.getElementById('client-timeline-steps');
        const statusBadge = document.getElementById('live-tracking-status');
        const messageBox = document.getElementById('client-timeline-messages');
        const featureConfig = (window.FEATURES && typeof window.FEATURES === 'object') ? window.FEATURES : {};
        // Mode unique: toujours en traitement inline (index) pour TOUS les paiements
        const enhancedEnabled = true;
        window.__cashFlowEnhanced = true;
        window.__cashFlowEnhancedCash = true;

        const baseTimeline = [
            { key: 'pending', icon: '📝', label: 'Commande reçue', description: 'Nous confirmons vos informations', status: 'active' },
            { key: 'confirmed', icon: '✅', label: 'Coursier confirmé', description: 'Assignation du meilleur coursier disponible', status: 'pending' },
            { key: 'pickup', icon: '🛵', label: 'En route pour collecte', description: 'Le coursier se dirige vers le point de départ', status: 'pending' },
            { key: 'transit', icon: '🚚', label: 'Colis récupéré', description: 'Le colis est en chemin vers la destination', status: 'pending' },
            { key: 'delivery', icon: '🏠', label: 'Livraison en cours', description: 'Arrivée imminente à destination', status: 'pending' },
            { key: 'completed', icon: '✨', label: 'Commande terminée', description: 'Livraison confirmée et clôturée', status: 'pending' }
        ];

        const state = {
            payload: null,
            liveContext: null,
            pollTimer: null,
            pollCount: 0,
            maxPoll: 600,
            lastOrderMeta: null,
            timeline: [...baseTimeline],
            timelineLastUpdate: 0,
            messages: [],
            lastMethod: 'cash',
            retryEnabled: false,
            retrying: false
        };

        let attemptRetry = null;

        const safeNumber = (val) => {
            if (val === null || val === undefined || val === '') return null;
            const parsed = typeof val === 'number' ? val : parseFloat(val);
            return Number.isFinite(parsed) ? parsed : null;
        };

        const formatTimestamp = (value) => {
            if (!value) return '';
            try {
                const date = typeof value === 'number' && value > 1e11 ? new Date(value) :
                    (typeof value === 'number' ? new Date(value * 1000) : new Date(value));
                if (Number.isNaN(date.getTime())) return '';
                const now = new Date();
                const sameDay = date.toDateString() === now.toDateString();
                const options = sameDay
                    ? { hour: '2-digit', minute: '2-digit' }
                    : { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' };
                return date.toLocaleString('fr-FR', options);
            } catch (error) {
                return '';
            }
        };

        const setStepState = (element, status) => {
            if (!element) return;
            let normalized = status;
            if (typeof normalized === 'boolean') {
                normalized = normalized ? 'completed' : 'pending';
            }
            if (!normalized) normalized = 'pending';
            element.classList.remove('is-completed', 'is-active', 'is-pending');
            if (normalized === 'completed') {
                element.classList.add('is-completed');
            } else if (normalized === 'active') {
                element.classList.add('is-active');
            } else {
                element.classList.add('is-pending');
            }
            element.dataset.state = normalized;
        };

    const renderTimelineUI = (timelineInput) => {
            if (!stepsList) return;
            const steps = Array.isArray(timelineInput) && timelineInput.length ? timelineInput : baseTimeline;
            stepsList.innerHTML = '';
            steps.forEach(step => {
                const li = document.createElement('li');
                li.className = 'client-timeline-step';
                li.dataset.key = step.key || '';
                const status = step.status || (step.done ? 'completed' : 'pending');
                setStepState(li, status);
                const icon = step.icon || (status === 'completed' ? '✅' : status === 'active' ? '🛵' : '⏳');
                const label = step.label || step.key || '';
                const description = step.description || '';
                const formattedTs = formatTimestamp(step.datetime || step.timestamp || step.ts);
                li.innerHTML = `
                    <span class="step-icon">${icon}</span>
                    <div class="step-details">
                        <span class="step-label">${label}</span>
                        ${description ? `<small>${description}</small>` : ''}
                        ${formattedTs ? `<small class="step-time">${formattedTs}</small>` : ''}
                    </div>
                `;
                stepsList.appendChild(li);
            });
        };

    const renderTimelineMessages = (messagesInput) => {
            if (!messageBox) return;
            const items = Array.isArray(messagesInput) ? messagesInput.filter(msg => msg && msg.text) : [];
            if (!items.length) {
                messageBox.style.display = 'none';
                messageBox.innerHTML = '';
                return;
            }
            messageBox.innerHTML = '';
            items.forEach(msg => {
                const div = document.createElement('div');
                div.className = 'client-timeline-message';
                if (msg.type) div.dataset.type = msg.type;
                const span = document.createElement('span');
                span.className = 'timeline-message-text';
                span.textContent = msg.text;
                div.appendChild(span);

                const canRetry = state.retryEnabled && !state.retrying && msg.type === 'error' && msg.retry !== false;
                if (canRetry && typeof attemptRetry === 'function') {
                    const retryBtn = document.createElement('button');
                    retryBtn.type = 'button';
                    retryBtn.className = 'timeline-retry-btn';
                    retryBtn.textContent = msg.retryLabel || 'Réessayer';
                    retryBtn.addEventListener('click', () => {
                        if (state.retrying) return;
                        retryBtn.disabled = true;
                        const retryPromise = attemptRetry();
                        if (retryPromise && typeof retryPromise.catch === 'function') {
                            retryPromise.catch(() => {
                                // La gestion des erreurs réactive l'UI via stopTracking
                            });
                        }
                    });
                    div.appendChild(retryBtn);
                }
                messageBox.appendChild(div);
            });
            messageBox.style.display = 'flex';
        };

        const markStep = (key, status) => {
            if (!stepsList) return;
            const el = stepsList.querySelector(`[data-key="${key}"]`);
            if (!el) return;
            setStepState(el, status);
        };

        const updateBadge = (mode, detail) => {
            if (!statusBadge) return;
            if (mode === null) {
                statusBadge.style.display = 'none';
                statusBadge.textContent = '';
                statusBadge.dataset.mode = '';
                return;
            }
            const defaultMessages = {
                pending: "⌛ En attente d'un coursier",
                live: '🛰️ Suivi en direct actif',
                payment: '💳 Paiement en cours...',
                stopped: 'Suivi interrompu',
                delivered: '✅ Livraison terminée'
            };
            const text = detail || defaultMessages[mode] || defaultMessages.pending;
            statusBadge.dataset.mode = mode;
            statusBadge.textContent = text;
            statusBadge.style.display = 'inline-flex';
        };

        const showTimeline = () => {
            if (timelineBox) timelineBox.style.display = 'block';
            renderTimelineUI(state.timeline);
            renderTimelineMessages(state.messages);
            updateBadge('pending');
        };

        const capturePoint = (type) => {
            const payload = state.payload || {};
            const point = { lat: null, lng: null, address: null };
            if (type === 'departure') {
                point.address = payload.departure || document.getElementById('departure')?.value || null;
                const latField = safeNumber(form.querySelector('#departure_lat')?.value);
                const lngField = safeNumber(form.querySelector('#departure_lng')?.value);
                const fromPayloadLat = safeNumber(payload.departure_lat);
                const fromPayloadLng = safeNumber(payload.departure_lng);
                point.lat = latField ?? fromPayloadLat;
                point.lng = lngField ?? fromPayloadLng;
                if ((!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) && window.markerA && typeof window.markerA.getPosition === 'function') {
                    const posA = window.markerA.getPosition();
                    if (posA) {
                        point.lat = typeof posA.lat === 'function' ? posA.lat() : posA.lat;
                        point.lng = typeof posA.lng === 'function' ? posA.lng() : posA.lng;
                    }
                }
                if (Number.isFinite(point.lat)) payload.departure_lat = point.lat;
                if (Number.isFinite(point.lng)) payload.departure_lng = point.lng;
            } else {
                point.address = payload.destination || document.getElementById('destination')?.value || null;
                const latField = safeNumber(form.querySelector('#destination_lat')?.value);
                const lngField = safeNumber(form.querySelector('#destination_lng')?.value);
                const fromPayloadLat = safeNumber(payload.destination_lat);
                const fromPayloadLng = safeNumber(payload.destination_lng);
                point.lat = latField ?? fromPayloadLat;
                point.lng = lngField ?? fromPayloadLng;
                if ((!Number.isFinite(point.lat) || !Number.isFinite(point.lng)) && window.markerB && typeof window.markerB.getPosition === 'function') {
                    const posB = window.markerB.getPosition();
                    if (posB) {
                        point.lat = typeof posB.lat === 'function' ? posB.lat() : posB.lat;
                        point.lng = typeof posB.lng === 'function' ? posB.lng() : posB.lng;
                    }
                }
                if (Number.isFinite(point.lat)) payload.destination_lat = point.lat;
                if (Number.isFinite(point.lng)) payload.destination_lng = point.lng;
            }
            return point;
        };

        const ensureLiveContext = (orderId) => {
            if (orderId && state.liveContext && state.liveContext.orderId === orderId) return state.liveContext;
            const departurePoint = capturePoint('departure');
            const destinationPoint = capturePoint('destination');
            state.liveContext = {
                orderId: orderId || state.liveContext?.orderId || null,
                route: {
                    departure: departurePoint,
                    destination: destinationPoint
                },
                payloadSnapshot: {
                    departure: state.payload?.departure ?? null,
                    destination: state.payload?.destination ?? null,
                    priority: state.payload?.priority ?? null
                }
            };
            return state.liveContext;
        };

        const clearPollTimer = () => {
            if (state.pollTimer) {
                clearTimeout(state.pollTimer);
                state.pollTimer = null;
            }
        };

        const stopTracking = (reason, detail) => {
            clearPollTimer();
            state.lastOrderMeta = null;
            state.pollCount = 0;
            state.timelineLastUpdate = 0;
            state.retryEnabled = false;
            state.retrying = false;
            if (window.ClientLiveTracking && typeof window.ClientLiveTracking.stop === 'function') {
                window.ClientLiveTracking.stop(reason);
            }
            let messageType = 'info';
            let messageText = detail || '';
            if (reason === 'delivered') {
                updateBadge('delivered');
                messageType = 'success';
                messageText = detail || 'Votre colis a été livré avec succès.';
            } else if (reason === 'fallback') {
                updateBadge('stopped', detail || 'Retour au mode standard');
                messageType = 'warning';
                messageText = detail || 'Retour au mode standard.';
            } else if (reason === 'erreur') {
                updateBadge('stopped', detail || 'Suivi indisponible');
                messageType = 'error';
                messageText = detail || 'Suivi indisponible.';
                if (state.payload) {
                    state.retryEnabled = true;
                }
            } else if (reason === 'timeout' || reason === 'délai') {
                updateBadge('stopped', detail || 'Suivi indisponible (délai)');
                messageType = 'warning';
                messageText = detail || 'Suivi indisponible (délai).';
            } else if (reason) {
                updateBadge('stopped', detail || 'Suivi interrompu');
                messageType = 'info';
                messageText = detail || `Suivi interrompu (${reason})`;
            } else {
                updateBadge('stopped');
            }
            if (messageText) {
                const messagePayload = { type: messageType, text: messageText };
                if (messageType === 'error' && state.retryEnabled) {
                    messagePayload.retry = true;
                }
                state.messages = [messagePayload];
            } else {
                state.messages = [];
            }
            renderTimelineMessages(state.messages);
        };

        const notifyTracking = (statusData, context) => {
            if (statusData && statusData.live_tracking) {
                updateBadge('live');
            } else {
                updateBadge('pending');
            }
            if (window.ClientLiveTracking && typeof window.ClientLiveTracking.notifyStatus === 'function') {
                window.ClientLiveTracking.notifyStatus(statusData, context);
            }
        };

        const buildStatusUrl = () => {
            const base = new URL('/api/timeline_sync.php', window.location.origin);
            if (state.lastOrderMeta?.order_id) {
                base.searchParams.set('order_id', state.lastOrderMeta.order_id);
            } else if (state.lastOrderMeta?.code_commande) {
                base.searchParams.set('code_commande', state.lastOrderMeta.code_commande);
            }
            if (state.timelineLastUpdate) {
                base.searchParams.set('last_check', state.timelineLastUpdate);
            }
            return base.toString();
        };

        // Désactive tous les champs et modes de paiement après acceptation
        function lockOrderForm() {
            if (!form) return;
            // Désactive tous les champs du formulaire
            Array.from(form.elements).forEach(el => {
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT') {
                    el.readOnly = true;
                    el.disabled = true;
                }
            });
            // Désactive tous les boutons radio de paiement
            const radios = form.querySelectorAll('input[type="radio"][name="paymentMethod"]');
            radios.forEach(r => { r.disabled = true; });
            // Désactive le bouton de soumission
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            // Ajoute une classe visuelle si besoin
            form.classList.add('order-locked');
        }

        // ...existing code...
        const startPolling = (orderMeta) => {
            if (!orderMeta) return;
            clearPollTimer();
            state.lastOrderMeta = {
                order_id: orderMeta.order_id || orderMeta.id || null,
                code_commande: orderMeta.code_commande || orderMeta.numero_commande || null,
                coursier_id: orderMeta.coursier_id || null
            };
            state.pollCount = 0;
            state.timelineLastUpdate = 0;

            const poll = () => {
                state.pollCount += 1;
                fetch(buildStatusUrl(), { cache: 'no-store', headers: { 'Accept': 'application/json' } })
                    .then(async (r) => {
                        const txt = await r.text();
                        let s = null;
                        try { s = txt ? JSON.parse(txt) : null; } catch (e) { s = null; }
                        if (!r.ok || !s) {
                            state.pollTimer = setTimeout(poll, 5000);
                            return;
                        }
                        return s;
                    })
                    .then(s => {
                        if (s && s.success && s.data) {
                            const data = s.data;
                            if (typeof data.last_update !== 'undefined' && data.last_update !== null) {
                                state.timelineLastUpdate = Number(data.last_update) || 0;
                            } else {
                                state.timelineLastUpdate = Math.floor(Date.now() / 1000);
                            }

                            const timeline = Array.isArray(data.timeline) && data.timeline.length ? data.timeline : state.timeline;
                            state.timeline = timeline.length ? timeline : baseTimeline;
                            renderTimelineUI(state.timeline);

                            if (Array.isArray(data.messages)) {
                                state.messages = data.messages.filter(msg => msg && msg.text);
                                renderTimelineMessages(state.messages);
                            } else if (state.messages.length) {
                                state.messages = [];
                                renderTimelineMessages(state.messages);
                            }

                            const statut = data.statut || 'nouvelle';
                            const statusData = {
                                order_id: data.order_id || state.lastOrderMeta?.order_id || null,
                                statut,
                                live_tracking: !!data.coursier_position || ['assignee', 'picked_up', 'en_cours'].includes(statut),
                                coursier_id: state.lastOrderMeta?.coursier_id ?? null,
                                timeline: state.timeline,
                                messages: state.messages
                            };

                            if (data.coursier && data.coursier.id) {
                                statusData.coursier_id = data.coursier.id;
                            } else if (state.lastOrderMeta?.coursier_id) {
                                statusData.coursier_id = state.lastOrderMeta.coursier_id;
                            }
                            if (statusData.coursier_id) {
                                state.lastOrderMeta.coursier_id = statusData.coursier_id;
                            }


                            // Affichage contact coursier ET verrouillage du formulaire si commande acceptée ou plus
                            try {
                                const showContact = ['acceptee','picked_up','en_cours','livree'].includes(statut);
                                if (showContact && data.coursier && (data.coursier.nom || data.coursier.telephone)) {
                                    renderCourierContact(data.coursier);
                                    lockOrderForm();
                                } else {
                                    renderCourierContact(null);
                                }
                                // Si la commande est acceptée ou plus, verrouille le formulaire même sans contact
                                if (showContact) {
                                    lockOrderForm();
                                }
                            } catch (e) {}

                            // Mise à jour carte en temps réel si position disponible
                            try {
                                if (data.coursier_position && Number.isFinite(data.coursier_position.lat) && Number.isFinite(data.coursier_position.lng)) {
                                    updateCourierOnMap(data.coursier_position);
                                }
                            } catch (e) {}

                            const ctx = ensureLiveContext(statusData.order_id);
                            notifyTracking(statusData, ctx);

                            if (statut === 'livree') {
                                stopTracking('delivered');
                                renderTimelineUI(state.timeline);
                                return;
                            }
                            if (!statusData.live_tracking && state.pollCount >= state.maxPoll) {
                                stopTracking('timeout', 'Suivi indisponible (délai)');
                                return;
                            }
                        }
                        state.pollTimer = setTimeout(poll, 5000);
                    })
                    .catch(() => {
                        state.pollTimer = setTimeout(poll, 5000);
                    });
            };

            state.pollTimer = setTimeout(poll, 2000);
        };

        const ensureLatLng = (payload) => new Promise((resolve) => {
            if (payload.departure_lat && payload.departure_lng) {
                resolve();
                return;
            }
            if (!window.google || !google.maps || !google.maps.Geocoder) {
                resolve();
                return;
            }
            try {
                const geocoder = new google.maps.Geocoder();
                if (!payload.departure || payload.departure.length < 3) {
                    resolve();
                    return;
                }
                geocoder.geocode({ address: payload.departure, region: 'ci' }, (results, status) => {
                    if (status === 'OK' && results && results[0]) {
                        const loc = results[0].geometry?.location;
                        if (loc) {
                            payload.departure_lat = loc.lat();
                            payload.departure_lng = loc.lng();
                            const latEl = form.querySelector('#departure_lat');
                            const lngEl = form.querySelector('#departure_lng');
                            if (latEl) latEl.value = payload.departure_lat;
                            if (lngEl) lngEl.value = payload.departure_lng;
                        }
                    }
                    resolve();
                });
            } catch (e) {
                resolve();
            }
        });

        const submitOrder = (payload) => fetch((window.ROOT_PATH || '') + '/api/submit_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        }).then(async (r) => {
            const txt = await r.text();
            let json = null;
            try { json = txt ? JSON.parse(txt) : null; } catch (e) { json = null; }
            if (!r.ok || !json) {
                const detail = (json && (json.message || json.error)) || txt || 'Réponse serveur invalide';
                throw new Error(detail);
            }
            return json;
        });

        const readPriceInfo = () => {
            const priceData = window.currentPriceData;
            const tripData = window.currentTripData || {};
            const price = (priceData && typeof priceData.totalPrice === 'number') ? priceData.totalPrice : (tripData.price || 0);
            const distance = priceData && typeof priceData.totalDistance === 'number' ? `${priceData.totalDistance.toFixed(1)} km` : (tripData.distance || '');
            const duration = typeof tripData.estimatedTime !== 'undefined' && tripData.estimatedTime !== null ? `${tripData.estimatedTime} min` : '';
            return { price, distance, duration };
        };

        const preparePayload = (method) => {
            const fd = new FormData(form);
            if (!fd.has('packageDescription')) {
                const alt = form.querySelector('#packageDesc');
                if (alt && alt.value) fd.set('packageDescription', alt.value);
            }
            const priceInfo = readPriceInfo();
            const payload = {
                departure: fd.get('departure') || '',
                destination: fd.get('destination') || '',
                senderPhone: fd.get('senderPhone') || '',
                receiverPhone: fd.get('receiverPhone') || '',
                packageDescription: fd.get('packageDescription') || fd.get('packageDesc') || '',
                priority: fd.get('priority') || 'normale',
                paymentMethod: method,
                price: priceInfo.price || 0,
                distance: priceInfo.distance || window.currentTripData?.distance || '',
                duration: priceInfo.duration || (window.currentTripData?.estimatedTime ? `${window.currentTripData.estimatedTime} min` : '')
            };
            const depLat = safeNumber(form.querySelector('#departure_lat')?.value);
            const depLng = safeNumber(form.querySelector('#departure_lng')?.value);
            const destLat = safeNumber(form.querySelector('#destination_lat')?.value);
            const destLng = safeNumber(form.querySelector('#destination_lng')?.value);
            if (Number.isFinite(depLat)) payload.departure_lat = depLat;
            if (Number.isFinite(depLng)) payload.departure_lng = depLng;
            if (Number.isFinite(destLat)) payload.destination_lat = destLat;
            if (Number.isFinite(destLng)) payload.destination_lng = destLng;
            return payload;
        };

        // Simple helper: ouvrir un paiement dans une modale inline si aucune modale projet n'est fournie
        const openPaymentInline = (paymentUrl) => {
            try {
                if (typeof window.showPaymentModal === 'function') {
                    window.showPaymentModal(paymentUrl);
                    return;
                }
                let modal = document.getElementById('payment-inline-modal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'payment-inline-modal';
                    Object.assign(modal.style, {
                        position: 'fixed', inset: '0', background: 'rgba(0,0,0,0.6)', zIndex: '9999', display: 'flex',
                        alignItems: 'center', justifyContent: 'center', padding: '20px'
                    });
                    const box = document.createElement('div');
                    Object.assign(box.style, { width: 'min(900px, 100%)', height: 'min(80vh, 800px)', background: '#111', borderRadius: '12px', overflow: 'hidden', border: '1px solid rgba(212,168,83,0.4)', position: 'relative' });
                    const close = document.createElement('button');
                    close.textContent = 'Fermer';
                    Object.assign(close.style, { position: 'absolute', top: '8px', right: '8px', background: '#222', color: '#fff', border: '1px solid rgba(255,255,255,0.2)', borderRadius: '6px', padding: '6px 10px', cursor: 'pointer' });
                    close.addEventListener('click', () => { modal.remove(); });
                    const iframe = document.createElement('iframe');
                    iframe.src = paymentUrl;
                    iframe.title = 'Paiement';
                    iframe.allow = 'payment *; clipboard-read; clipboard-write;';
                    Object.assign(iframe.style, { width: '100%', height: '100%', border: '0' });
                    box.appendChild(close);
                    box.appendChild(iframe);
                    modal.appendChild(box);
                    document.body.appendChild(modal);
                } else {
                    const iframe = modal.querySelector('iframe');
                    if (iframe) iframe.src = paymentUrl;
                }
            } catch (e) {
                console.warn('Paiement inline indisponible, URL:', paymentUrl);
            }
        };

        const afterEnhancedSuccess = (method, data) => {
            state.retrying = false;
            state.retryEnabled = false;
            if (method !== 'cash' && data && data.payment_url) {
                updateBadge('payment');
                openPaymentInline(data.payment_url);
            } else if (method !== 'cash') {
                updateBadge('payment', 'Paiement à finaliser');
            }
        };

        const handleEnhancedSubmit = async (method) => {
            state.lastMethod = method || state.lastMethod || 'cash';
            state.retryEnabled = false;
            state.payload = preparePayload(method);
            state.liveContext = null;
            state.timeline = [...baseTimeline];
            state.messages = [];
            state.timelineLastUpdate = 0;
            showTimeline();
            updateBadge(method === 'cash' ? 'pending' : 'payment');
            await ensureLatLng(state.payload);
            const res = await submitOrder(state.payload);
            if (!res || !res.success) {
                throw new Error(res?.message || 'Soumission échouée');
            }
            const data = res.data || {};
            
            // Marquer immédiatement les premières étapes
            markStep('commande_creee', 'completed');
            markStep('recherche_coursier', 'active');
            
            // Ajouter message de confirmation
            state.messages.push({
                text: `✅ Commande ${data.order_number || data.code_commande || 'créée'} enregistrée avec succès`,
                type: 'success'
            });
            renderTimelineMessages(state.messages);
            
            startPolling(data);
            return data;
        };

        attemptRetry = async () => {
            if (state.retrying) return;
            const fallbackMethod = (document.querySelector('input[name="paymentMethod"]:checked') || {}).value || 'cash';
            const method = state.lastMethod || fallbackMethod;
            state.retrying = true;
            state.retryEnabled = false;
            window.__orderFlowHandled = true;
            try {
                const data = await handleEnhancedSubmit(method);
                afterEnhancedSuccess(method, data);
                return data;
            } catch (err) {
                console.error('❌ Erreur lors du nouvel essai:', err);
                state.retrying = false;
                const msg = (err && (err.message || err)) ? String(err.message || err) : 'Une erreur est survenue.';
                stopTracking('erreur', msg);
                window.__orderFlowHandled = false;
                throw err;
            }
        };

        form.addEventListener('submit', function(ev){
            try {
                // Mode unique: toujours en inline
                const method = (document.querySelector('input[name="paymentMethod"]:checked') || {}).value || 'cash';
                if (typeof window.currentClient !== 'undefined' && !window.currentClient) {
                    return;
                }
                window.__orderFlowHandled = true;
                ev.preventDefault();
                handleEnhancedSubmit(method).then(data => {
                    afterEnhancedSuccess(method, data);
                }).catch(err => {
                    console.error('❌ Erreur flux amélioré:', err);
                    const msg = (err && (err.message || err)) ? String(err.message || err) : 'Une erreur est survenue.';
                    // Toujours inline: afficher l’erreur dans la timeline et permettre de réessayer
                    stopTracking('erreur', msg);
                    window.__orderFlowHandled = false;
                });
            } catch (error) {
                console.error('Erreur timeline améliorée:', error);
            }
        }, { capture: true });

        window.OrderTrackingBridge = {
            updateBadge,
            ensureLiveContext,
            markStep,
            renderTimeline: (steps) => {
                if (Array.isArray(steps) && steps.length) {
                    state.timeline = steps;
                }
                renderTimelineUI(state.timeline);
            },
            renderMessages: (messages) => {
                if (Array.isArray(messages)) {
                    state.messages = messages;
                }
                renderTimelineMessages(state.messages);
            },
            stop: stopTracking,
            getPayload: () => state.payload,
            getTimeline: () => state.timeline,
            showTimeline,
            isEnhanced: () => enhancedEnabled
        };
    })();
    </script>
