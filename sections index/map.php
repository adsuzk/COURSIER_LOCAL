<?php
// sections/map.php - Carte Google Maps intégrée dans order_form.php
    // Configuration de l'autocomplétion Google Places définie dans sections/js_autocomplete.php
                    position: event.latLng,
                    map: map,
                    title: 'Départ (clic sur carte)',
                    draggable: true,
                    label: {
                        text: 'A',
                        color: '#1A1A2E',
                        fontWeight: 'bold',
                        fontSize: '16px'
                    },
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                                <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#00FF00" stroke="#000000" stroke-width="2"/>
                                <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                                <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">A</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(40, 50),
                        anchor: new google.maps.Point(20, 50)
                    }
                });
                
                markerA.addListener('dragend', function() {
                    updateAddressFromCoordinates(markerA.getPosition(), 'departure');
                    if (markerB) {
                        calculateRoute(markerA.getPosition(), markerB.getPosition());
                    }
                });
                
                updateAddressFromCoordinates(event.latLng, 'departure');
                
            } else {
                // Placer le marqueur B
                if (markerB) markerB.setMap(null);
                
                markerB = new google.maps.Marker({
                    position: event.latLng,
                    map: map,
                    title: 'Destination (clic sur carte)',
                    draggable: true,
                    label: {
                        text: 'B',
                        color: '#1A1A2E',
                        fontWeight: 'bold',
                        fontSize: '16px'
                    },
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                                <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#FF0000" stroke="#000000" stroke-width="2"/>
                                <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                                <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">B</text>
                            </svg>
                        `),
                        scaledSize: new google.maps.Size(40, 50),
                        anchor: new google.maps.Point(20, 50)
                    }
                });
                
                markerB.addListener('dragend', function() {
                    updateAddressFromCoordinates(markerB.getPosition(), 'destination');
                    if (markerA) {
                        calculateRoute(markerA.getPosition(), markerB.getPosition());
                    }
                });
                
                updateAddressFromCoordinates(event.latLng, 'destination');
            }
            
            // Recalculer l'itinéraire si les deux marqueurs existent
            if (markerA && markerB) {
                calculateRoute(markerA.getPosition(), markerB.getPosition());
            }
        });

        // Configuration Google Places Autocomplete maintenant que l'API est chargée
        console.log('initMap() - Configuration de l\'autocomplétion');
        
        // Attendre un peu que le DOM soit prêt pour l'autocomplétion
        setTimeout(() => {
            setupAutocomplete();
        }, 100);
        
        } catch (error) {
            console.error('Erreur lors de l\'initialisation de Google Maps:', error);
            showMapError();
        }
    }
    
    // Fonction pour afficher une erreur de carte
    function showMapError(message = 'Erreur de chargement Google Maps') {
        const mapContainer = document.getElementById('map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: rgba(255,255,255,0.1); border-radius: 12px; color: white; text-align: center; padding: 20px;">
                    <div>
                        <h3>🗺️ ${message}</h3>
                        <p>Vérifiez votre connexion internet et les clés API</p>
                        <button onclick="location.reload()" style="background: #D4A853; color: #1A1A2E; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-top: 10px;">
                            🔄 Recharger la page
                        </button>
                    </div>
                </div>
            `;
        }
    }
    
    // Fonction de fallback si Google Maps ne se charge pas (activée uniquement en production)
    window.addEventListener('load', function() {
        const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (isLocalhost) {
            console.log('⚙️ Fallback carte désactivé en local');
            return;
        }
        // En production, afficher overlay si la carte ne se charge pas
        setTimeout(() => {
            if (typeof google === 'undefined') {
                console.error('Google Maps API non chargée - Tentative de rechargement');
                const mapContainer = document.getElementById('map');
                if (mapContainer) {
                    mapContainer.innerHTML = `
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: rgba(255,255,255,0.1); border-radius: 12px; color: white; text-align: center; padding: 20px;">
                            <div>
                                <h3>🗺️ Carte temporairement indisponible</h3>
                                <p>Veuillez rafraîchir la page ou saisir les adresses manuellement</p>
                                <button onclick="location.reload()" style="background: #D4A853; color: #1A1A2E; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-top: 10px;">
                                    🔄 Recharger la page
                                </button>
                            </div>
                        </div>
                    `;
                }
            }
        }, 3000);
    });

    // Configuration de l'autocomplétion Google Places
    function setupAutocomplete() {
        console.log('Initialisation de l\'autocomplétion...');
        
        try {
            // Vérifier que Google Maps est chargé
            if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
                console.error('Google Maps API ou Places library non chargée');
                return;
            }
            
            // Champ départ
            const departureInput = document.getElementById('departure');
            if (!departureInput) {
                console.error('Champ departure non trouvé');
                return;
            }
            
            const departureAutocomplete = new google.maps.places.Autocomplete(departureInput, {
                types: ['establishment', 'geocode'],
                componentRestrictions: { country: 'ci' }, // Côte d'Ivoire
                fields: ['place_id', 'geometry', 'name', 'formatted_address']
            });

        // Champ destination
        const destinationInput = document.getElementById('destination');
        if (!destinationInput) {
            console.error('Champ destination non trouvé');
            return;
        }
        
        const destinationAutocomplete = new google.maps.places.Autocomplete(destinationInput, {
            types: ['establishment', 'geocode'],
            componentRestrictions: { country: 'ci' }, // Côte d'Ivoire
            fields: ['place_id', 'geometry', 'name', 'formatted_address']
        });

        console.log('Autocomplétion configurée avec succès');

        // Gestion de la sélection départ
        departureAutocomplete.addListener('place_changed', function() {
            const place = departureAutocomplete.getPlace();
            if (!place.geometry) {
                console.log("Aucun détail disponible pour: '" + place.name + "'");
                return;
            }
            
            console.log('Départ sélectionné:', place.formatted_address);
            
            // Ajouter/Mettre à jour le marqueur A (départ)
            if (markerA) {
                markerA.setMap(null);
            }
            
            markerA = new google.maps.Marker({
                position: place.geometry.location,
                map: map,
                title: `Départ: ${place.formatted_address}`,
                draggable: true, // Rendre le marqueur déplaçable
                label: {
                    text: 'A',
                    color: '#1A1A2E',
                    fontWeight: 'bold',
                    fontSize: '16px'
                },
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                            <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#00FF00" stroke="#000000" stroke-width="2"/>
                            <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                            <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">A</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(40, 50),
                    anchor: new google.maps.Point(20, 50)
                }
            });
            
            // Listener pour le déplacement du marqueur A
            markerA.addListener('dragend', function() {
                const newPosition = markerA.getPosition();
                console.log('Marqueur A déplacé vers:', newPosition.toString());
                
                // Recalculer l'itinéraire si le marqueur B existe
                if (markerB) {
                    calculateRoute(newPosition, markerB.getPosition());
                }
                
                // Mettre à jour le champ avec les coordonnées
                updateAddressFromCoordinates(newPosition, 'departure');
            });
            
            // Calculer l'itinéraire si les deux adresses sont remplies
            const destination = destinationInput.value;
            if (destination && destination.trim() !== '' && markerB) {
                calculateRoute(place.geometry.location, markerB.getPosition());
            }
        });

        // Gestion de la sélection destination
        destinationAutocomplete.addListener('place_changed', function() {
            const place = destinationAutocomplete.getPlace();
            if (!place.geometry) {
                console.log("Aucun détail disponible pour: '" + place.name + "'");
                return;
            }
            
            console.log('Destination sélectionnée:', place.formatted_address);
            
            // Ajouter/Mettre à jour le marqueur B (destination)
            if (markerB) {
                markerB.setMap(null);
            }
            
            markerB = new google.maps.Marker({
                position: place.geometry.location,
                map: map,
                title: `Destination: ${place.formatted_address}`,
                draggable: true, // Rendre le marqueur déplaçable
                label: {
                    text: 'B',
                    color: '#1A1A2E',
                    fontWeight: 'bold',
                    fontSize: '16px'
                },
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                            <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#FF0000" stroke="#000000" stroke-width="2"/>
                            <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                            <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">B</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(40, 50),
                    anchor: new google.maps.Point(20, 50)
                }
            });
            
            // Listener pour le déplacement du marqueur B
            markerB.addListener('dragend', function() {
                const newPosition = markerB.getPosition();
                console.log('Marqueur B déplacé vers:', newPosition.toString());
                
                // Recalculer l'itinéraire si le marqueur A existe
                if (markerA) {
                    calculateRoute(markerA.getPosition(), newPosition);
                }
                
                // Mettre à jour le champ avec les coordonnées
                updateAddressFromCoordinates(newPosition, 'destination');
            });
            
            // Calculer l'itinéraire si les deux adresses sont remplies
            const departure = departureInput.value;
            if (departure && departure.trim() !== '' && markerA) {
                calculateRoute(markerA.getPosition(), place.geometry.location);
            }
        });

        // Styles pour les suggestions d'autocomplétion
        const style = document.createElement('style');
        style.textContent = `
            .pac-container {
                background: rgba(26, 26, 46, 0.95) !important;
                border: 1px solid rgba(212, 168, 83, 0.3) !important;
                border-radius: 12px !important;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4) !important;
                backdrop-filter: blur(20px) !important;
                margin-top: 4px !important;
            }
            
            .pac-item {
                background: transparent !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
                color: #fff !important;
                padding: 12px 16px !important;
                font-family: 'Montserrat', sans-serif !important;
            }
            
            .pac-item:hover {
                background: rgba(212, 168, 83, 0.1) !important;
            }
            
            .pac-item-selected {
                background: rgba(212, 168, 83, 0.2) !important;
            }
            
            .pac-item-query {
                color: #d4a853 !important;
                font-weight: 600 !important;
            }
            
            .pac-matched {
                color: #d4a853 !important;
                font-weight: 700 !important;
            }
            
            .pac-icon {
                background-image: none !important;
            }
            
            .pac-icon-marker::before {
                content: "📍" !important;
                font-size: 16px !important;
            }
        `;
        document.head.appendChild(style);
        
        console.log('Autocomplétion configurée avec succès');
        
        } catch (error) {
            console.error('Erreur lors de la configuration de l\'autocomplétion:', error);
        }
    }

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
    
    // Fonction de géolocalisation GPS
    function getCurrentLocation(targetField = 'departure') {
        if (!navigator.geolocation) {
            alert('La géolocalisation n\'est pas supportée par ce navigateur');
            return;
        }
        
        // Afficher un indicateur de chargement
        const loadingText = targetField === 'departure' ? 'A' : 'B';
        showLocationLoading(loadingText);
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const userLocation = new google.maps.LatLng(lat, lng);
                
                console.log('Position GPS obtenue:', lat, lng);
                
                // Centrer la carte sur la position
                map.setCenter(userLocation);
                map.setZoom(16);
                
                // Ajouter le marqueur approprié
                if (targetField === 'departure') {
                    if (markerA) markerA.setMap(null);
                    
                    markerA = new google.maps.Marker({
                        position: userLocation,
                        map: map,
                        title: 'Ma position (Départ)',
                        draggable: true,
                        label: {
                            text: 'A',
                            color: '#1A1A2E',
                            fontWeight: 'bold',
                            fontSize: '16px'
                        },
                        icon: {
                            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                                    <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#00FF00" stroke="#000000" stroke-width="2"/>
                                    <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                                    <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">A</text>
                                </svg>
                            `),
                            scaledSize: new google.maps.Size(40, 50),
                            anchor: new google.maps.Point(20, 50)
                        }
                    });
                    
                    // Listener pour le déplacement
                    markerA.addListener('dragend', function() {
                        const newPosition = markerA.getPosition();
                        updateAddressFromCoordinates(newPosition, 'departure');
                        if (markerB) {
                            calculateRoute(newPosition, markerB.getPosition());
                        }
                    });
                    
                } else {
                    if (markerB) markerB.setMap(null);
                    
                    markerB = new google.maps.Marker({
                        position: userLocation,
                        map: map,
                        title: 'Ma position (Destination)',
                        draggable: true,
                        label: {
                            text: 'B',
                            color: '#1A1A2E',
                            fontWeight: 'bold',
                            fontSize: '16px'
                        },
                        icon: {
                            url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                                    <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#FF0000" stroke="#000000" stroke-width="2"/>
                                    <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                                    <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">B</text>
                                </svg>
                            `),
                            scaledSize: new google.maps.Size(40, 50),
                            anchor: new google.maps.Point(20, 50)
                        }
                    });
                    
                    // Listener pour le déplacement
                    markerB.addListener('dragend', function() {
                        const newPosition = markerB.getPosition();
                        updateAddressFromCoordinates(newPosition, 'destination');
                        if (markerA) {
                            calculateRoute(markerA.getPosition(), newPosition);
                        }
                    });
                }
                
                // Mettre à jour le champ d'adresse
                updateAddressFromCoordinates(userLocation, targetField);
                
                hideLocationLoading();
            },
            function(error) {
                console.error('Erreur de géolocalisation:', error);
                hideLocationLoading();
                
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        alert('Accès à la géolocalisation refusé. Veuillez autoriser la localisation dans votre navigateur.');
                        break;
                    case error.POSITION_UNAVAILABLE:
                        alert('Informations de localisation non disponibles.');
                        break;
                    case error.TIMEOUT:
                        alert('Délai d\'attente dépassé pour la géolocalisation.');
                        break;
                    default:
                        alert('Erreur inconnue lors de la géolocalisation.');
                        break;
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }
    
    function showLocationLoading(marker) {
        const button = document.querySelector(`[onclick="getCurrentLocation('${marker === 'A' ? 'departure' : 'destination'}')"]`);
        if (button) {
            button.innerHTML = '🔄 Localisation...';
            button.disabled = true;
        }
    }
    
    function hideLocationLoading() {
        const buttons = document.querySelectorAll('[onclick*="getCurrentLocation"]');
        buttons.forEach(btn => {
            if (btn.innerHTML.includes('Localisation')) {
                btn.innerHTML = btn.innerHTML.includes('departure') ? '📍 Ma position (A)' : '📍 Ma position (B)';
                btn.disabled = false;
            }
        });
    }
    </script>
