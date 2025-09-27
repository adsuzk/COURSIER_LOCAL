<?php
// sections/js_route_calculation.php - Calcul d'itinéraires et prix
require_once __DIR__ . '/../config.php';

// Charger paramètres de tarification actuels
$prix_km = 300;
$frais_base = 500;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
    $params = [];
    foreach ($stmt as $row) { $params[$row['parametre']] = (float)$row['valeur']; }
    if (isset($params['prix_kilometre'])) { $prix_km = (int)$params['prix_kilometre']; }
    if (isset($params['frais_base'])) { $frais_base = (int)$params['frais_base']; }
} catch (Exception $e) { /* fallback valeurs par défaut */ }
?>
    <script>
    // Fonction pour calculer un itinéraire
    function calculateRoute(origin, destination) {
        if (!origin || !destination) {
            console.log('Origine ou destination manquante');
            return;
        }
        
        const request = {
            origin: origin,
            destination: destination,
            travelMode: google.maps.TravelMode.DRIVING,
            avoidHighways: false,
            avoidTolls: false
        };

        directionsService.route(request, (result, status) => {
            if (status === 'OK') {
                // Masquer les marqueurs A et B pendant l'affichage de l'itinéraire
                if (markerA) markerA.setVisible(false);
                if (markerB) markerB.setVisible(false);
                
                directionsRenderer.setDirections(result);
                
                // Calculer le prix basé sur la distance
                const distance = result.routes[0].legs[0].distance.value / 1000; // en km
                const duration = result.routes[0].legs[0].duration.text;
                
                updatePriceEstimate(distance, duration);
            } else {
                console.error('Erreur de calcul d\'itinéraire:', status);
            }
        });
    }

    // Mise à jour de l'estimation du prix
    function updatePriceEstimate(distance, duration) {
    const priceElement = document.getElementById('estimatedPrice');
        if (!priceElement) {
            return;
        }

        const priority = document.querySelector('input[name="priority"]:checked');
        const urgency = priority ? priority.value : 'normale';
        const priceData = calculateDynamicPrice({ distance }, urgency);

        priceElement.innerHTML = `
            💰 Prix estimé: <strong>${priceData.totalPrice.toLocaleString()} FCFA</strong><br>
            📏 Distance: ${priceData.totalDistance.toFixed(1)} km<br>
            ⏱️ Durée estimée: ${duration}
        `;
        priceElement.style.display = 'block';
    }
    
    // Fonction pour effacer l'itinéraire et réafficher les marqueurs A et B
    function clearRoute() {
        directionsRenderer.setDirections({routes: []});
        if (markerA) markerA.setVisible(true);
        if (markerB) markerB.setVisible(true);
    }
    
    // Fonction pour effacer tous les marqueurs
    function clearMarkers() {
        if (markerA) {
            markerA.setMap(null);
            markerA = null;
        }
        if (markerB) {
            markerB.setMap(null);
            markerB = null;
        }
        clearRoute();
        document.getElementById('estimatedPrice').style.display = 'none';
    }
    
    // Fonction pour mettre à jour l'adresse depuis les coordonnées
    function updateAddressFromCoordinates(position, fieldType) {
        if (!google || !google.maps || !google.maps.Geocoder) {
            console.error('Service Google Maps Geocoder non disponible');
            return;
        }
        
        const geocoder = new google.maps.Geocoder();
        
        geocoder.geocode({
            location: position,
            region: 'CI' // Côte d'Ivoire
        }, (results, status) => {
            if (status === 'OK' && results && results[0]) {
                const address = results[0].formatted_address;
                const inputField = document.getElementById(fieldType);
                if (inputField) {
                    inputField.value = `📍 ${address}`;
                    console.log(`${fieldType} mis à jour:`, address);
                } else {
                    console.error(`Champ ${fieldType} non trouvé`);
                }

                // Renseigner les coordonnées si champ caché présent (utile pour l'attribution du coursier)
                try {
                    const lat = (typeof position.lat === 'function') ? position.lat() : position.lat;
                    const lng = (typeof position.lng === 'function') ? position.lng() : position.lng;
                        if (fieldType === 'departure' || fieldType === 'destination') {
                            const latEl = document.getElementById(fieldType === 'departure' ? 'departure_lat' : 'destination_lat');
                            const lngEl = document.getElementById(fieldType === 'departure' ? 'departure_lng' : 'destination_lng');
                            if (latEl) latEl.value = lat;
                            if (lngEl) lngEl.value = lng;
                        }
                } catch (e) { console.warn('Impossible de setter lat/lng cachés:', e); }
            } else {
                console.error('Erreur de géocodage:', status);
                const inputField = document.getElementById(fieldType);
                try {
                    const lat = (typeof position.lat === 'function') ? position.lat() : position.lat;
                    const lng = (typeof position.lng === 'function') ? position.lng() : position.lng;
                    if (inputField) {
                        inputField.value = `📍 ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
                    }
                    if (fieldType === 'departure' || fieldType === 'destination') {
                        const latEl = document.getElementById(fieldType === 'departure' ? 'departure_lat' : 'destination_lat');
                        const lngEl = document.getElementById(fieldType === 'departure' ? 'departure_lng' : 'destination_lng');
                        if (latEl) latEl.value = lat;
                        if (lngEl) lngEl.value = lng;
                    }
                } catch (e) {
                    if (inputField) {
                        inputField.value = '📍 Coordonnées indisponibles';
                    }
                }
            }
        });
    }

    // 🚀 NOUVELLES FONCTIONS CALCUL DYNAMIQUE - 10/08/2025
    
    // Configuration tarifaire Suzosky
    const PRICING_CONFIG = {
        baseFare: <?php echo (int)$frais_base; ?>,   // Tarif de base FCFA
        pricePerKm: <?php echo (int)$prix_km; ?>,    // Prix par kilomètre
        timeBasedRate: 50,                 // Prix par minute
        urgencyMultiplier: {
            'normale': 1.0,
            'urgente': 1.5,
            'express': 2.0
        },
        weatherSurcharge: {
            'clear': 0,
            'rain': 500,
            'storm': 1000
        }
    };
    
    // Calcul temps de trajet dynamique
    async function calculateDynamicDeliveryTime(departure, destination) {
        try {
            if (!window.directionsService) {
                console.log('⚠️ Service Google Maps non disponible, utilisation estimation');
                return getStaticEstimate(departure, destination);
            }
            
            return new Promise((resolve, reject) => {
                const request = {
                    origin: departure,
                    destination: destination,
                    travelMode: google.maps.TravelMode.DRIVING,
                    drivingOptions: {
                        departureTime: new Date(),
                        trafficModel: google.maps.TrafficModel.BEST_GUESS
                    },
                    avoidHighways: false,
                    avoidTolls: true
                };
                
                window.directionsService.route(request, (result, status) => {
                    if (status === 'OK') {
                        const route = result.routes[0].legs[0];
                        let baseTime = route.duration_in_traffic ? 
                                     route.duration_in_traffic.value : 
                                     route.duration.value;
                        
                        // Ajustements heures de pointe
                        const currentHour = new Date().getHours();
                        if ((currentHour >= 7 && currentHour <= 9) || (currentHour >= 17 && currentHour <= 19)) {
                            baseTime *= 1.4; // +40% heures de pointe
                        }
                        
                        resolve({
                            estimatedTime: Math.ceil(baseTime / 60), // Minutes
                            distance: route.distance.value / 1000, // Kilomètres
                            trafficCondition: route.duration_in_traffic ? 
                                           'Temps réel avec trafic' : 
                                           'Estimation standard',
                            baseRoute: route
                        });
                    } else {
                        console.log('Erreur directions:', status);
                        resolve(getStaticEstimate(departure, destination));
                    }
                });
            });
        } catch (error) {
            console.error('Erreur calcul temps:', error);
            return getStaticEstimate(departure, destination);
        }
    }
    
    // Estimation statique de fallback
    function getStaticEstimate(departure, destination) {
        // Estimation basique pour Abidjan
        const estimatedDistance = 15; // km moyen
        const estimatedTime = 35; // minutes moyen
        
        return {
            estimatedTime: estimatedTime,
            distance: estimatedDistance,
            trafficCondition: 'Estimation approximative',
            baseRoute: null
        };
    }
    
    // Calcul prix dynamique
    function normalizeDistanceKm(input) {
        if (input === null || typeof input === 'undefined') {
            return 0;
        }

        if (typeof input === 'number' && Number.isFinite(input)) {
            return Math.max(0, input);
        }

        if (typeof input === 'string') {
            const match = input.replace(',', '.').match(/([\d.]+)/);
            return match ? Math.max(0, parseFloat(match[1])) : 0;
        }

        if (typeof input === 'object') {
            if (typeof input.distance === 'number' && Number.isFinite(input.distance)) {
                return Math.max(0, input.distance);
            }
            if (typeof input.distance === 'string') {
                const match = input.distance.replace(',', '.').match(/([\d.]+)/);
                return match ? Math.max(0, parseFloat(match[1])) : 0;
            }
        }

        return 0;
    }

    function calculateDynamicPrice(tripData, urgency = 'normale') {
        try {
            const distanceKm = normalizeDistanceKm(tripData);
            const multipliers = {
                normale: { base: 1.0, perKm: 1.0 },
                urgente: { base: 1.4, perKm: 1.3 },
                express: { base: 1.8, perKm: 1.6 }
            };

            const multiplier = multipliers[urgency] ?? multipliers.normale;
            const baseFare = Math.max(PRICING_CONFIG.baseFare, Math.round(PRICING_CONFIG.baseFare * multiplier.base));
            const perKmRate = Math.max(PRICING_CONFIG.pricePerKm, Math.round(PRICING_CONFIG.pricePerKm * multiplier.perKm));
            const distanceCharge = Math.round(Math.max(0, distanceKm) * perKmRate);
            const totalPrice = Math.max(baseFare, baseFare + distanceCharge);

            return {
                totalPrice,
                breakdown: {
                    baseFare,
                    perKmRate,
                    distanceCharge,
                    baseMultiplier: multiplier.base,
                    perKmMultiplier: multiplier.perKm
                },
                currency: 'FCFA',
                totalDistance: Math.max(0, distanceKm)
            };
        } catch (error) {
            console.error('Erreur calcul prix:', error);
            return {
                totalPrice: Math.round(PRICING_CONFIG.baseFare),
                currency: 'FCFA',
                breakdown: { error: 'Calcul en cours...' },
                totalDistance: 0
            };
        }
    }
    
    // Affichage des informations de trajet et prix
    async function displayTripInfo() {
        const departure = document.getElementById('departure').value.trim();
        const destination = document.getElementById('destination').value.trim();
        
        // SEULS DÉPART ET DESTINATION SONT OBLIGATOIRES
        if (!departure || !destination) {
            const estimatedPrice = document.getElementById('estimatedPrice');
            const paymentMethods = document.getElementById('paymentMethods');
            
            if (estimatedPrice) estimatedPrice.style.display = 'none';
            if (paymentMethods) paymentMethods.style.display = 'none';
            return;
        }
        
        console.log('📋 Tous les champs obligatoires remplis, affichage des modes de paiement');
        
        // Afficher les sections (avec vérification null)
        const paymentMethods = document.getElementById('paymentMethods');
        if (paymentMethods) {
            paymentMethods.style.display = 'block';
        }
        
        // Calcul temps et distance
        const tripData = await calculateDynamicDeliveryTime(departure, destination);
        
        // Récupération priorité sélectionnée
        const urgency = document.querySelector('input[name="priority"]:checked')?.value || 'normale';
        
        // Calcul prix
        const priceData = calculateDynamicPrice(tripData, urgency);
        
        // Mise à jour prix (avec vérification null)
        const priceElement = document.getElementById('estimatedPrice');
        if (priceElement) {
            priceElement.style.display = 'block';
            priceElement.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(212,168,83,0.1); border: 1px solid #D4A853; border-radius: 12px; padding: 16px; margin: 10px 0;">
                    <div>
                        <span style="font-size: 1.1rem; font-weight: 600; color: #D4A853;">Prix estimé:</span>
                        <span style="font-size: 1.4rem; font-weight: 900; color: #D4A853; margin-left: 8px;">${priceData.totalPrice.toLocaleString()} ${priceData.currency}</span>
                    </div>
                    <div style="font-size: 0.85rem; color: rgba(255,255,255,0.7);">
                        Distance: ${priceData.totalDistance.toFixed(1)}km
                    </div>
                </div>
            `;
        }
        
        // Sauvegarder pour usage ultérieur
        window.currentTripData = tripData;
        window.currentPriceData = priceData;
        
        console.log('📊 Informations trajet mises à jour:', { tripData, priceData });
    }

    // FONCTION MANQUANTE : Vérification des champs et affichage des modes de paiement
    function checkFormCompleteness() {
        console.log('🔍 checkFormCompleteness() appelée');
        
        const departure = document.getElementById('departure').value.trim();
        const destination = document.getElementById('destination').value.trim();
        
        console.log('📋 États des champs obligatoires:', { departure, destination });
        
        // SEULS DÉPART ET ARRIVÉE SONT OBLIGATOIRES pour afficher les modes de paiement
        if (!departure || !destination) {
            console.log('⚠️ Départ ou arrivée manquant, masquage des modes de paiement');
            const pm = document.getElementById('paymentMethods');
            const ep = document.getElementById('estimatedPrice');
            if (pm) pm.style.display = 'none';
            if (ep) ep.style.display = 'none';
            return;
        }
        
        console.log('✅ Départ et arrivée remplis, affichage des modes de paiement');
        
        // Afficher les modes de paiement
        const paymentMethods = document.getElementById('paymentMethods');
        if (paymentMethods) {
            paymentMethods.style.display = 'block';
            console.log('💳 Section modes de paiement affichée');
        } else {
            console.error('❌ Element paymentMethods introuvable !');
        }
        
        // Déclencher le calcul de prix si possible
        if (typeof displayTripInfo === 'function') {
            displayTripInfo();
        }
    }

    // AJOUTER LES ÉVÉNEMENTS POUR DÉCLENCHER L'AFFICHAGE DES MODES DE PAIEMENT
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🔧 Initialisation des événements pour modes de paiement');
        
        // Fonction qui vérifie les champs et affiche les modes de paiement
        function triggerFormCheck() {
            console.log('🔍 Vérification des champs du formulaire...');
            // Petite temporisation pour permettre à l'utilisateur de terminer sa saisie
            setTimeout(() => {
                checkFormCompleteness();
            }, 300);
        }
        
        // Écouteurs sur les champs obligatoires
        const departureField = document.getElementById('departure');
        const destinationField = document.getElementById('destination');
        const senderPhoneField = document.getElementById('senderPhone');
        const receiverPhoneField = document.getElementById('receiverPhone');
        
        // Ajouter les événements sur tous les champs obligatoires
        if (departureField) {
            departureField.addEventListener('input', triggerFormCheck);
            departureField.addEventListener('change', triggerFormCheck);
            console.log('✓ Événements ajoutés sur le champ départ');
        }
        
        if (destinationField) {
            destinationField.addEventListener('input', triggerFormCheck);
            destinationField.addEventListener('change', triggerFormCheck);
            console.log('✓ Événements ajoutés sur le champ arrivée');
        }
        
        if (senderPhoneField) {
            senderPhoneField.addEventListener('input', triggerFormCheck);
            senderPhoneField.addEventListener('change', triggerFormCheck);
            console.log('✓ Événements ajoutés sur le téléphone expéditeur');
        }
        
        if (receiverPhoneField) {
            receiverPhoneField.addEventListener('input', triggerFormCheck);
            receiverPhoneField.addEventListener('change', triggerFormCheck);
            console.log('✓ Événements ajoutés sur le téléphone destinataire');
        }
        
        console.log('🎯 Tous les événements de vérification du formulaire sont configurés');
    });
    </script>
