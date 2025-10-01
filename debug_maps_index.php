<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Debug Google Maps</title>
    <style>
        body { 
            margin: 0; 
            font-family: monospace; 
            background: #000; 
            color: #0f0;
            padding: 20px;
        }
        #map { 
            width: 100%; 
            height: 500px; 
            background: #333;
            border: 2px solid #d4a853;
            margin: 20px 0;
        }
        .log { 
            background: #111; 
            padding: 10px; 
            margin: 5px 0;
            border-left: 3px solid #0f0;
        }
        .error { border-left-color: #f00; color: #f00; }
        .warning { border-left-color: #fa0; color: #fa0; }
    </style>
</head>
<body>
    <h1>🔍 DEBUG GOOGLE MAPS INDEX</h1>
    <div id="logs"></div>
    
    <h2>Carte Google Maps:</h2>
    <div id="map"></div>
    
    <?php
    require_once __DIR__ . '/config.php';
    $mapsApiKey = getenv('GOOGLE_MAPS_API_KEY');
    if (!$mapsApiKey && defined('GOOGLE_MAPS_API_KEY')) {
        $mapsApiKey = GOOGLE_MAPS_API_KEY;
    }
    ?>
    
    <script>
        const logs = document.getElementById('logs');
        
        function log(msg, type = 'info') {
            const div = document.createElement('div');
            div.className = 'log ' + type;
            div.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            logs.appendChild(div);
            console.log(msg);
        }
        
        log('🔍 Début du debug');
        log('📍 Élément #map existe: ' + (document.getElementById('map') ? 'OUI' : 'NON'));
        log('📍 Document ready state: ' + document.readyState);
        
        // Simulation de ce que fait index.php
        window.initGoogleMapsEarly = function() {
            log('✅ Callback initGoogleMapsEarly appelé', 'info');
            log('📍 typeof google: ' + typeof google);
            
            if (typeof google !== 'undefined' && google.maps) {
                log('✅ google.maps disponible', 'info');
                log('✅ Version: ' + google.maps.version);
            } else {
                log('❌ google.maps NON disponible!', 'error');
                return;
            }
            
            window.googleMapsReady = true;
            log('🏁 googleMapsReady = true');
            
            // Maintenant on essaie d'initialiser
            if (typeof window.initializeMapAfterLoad === 'function') {
                log('🔄 Appel de initializeMapAfterLoad()');
                window.initializeMapAfterLoad();
            } else {
                log('⚠️ initializeMapAfterLoad non définie!', 'warning');
                
                // On initialise directement ici
                log('🔄 Initialisation directe de la carte...');
                initMapDirectly();
            }
        };
        
        function initMapDirectly() {
            const mapElement = document.getElementById('map');
            
            if (!mapElement) {
                log('❌ Élément #map NON TROUVÉ!', 'error');
                return;
            }
            
            log('✅ Élément #map trouvé');
            
            try {
                const abidjan = { lat: 5.3364, lng: -4.0267 };
                
                const map = new google.maps.Map(mapElement, {
                    zoom: 12,
                    center: abidjan,
                    mapTypeControl: true
                });
                
                new google.maps.Marker({
                    position: abidjan,
                    map: map,
                    title: 'Abidjan'
                });
                
                log('✅ CARTE INITIALISÉE AVEC SUCCÈS!', 'info');
                
            } catch (error) {
                log('❌ ERREUR lors de l\'initialisation: ' + error.message, 'error');
            }
        }
        
        // Timeout de sécurité
        setTimeout(() => {
            if (typeof google === 'undefined') {
                log('❌ TIMEOUT: Google Maps API non chargée après 10s', 'error');
            }
        }, 10000);
        
        log('⏳ En attente du chargement de l\'API Google Maps...');
    </script>
    
    <!-- Chargement de l'API EXACTEMENT comme dans index.php -->
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapsApiKey, ENT_QUOTES); ?>&libraries=places,geometry&callback=initGoogleMapsEarly">
    </script>
    
    <script>
        // Log après le chargement du script
        log('📋 Script Google Maps ajouté au DOM');
    </script>
</body>
</html>
