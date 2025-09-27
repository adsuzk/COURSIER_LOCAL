<?php
// sections/js_autocomplete.php - Fonctions autocomplétion Google Places
?>
    <script>
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
                clickable: false, // Désactive la fenêtre d'infos au clic
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
            
            // Déclencher le calcul de prix via le formulaire
            const depInput = document.getElementById('departure');
            if (depInput) depInput.dispatchEvent(new Event('input'));
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
                clickable: false, // Désactive la fenêtre d'infos au clic
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

            try {
                const loc = place.geometry && place.geometry.location;
                const destLatInput = document.getElementById('destination_lat');
                const destLngInput = document.getElementById('destination_lng');
                if (loc && destLatInput && destLngInput) {
                    const lat = typeof loc.lat === 'function' ? loc.lat() : loc.lat;
                    const lng = typeof loc.lng === 'function' ? loc.lng() : loc.lng;
                    destLatInput.value = lat;
                    destLngInput.value = lng;
                }
            } catch (e) {
                console.warn('Impossible de définir destination_lat/lng:', e);
            }
            
            // Listener pour le déplacement du marqueur B
            markerB.addListener('dragend', function() {
                const newPosition = markerB.getPosition();
                console.log('Marqueur B déplacé vers:', newPosition.toString());

                try {
                    const lat = typeof newPosition.lat === 'function' ? newPosition.lat() : newPosition.lat;
                    const lng = typeof newPosition.lng === 'function' ? newPosition.lng() : newPosition.lng;
                    const destLatInput = document.getElementById('destination_lat');
                    const destLngInput = document.getElementById('destination_lng');
                    if (destLatInput) destLatInput.value = lat;
                    if (destLngInput) destLngInput.value = lng;
                } catch (e) {
                    console.warn('Impossible de mettre à jour destination_lat/lng après drag:', e);
                }
                
                // Recalculer l'itinéraire si le marqueur A existe
                if (markerA) {
                    calculateRoute(markerA.getPosition(), newPosition);
                }
                
                // Mettre à jour le champ avec les coordonnées
                updateAddressFromCoordinates(newPosition, 'destination');
            });
            
            // Déclencher le calcul de prix via le formulaire
            const destInput = document.getElementById('destination');
            if (destInput) destInput.dispatchEvent(new Event('input'));
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
    </script>
