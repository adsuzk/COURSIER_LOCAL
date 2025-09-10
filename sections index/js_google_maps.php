<?php
// sections/js_google_maps.php - Fonctions Google Maps et géolocalisation
?>
    <!-- Gestion d'erreur Google Maps -->
    <script>
    // Initialisation globale des marqueurs
    window.markerA = window.markerA || null;
    window.markerB = window.markerB || null;
    // Variables locales référant aux globals
    var markerA = window.markerA;
    var markerB = window.markerB;
        // Global JS error logging
        window.addEventListener('error', function(event) {
            try {
                fetch('api/log_js_error.php', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        message: event.message,
                        source: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        error: event.error && event.error.stack
                    })
                });
            } catch (e) {}
        });
        window.addEventListener('unhandledrejection', function(event) {
            try {
                fetch('api/log_js_error.php', {
                    method: 'POST', headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({
                        message: 'Unhandled Promise Rejection: ' + event.reason,
                        stack: event.reason && event.reason.stack
                    })
                });
            } catch (e) {}
        });
    // Fonction d'erreur globale pour Google Maps
    window.gm_authFailure = function() {
        console.error('Erreur d\'authentification Google Maps API');
        showMapError('Erreur d\'authentification API - Vérifiez la clé API');
    };
    
    // Timeout pour détecter les échecs de chargement
    setTimeout(function() {
        if (typeof google === 'undefined') {
            console.error('Google Maps API n\'a pas pu être chargée');
            showMapError('API Google Maps non chargée - Vérifiez votre connexion internet');
        }
    }, 10000); // 10 secondes de timeout
    </script>
    
    <script>
    // Configuration Google Maps - Namespace global pour éviter les conflits
    if (!window.GoogleMapsConfig) {
        window.GoogleMapsConfig = {
            map: null,
            service: null,
            directionsService: null,
            directionsRenderer: null,
            markerA: null, // Marqueur de départ
            markerB: null  // Marqueur d'arrivée
        };
    }

    // Fonction globale pour initialiser Google Maps
    window.initMap = function() {
        console.log('Initialisation de Google Maps...');
        
        // Vérifier que Google Maps est chargé
        if (typeof google === 'undefined' || !google.maps) {
            console.error('API Google Maps non chargée');
            showMapError();
            return;
        }
        
        // Vérifier que l'élément map existe
        const mapElement = document.getElementById("map");
        if (!mapElement) {
            console.error('Élément #map non trouvé dans le DOM');
            return;
        }
        
        try {
            // Centre d'Abidjan
            const abidjan = { lat: 5.3364, lng: -4.0267 };
        
        // Initialisation de la carte
        map = new google.maps.Map(mapElement, {
            zoom: 12,
            center: abidjan,
            styles: [
                {
                    "featureType": "all",
                    "elementType": "geometry.fill",
                    "stylers": [
                        { "color": "#1a1a2e" }
                    ]
                },
                {
                    "featureType": "water",
                    "elementType": "geometry",
                    "stylers": [
                        { "color": "#0f3460" }
                    ]
                },
                {
                    "featureType": "road",
                    "elementType": "geometry.stroke",
                    "stylers": [
                        { "color": "#d4a853" },
                        { "weight": 0.5 }
                    ]
                },
                {
                    "featureType": "poi",
                    "elementType": "labels.text",
                    "stylers": [
                        { "color": "#e0e0e0" },
                        { "fontWeight": "normal" }
                    ]
                },
                {
                    "featureType": "road",
                    "elementType": "labels.text",
                    "stylers": [
                        { "color": "#f5f5f5" },
                        { "fontWeight": "normal" }
                    ]
                },
                {
                    "featureType": "administrative",
                    "elementType": "labels.text",
                    "stylers": [
                        { "color": "#f0f0f0" },
                        { "fontWeight": "normal" }
                    ]
                },
                {
                    "featureType": "all",
                    "elementType": "labels.text.stroke",
                    "stylers": [
                        { "color": "#1a1a2e" },
                        { "weight": 2 }
                    ]
                },
                {
                    "featureType": "all",
                    "elementType": "labels.text.fill",
                    "stylers": [
                        { "color": "#ffffff" },
                        { "fontWeight": "400" }
                    ]
                }
            ],
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
        });

        // Services Google Maps
        service = new google.maps.places.PlacesService(map);
        directionsService = new google.maps.DirectionsService();
        directionsRenderer = new google.maps.DirectionsRenderer({
            draggable: true,
            polylineOptions: {
                strokeColor: "#d4a853",
                strokeWeight: 4
            }
        });
        directionsRenderer.setMap(map);

        // Marqueurs des zones de livraison
        const zones = [
            { name: "Plateau", lat: 5.32745, lng: -4.01546 },
            { name: "Cocody", lat: 5.35444, lng: -3.95972 },
            { name: "Yopougon", lat: 5.34532, lng: -4.08251 },
            { name: "Marcory", lat: 5.29653, lng: -4.00243 },
            { name: "Adjamé", lat: 5.35083, lng: -4.02056 }
        ];

        zones.forEach(zone => {
            new google.maps.Marker({
                position: { lat: zone.lat, lng: zone.lng },
                map: map,
                title: `Zone: ${zone.name}`,
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30">
                            <circle cx="15" cy="15" r="12" fill="#d4a853" stroke="#1a1a2e" stroke-width="2"/>
                            <text x="15" y="19" text-anchor="middle" fill="#1a1a2e" font-size="12" font-weight="bold">📦</text>
                        </svg>
                    `),
                    scaledSize: new google.maps.Size(30, 30)
                }
            });
        });

        // Gestion des clics sur la carte
        map.addListener('click', function(event) {
            console.log('Position cliquée:', event.latLng.toString());
            
            // Demander à l'utilisateur quel marqueur il veut placer
            const choice = confirm('Voulez-vous placer le marqueur A (Départ) ?\nCliquez sur "Annuler" pour placer le marqueur B (Destination)');
            
            if (choice) {
                // Placer le marqueur A
                if (markerA) markerA.setMap(null);
                
                markerA = new google.maps.Marker({
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
    </script>

    <!-- Chargement de l'API Google Maps avec Places et callback initMap -->
    <script async defer
      src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAGKC21fGmY-k6i0dcY8MpBExa5IqqBXbE&libraries=places&callback=initMap">
    </script>
