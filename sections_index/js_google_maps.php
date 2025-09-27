<?php
// sections/js_google_maps.php - Fonctions Google Maps et géolocalisation
?>
    <!-- Gestion d'erreur Google Maps -->
    <script>
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

    (function(window, document) {
        'use strict';

        const MAP_ELEMENT_ID = 'map';
        const DOM_RETRY_LIMIT = 10;
        const DOM_RETRY_DELAY = 250;
        const AUTOCOMPLETE_RETRY_LIMIT = 10;
        const AUTOCOMPLETE_RETRY_DELAY = 200;

        window.markerA = window.markerA || null;
        window.markerB = window.markerB || null;
        window.googleMapsInitialized = window.googleMapsInitialized || false;
        window.googleMapsInitializing = window.googleMapsInitializing || false;
        window.GoogleMapsConfig = window.GoogleMapsConfig || {};
        window.GoogleMapsConfig.map = window.GoogleMapsConfig.map || null;
        window.GoogleMapsConfig.service = window.GoogleMapsConfig.service || null;
        window.GoogleMapsConfig.directionsService = window.GoogleMapsConfig.directionsService || null;
        window.GoogleMapsConfig.directionsRenderer = window.GoogleMapsConfig.directionsRenderer || null;
        window.GoogleMapsConfig.markerA = window.GoogleMapsConfig.markerA || null;
        window.GoogleMapsConfig.markerB = window.GoogleMapsConfig.markerB || null;

        window.addEventListener('error', function(event) {
            try {
                fetch('api/log_js_error.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: event.message,
                        source: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        error: event.error && event.error.stack
                    })
                });
            } catch (e) {
                /* ignore logging failure */
            }
        });

        window.addEventListener('unhandledrejection', function(event) {
            try {
                fetch('api/log_js_error.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: 'Unhandled Promise Rejection: ' + event.reason,
                        stack: event.reason && event.reason.stack
                    })
                });
            } catch (e) {
                /* ignore logging failure */
            }
        });

        window.gm_authFailure = function() {
            console.error('Erreur d\'authentification Google Maps API');
            showMapError('Erreur d\'authentification API - Vérifiez la clé API');
        };

        setTimeout(function() {
            if (typeof google === 'undefined') {
                console.error('Google Maps API n\'a pas pu être chargée');
                showMapError('API Google Maps non chargée - Vérifiez votre connexion internet');
            }
        }, 10000);

        function getMarkerSvg(type) {
            if (type === 'departure') {
                return `
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                        <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#00FF00" stroke="#000000" stroke-width="2"/>
                        <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                        <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">A</text>
                    </svg>
                `;
            }
            return `
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="50" viewBox="0 0 40 50">
                    <path d="M20 0C31.046 0 40 8.954 40 20c0 15-20 30-20 30S0 35 0 20C0 8.954 8.954 0 20 0z" fill="#FF0000" stroke="#000000" stroke-width="2"/>
                    <circle cx="20" cy="20" r="12" fill="#FFFFFF"/>
                    <text x="20" y="26" text-anchor="middle" fill="#000000" font-size="16" font-weight="bold">B</text>
                </svg>
            `;
        }

        function triggerAutocompleteSetup(attempt) {
            const currentAttempt = attempt || 0;
            if (typeof setupAutocomplete === 'function') {
                try {
                    setupAutocomplete();
                } catch (error) {
                    console.error('Erreur lors de l\'initialisation de l\'autocomplétion:', error);
                }
                return;
            }

            if (currentAttempt < AUTOCOMPLETE_RETRY_LIMIT) {
                setTimeout(function() {
                    triggerAutocompleteSetup(currentAttempt + 1);
                }, AUTOCOMPLETE_RETRY_DELAY);
            } else {
                console.warn('Impossible d\'initialiser l\'autocomplétion Google Places (fonction setupAutocomplete indisponible)');
            }
        }

        function placeMarker(type, latLng, mapInstance) {
            const isDeparture = type === 'departure';
            const markerKey = isDeparture ? 'markerA' : 'markerB';

            if (window[markerKey]) {
                window[markerKey].setMap(null);
            }

            const marker = new google.maps.Marker({
                clickable: false,
                position: latLng,
                map: mapInstance,
                title: isDeparture ? 'Départ (clic sur carte)' : 'Destination (clic sur carte)',
                draggable: true,
                label: {
                    text: isDeparture ? 'A' : 'B',
                    color: '#1A1A2E',
                    fontWeight: 'bold',
                    fontSize: '16px'
                },
                icon: {
                    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(getMarkerSvg(type)),
                    scaledSize: new google.maps.Size(40, 50),
                    anchor: new google.maps.Point(20, 50)
                }
            });

            marker.addListener('dragend', function() {
                const newPosition = marker.getPosition();
                if (typeof updateAddressFromCoordinates === 'function') {
                    updateAddressFromCoordinates(newPosition, isDeparture ? 'departure' : 'destination');
                }

                const inputId = isDeparture ? 'departure' : 'destination';
                const inputElement = document.getElementById(inputId);
                if (inputElement) {
                    inputElement.dispatchEvent(new Event('input'));
                }

                if (!isDeparture && window.markerA && typeof calculateRoute === 'function') {
                    calculateRoute(window.markerA.getPosition(), newPosition);
                }
            });

            window[markerKey] = marker;
            window.GoogleMapsConfig[markerKey] = marker;

            return marker;
        }

        function handleMapClick(event, mapInstance) {
            console.log('Position cliquée:', event.latLng.toString());
            const wantsDeparture = confirm('Voulez-vous placer le marqueur A (Départ) ?\nCliquez sur "Annuler" pour placer le marqueur B (Destination)');

            if (wantsDeparture) {
                placeMarker('departure', event.latLng, mapInstance);
                if (typeof updateAddressFromCoordinates === 'function') {
                    updateAddressFromCoordinates(event.latLng, 'departure');
                }
                const depInput = document.getElementById('departure');
                if (depInput) {
                    depInput.dispatchEvent(new Event('input'));
                }
            } else {
                placeMarker('destination', event.latLng, mapInstance);
                if (typeof updateAddressFromCoordinates === 'function') {
                    updateAddressFromCoordinates(event.latLng, 'destination');
                }
                const destInput = document.getElementById('destination');
                if (destInput) {
                    destInput.dispatchEvent(new Event('input'));
                }
                if (window.markerA && window.markerB && typeof calculateRoute === 'function') {
                    calculateRoute(window.markerA.getPosition(), window.markerB.getPosition());
                }
            }
        }

        window.initMap = function initMap() {
            console.log('Initialisation de Google Maps...');
            if (typeof google === 'undefined' || !google.maps) {
                console.error('API Google Maps non chargée');
                showMapError();
                return;
            }
            window.googleMapsReady = true;
            window.initializeMapAfterLoad();
        };

        window.initializeMapAfterLoad = function initializeMapAfterLoad(retryCount) {
            const attempt = retryCount || 0;

            if (!window.googleMapsReady) {
                return;
            }

            if (window.googleMapsInitialized) {
                triggerAutocompleteSetup();
                return;
            }

            if (window.googleMapsInitializing) {
                return;
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    window.initializeMapAfterLoad(attempt);
                });
                return;
            }

            if (typeof google === 'undefined' || !google.maps) {
                if (attempt < DOM_RETRY_LIMIT) {
                    setTimeout(function() {
                        window.initializeMapAfterLoad(attempt + 1);
                    }, DOM_RETRY_DELAY);
                } else {
                    showMapError('API Google Maps indisponible');
                }
                return;
            }

            const mapElement = document.getElementById(MAP_ELEMENT_ID);
            if (!mapElement) {
                if (attempt < DOM_RETRY_LIMIT) {
                    setTimeout(function() {
                        window.initializeMapAfterLoad(attempt + 1);
                    }, DOM_RETRY_DELAY);
                } else {
                    console.warn('Élément #map introuvable après plusieurs tentatives');
                }
                return;
            }

            try {
                window.googleMapsInitializing = true;

                const abidjan = { lat: 5.3364, lng: -4.0267 };

                const mapInstance = new google.maps.Map(mapElement, {
                    zoom: 12,
                    center: abidjan,
                    styles: [
                        {
                            featureType: 'all',
                            elementType: 'geometry.fill',
                            stylers: [{ color: '#1a1a2e' }]
                        },
                        {
                            featureType: 'water',
                            elementType: 'geometry',
                            stylers: [{ color: '#0f3460' }]
                        },
                        {
                            featureType: 'road',
                            elementType: 'geometry.stroke',
                            stylers: [
                                { color: '#d4a853' },
                                { weight: 0.5 }
                            ]
                        },
                        {
                            featureType: 'poi',
                            elementType: 'labels.text',
                            stylers: [
                                { color: '#e0e0e0' },
                                { fontWeight: 'normal' }
                            ]
                        },
                        {
                            featureType: 'road',
                            elementType: 'labels.text',
                            stylers: [
                                { color: '#f5f5f5' },
                                { fontWeight: 'normal' }
                            ]
                        },
                        {
                            featureType: 'administrative',
                            elementType: 'labels.text',
                            stylers: [
                                { color: '#f0f0f0' },
                                { fontWeight: 'normal' }
                            ]
                        },
                        {
                            featureType: 'all',
                            elementType: 'labels.text.stroke',
                            stylers: [
                                { color: '#1a1a2e' },
                                { weight: 2 }
                            ]
                        },
                        {
                            featureType: 'all',
                            elementType: 'labels.text.fill',
                            stylers: [
                                { color: '#ffffff' },
                                { fontWeight: '400' }
                            ]
                        }
                    ],
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true
                });

                window.map = mapInstance;
                window.GoogleMapsConfig.map = mapInstance;

                window.GoogleMapsConfig.service = new google.maps.places.PlacesService(mapInstance);

                window.directionsService = new google.maps.DirectionsService();
                window.GoogleMapsConfig.directionsService = window.directionsService;

                window.directionsRenderer = new google.maps.DirectionsRenderer({
                    draggable: true,
                    suppressMarkers: true,
                    suppressInfoWindows: true,
                    polylineOptions: {
                        strokeColor: '#d4a853',
                        strokeWeight: 4
                    }
                });
                window.directionsRenderer.setMap(mapInstance);
                window.GoogleMapsConfig.directionsRenderer = window.directionsRenderer;

                const zones = [
                    { name: 'Plateau', lat: 5.32745, lng: -4.01546 },
                    { name: 'Cocody', lat: 5.35444, lng: -3.95972 },
                    { name: 'Yopougon', lat: 5.34532, lng: -4.08251 },
                    { name: 'Marcory', lat: 5.29653, lng: -4.00243 },
                    { name: 'Adjamé', lat: 5.35083, lng: -4.02056 }
                ];

                zones.forEach(function(zone) {
                    new google.maps.Marker({
                        position: { lat: zone.lat, lng: zone.lng },
                        map: mapInstance,
                        title: 'Zone: ' + zone.name,
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

                mapInstance.addListener('click', function(event) {
                    handleMapClick(event, mapInstance);
                });

                window.googleMapsInitialized = true;
                window.googleMapsInitializing = false;

                console.log('✅ Google Maps initialisé avec succès');
                setTimeout(function() {
                    triggerAutocompleteSetup();
                }, 100);
            } catch (error) {
                window.googleMapsInitializing = false;
                console.error('Erreur lors de l\'initialisation de Google Maps:', error);
                showMapError();
            }
        };

        window.addEventListener('load', function() {
            const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            if (isLocalhost) {
                console.log('⚙️ Fallback carte désactivé en local');
                return;
            }
            setTimeout(function() {
                if (typeof google === 'undefined') {
                    console.error('Google Maps API non chargée - Tentative de rechargement');
                    showMapError('Carte temporairement indisponible - Veuillez rafraîchir la page');
                }
            }, 3000);
        });

        if (window.googleMapsReady) {
            window.initializeMapAfterLoad();
        }
    })(window, document);
    </script>
