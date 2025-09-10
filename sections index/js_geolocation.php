<?php
// sections/js_geolocation.php - Fonctions de géolocalisation GPS
?>
    <script>
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
