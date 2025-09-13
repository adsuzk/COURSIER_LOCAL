<?php
// sections/js_route_calculation.php - Calcul d'itinéraires et prix
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
        const baseRate = 300; // FCFA par km
        const minimumPrice = 1000; // FCFA
        
        let price = Math.max(distance * baseRate, minimumPrice);
        
        // Ajustement selon la priorité
        const priority = document.querySelector('input[name="priority"]:checked');
        if (priority) {
            switch(priority.value) {
                case 'urgente':
                    price *= 1.5;
                    break;
                case 'express':
                    price *= 2.0;
                    break;
            }
        }
        
        priceElement.innerHTML = `
            💰 Prix estimé: <strong>${Math.round(price)} FCFA</strong><br>
            📏 Distance: ${distance.toFixed(1)} km<br>
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
            } else {
                console.error('Erreur de géocodage:', status);
                const inputField = document.getElementById(fieldType);
                if (inputField) {
                    inputField.value = `📍 ${position.lat().toFixed(6)}, ${position.lng().toFixed(6)}`;
                }
            }
        });
    }

    // 🚀 NOUVELLES FONCTIONS CALCUL DYNAMIQUE - 10/08/2025
    
    // Configuration tarifaire Suzosky
    const PRICING_CONFIG = {
        baseFare: 1000,                    // Tarif de base FCFA
        pricePerKm: 300,                   // Prix par kilomètre
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
    function calculateDynamicPrice(tripData, urgency = 'normale') {
        try {
            // Distance totale (ajout coursier vers expéditeur estimé à 3km)
            const courierDistance = 3; // km estimation déplacement coursier
            const totalDistance = courierDistance + tripData.distance;
            
            // Prix de base
            let totalPrice = PRICING_CONFIG.baseFare + (totalDistance * PRICING_CONFIG.pricePerKm);
            
            // Multiplicateur urgence
            totalPrice *= PRICING_CONFIG.urgencyMultiplier[urgency];
            
            // Ajustements temporels
            const currentHour = new Date().getHours();
            if (currentHour >= 20 || currentHour <= 6) {
                totalPrice *= 1.3; // +30% nuit
            }
            
            // Weekend
            const isWeekend = [0, 6].includes(new Date().getDay());
            if (isWeekend) {
                totalPrice *= 1.2; // +20% weekend
            }
            
            return {
                totalPrice: Math.round(totalPrice),
                breakdown: {
                    baseFare: PRICING_CONFIG.baseFare,
                    distanceCharge: Math.round(totalDistance * PRICING_CONFIG.pricePerKm),
                    urgencyMultiplier: PRICING_CONFIG.urgencyMultiplier[urgency],
                    nightSurcharge: (currentHour >= 20 || currentHour <= 6) ? '30%' : '0%',
                    weekendSurcharge: isWeekend ? '20%' : '0%'
                },
                currency: 'FCFA',
                totalDistance: totalDistance
            };
        } catch (error) {
            console.error('Erreur calcul prix:', error);
            return {
                totalPrice: 2500,
                currency: 'FCFA',
                breakdown: { error: 'Calcul en cours...' }
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
            document.getElementById('paymentMethods').style.display = 'none';
            document.getElementById('estimatedPrice').style.display = 'none';
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
