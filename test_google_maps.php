<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Google Maps</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #1a1a2e;
            color: white;
        }
        #map {
            width: 100%;
            height: 600px;
            border: 2px solid #d4a853;
            border-radius: 12px;
        }
        .status {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        .error {
            background: rgba(255,0,0,0.2);
        }
        .success {
            background: rgba(0,255,0,0.2);
        }
    </style>
</head>
<body>
    <h1>🔍 Test Google Maps API</h1>
    
    <div id="status" class="status">
        ⏳ Chargement...
    </div>
    
    <div id="map"></div>

    <?php
    // Charger la clé API
    require_once __DIR__ . '/config.php';
    $mapsApiKey = getenv('GOOGLE_MAPS_API_KEY');
    if (!$mapsApiKey && defined('GOOGLE_MAPS_API_KEY')) {
        $mapsApiKey = GOOGLE_MAPS_API_KEY;
    }
    ?>

    <script>
        // Test de chargement de l'API
        console.log('🔍 Début du test Google Maps');
        console.log('📍 Clé API présente:', '<?php echo !empty($mapsApiKey) ? "OUI" : "NON"; ?>');
        
        function updateStatus(message, isError = false) {
            const statusDiv = document.getElementById('status');
            statusDiv.textContent = message;
            statusDiv.className = 'status ' + (isError ? 'error' : 'success');
            console.log(message);
        }

        // Callback pour l'API Google Maps
        window.initMap = function() {
            console.log('✅ Callback initMap() appelé');
            
            if (typeof google === 'undefined') {
                updateStatus('❌ ERREUR: Objet google non défini', true);
                return;
            }

            if (!google.maps) {
                updateStatus('❌ ERREUR: google.maps non disponible', true);
                return;
            }

            updateStatus('✅ API Google Maps chargée avec succès !');

            try {
                const mapElement = document.getElementById('map');
                const abidjan = { lat: 5.3364, lng: -4.0267 };
                
                const map = new google.maps.Map(mapElement, {
                    zoom: 12,
                    center: abidjan,
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true
                });

                // Ajouter un marqueur
                new google.maps.Marker({
                    position: abidjan,
                    map: map,
                    title: 'Abidjan - Test Marker'
                });

                updateStatus('✅ Carte initialisée avec succès ! Marqueur placé à Abidjan');
                console.log('✅ Carte créée avec succès');

            } catch (error) {
                updateStatus('❌ ERREUR lors de la création de la carte: ' + error.message, true);
                console.error('❌ Erreur:', error);
            }
        };

        // Gestion des erreurs d'authentification
        window.gm_authFailure = function() {
            updateStatus('❌ ERREUR D\'AUTHENTIFICATION: Clé API invalide ou restrictions incorrectes', true);
            console.error('❌ Erreur d\'authentification Google Maps');
        };

        // Timeout si l'API ne charge pas
        setTimeout(function() {
            if (typeof google === 'undefined') {
                updateStatus('❌ TIMEOUT: L\'API Google Maps n\'a pas pu être chargée en 10 secondes', true);
            }
        }, 10000);

        console.log('🔧 Script de test chargé, en attente de l\'API...');
    </script>

    <!-- Chargement de l'API Google Maps -->
    <script async defer 
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapsApiKey, ENT_QUOTES); ?>&libraries=places,geometry&callback=initMap"
        onerror="updateStatus('❌ ERREUR: Échec du chargement du script Google Maps', true)">
    </script>

    <script>
        // Log des détails
        console.log('📋 Informations de test:');
        console.log('  - URL API:', 'https://maps.googleapis.com/maps/api/js?key=<?php echo substr($mapsApiKey, 0, 10); ?>...&libraries=places,geometry&callback=initMap');
        console.log('  - Clé API (10 premiers caractères):', '<?php echo substr($mapsApiKey, 0, 10); ?>...');
    </script>
</body>
</html>
